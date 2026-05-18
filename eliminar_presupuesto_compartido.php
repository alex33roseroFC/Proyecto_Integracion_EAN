<?php
header('Content-Type: application/json');
require_once __DIR__ . '/include.php';
require_once __DIR__ . '/config.php';
// $conn debe estar definido en config.php
$conn->set_charset('utf8mb4');
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id > 0) {
    $sql = "DELETE FROM compartir_presupuesto WHERE id = $id LIMIT 1";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo eliminar']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
}
$conn->close();
