
<?php
require_once 'include.php';
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$nombre = '';
if (isset($_GET['nombre_proyecto'])) $nombre = trim((string)$_GET['nombre_proyecto']);
elseif (isset($_POST['nombre_proyecto'])) $nombre = trim((string)$_POST['nombre_proyecto']);

if ($nombre === '') {
    echo json_encode(['success' => false, 'error' => 'missing_name']);
    exit;
}


// $conn ya debe estar definido en config.php
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'error' => 'db_connect']);
    exit;
}

// Normalize comparison: remove leading/trailing spaces and compare lowercased

$sql = "SELECT nombre_proyecto, centro_costos FROM proyectos WHERE LOWER(TRIM(nombre_proyecto)) = LOWER(TRIM(?)) LIMIT 1";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('s', $nombre);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'nombre_proyecto' => $row['nombre_proyecto'],
                'centro_costos' => $row['centro_costos']
            ]);
            $res->free();
            $stmt->close();
            // No cerrar $conn, lo gestiona config.php
            exit;
        }
        $res->free();
    }
    $stmt->close();
}

echo json_encode(['success' => false, 'error' => 'not_found']);
exit;
