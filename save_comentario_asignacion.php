<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once 'include.php';

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No hay conexión a la base de datos.']);
    exit;
}

$numero_de_empleado = isset($_POST['numero_de_empleado']) ? (int)$_POST['numero_de_empleado'] : 0;
$nom = isset($_POST['nom']) ? trim((string)$_POST['nom']) : '';
$prenom = isset($_POST['prenom']) ? trim((string)$_POST['prenom']) : '';
$codigo_affaire = isset($_POST['codigo_affaire']) ? trim((string)$_POST['codigo_affaire']) : '';
$nombre_proyecto = isset($_POST['nombre_proyecto']) ? trim((string)$_POST['nombre_proyecto']) : '';
$comentario = isset($_POST['comentario']) ? trim((string)$_POST['comentario']) : '';
$fecha = isset($_POST['fecha']) ? trim((string)$_POST['fecha']) : '';

if ($numero_de_empleado <= 0 || $codigo_affaire === '' || $nombre_proyecto === '' || $fecha === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos para guardar el comentario.']);
    exit;
}

$conn->begin_transaction();

try {
    $sql_check = "SELECT COUNT(*) AS total FROM comentarios_asignacion WHERE numero_de_empleado = ? AND codigo_affaire = ? AND fecha = ?";
    $stmt_check = $conn->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception('No fue posible validar la existencia del comentario.');
    }

    $stmt_check->bind_param('iss', $numero_de_empleado, $codigo_affaire, $fecha);
    if (!$stmt_check->execute()) {
        $stmt_check->close();
        throw new Exception('No fue posible validar el comentario.');
    }

    $result_check = $stmt_check->get_result();
    $existing_row = $result_check ? $result_check->fetch_assoc() : null;
    if ($result_check) {
        $result_check->free();
    }
    $stmt_check->close();

    $exists = !empty($existing_row) && (int)($existing_row['total'] ?? 0) > 0;

    if ($exists) {
        $sql_update = "UPDATE comentarios_asignacion SET nom = ?, prenom = ?, nombre_proyecto = ?, comentario = ? WHERE numero_de_empleado = ? AND codigo_affaire = ? AND fecha = ?";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception('No fue posible preparar la actualización.');
        }

        $stmt_update->bind_param('ssssiss', $nom, $prenom, $nombre_proyecto, $comentario, $numero_de_empleado, $codigo_affaire, $fecha);
        if (!$stmt_update->execute()) {
            $stmt_update->close();
            throw new Exception('No fue posible actualizar el comentario.');
        }
        $stmt_update->close();
    } elseif ($comentario !== '') {
        $sql_insert = "INSERT INTO comentarios_asignacion (numero_de_empleado, nom, prenom, codigo_affaire, nombre_proyecto, comentario, fecha) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) {
            throw new Exception('No fue posible preparar la inserción.');
        }

        $stmt_insert->bind_param('issssss', $numero_de_empleado, $nom, $prenom, $codigo_affaire, $nombre_proyecto, $comentario, $fecha);
        if (!$stmt_insert->execute()) {
            $stmt_insert->close();
            throw new Exception('No fue posible guardar el comentario.');
        }
        $stmt_insert->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Comentario guardado correctamente.']);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('save_comentario_asignacion.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
