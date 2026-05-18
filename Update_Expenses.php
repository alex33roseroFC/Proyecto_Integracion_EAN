<?php
header('Content-Type: application/json; charset=utf-8');

// Helper to reply and exit
function respond($ok, $extra = []){
    echo json_encode(array_merge(['success' => (bool)$ok], $extra));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        respond(false, ['error' => 'Método no permitido']);
    }

    // Permitir JSON body o form-data
    $raw = file_get_contents('php://input');
    $data = [];
    if ($raw) {
        $dec = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $dec;
        }
    }
    if (empty($data)) { $data = $_POST; }

    $proyecto = isset($data['proyecto']) ? trim((string)$data['proyecto']) : '';
    $version  = isset($data['version']) ? trim((string)$data['version']) : '';
    $categoria = isset($data['categoria']) ? trim((string)$data['categoria']) : '';
    $nombre_categoria = isset($data['nombre_categoria']) ? trim((string)$data['nombre_categoria']) : '';
    $area_funcional = isset($data['area_funcional']) ? trim((string)$data['area_funcional']) : '';
    $campo = isset($data['campo']) ? trim((string)$data['campo']) : '';
    $valor = isset($data['valor']) ? $data['valor'] : null;

    if ($proyecto === '' || $version === '' || $categoria === '' || $nombre_categoria === '' || $area_funcional === '' || $campo === '' || $valor === null) {
        http_response_code(400);
        respond(false, ['error' => 'Parámetros inválidos']);
    }

    // Incluye la configuración centralizada para compatibilidad de entorno
    require_once __DIR__ . '/include.php'; // crea $conn

    // Lista blanca de campos permitidos
    $meses = [
        'ene25','feb25','mar25','abr25','may25','jun25','jul25','ago25','sep25','oct25','nov25','dic25',
        'ene26','feb26','mar26','abr26','may26','jun26','jul26','ago26','sep26','oct26','nov26','dic26',
        'ene27','feb27','mar27','abr27','may27','jun27','jul27','ago27','sep27','oct27','nov27','dic27',
        'ene28','feb28','mar28','abr28','may28','jun28','jul28','ago28','sep28','oct28','nov28','dic28'
    ];
    $permitidos = array_fill_keys($meses, 'd'); // double
    $permitidos['TARIFA COAN 2'] = 'd';

    if (!array_key_exists($campo, $permitidos)) {
        http_response_code(400);
        respond(false, ['error' => 'Campo no permitido']);
    }

    // Normalizar valor numérico (aceptar "." o ",")
    if ($permitidos[$campo] === 'd') {
        if (is_string($valor)) {
            $valor = str_replace(['.', ','], ['', '.'], $valor); // quitar miles y usar punto decimal
        }
        if (!is_numeric($valor)) {
            http_response_code(400);
            respond(false, ['error' => 'Valor numérico inválido']);
        }
        $valor = (float)$valor;
    }

    // Construir UPDATE dinámico. Columnas con espacios/acentos deben ir entre backticks
    // NOTA: las columnas de filtro también usan nombres con espacios y acentos según el esquema usado en el proyecto
    $sql = "UPDATE gastos_personal SET `" . str_replace("`", "", $campo) . "` = ? WHERE `PROYECTO` = ? AND `VERSION` = ? AND `CATEGORIA` = ? AND `NOMBRE CATEGORIA` = ? AND `ÁREA FUNCIONAL` = ?";

    if (!$stmt = $conn->prepare($sql)) {
        http_response_code(500);
        respond(false, ['error' => 'Error al preparar sentencia']);
    }

    // version puede ser numérica o string; vinculamos como string para evitar problemas de tipos heterogéneos
    $stmt->bind_param('dsssss', $valor, $proyecto, $version, $categoria, $nombre_categoria, $area_funcional);

    if (!$stmt->execute()) {
        http_response_code(500);
        respond(false, ['error' => 'Error al actualizar: ' . $stmt->error]);
    }

    // Afectadas 0 no necesariamente es error (mismo valor)
    respond(true, ['affected' => $stmt->affected_rows]);

} catch (Throwable $e) {
    http_response_code(500);
    respond(false, ['error' => 'Excepción: ' . $e->getMessage()]);
}
