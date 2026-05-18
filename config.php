<?php
/**
 * CONFIGURACIÓN DINÁMICA DE LA APLICACIÓN
 * Funciona tanto en localhost como en producción (SiteGround)
 */

if (!function_exists('get_environment')) {
    function get_environment() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'app1boggca.setec.local') !== false) {
            return 'LOCAL';
        }
        return 'PRODUCCION';
    }
}

$ENVIRONMENT = get_environment();
$host = $_SERVER['HTTP_HOST'];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$is_localhost = ($ENVIRONMENT === 'LOCAL');

// Detectar la ruta base automáticamente
$script_name = $_SERVER['SCRIPT_NAME']; // Ejemplo: /App_Control_Documental/Login.php
$script_dir = str_replace('\\', '/', dirname($script_name)); // Obtener la carpeta: /App_Control_Documental

// Calcular base_path correctamente
$base_path = ($script_dir === '/' || $script_dir === '') ? '' : $script_dir;

// VARIABLES DE ENTORNO
define('IS_LOCALHOST', $is_localhost);
define('ENVIRONMENT', $ENVIRONMENT);
define('APP_HOST', $host);
define('APP_PROTOCOL', $protocol);

// CONFIGURACIÓN DE RUTAS BASE
if ($ENVIRONMENT === 'LOCAL') {
    // Configuración para LOCALHOST (XAMPP) - Forzar siempre la misma URL base
    define('BASE_URL', 'http://app1boggca.setec.local/');
    define('BASE_PATH', 'App_Control_Documental');
} else {
    // Configuración para PRODUCCIÓN (SiteGround)
    define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST'] . $base_path . '/');
    define('BASE_PATH', trim($base_path, '/'));
}

// FUNCIÓN AUXILIAR para generar URLs dinámicas
function get_url($page, $params = []) {
    $url = BASE_URL . $page;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

// FUNCIÓN para obtener URL relativa (para casos especiales)
function get_relative_url($page, $params = []) {
    $base_path = BASE_PATH ? '/' . BASE_PATH . '/' : '/';
    $url = $base_path . $page;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

// FUNCIÓN para obtener el nombre de la página actual
function get_current_page() {
    return basename($_SERVER['PHP_SELF']);
}

// FUNCIÓN para verificar si página actual es igual a la página pasada
function is_active($page) {
    return get_current_page() === $page ? true : false;
}

// FUNCIÓN para generar clase active si está en página actual
function active_class($page) {
    return is_active($page) ? ' active' : '';
}
