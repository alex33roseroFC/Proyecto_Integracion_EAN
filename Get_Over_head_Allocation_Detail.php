<?php
// Limpiar cualquier salida previa y desactivar errores visuales
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json; charset=utf-8');


// Conexión centralizada
require_once 'include.php';
require_once 'config.php';
// $conn ya debe estar definido y conectado
try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Error de conexión: conexión no disponible');
    }
    $conn->set_charset('utf8mb4');

    // Obtener parámetros
    $numero_empleado = isset($_GET['numero_empleado']) ? $conn->real_escape_string($_GET['numero_empleado']) : '';
    $codigo_affaire = isset($_GET['codigo_affaire']) ? $conn->real_escape_string($_GET['codigo_affaire']) : '';

    // Validar que se hayan recibido los parámetros
    if (empty($numero_empleado) || empty($codigo_affaire)) {
        throw new Exception('Faltan parámetros requeridos');
    }

    // Consultar la tabla horas_dia
    $sql = "SELECT 
        numero_empleado,
        nom,
        prenom,
        nombre_affaire,
        DATE_FORMAT(fecha, '%d/%m/%Y') as fecha,
        tiempo_imputado_horas,
        tiempo_imputado_costo,
        comentario
    FROM horas_dia
    WHERE numero_empleado = '$numero_empleado'
        AND codigo_affaire = '$codigo_affaire'
        AND Estado_Aprobacion = 'Aprobado En Curso'
    ORDER BY fecha DESC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Error en la consulta: ' . $conn->error);
    }

    $detalles = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $detalles[] = [
                'numero_empleado' => $row['numero_empleado'] ?? '-',
                'nom' => $row['nom'] ?? '-',
                'prenom' => $row['prenom'] ?? '-',
                'proyecto' => $row['nombre_affaire'] ?? '-',
                'fecha' => $row['fecha'] ?? '-',
                'horas' => number_format((float)($row['tiempo_imputado_horas'] ?? 0), 2, ',', '.'),
                'costo' => number_format((float)($row['tiempo_imputado_costo'] ?? 0), 0, ',', '.'),
                'comentario' => $row['comentario'] ?? '-'
            ];
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'detalles' => $detalles,
            'total_registros' => count($detalles)
        ], JSON_UNESCAPED_UNICODE);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'No se encontraron detalles para este empleado y proyecto',
            'detalles' => [],
            'debug' => [
                'numero_empleado' => $numero_empleado,
                'codigo_affaire' => $codigo_affaire,
                'tabla' => 'horas_dia'
            ]
        ], JSON_UNESCAPED_UNICODE);
    }

    // No cerrar $conn, lo gestiona config.php
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'detalles' => []
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
?>
