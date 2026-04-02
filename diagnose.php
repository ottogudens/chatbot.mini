<?php
/**
 * diagnose.php
 * Script de diagnóstico para SkaleBot
 * Verifica la conexión a la base de datos, las tablas y las extensiones necesarias.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnóstico de SkaleBot</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; background: #f4f7f6; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #333; border-bottom: 2px solid #8b5cf6; padding-bottom: 10px; }
        .status { font-weight: bold; padding: 3px 8px; border-radius: 4px; }
        .ok { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .warning { background: #fef9c3; color: #854d0e; }
        pre { background: #eee; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>🛠️ Diagnóstico de SkaleBot</h1>";

// 1. PHP Extensions
echo "<div class='card'>
    <h2>1. Extensiones PHP</h2>
    <table>
        <tr><th>Extensión</th><th>Estado</th></tr>";

$extensions = ['mysqli', 'curl', 'json', 'mbstring', 'openssl', 'gd'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<tr><td>$ext</td><td><span class='status ok'>Cargada</span></td></tr>";
    } else {
        echo "<tr><td>$ext</td><td><span class='status error'>NO CARGADA</span></td></tr>";
    }
}
echo "    </table>
</div>";

// 2. Database Connection
echo "<div class='card'>
    <h2>2. Conexión a Base de Datos</h2>";

require_once 'db.php';

if (isset($conn) && $conn) {
    echo "<p><span class='status ok'>Conectado exitosamente</span> al host <code>$db_host</code></p>";
    echo "<p>Versión del Servidor: " . mysqli_get_server_info($conn) . "</p>";

    // 3. Tables Check
    echo "<h2>3. Verificación de Tablas</h2>
    <table>
        <tr><th>Tabla</th><th>Estado</th><th>Filas</th></tr>";
    
    $required_tables = [
        'clients', 'users', 'assistants', 'information_sources', 'chatbot', 
        'conversation_logs', 'leads', 'marketing_campaigns', 'appointments'
    ];

    foreach ($required_tables as $table) {
        $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if ($res && mysqli_num_rows($res) > 0) {
            $count_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM $table");
            $count = ($count_res) ? mysqli_fetch_assoc($count_res)['c'] : 'Error';
            echo "<tr><td>$table</td><td><span class='status ok'>Existe</span></td><td>$count</td></tr>";
        } else {
            echo "<tr><td>$table</td><td><span class='status error'>FALTA</span></td><td>-</td></tr>";
        }
    }
    echo "    </table>";

    // 4. Schema Check (Specific columns)
    echo "<h2>4. Verificación de Columnas Críticas</h2>";
    $checks = [
        ['table' => 'assistants', 'column' => 'voice_enabled'],
        ['table' => 'assistants', 'column' => 'handover_enabled'],
        ['table' => 'conversation_logs', 'column' => 'matched']
    ];
    
    echo "<table><tr><th>Tabla</th><th>Columna</th><th>Estado</th></tr>";
    foreach ($checks as $check) {
        $table = $check['table'];
        $column = $check['column'];
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($res && mysqli_num_rows($res) > 0) {
            echo "<tr><td>$table</td><td>$column</td><td><span class='status ok'>OK</span></td></tr>";
        } else {
            echo "<tr><td>$table</td><td>$column</td><td><span class='status error'>FALTA</span></td></tr>";
        }
    }
    echo "</table>";

} else {
    echo "<p class='status error'>ERROR: No se pudo establecer conexión con la base de datos.</p>";
    echo "<p>Verifique los valores en <code>db.php</code> o las variables de entorno.</p>";
}

echo "</div>";

// 5. Env Vars (Safe check)
echo "<div class='card'>
    <h2>5. Variables de Entorno (Resumen)</h2>
    <ul>
        <li>MYSQLHOST: " . (getenv('MYSQLHOST') ?: "<span class='status warning'>No definido</span>") . "</li>
        <li>MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ?: "<span class='status warning'>No definido (Usando default)</span>") . "</li>
        <li>GEMINI_API_KEY: " . (getenv('GEMINI_API_KEY') ? "<span class='status ok'>Configurada</span>" : "<span class='status error'>FALTA</span>") . "</li>
    </ul>
</div>";

echo "</body>
</html>";
?>
