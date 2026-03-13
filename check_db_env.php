<?php
header('Content-Type: application/json');

$env_vars = [
    'MYSQLHOST' => getenv('MYSQLHOST'),
    'MYSQLUSER' => getenv('MYSQLUSER'),
    'MYSQLPORT' => getenv('MYSQLPORT'),
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
    'MYSQL_URL' => getenv('MYSQL_URL') ? 'SET' : 'NOT SET',
    'PHP_VERSION' => PHP_VERSION,
];

$results = [
    'env' => $env_vars,
    'connection_test' => []
];

// Connection test
$host = $env_vars['MYSQLHOST'] ?: 'localhost';
$port = $env_vars['MYSQLPORT'] ?: '3306';
$user = $env_vars['MYSQLUSER'] ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db = $env_vars['MYSQLDATABASE'] ?: '';

try {
    $conn = @mysqli_connect($host, $user, $pass, $db, $port);
    if ($conn) {
        $results['connection_test'] = [
            'status' => 'success',
            'server_info' => mysqli_get_server_info($conn),
        ];
    } else {
        $results['connection_test'] = [
            'status' => 'failed',
            'error' => mysqli_connect_error(),
            'error_no' => mysqli_connect_errno(),
        ];
    }
} catch (Exception $e) {
    $results['connection_test'] = [
        'status' => 'exception',
        'message' => $e->getMessage(),
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>