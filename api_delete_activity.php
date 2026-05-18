<?php
header('Content-Type: application/json');
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado', 'success' => false]);
    exit;
}


require_once 'include.php';
require_once 'config.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión DB', 'success' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$activity_id = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;

if ($activity_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de actividad inválido']);
    exit;
}

$usuario = $_SESSION['usuario'];
$numero_empleado = isset($_SESSION['matricula']) ? $_SESSION['matricula'] : '';

// Verificar que la actividad pertenezca al usuario
$sql_check = "SELECT id FROM cargue_horas WHERE id = ? AND numero_de_empleado = ?";

$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("is", $activity_id, $numero_empleado);
$stmt_check->execute();
$check_result = $stmt_check->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Actividad no encontrada']);
    $stmt_check->close();
    exit;
}
$stmt_check->close();

// Obtener código_affaire y fecha del registro
    $sql_get = "SELECT codigo_affaire, fecha FROM cargue_horas WHERE id = ? AND numero_de_empleado = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("is", $activity_id, $numero_empleado);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
if ($result_get->num_rows === 0) {
    $stmt_get->close();
    echo json_encode(['success' => false, 'message' => 'Actividad no encontrada']);
    $mysqli->close();
    exit;
}
$row_get = $result_get->fetch_assoc();
$codigo_affaire = $row_get['codigo_affaire'];
$fecha = $row_get['fecha'];
$stmt_get->close();

// Calcular semana de la fecha

// Calcular el rango de la semana de la fecha seleccionada
$dia_semana = date('N', strtotime($fecha)); // 1 (lunes) a 7 (domingo)
$fecha_lunes = date('Y-m-d', strtotime($fecha . ' -' . ($dia_semana - 1) . ' days'));
$fecha_domingo = date('Y-m-d', strtotime($fecha . ' +' . (7 - $dia_semana) . ' days'));

// Eliminar SOLO los registros de horas_dia de la semana correspondiente


// DEBUG: Registrar los valores usados en la consulta
error_log("DEBUG DELETE horas_dia: empleado=$numero_empleado, affaire=$codigo_affaire, lunes=$fecha_lunes, domingo=$fecha_domingo");
$sql_delete_horas = "DELETE FROM horas_dia WHERE numero_empleado = ? AND codigo_affaire = ? AND fecha BETWEEN ? AND ?";

$stmt_delete_horas = $conn->prepare($sql_delete_horas);
$stmt_delete_horas->bind_param("ssss", $numero_empleado, $codigo_affaire, $fecha_lunes, $fecha_domingo);
$stmt_delete_horas->execute();

$affected_rows = $stmt_delete_horas->affected_rows;
$stmt_delete_horas->close();

if ($affected_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No se eliminaron registros. Verifica los datos y el rango de fechas.',
        'debug' => [
            'empleado' => $numero_empleado,
            'affaire' => $codigo_affaire,
            'lunes' => $fecha_lunes,
            'domingo' => $fecha_domingo
        ]
    ]);
    exit;
}

// Verificar si quedan registros en horas_dia para ese usuario y código_affaire
$sql_check_horas = "SELECT COUNT(*) as total FROM horas_dia WHERE numero_empleado = ? AND codigo_affaire = ?";
$stmt_check_horas = $conn->prepare($sql_check_horas);
$stmt_check_horas->bind_param("ss", $numero_empleado, $codigo_affaire);
$stmt_check_horas->execute();
$result_check_horas = $stmt_check_horas->get_result();
$total_horas = 0;
if ($row = $result_check_horas->fetch_assoc()) {
    $total_horas = (int)$row['total'];
}
$stmt_check_horas->close();

$success = true;
// Si ya no quedan registros, eliminar la actividad en cargue_horas
if ($total_horas === 0) {
    $sql_delete = "DELETE FROM cargue_horas WHERE id = ? AND numero_de_empleado = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("is", $activity_id, $numero_empleado);
    $success = $stmt_delete->execute();
    $stmt_delete->close();
}

echo json_encode(['success' => $success, 'horas_restantes' => $total_horas]);
$conn->close();
?>
