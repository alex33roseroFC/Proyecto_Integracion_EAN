<?php
// db_connection.php - Conexión adaptable a entorno local y producción


// Detectar entorno solo si no existe la función
if (!function_exists('get_environment')) {
    function get_environment() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            return 'LOCAL';
        }
        return 'PRODUCCION';
    }
}

$ENVIRONMENT = get_environment();

if ($ENVIRONMENT === 'LOCAL') {
    // Credenciales para entorno local
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'control_presupuestal_horas');
} else {
    // Credenciales para producción
    define('DB_SERVER', 'localhost');
    define('DB_USERNAME', 'untiembzufpqh');
    define('DB_PASSWORD', '{1c#1@@:13~u');
    define('DB_NAME', 'dbugqgfvwmknci');
}

// Crear conexión
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if($mysqli === false){
    die("ERROR: No se pudo conectar. " . $mysqli->connect_error);
}
?>