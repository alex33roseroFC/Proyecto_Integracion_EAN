<?php
header('Content-Type: application/json');
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}


require_once 'include.php';
require_once 'config.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión DB']);
    exit;
}

$usuario = $_SESSION['usuario'];
$numero_empleado = isset($_SESSION['matricula']) ? $_SESSION['matricula'] : '';

// Obtener la semana actual (lunes a domingo)
$fecha_actual = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$timestamp = strtotime($fecha_actual);
$lunes = strtotime('monday this week', $timestamp);
$domingo = strtotime('sunday this week', $timestamp);

$fecha_lunes = date('Y-m-d', $lunes);
$fecha_domingo = date('Y-m-d', $domingo);

// Obtener actividades del usuario para esta semana
$sql = "SELECT id, codigo_affaire, nombre_proyect, fecha, tiempo_imputado_horas 
        FROM cargue_horas 
        WHERE numero_de_empleado = ? 
        AND fecha >= ? 
        AND fecha <= ?
        ORDER BY codigo_affaire, fecha";


$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $numero_empleado, $fecha_lunes, $fecha_domingo);
$stmt->execute();
$result = $stmt->get_result();

$actividades = [];
$actividades_agrupadas = [];

while ($row = $result->fetch_assoc()) {
    $key = $row['codigo_affaire'];
    if (!isset($actividades_agrupadas[$key])) {
        $actividades_agrupadas[$key] = [
            'codigo_affaire' => $row['codigo_affaire'],
            'nombre_proyect' => $row['nombre_proyect'],
            'horas' => array_fill(0, 7, 0),
            'id' => $row['id']
        ];
    }
    
    // Obtener el día de la semana (0 = lunes, 6 = domingo)
    $fecha_ts = strtotime($row['fecha']);
    $dia_semana = (int)date('w', $fecha_ts) - 1;
    if ($dia_semana < 0) $dia_semana = 6;
    
    $actividades_agrupadas[$key]['horas'][$dia_semana] = $row['tiempo_imputado_horas'];
}


$stmt->close();
$conn->close();

echo json_encode(array_values($actividades_agrupadas));
?>
