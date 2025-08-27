<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\View\RenderInterface;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Constants\SessionStatuses;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPlayerDTO;
use OnixSystemsPHP\HyperfScorm\Factory\ScormApiStrategyFactory;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;
use function Hyperf\Support\{make,now};

/**
 * SCORM Player Service - generates player content with session restoration
 * Supports both SCORM 1.2 and SCORM 2004 with user progress restoration
 */
#[Service]
class ScormPlayerService
{
    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly ScormUserSessionRepository $scormUserSessionRepository,
        private readonly ScormApiStrategyFactory $apiStrategyFactory,
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * Generate SCORM player with session restoration capability
     */
    public function getPlayer(int $packageId, int $userId): ScormPlayerDTO
    {

        $package = $this->scormPackageRepository->findById($packageId);
        if (!$package) {
            throw new \InvalidArgumentException("SCORM package not found: {$packageId}");
        }

        $scormUserSession = $this->resolveUserSession($package, $userId);
        $apiStrategy = $this->apiStrategyFactory->createForVersion($package->scorm_version);


        return ScormPlayerDTO::make([
            'packageId' => $packageId,
            'sessionId' => $scormUserSession->id,
            'contentUrl' => $this->generateContentUrl($package),
            'launchUrl' => $this->generateLaunchUrl($package),
            'apiConfiguration' => $apiStrategy->getApiConfiguration(),
            'sessionData' => $this->prepareSessionData($scormUserSession),
            'playerHtml' => $this->generatePlayerHtml($package, $scormUserSession, $apiStrategy),
        ]);
    }

