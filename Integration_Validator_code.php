<?php
// validar_integracion.php
header('Content-Type: application/json');
if (!isset($_POST['integracion'])) {
    echo json_encode(['exists' => false, 'error' => 'No integration provided']);
    exit;
}
$integracion = $_POST['integracion'];

// Incluye la configuración centralizada para compatibilidad de entorno
require_once __DIR__ . '/include.php'; // crea $conn
if (!$conn || $conn->connect_error) {
    echo json_encode(['exists' => false, 'error' => 'DB connection error']);
    exit;
}
$sql = "SELECT COUNT(*) as total FROM gastos_personal WHERE `INTEGRACION` = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $integracion);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$exists = ($row && $row['total'] > 0);
echo json_encode(['exists' => $exists]);
