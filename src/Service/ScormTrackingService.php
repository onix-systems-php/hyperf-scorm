<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Repository\ScormTrackingRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Factory\ScormApiStrategyFactory;
use OnixSystemsPHP\HyperfScorm\Entity\ScormUserSession;

/**
 * SCORM Tracking Service - handles all SCORM tracking operations
 */
class ScormTrackingService implements ScormTrackingServiceInterface
{
    private array $sessionCache = [];

    public function __construct(
        private ScormUserSessionRepositoryInterface $sessionRepository,
        private ScormTrackingRepositoryInterface $trackingRepository,
        private ScormPackageRepositoryInterface $packageRepository,
        private ScormApiStrategyFactory $strategyFactory
    ) {}

    public function initializeSession(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);

        if (!$session) {
            return false;
        }

        // Resume session if it was suspended
        if ($session->isSuspended()) {
            $session->resume();
            $this->sessionRepository->save($session);
        }

        // Cache the session for performance
        $this->sessionCache[$sessionId] = $session;

        return true;
    }

    public function setValue(string $sessionId, string $element, string $value): bool
    {
        $session = $this->getSession($sessionId);

        if (!$session || !$session->canResume()) {
            return false;
        }

        // Get the appropriate strategy based on package SCORM version
        $package = $this->getPackageForSession($session);
        $strategy = $this->strategyFactory->createForVersion($package->getScormVersion());

        // Validate the element and value
        if (!$strategy->validateElement($element, $value)) {
            return false;
        }

        // Map the element to database structure
        $mappedData = $strategy->mapTrackingElement($element, $value);

        // Update session-level data
        $this->updateSessionData($session, $element, $value);

        // Store detailed tracking data
        $this->trackingRepository->storeTrackingData(
            $session->getPackageId(),
            $session->getId(),
            $session->getUserId(),
            $mappedData['element_name'],
            $mappedData['element_value']
        );

        return true;
    }

    public function getValue(string $sessionId, string $element): ?string
    {
        $session = $this->getSession($sessionId);

        if (!$session) {
            return null;
        }

        // Get value from session data first
        $sessionValue = $this->getSessionElementValue($session, $element);

        if ($sessionValue !== null) {
            return $sessionValue;
        }

        // Fall back to tracking repository
        return $this->trackingRepository->getTrackingValue(
            $session->getPackageId(),
            $session->getId(),
            $session->getUserId(),
            $element
        );
    }

    public function commitSession(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);

        if (!$session) {
            return false;
        }

        // Save session updates
        $this->sessionRepository->save($session);

        // Commit any pending tracking data
        $this->trackingRepository->commitPendingData($sessionId);

        return true;
    }

    public function terminateSession(string $sessionId): bool
    {
        $session = $this->getSession($sessionId);

        if (!$session) {
            return false;
        }

        // Terminate the session
        $session->terminate();
        $this->sessionRepository->save($session);

        // Final commit of all data
        $this->trackingRepository->commitPendingData($sessionId);

        // Remove from cache
        unset($this->sessionCache[$sessionId]);

        return true;
    }

    public function getSessionTrackingData(string $sessionId): array
    {
        $session = $this->getSession($sessionId);

        if (!$session) {
            return [];
        }

        return $this->trackingRepository->getSessionTrackingData($sessionId);
    }

    public function suspendSession(string $sessionId, array $suspendData): bool
    {
        $session = $this->getSession($sessionId);

        if (!$session) {
            return false;
        }

        // Suspend the session with provided data
        $session->suspend($suspendData);
        $this->sessionRepository->save($session);

        return true;
    }

    /**
     * Get session from cache or repository
     */
    private function getSession(string $sessionId): ?ScormUserSession
    {
        if (isset($this->sessionCache[$sessionId])) {
            return $this->sessionCache[$sessionId];
        }

        $session = $this->sessionRepository->findById($sessionId);

        if ($session) {
            $this->sessionCache[$sessionId] = $session;
        }

        return $session;
    }

    /**
     * Update session-level data based on SCORM element
     */
    private function updateSessionData(ScormUserSession $session, string $element, string $value): void
    {
        switch ($element) {
            case 'cmi.core.lesson_status':
            case 'cmi.completion_status':
                $session->updateLessonStatus($value);
                if (in_array($value, ['completed', 'passed'])) {
                    $session->complete();
                }
                break;

            case 'cmi.core.lesson_location':
            case 'cmi.location':
                $session->updateLocation($value);
                break;

            case 'cmi.core.score.raw':
            case 'cmi.score.raw':
                $session->complete((float) $value);
                break;

            case 'cmi.core.session_time':
            case 'cmi.session_time':
                // Convert SCORM time format to seconds and add to total
                $seconds = $this->convertScormTimeToSeconds($value);
                $session->updateSessionTime($seconds);
                break;

            case 'cmi.suspend_data':
                $suspendData = json_decode($value, true) ?? [$value];
                $session->suspend($suspendData);
                break;
        }
    }

    /**
     * Get session element value
     */
    private function getSessionElementValue(ScormUserSession $session, string $element): ?string
    {
        switch ($element) {
            case 'cmi.core.lesson_status':
            case 'cmi.completion_status':
                return $session->getLessonStatus();

            case 'cmi.core.lesson_location':
            case 'cmi.location':
                return $session->getLessonLocation();

            case 'cmi.core.score.raw':
            case 'cmi.score.raw':
                return $session->getScore() !== null ? (string) $session->getScore() : null;

            case 'cmi.suspend_data':
                return json_encode($session->getSuspendData());

            default:
                return null;
        }
    }

    /**
     * Convert SCORM time format to seconds
     */
    private function convertScormTimeToSeconds(string $scormTime): int
    {
        // SCORM 1.2 format: HHHH:MM:SS.SS
        if (preg_match('/^(\d{4}):(\d{2}):(\d{2})(?:\.(\d{2}))?$/', $scormTime, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        // SCORM 2004 format: PT[n]H[n]M[n]S
        if (preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?$/', $scormTime, $matches)) {
            $hours = isset($matches[1]) ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
            $seconds = isset($matches[3]) ? (float) $matches[3] : 0;
            return (int) (($hours * 3600) + ($minutes * 60) + $seconds);
        }

        return 0;
    }

    /**
     * Get package for session
     */
    private function getPackageForSession(ScormUserSession $session)
    {
        return $this->packageRepository->findById($session->getPackageId());
    }
}
