<div id="loading" class="scorm-loading">
    <div class="loading-spinner"></div>
    <div class="loading-text">Loading SCORM content...</div>
    <div class="package-info">
        <strong>Package:</strong> {{$package->title}}
        @if($package->description)
            <br><small>{{$package->description}}</small>
        @endif
    </div>
    <div class="loading-progress">
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
    </div>
</div>
