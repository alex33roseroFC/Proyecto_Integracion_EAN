<?php
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }


    // Incluye la configuración centralizada para compatibilidad de entorno
    require_once __DIR__ . '/include.php'; // crea $conn

    // Validar entradas
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $campo = isset($_POST['campo']) ? trim($_POST['campo']) : '';
    $valor = isset($_POST['valor']) ? $_POST['valor'] : null;
    $numero_empleado = isset($_POST['numero_empleado']) ? trim($_POST['numero_empleado']) : '';
    $codigo_affaire = isset($_POST['codigo_affaire']) ? trim($_POST['codigo_affaire']) : '';
    $accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

    $is_masivo = ($accion === 'masivo' && $numero_empleado !== '' && $codigo_affaire !== '' && $campo !== '' && $valor !== null);
    if (!$is_masivo && ($id <= 0 || $campo === '' || $valor === null)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }

    // Lista blanca de campos permitidos
    $permitidos = [
        'nom' => 's',
        'prenom' => 's',
        'tiempo_imputado_horas' => 'd',
        'tiempo_imputado_costo' => 'd',
        'aprobado_coordinador' => 'i',
        'rechazado_coordinador' => 'i',
        'comentario_coordinador' => 's',
        'aprobado_director' => 'i',
        'rechazado_director' => 'i',
        'comentario_director' => 's',
    ];

    if (!array_key_exists($campo, $permitidos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Campo no permitido']);
        exit;
    }

    // Asegurar tipos correctos
    if ($permitidos[$campo] === 'd') {
        if (!is_numeric($valor)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valor numérico inválido']);
            exit;
        }
        $valor = (float)$valor;
    } elseif ($permitidos[$campo] === 'i') {
        // tratar como booleano 0/1 para los checkboxes
        $valor = $valor ? 1 : 0;
    } else {
        // strings: sanitizar un poco (mysqli hará el escape)
        $valor = (string)$valor;
    }

    // Preparar y ejecutar UPDATE
    if ($is_masivo) {
        // Actualización masiva en horas_dia SOLO para registros con Estado_Aprobacion = 'Aprobado En Curso'
        $sql = "UPDATE horas_dia SET `$campo` = ? WHERE numero_empleado = ? AND codigo_affaire = ? AND Estado_Aprobacion = 'Aprobado En Curso'";
        if ($stmt = $conn->prepare($sql)) {
            $tipo = $permitidos[$campo];
            $stmt->bind_param($tipo . 'ss', $valor, $numero_empleado, $codigo_affaire);
            if ($stmt->execute()) {
                // Exclusión mutua masiva SOLO para registros con Estado_Aprobacion = 'Aprobado En Curso'
                if ($campo === 'aprobado_coordinador' && (int)$valor === 1) {
                    $conn->query("UPDATE horas_dia SET rechazado_coordinador = 0 WHERE numero_empleado = '".$conn->real_escape_string($numero_empleado)."' AND codigo_affaire = '".$conn->real_escape_string($codigo_affaire)."' AND Estado_Aprobacion = 'Aprobado En Curso'");
                } elseif ($campo === 'rechazado_coordinador' && (int)$valor === 1) {
                    $conn->query("UPDATE horas_dia SET aprobado_coordinador = 0 WHERE numero_empleado = '".$conn->real_escape_string($numero_empleado)."' AND codigo_affaire = '".$conn->real_escape_string($codigo_affaire)."' AND Estado_Aprobacion = 'Aprobado En Curso'");
                }
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al actualizar masivo: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al preparar sentencia masiva']);
        }
    } else {
        // ...existing code for individual update, pero en horas_dia...
        $sql = "UPDATE horas_dia SET `$campo` = ? WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $tipo = $permitidos[$campo];
            $stmt->bind_param($tipo . 'i', $valor, $id);
            if ($stmt->execute()) {
                // Exclusión mutua individual
                if ($campo === 'aprobado_coordinador' && (int)$valor === 1) {
                    $conn->query("UPDATE horas_dia SET rechazado_coordinador = 0 WHERE id = " . (int)$id);
                } elseif ($campo === 'rechazado_coordinador' && (int)$valor === 1) {
                    $conn->query("UPDATE horas_dia SET aprobado_coordinador = 0 WHERE id = " . (int)$id);
                }
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al actualizar: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al preparar sentencia']);
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Excepción: ' . $e->getMessage()]);
}
