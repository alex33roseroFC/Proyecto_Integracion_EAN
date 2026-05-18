<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once 'include.php';

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No hay conexión a la base de datos.']);
    exit;
}

$numero_de_empleado = isset($_GET['numero_de_empleado']) ? (int)$_GET['numero_de_empleado'] : 0;
$codigo_affaire = isset($_GET['codigo_affaire']) ? trim((string)$_GET['codigo_affaire']) : '';
$fecha = isset($_GET['fecha']) ? trim((string)$_GET['fecha']) : '';

if ($numero_de_empleado <= 0 || $codigo_affaire === '' || $fecha === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos.']);
    exit;
}

$sql = "SELECT comentario, nom, prenom, nombre_proyecto FROM comentarios_asignacion WHERE numero_de_empleado = ? AND codigo_affaire = ? AND fecha = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible preparar la consulta.']);
    exit;
}

$stmt->bind_param('iss', $numero_de_empleado, $codigo_affaire, $fecha);
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No fue posible consultar el comentario.']);
    exit;
}

$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
if ($result) {
    $result->free();
}
$stmt->close();

echo json_encode([
    'success' => true,
    'comentario' => (string)($row['comentario'] ?? ''),
    'nom' => (string)($row['nom'] ?? ''),
    'prenom' => (string)($row['prenom'] ?? ''),
    'nombre_proyecto' => (string)($row['nombre_proyecto'] ?? ''),
]);
