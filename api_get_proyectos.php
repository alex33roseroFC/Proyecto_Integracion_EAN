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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Obtener proyectos de la tabla proyectos
$sql = "SELECT id, centro_costos, nombre_proyecto FROM proyectos";


if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $sql .= " WHERE centro_costos LIKE ? OR nombre_proyecto LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $search_param, $search_param);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

$proyectos = [];
while ($row = $result->fetch_assoc()) {
    $proyectos[] = $row;
}


$stmt->close();
$conn->close();

echo json_encode($proyectos);
?>
