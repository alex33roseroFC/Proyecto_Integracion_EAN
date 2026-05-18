<?php
// update_aprobacion_coordinador.php
// Actualiza aprobado_coordinador, rechazado_coordinador y comentario_coordinador para un registro de app_reporte_inputhh

header('Content-Type: application/json');


// Incluye la configuración centralizada para compatibilidad de entorno
require_once __DIR__ . '/include.php'; // crea $conn
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error de conexión: " . ($conn ? $conn->connect_error : 'No se pudo establecer la conexión.')]);
    exit;
}
$conn->set_charset('utf8mb4');

// Recibir datos POST
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "error" => "Datos no recibidos"]);
    exit;
}

$codigo_affaire = $conn->real_escape_string($data['codigo_affaire'] ?? '');
$area_funcional = $conn->real_escape_string($data['area_funcional'] ?? '');
$nom = $conn->real_escape_string($data['nom'] ?? '');
$prenom = $conn->real_escape_string($data['prenom'] ?? '');
$aprobado = isset($data['aprobado_coordinador']) ? (int)$data['aprobado_coordinador'] : 0;
$rechazado = isset($data['rechazado_coordinador']) ? (int)$data['rechazado_coordinador'] : 0;
$comentario = $conn->real_escape_string($data['comentario_coordinador'] ?? '');

if (!$codigo_affaire || !$area_funcional || !$nom || !$prenom) {
    echo json_encode(["success" => false, "error" => "Faltan identificadores"]);
    exit;
}

$sql = "UPDATE app_reporte_inputhh SET aprobado_coordinador=$aprobado, rechazado_coordinador=$rechazado, comentario_coordinador='$comentario' WHERE codigo_affaire='$codigo_affaire' AND area_funcional='$area_funcional' AND nom='$nom' AND prenom='$prenom' LIMIT 1";

if ($conn->query($sql)) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
$conn->close();
