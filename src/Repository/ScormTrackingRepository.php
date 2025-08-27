<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Hyperf\DbConnection\Db;
use function Hyperf\Support\now;

/**
 * SCORM Tracking Repository implementation
 */
class ScormTrackingRepository implements ScormTrackingRepositoryInterface
{
    private string $trackingTable = 'scorm_tracking';
    private string $sessionTrackingTable = 'scorm_session_tracking';

    public function storeTrackingData(
        int $packageId,
        string $sessionId,
        int $userId,
        string $elementName,
        string $elementValue
    ): bool {
        $data = [
            'package_id' => $packageId,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'element_name' => $elementName,
            'element_value' => $elementValue,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s')
        ];

        // Check if tracking record already exists for this session and element
        $existing = Db::table($this->sessionTrackingTable)
            ->where('session_id', $sessionId)
            ->where('element_name', $elementName)
            ->first();

        if ($existing) {
            // Update existing record
            return Db::table($this->sessionTrackingTable)
                ->where('id', $existing->id)
                ->update([
                    'element_value' => $elementValue,
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ]) > 0;
        } else {
            // Insert new record
            return Db::table($this->sessionTrackingTable)->insert($data) > 0;
        }
    }

    public function getTrackingValue(
        int $packageId,
        string $sessionId,
        int $userId,
        string $elementName
    ): ?string {
        $record = Db::table($this->sessionTrackingTable)
            ->where('session_id', $sessionId)
            ->where('element_name', $elementName)
            ->orderBy('updated_at', 'desc')
            ->first();

        return $record ? $record->element_value : null;
    }

    public function getSessionTrackingData(string $sessionId): array
    {
        $records = Db::table($this->sessionTrackingTable)
            ->where('session_id', $sessionId)
            ->orderBy('updated_at', 'desc')
            ->get();

        $trackingData = [];
        foreach ($records as $record) {
            $trackingData[$record->element_name] = $record->element_value;
        }

        return $trackingData;
    }

    public function getUserPackageTrackingData(int $userId, int $packageId): array
    {
        $records = Db::table($this->trackingTable)
            ->where('user_id', $userId)
            ->where('package_id', $packageId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $records->toArray();
    }

    public function commitPendingData(string $sessionId): bool
    {
        // Move session tracking data to main tracking table for historical record
        $sessionData = Db::table($this->sessionTrackingTable)
            ->where('session_id', $sessionId)
            ->get();

        foreach ($sessionData as $record) {
            // Insert into main tracking table
            Db::table($this->trackingTable)->insert([
                'package_id' => $record->package_id,
                'sco_id' => $record->sco_id ?? null,
                'user_id' => $record->user_id,
                'attempt_id' => null, // This could be linked to attempts if needed
                'element_name' => $record->element_name,
                'element_value' => $record->element_value,
                'created_at' => $record->created_at,
                'updated_at' => now()->format('Y-m-d H:i:s')
            ]);
        }

        return true;
    }

    public function getPackageTrackingStatistics(int $packageId): array
    {
        $stats = Db::table($this->trackingTable)
            ->select([
                Db::raw('COUNT(DISTINCT user_id) as total_users'),
                Db::raw('COUNT(*) as total_interactions'),
                Db::raw('AVG(CASE WHEN element_name = "score_raw" THEN CAST(element_value AS DECIMAL) END) as avg_score'),
                Db::raw('COUNT(CASE WHEN element_name = "lesson_status" AND element_value IN ("completed", "passed") THEN 1 END) as completed_count')
            ])
            ->where('package_id', $packageId)
            ->first();

        // Get completion rate by status
        $statusStats = Db::table($this->trackingTable)
            ->select(['element_value', Db::raw('COUNT(*) as count')])
            ->where('package_id', $packageId)
            ->where('element_name', 'lesson_status')
            ->groupBy('element_value')
            ->get()
            ->keyBy('element_value');

        return [
            'total_users' => (int) $stats->total_users,
            'total_interactions' => (int) $stats->total_interactions,
            'avg_score' => round((float) $stats->avg_score, 2),
            'completed_count' => (int) $stats->completed_count,
            'status_distribution' => $statusStats->toArray()
        ];
    }

    public function deleteSessionTrackingData(string $sessionId): bool
    {
        return Db::table($this->sessionTrackingTable)
            ->where('session_id', $sessionId)
            ->delete() > 0;
    }

    public function getInteractionData(string $sessionId): array
    {
        $interactions = Db::table($this->sessionTrackingTable)
            ->where('session_id', $sessionId)
            ->where('element_name', 'like', 'cmi.interactions.%')
            ->orderBy('created_at')
            ->get();

        $interactionData = [];
        foreach ($interactions as $interaction) {
            // Parse interaction element name (e.g., cmi.interactions.0.id)
            if (preg_match('/cmi\.interactions\.(\d+)\.(.+)/', $interaction->element_name, $matches)) {
                $interactionIndex = $matches[1];
                $property = $matches[2];

                if (!isset($interactionData[$interactionIndex])) {
                    $interactionData[$interactionIndex] = [];
                }

                $interactionData[$interactionIndex][$property] = $interaction->element_value;
            }
        }

        return array_values($interactionData);
    }

    public function storeInteractionData(
        string $sessionId,
        string $interactionId,
        array $interactionData
    ): bool {
        // Store each interaction property as separate tracking record
        foreach ($interactionData as $property => $value) {
            $elementName = "cmi.interactions.{$interactionId}.{$property}";

            // Get session info for this tracking
            $sessionInfo = Db::table('scorm_user_sessions')
                ->where('id', $sessionId)
                ->first();

            if ($sessionInfo) {
                $this->storeTrackingData(
                    $sessionInfo->package_id,
                    $sessionId,
                    $sessionInfo->user_id,
                    $elementName,
                    (string) $value
                );
            }
        }

        return true;
    }
}