    private function resolveUserSession(ScormPackage $package, int $userId): ScormUserSession
    {
        $session = $this->scormUserSessionRepository->findUserSessionForPackage($package->id, $userId);

        if (!$session) {
            $session = $this->scormUserSessionRepository->create([
                    'package_id' => $package->id,
                    'user_id' => $userId,
                    'status' => SessionStatuses::BROWSED,
                    'current_location' => '',
                    'suspend_data' => [],
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);

            $this->scormUserSessionRepository->save($session);
        }

        return $session;
    }

    /**
     * Get initial CMI data based on SCORM version
     */
    private function getInitialCmiData(string $scormVersion): array
    {
        $baseData = [
            'cmi.core.lesson_status' => 'not attempted',
            'cmi.core.student_id' => '',
            'cmi.core.student_name' => '',
            'cmi.core.lesson_location' => '',
            'cmi.suspend_data' => '',
        ];

        if ($scormVersion === '2004') {
            $baseData['cmi.completion_status'] = 'not attempted';
            $baseData['cmi.success_status'] = 'unknown';
            $baseData['cmi.learner_id'] = '';
            $baseData['cmi.learner_name'] = '';
            $baseData['cmi.location'] = '';
            $baseData['cmi.progress_measure'] = '';
        }

        return $baseData;
    }

    /**
     * Generate secure content URL for SCORM package
     */
    private function generateContentUrl(ScormPackage $package): string
    {
        return $this->fileProcessor->getPublicUrl($package->content_path);
    }

    /**
     * Generate launch URL for the main SCO
     */
    private function generateLaunchUrl(ScormPackage $package): string
    {
        $primaryLaunchUrl = $package->manifest_data->getPrimaryLaunchUrl();
        if (!$primaryLaunchUrl) {
            throw new \RuntimeException("No SCOs found for package: {$package->id}");
        }

        return $this->fileProcessor->getPublicUrl(
            $package->content_path,
            $primaryLaunchUrl
        );
    }

    /**
     * Prepare session data for restoration
     */
    private function prepareSessionData(ScormUserSession $attempt): array
    {
        return [
            'attemptId' => $attempt->id,
            'status' => $attempt->status ?? 'not attempted',
            'suspendData' => $attempt->suspend_data ?? [],
            'currentLocation' => $attempt->current_location ?? '',
            'startedAt' => $attempt->started_at ? $attempt->started_at->toISOString() : null,
        ];
    }

    private function generatePlayerHtml(
        ScormPackage $package,
        ScormUserSession $attempt,
        $apiStrategy
    ): string {
        $launchUrl = $this->generateLaunchUrl($package);
        $apiConfig = $apiStrategy->getApiConfiguration();
        $sessionData = $this->prepareSessionData($attempt);
        $apiEndpoint = $this->config->get('scorm.player.api_endpoint', '/api/v1/scorm/api');
        $render = make(RenderInterface::class);
        $template = $render->getContents('OnixSystemsPHP\\HyperfScorm::player', [
            'package' => $package,
            'launchUrl' => $launchUrl,
            'apiEndpoint' => $apiEndpoint,
            'apiConfig' => $apiConfig,
            'sessionData' => $sessionData,
            'scormVersion' => $package->scorm_version,
        ]);

        return $template;
//        return <<<HTML
//<!DOCTYPE html>
//<html lang="en">
//<head>
//    <meta charset="UTF-8">
//    <meta name="viewport" content="width=device-width, initial-scale=1.0">
//    <title>SCORM Player - {$package->title}</title>
//    <style>
//        body {
//            margin: 0;
//            padding: 0;
//            font-family: Arial, sans-serif;
//            background: #f5f5f5;
//        }
//        #scorm-container {
//            position: relative;
//            width: 100vw;
//            height: 100vh;
//        }
//        #scorm-frame {
//            width: 100%;
//            height: 100%;
//            border: none;
//            background: white;
//        }
//        #loading {
//            position: absolute;
//            top: 50%;
//            left: 50%;
//            transform: translate(-50%, -50%);
//            background: white;
//            padding: 20px;
//            border-radius: 8px;
//            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
//            text-align: center;
//        }
//        .spinner {
//            border: 4px solid #f3f3f3;
//            border-top: 4px solid #3498db;
//            border-radius: 50%;
//            width: 30px;
//            height: 30px;
//            animation: spin 1s linear infinite;
//            margin: 0 auto 10px;
//        }
//        @keyframes spin {
//            0% { transform: rotate(0deg); }
//            100% { transform: rotate(360deg); }
//        }
//        #debug-panel {
//            position: fixed;
//            bottom: 10px;
//            right: 10px;
//            background: rgba(0,0,0,0.8);
//            color: white;
//            padding: 10px;
//            border-radius: 4px;
//            font-size: 12px;
//            max-width: 300px;
//            display: none;
//        }
//    </style>
//</head>
//<body>
//    <div id="scorm-container">
//        <div id="loading">
//            <div class="spinner"></div>
//            <div>Loading SCORM content...</div>
//            <div style="font-size: 12px; color: #666; margin-top: 10px;">
//                Package: {$package->title}
//            </div>
//        </div>
//        <iframe id="scorm-frame" src="{$launchUrl}" style="display:none;"></iframe>
//    </div>
//
//    <div id="debug-panel"></div>
//
//    <script>
//        // Global configuration
//        window.SCORM_CONFIG = {
//            apiEndpoint: '{$apiEndpoint}',
//            timeout: {$this->config->get('scorm.player.timeout', 30000)},
//            debug: true,
//            autoCommitInterval: {$this->config->get('scorm.tracking.auto_commit_interval', 30)} * 1000
//        };
//
//        // SCORM API Implementation
//        {$this->generateScormApiScript($apiConfig, $sessionData)}
//
//        // Frame load handler
//        document.getElementById('scorm-frame').onload = function() {
//            console.log('SCORM content loaded');
//            document.getElementById('loading').style.display = 'none';
//            this.style.display = 'block';
//
//            // Debug panel
//            if (window.SCORM_CONFIG.debug) {
//                document.getElementById('debug-panel').style.display = 'block';
//                updateDebugPanel();
//            }
//        };
//
//        // Debug panel updates
//        function updateDebugPanel() {
//            if (!window.SCORM_CONFIG.debug) return;
//
//            const panel = document.getElementById('debug-panel');
//            const api = window.{$apiConfig['apiObjectName']};
//            panel.innerHTML = `
//                <strong>SCORM Debug</strong><br>
//                Initialized: \${api.initialized}<br>
//                Terminated: \${api.terminated}<br>
//                Attempt ID: \${api.attemptId}<br>
//                Last Error: \${api.lastError}<br>
//                API Calls: \${api.apiCalls}
//            `;
//        }
//
//        // Error handling
//        window.onerror = function(msg, url, line, col, error) {
//            console.error('SCORM Player Error:', {msg, url, line, col, error});
//            if (window.SCORM_CONFIG.debug) {
//                updateDebugPanel();
//            }
//        };
//    </script>
//</body>
//</html>
//HTML;
    }

    /**
     * Generate SCORM API JavaScript based on version
     */
    private function generateScormApiScript(array $apiConfig, array $sessionData): string
    {
        $sessionDataJson = $this->jsonEncode($sessionData);
        $elementMappingJson = $this->jsonEncode($apiConfig['elementMapping']);

        return <<<JAVASCRIPT
// SCORM Session Data
window.scormSessionData = {$sessionDataJson};

// SCORM API Object
window.{$apiConfig['apiObjectName']} = {
    initialized: false,
    terminated: false,
    attemptId: {$sessionData['attemptId']},
    lastError: "0",
    apiCalls: 0,
    pendingData: {},

    {$apiConfig['initializeFunction']}: function(parameter) {
        this.apiCalls++;
        console.log('SCORM API: Initialize called');

        if (this.initialized) {
            this.lastError = "103"; // Already initialized
            return "false";
        }

        // Initialize session with server
        this.sendApiRequest('initialize', {
            action: 'initialize',
            parameter: parameter
        }).then(response => {
            if (response.success) {
                console.log('SCORM session initialized');
            }
        }).catch(error => {
            console.error('Failed to initialize SCORM session:', error);
        });

        this.initialized = true;
        this.lastError = "0";
        return "true";
    },

    {$apiConfig['terminateFunction']}: function(parameter) {
        this.apiCalls++;
        console.log('SCORM API: Terminate called');

        if (!this.initialized) {
            this.lastError = "112"; // Not initialized
            return "false";
        }

        if (this.terminated) {
            this.lastError = "113"; // Already terminated
            return "false";
        }

        // Commit any pending data before terminating
        this.{$apiConfig['commitFunction']}("");

        // Terminate session with server
        this.sendApiRequest('terminate', {
            action: 'terminate',
            parameter: parameter
        }).then(response => {
            if (response.success) {
                console.log('SCORM session terminated');
            }
        }).catch(error => {
            console.error('Failed to terminate SCORM session:', error);
        });

        this.terminated = true;
        this.lastError = "0";
        return "true";
    },

    {$apiConfig['getValueFunction']}: function(element) {
        this.apiCalls++;

        if (!this.initialized) {
            this.lastError = "112"; // Not initialized
            return "";
        }

        const value = this.getElementValue(element);
        console.log(`SCORM API: Get value - \${element} = \${value}`);

        this.lastError = "0";
        return value;
    },

    {$apiConfig['setValueFunction']}: function(element, value) {
        this.apiCalls++;
        console.log(`SCORM API: Set value - \${element} = \${value}`);

        if (!this.initialized) {
            this.lastError = "112"; // Not initialized
            return "false";
        }

        // Store in pending data for batch commit
        this.pendingData[element] = value;

        // Update local session data immediately
        const elementMap = {$elementMappingJson};
        if (elementMap[element]) {
            window.scormSessionData[elementMap[element]] = value;
        }

        this.lastError = "0";
        return "true";
    },

    {$apiConfig['commitFunction']}: function(parameter) {
        this.apiCalls++;
        console.log('SCORM API: Commit called');

        if (!this.initialized) {
            this.lastError = "112"; // Not initialized
            return "false";
        }

        if (Object.keys(this.pendingData).length === 0) {
            console.log('No data to commit');
            this.lastError = "0";
            return "true";
        }

        // Commit data to server
        this.sendApiRequest('commit', {
            action: 'commit',
            data: this.pendingData,
            parameter: parameter
        }).then(response => {
            if (response.success) {
                console.log('SCORM data committed successfully');
                this.pendingData = {}; // Clear pending data
            } else {
                console.error('Failed to commit SCORM data:', response.message);
            }
        }).catch(error => {
            console.error('Error committing SCORM data:', error);
        });

        this.lastError = "0";
        return "true";
    },

    {$apiConfig['getLastErrorFunction']}: function() {
        return this.lastError;
    },

    {$apiConfig['getErrorStringFunction']}: function(errorCode) {
        const errorMessages = {
            "0": "No Error",
            "101": "General Exception",
            "102": "General Initialization Failure",
            "103": "Already Initialized",
            "104": "Content Instance Terminated",
            "111": "General Termination Failure",
            "112": "Termination Before Initialization",
            "113": "Termination After Termination",
            "122": "Retrieve Data Before Initialization",
            "123": "Retrieve Data After Termination",
            "132": "Store Data Before Initialization",
            "133": "Store Data After Termination",
            "142": "Commit Before Initialization",
            "143": "Commit After Termination"
        };

        return errorMessages[errorCode] || "Unknown Error";
    },

    {$apiConfig['getDiagnosticFunction']}: function(errorCode) {
        return this.{$apiConfig['getErrorStringFunction']}(errorCode);
    },

    getElementValue: function(element) {
        const elementMap = {$elementMappingJson};

        if (elementMap[element]) {
            const value = window.scormSessionData[elementMap[element]];
            return value !== undefined ? String(value) : "";
        }

        // Handle special elements
        if (element === 'cmi.core.student_id' || element === 'cmi.learner_id') {
            return String(window.scormSessionData.userId || "");
        }

        // Return from CMI data if available
        if (window.scormSessionData.cmiData && window.scormSessionData.cmiData[element]) {
            return String(window.scormSessionData.cmiData[element]);
        }

        return "";
    },

    sendApiRequest: async function(endpoint, data) {
        const url = `\${window.SCORM_CONFIG.apiEndpoint}/\${this.attemptId}/\${endpoint}`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data),
                timeout: window.SCORM_CONFIG.timeout
            });

            if (!response.ok) {
                throw new Error(`HTTP \${response.status}: \${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('SCORM API request failed:', error);
            throw error;
        }
    }
};

// Auto-commit data at regular intervals
if (window.SCORM_CONFIG.autoCommitInterval > 0) {
    setInterval(function() {
        const api = window.{$apiConfig['apiObjectName']};
        if (api.initialized && !api.terminated) {
            api.{$apiConfig['commitFunction']}("");
        }
    }, window.SCORM_CONFIG.autoCommitInterval);
}

// Commit data before page unload
window.addEventListener('beforeunload', function() {
    const api = window.{$apiConfig['apiObjectName']};
    if (api.initialized && !api.terminated) {
        api.{$apiConfig['commitFunction']}("");
    }
});

console.log('SCORM API {$apiConfig['apiObjectName']} ready');
JAVASCRIPT;
    }

    /**
     * JSON encode with proper escaping
     */
    private function jsonEncode(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT);
    }
}
