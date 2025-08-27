<div id="debug-panel" class="scorm-debug-panel">
    <div class="debug-header">
        <h4>SCORM Debug</h4>
        <button class="debug-toggle" onclick="toggleDebugPanel()">Toggle</button>
    </div>
    <div class="debug-content">
        <div class="debug-section">
            <strong>Status:</strong>
            <span id="debug-status">{{ $sessionData['status'] ?? 'Initializing...' }}</span>
        </div>
        <div class="debug-section">
            <strong>Session ID:</strong>
            <span id="debug-session-id">{{ $sessionData['attemptId'] ?? 'N/A' }}</span>
        </div>
        <div class="debug-section">
            <strong>Location:</strong>
            <span id="debug-location">{{ $sessionData['currentLocation'] ?? '' }}</span>
        </div>
        <div class="debug-section">
            <strong>API Calls:</strong>
            <span id="debug-api-calls">0</span>
        </div>
        <div class="debug-section">
            <strong>Last Error:</strong>
            <span id="debug-last-error">None</span>
        </div>
    </div>
</div>
