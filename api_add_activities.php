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
$project_ids = isset($input['project_ids']) ? $input['project_ids'] : [];

if (empty($project_ids)) {
    echo json_encode(['success' => false, 'message' => 'No hay proyectos seleccionados']);
    exit;
}

$usuario = $_SESSION['usuario'];
$numero_empleado = isset($_SESSION['matricula']) ? $_SESSION['matricula'] : '';

// Obtener información del usuario
$sql_user = "SELECT Nombre_Usuario FROM login_usuarios WHERE Usuario = ?";

$stmt = $conn->prepare($sql_user);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$nombre_usuario = isset($user_data['Nombre_Usuario']) ? $user_data['Nombre_Usuario'] : $usuario;
$stmt->close();

// Obtener proyectos seleccionados
$placeholders = implode(',', array_fill(0, count($project_ids), '?'));
$sql = "SELECT id, centro_costos, nombre_proyecto FROM proyectos WHERE id IN ($placeholders)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($project_ids)), ...$project_ids);
$stmt->execute();
$result = $stmt->get_result();

$today = date('Y-m-d');
$added_count = 0;

while ($project = $result->fetch_assoc()) {
    // Verificar si ya existe en cargue_horas
    $sql_check = "SELECT id FROM cargue_horas WHERE numero_de_empleado = ? AND codigo_affaire = ? AND fecha = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("sss", $numero_empleado, $project['centro_costos'], $today);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    
    if ($check_result->num_rows === 0) {
        // Insertar nueva actividad
        $sql_insert = "INSERT INTO cargue_horas 
            (numero_de_empleado, nom, prenom, codigo_affaire, nombre_proyect, fecha, tiempo_imputado_horas, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
        $stmt_insert = $conn->prepare($sql_insert);
        
        // Separar nombre y apellido del nombre completo
        $partes_nombre = explode(' ', $nombre_usuario, 2);
        $nom = $partes_nombre[0];
        $prenom = isset($partes_nombre[1]) ? $partes_nombre[1] : '';
        
        $stmt_insert->bind_param(
            "ssssss",
            $numero_empleado,
            $nom,
            $prenom,
            $project['centro_costos'],
            $project['nombre_proyecto'],
            $today
        );
        
        if ($stmt_insert->execute()) {
            $added_count++;
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => "Se agregaron $added_count actividades",
    'added' => $added_count
]);
?>
