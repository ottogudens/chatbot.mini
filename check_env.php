<?php
header('Content-Type: application/json');
$report = [
    'php_version' => PHP_VERSION,
    'extensions' => [
        'fileinfo' => extension_loaded('fileinfo'),
        'curl' => extension_loaded('curl'),
        'mysqli' => extension_loaded('mysqli'),
        'openssl' => extension_loaded('openssl'),
    ],
    'directories' => [],
    'env' => [
        'GEMINI_API_KEY' => getenv('GEMINI_API_KEY') ? 'Configured' : 'Missing',
    ]
];

$dirs = ['uploads', 'uploads/clients'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    $report['directories'][$dir] = [
        'exists' => file_exists($path),
        'writable' => is_writable($path),
        'perms' => file_exists($path) ? substr(sprintf('%o', file_perms($path)), -4) : null
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT);
?>