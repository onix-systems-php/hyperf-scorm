<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SCORM Player')</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        #scorm-container {
            position: relative;
            width: 100vw;
            height: 100vh;
        }

        #scorm-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }

        .scorm-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 300px;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .scorm-debug-panel {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 15px;
            border-radius: 6px;
            font-size: 12px;
            max-width: 350px;
            display: none;
            z-index: 9999;
        }

        .debug-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .debug-toggle {
            background: #3498db;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 10px;
        }

        .debug-section {
            margin-bottom: 8px;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: #eee;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: #3498db;
            width: 0%;
            animation: progress 2s ease-in-out infinite;
        }

        @keyframes progress {
            0% { width: 0%; }
            50% { width: 60%; }
            100% { width: 100%; }
        }

        @yield('styles')
    </style>
</head>
<body>
    @yield('content')
    @yield('scripts')
</body>
</html>
