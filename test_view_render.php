<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use Hyperf\View\RenderInterface;
use function Hyperf\Support\make;

echo "🔧 Testing SCORM View Rendering\n";
echo "==============================\n\n";

try {
    $render = make(RenderInterface::class);
    
    // Test data
    $data = [
        'package' => (object) [
            'title' => 'Test SCORM Package',
            'id' => 1
        ],
        'launchUrl' => 'http://localhost:9501/scorm/content/test.html',
        'apiEndpoint' => '/api/v1/scorm/api',
        'apiConfig' => [
            'apiObjectName' => 'API_1484_11',
            'version' => '2004'
        ],
        'sessionData' => [
            'attemptId' => 'session_123',
            'status' => 'active',
            'lessonLocation' => '',
            'lessonStatus' => 'not attempted'
        ],
        'scormVersion' => '2004',
        'apiScript' => '// SCORM API Script placeholder'
    ];
    
    echo "📋 Test Data Prepared:\n";
    echo "✅ Package: " . $data['package']->title . "\n";
    echo "✅ Launch URL: " . $data['launchUrl'] . "\n";
    echo "✅ API Endpoint: " . $data['apiEndpoint'] . "\n";
    echo "✅ SCORM Version: " . $data['scormVersion'] . "\n";
    
    // Try to render the view
    echo "\n🔄 Attempting to render view...\n";
    
    $html = $render->render('OnixSystemsPHP\\HyperfScorm::player', $data);
    
    echo "✅ View rendered successfully!\n";
    echo "✅ HTML Length: " . strlen($html) . " characters\n";
    
    // Check if important elements are present
    $checks = [
        'SCORM Player' => str_contains($html, 'SCORM Player'),
        'Package Title' => str_contains($html, $data['package']->title),
        'Launch URL' => str_contains($html, $data['launchUrl']),
        'API Config' => str_contains($html, 'SCORM_CONFIG'),
        'Session Data' => str_contains($html, 'scormSessionData'),
        'Loading Spinner' => str_contains($html, 'spinner'),
        'Debug Panel' => str_contains($html, 'debug-panel')
    ];
    
    echo "\n🔍 Content Checks:\n";
    foreach ($checks as $check => $result) {
        echo ($result ? "✅" : "❌") . " $check: " . ($result ? "Found" : "Missing") . "\n";
    }
    
    // Save rendered HTML for inspection
    file_put_contents(__DIR__ . '/test_output.html', $html);
    echo "\n💾 HTML saved to: test_output.html\n";
    
    echo "\n🎉 View rendering test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "❌ File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "❌ Trace: " . $e->getTraceAsString() . "\n";
}
