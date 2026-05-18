<?php
header('Content-Type: application/json; charset=UTF-8');
require_once 'include.php';

$numero_de_empleado = isset($_POST['numero_de_empleado']) ? trim($_POST['numero_de_empleado']) : '';
$codigo_affaire = isset($_POST['codigo_affaire']) ? trim($_POST['codigo_affaire']) : '';
$fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';

if ($numero_de_empleado === '' || $codigo_affaire === '' || $fecha === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan datos requeridos para eliminar el comentario.'
    ]);
    exit;
}

$sql = "DELETE FROM comentarios_asignacion WHERE numero_de_empleado = ? AND codigo_affaire = ? AND fecha = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $conn->error
    ]);
    exit;
}
$stmt->bind_param('sss', $numero_de_empleado, $codigo_affaire, $fecha);
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Comentario eliminado correctamente.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo eliminar el comentario: ' . $stmt->error
    ]);
}
$stmt->close();
