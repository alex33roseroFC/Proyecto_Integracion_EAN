<?php
header('Content-Type: application/json');
require_once 'include.php';
require_once 'config.php';

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión DB']);
    exit();
}
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$area_funcional = isset($_POST['Area_Funcional']) ? $conn->real_escape_string($_POST['Area_Funcional']) : '';
$monto_prestado = isset($_POST['Monto_Prestado']) ? floatval($_POST['Monto_Prestado']) : 0;
$area_funcional_sel = isset($_POST['Area_Funcional_Seleccionada']) ? $conn->real_escape_string($_POST['Area_Funcional_Seleccionada']) : '';
if ($id > 0 && $area_funcional && $area_funcional_sel) {
    $sql = "UPDATE compartir_presupuesto SET Area_Funcional='$area_funcional', Monto_Prestado=$monto_prestado, Area_Funcional_Seleccionada='$area_funcional_sel' WHERE id=$id LIMIT 1";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
}
$conn->close();
