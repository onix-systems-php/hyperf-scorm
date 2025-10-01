@extends('OnixSystemsPHP\\HyperfScorm::layout')

@section('title', 'SCORM Player - ' . $package->title)

@section('content')
    <script>
        window.SCORM_CONFIG = {
            apiEndpoint: '{{ $apiEndpoint }}',
            timeout: {{ \Hyperf\Config\config('scorm.player.timeout', 30000) }},
            debug: {{ \Hyperf\Config\config('scorm.player.debug', true) ? 'true' : 'false' }},
            autoCommitInterval: {{ \Hyperf\Config\config('scorm.tracking.auto_commit_interval', 30) }} * 1000
        };

        window.packageId = '{{ $package->id }}';

        console.log('[SCORM] Initializing player...');
        console.log('[SCORM] API Endpoint:', window.SCORM_CONFIG.apiEndpoint);
    </script>

    <!-- Load SCORM API - MUST be before iframe -->
    <script src="/public/scorm/js/api.js"></script>

    <script>
        console.log('[SCORM] API Objects available:');
        console.log('[SCORM] window.API:', typeof window.API);
        console.log('[SCORM] window.API_1484_11:', typeof window.API_1484_11);

        if (window.API) {
            console.log('[SCORM] SCORM 1.2 API ready');
        } else {
            console.error('[SCORM] SCORM 1.2 API NOT available!');
        }

        if (window.API_1484_11) {
            console.log('[SCORM] SCORM 2004 API ready');
        } else {
            console.error('[SCORM] SCORM 2004 API NOT available!');
        }
    </script>

    <div id="scorm-container">
        <div id="loading">
            <div class="loading-spinner"></div>
            <div>Loading SCORM content...</div>
            <div style="font-size: 12px; color: #666; margin-top: 10px;">
                Package: {{ $package->title }}
            </div>
        </div>
        <iframe id="scorm-frame" src="{{ $launchUrl }}" style="display:none;"></iframe>
    </div>

    <div id="debug-panel" class="scorm-debug-panel"></div>

    <!-- Load Player Logic AFTER iframe -->
    <script src="/public/scorm/js/player.js"></script>
@endsection

@section('scripts')
    <!-- Additional scripts can go here -->
@endsection
