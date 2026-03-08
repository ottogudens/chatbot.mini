<?php
require_once 'db.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Diagnóstico SkaleBot</title><style>body{font-family:sans-serif; padding: 20px;} .ok{color:green;font-weight:bold;} .fail{color:red;font-weight:bold;}</style></head><body>";
echo "<h2>1. Pruebas de Directorios (Mapeo de Volumen Railway)</h2>";

$upload_dir = __DIR__ . '/uploads';

if (file_exists($upload_dir)) {
    echo "Carpeta base <code>/app/uploads</code>: <span class='ok'>Existe</span><br>";
    if (is_writable($upload_dir)) {
        echo "Permisos de escritura en <code>/app/uploads</code>: <span class='ok'>Permitido</span><br>";
    } else {
        echo "Permisos de escritura en <code>/app/uploads</code>: <span class='fail'>DENEGADO (Permission Denied)</span><br>";

        $owner_id = fileowner($upload_dir);
        $owner_info = function_exists('posix_getpwuid') ? posix_getpwuid($owner_id)['name'] : $owner_id;
        $current_user = get_current_user();
        echo "<blockquote><strong>Diagnóstico:</strong> El sistema está ejecutando PHP con el usuario <code>$current_user</code>, pero el Disco/Volumen le pertenece al usuario <code>$owner_info</code>. Por eso no puede escribir. Debes ir a tu panel de Railway -> Variables, y añadir <code>RAILWAY_RUN_UID = 0</code>.</blockquote>";
    }
} else {
    echo "Carpeta base <code>/app/uploads</code>: <span class='fail'>No Existe</span><br>";
}

echo "<h2>2. Diagnóstico de Tablas en Base de Datos</h2>";

$expected_tables = ['clients', 'assistants', 'information_sources', 'chatbot', 'conversation_logs', 'users'];
echo "<ul>";
foreach ($expected_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "<li>Tabla <b>$table</b>: <span class='ok'>Existe</span><ul>";
        $cols = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
        while ($col = mysqli_fetch_assoc($cols)) {
            echo "<li><code>" . $col['Field'] . "</code> (" . $col['Type'] . ")</li>";
        }
        echo "</ul></li>";
    } else {
        echo "<li>Tabla <b>$table</b>: <span class='fail'>NO EXISTE</span></li>";
    }
}
echo "</ul>";

echo "</body></html>";
?>