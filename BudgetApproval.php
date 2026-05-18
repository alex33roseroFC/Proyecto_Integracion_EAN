<?php
session_start();
// resumen_proyectos.php
// Este archivo muestra un resumen ejecutivo por proyecto cargado

require_once 'include.php';
require_once 'config.php';
//echo '<pre>dashboard.php: '; print_r($_SESSION); echo '</pre>';
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}


$usuario_logueado = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
// Obtener Área_Funcional, Nombre_Usuario y ROL del usuario logueado desde la tabla login_usuarios
$area_funcional = '';
$nombre_usuario = '';
$rol_usuario = '';
if (!empty($usuario_logueado)) {
    $sql_user = "SELECT Área_Funcional, Nombre_Usuario, ROL FROM login_usuarios WHERE Usuario = '" . $conn->real_escape_string($usuario_logueado) . "' LIMIT 1";
    $res_user = $conn->query($sql_user);
    if ($res_user && $res_user->num_rows > 0) {
        $urow = $res_user->fetch_assoc();
        $area_funcional = isset($urow['Área_Funcional']) ? $urow['Área_Funcional'] : '';
        $nombre_usuario = isset($urow['Nombre_Usuario']) ? $urow['Nombre_Usuario'] : '';
        $rol_usuario = isset($urow['ROL']) ? $urow['ROL'] : '';
    }
}

// === Áreas funcionales permitidas ===
// Para agregar otro usuario con áreas restringidas, solo añade una entrada al array:
// 'IDENTIFICADOR' => ['Área 1', 'Área 2', ...],
$usuarios_areas_especiales = [
    'JGELVEZ' => ['Arquitectura y Urbanismo', 'Estructuras'],
    // Ejemplo: 'OTROUSER' => ['Área X', 'Área Y'],
];

$areas_permitidas = [
    'Vías',
    'Topografía',
    'BIM',
    'Vías y Topografía',
    'Geotecnia y Pavimentos',
    'Eléctrica',
    'Hidráulica y Medio Ambiente',
    'Arquitectura y Urbanismo',
    'Mecánica',
    'Estructuras',
    'Dirección de Proyectos',
    'Dirección de Ingeniería',
    'Tecnología',
    'Área_Prueba'
];

// --- Filtros por GET: Área Funcional y Nombre Proyecto ---
$area_filter = isset($_GET['area_funcional']) ? $conn->real_escape_string($_GET['area_funcional']) : '';
$name_filter = isset($_GET['nombre_proyecto']) ? $conn->real_escape_string($_GET['nombre_proyecto']) : '';

// Si el ROL es MIX1, MIX2 o COORD, filtrar automáticamente por el área funcional del usuario
// Si el ROL es MIX, mostrar solo áreas permitidas según usuario especial (si aplica), si no, las áreas estándar MIX
if ($rol_usuario === 'MIX') {
    $identificador_usuario = strtoupper(trim($usuario_logueado));
    if (array_key_exists($identificador_usuario, $usuarios_areas_especiales)) {
        $areas = $usuarios_areas_especiales[$identificador_usuario];
        $mostrar_todas = true;
    } else {
        $areas = ['BIM', 'Vías', 'Vías y Topografía'];
        $mostrar_todas = true;
    }
    // Si el filtro no es válido, quitarlo
    if ($area_filter !== '' && !in_array($area_filter, $areas)) {
        $area_filter = '';
    }
} elseif ((in_array($rol_usuario, ['MIX1', 'MIX2', 'COORD'])) && !empty($area_funcional)) {
    $area_filter = $area_funcional;
    $areas = [$area_funcional];
    $mostrar_todas = false;
} elseif ($rol_usuario === 'SUPER') {
    $areas = $areas_permitidas;
    $mostrar_todas = true;
} else {
    $areas = $areas_permitidas;
    $mostrar_todas = true;
}

// Construir consulta principal con filtros opcionales
$sql = "SELECT 
    gp.PROYECTO,
    p.nombre_proyecto,
    p.nature_imputation,
    gp.`ÁREA FUNCIONAL`,
    MIN(gp.`FECHA INICIO PROYECTO`) AS fecha_inicio,
    MAX(gp.`FECHA FIN PROYECTO`) AS fecha_fin,
    afep.PORCENTAJE_AVANCE_FISICO_EJECUTADO,
    afep.PORCENTAJE_AVANCE_FISICO_PROGRAMADO,
    SUM(
        gp.`ene25`+gp.`feb25`+gp.`mar25`+gp.`abr25`+gp.`may25`+gp.`jun25`+gp.`jul25`+gp.`ago25`+gp.`sep25`+gp.`oct25`+gp.`nov25`+gp.`dic25`+
        gp.`ene26`+gp.`feb26`+gp.`mar26`+gp.`abr26`+gp.`may26`+gp.`jun26`+gp.`jul26`+gp.`ago26`+gp.`sep26`+gp.`oct26`+gp.`nov26`+gp.`dic26`+
        gp.`ene27`+gp.`feb27`+gp.`mar27`+gp.`abr27`+gp.`may27`+gp.`jun27`+gp.`jul27`+gp.`ago27`+gp.`sep27`+gp.`oct27`+gp.`nov27`+gp.`dic27`+
        gp.`ene28`+gp.`feb28`+gp.`mar28`+gp.`abr28`+gp.`may28`+gp.`jun28`+gp.`jul28`+gp.`ago28`+gp.`sep28`+gp.`oct28`+gp.`nov28`+gp.`dic28`
    ) AS total_horas,
        (
                SELECT COALESCE(SUM(cv.`acum_año_anterior`+cv.`ene_25`+cv.`feb_25`+cv.`mar_25`+cv.`abr_25`+cv.`may_25`+cv.`jun_25`+cv.`jul_25`+cv.`ago_25`+cv.`sep_25`+cv.`oct_25`+cv.`nov_25`+cv.`dic_25`), 0)
                FROM costo_valorizado cv
                WHERE cv.CECO_CONEXION = gp.PROYECTO
                    AND cv.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`
        ) AS total_valorizado_2025,
        (SELECT COALESCE(SUM(ar2.tiempo_imputado_costo),0)
         FROM horas_dia ar2
         WHERE ar2.codigo_affaire = gp.PROYECTO
             AND ar2.area_funcional = gp.`ÁREA FUNCIONAL`
             AND ar2.Estado_Aprobacion = 'Aprobado En Curso') AS tiempo_imputado_costo,
        (SELECT COALESCE(SUM(ar3.tiempo_imputado_costo),0)
         FROM horas_dia ar3
         WHERE ar3.codigo_affaire = gp.PROYECTO
             AND ar3.area_funcional = gp.`ÁREA FUNCIONAL`
             AND ar3.aprobado_coordinador = 1
             AND ar3.Estado_Aprobacion = 'Aprobado En Curso') AS costo_aprobado,
        (SELECT COALESCE(SUM(ar5.tiempo_imputado_costo),0)
         FROM horas_dia ar5
         WHERE ar5.codigo_affaire = gp.PROYECTO
             AND ar5.area_funcional = gp.`ÁREA FUNCIONAL`
             AND ar5.Estado_Aprobacion = 'Aprobado') AS costo_real_aprobado,
    (SELECT SUM(
        (`ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
        `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
        `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
        `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`) * `TARIFA COAN 2`)
    FROM gastos_personal gp2
    WHERE gp2.PROYECTO = gp.PROYECTO AND gp2.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`
    ) AS total_costo
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
LEFT JOIN avance_fisico_ejecutado_programado afep ON gp.PROYECTO = afep.PROYECTO AND gp.`ÁREA FUNCIONAL` = afep.AREA_FUNCIONAL
WHERE 1=1 ";


// Aplicar filtro de área solo si el usuario lo selecciona explícitamente y es permitido
if ($area_filter !== '' && in_array($area_filter, $areas)) {
    $sql .= " AND gp.`ÁREA FUNCIONAL` = '" . $area_filter . "' ";
} else {
    // Si no hay filtro, limitar a las áreas permitidas para el usuario
    $areas_sql = array_map(function($a) use ($conn) { return "'" . $conn->real_escape_string($a) . "'"; }, $areas);
    $sql .= " AND gp.`ÁREA FUNCIONAL` IN (" . implode(",", $areas_sql) . ") ";
}

if ($name_filter !== '') {
    $sql .= " AND p.nombre_proyecto = '" . $name_filter . "' ";
}

// Cerrar primer SELECT (base desde gastos_personal)
$select1 = $sql . "GROUP BY gp.PROYECTO, p.nombre_proyecto, p.nature_imputation, gp.`ÁREA FUNCIONAL`";

// Segundo SELECT: proyectos existentes en horas_dia que no aparecen en gastos_personal
$select2 = "SELECT 
    ar.codigo_affaire AS PROYECTO,
    p.nombre_proyecto,
    p.nature_imputation,
    ar.area_funcional AS `ÁREA FUNCIONAL`,
    NULL AS fecha_inicio,
    NULL AS fecha_fin,
    NULL AS PORCENTAJE_AVANCE_FISICO_EJECUTADO,
    NULL AS PORCENTAJE_AVANCE_FISICO_PROGRAMADO,
    0 AS total_horas,
    (SELECT COALESCE(SUM(cv.`acum_año_anterior`+cv.`ene_25`+cv.`feb_25`+cv.`mar_25`+cv.`abr_25`+cv.`may_25`+cv.`jun_25`+cv.`jul_25`+cv.`ago_25`+cv.`sep_25`+cv.`oct_25`+cv.`nov_25`+cv.`dic_25`),0)
       FROM costo_valorizado cv
       WHERE cv.CECO_CONEXION = ar.codigo_affaire
         AND cv.`ÁREA FUNCIONAL` = ar.area_funcional) AS total_valorizado_2025,
    COALESCE(SUM(CASE WHEN ar.Estado_Aprobacion = 'Aprobado En Curso' THEN ar.tiempo_imputado_costo ELSE 0 END),0) AS tiempo_imputado_costo,
    (SELECT COALESCE(SUM(ar4.tiempo_imputado_costo),0)
    FROM horas_dia ar4
     WHERE ar4.codigo_affaire = ar.codigo_affaire
         AND ar4.area_funcional = ar.area_funcional
         AND ar4.aprobado_coordinador = 1
         AND ar4.Estado_Aprobacion = 'Aprobado En Curso') AS costo_aprobado,
    (SELECT COALESCE(SUM(ar5.tiempo_imputado_costo),0)
    FROM horas_dia ar5
     WHERE ar5.codigo_affaire = ar.codigo_affaire
         AND ar5.area_funcional = ar.area_funcional
         AND ar5.Estado_Aprobacion = 'Aprobado') AS costo_real_aprobado,
    0 AS total_costo
FROM horas_dia ar
LEFT JOIN proyectos p ON ar.codigo_affaire = p.centro_costos
WHERE NOT EXISTS (
    SELECT 1 FROM gastos_personal gp WHERE gp.PROYECTO = ar.codigo_affaire AND gp.`ÁREA FUNCIONAL` = ar.area_funcional
)";

if ($area_filter !== '') {
    $select2 .= " AND ar.area_funcional = '" . $conn->real_escape_string($area_filter) . "'";
}
if ($name_filter !== '') {
    $select2 .= " AND p.nombre_proyecto = '" . $conn->real_escape_string($name_filter) . "'";
}

$select2 .= "\nGROUP BY ar.codigo_affaire, ar.area_funcional, p.nombre_proyecto, p.nature_imputation";

// Unir ambos conjuntos y ordenar: primero AFFAIRE (PROYECTOS), luego FRAIS GENERAUX DIVERS (GASTO GENERAL)
$sql = "SELECT * FROM (" . $select1 . "\nUNION ALL\n" . $select2 . ") AS combined 
        ORDER BY 
            CASE 
                WHEN UPPER(TRIM(REPLACE(nature_imputation, '  ', ' '))) = 'AFFAIRE' THEN 1
                WHEN UPPER(TRIM(REPLACE(nature_imputation, '  ', ' '))) = 'FRAIS GENERAUX DIVERS' THEN 2
                ELSE 3
            END,
            PROYECTO ASC, 
            `ÁREA FUNCIONAL` ASC;";

$result = $conn->query($sql);
?>
<?php
// Convierte un porcentaje (0-100) a un color hex interpolado entre rojo->amarillo->verde
function percentToColor($p) {
    $p = max(0, min(100, (float)$p));
    $red = [255, 77, 79]; // rojo aproximado
    $yellow = [255, 204, 0]; // amarillo
    $green = [23, 130, 61]; // #17823d

    if ($p <= 50) {
        $t = $p / 50.0;
        $r = (int)round($red[0] + ($yellow[0] - $red[0]) * $t);
        $g = (int)round($red[1] + ($yellow[1] - $red[1]) * $t);
        $b = (int)round($red[2] + ($yellow[2] - $red[2]) * $t);
    } else {
        $t = ($p - 50.0) / 50.0;
        $r = (int)round($yellow[0] + ($green[0] - $yellow[0]) * $t);
        $g = (int)round($yellow[1] + ($green[1] - $yellow[1]) * $t);
        $b = (int)round($yellow[2] + ($green[2] - $yellow[2]) * $t);
    }

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

// Mapea nature_imputation a etiquetas legibles
function mapNatureImputation($value) {
    $raw = (string)$value;
    $upper = strtoupper(trim($raw));
    // Normalizar espacios múltiples
    $upper = preg_replace('/\s+/', ' ', $upper);
    if ($upper === 'AFFAIRE') return 'PROYECTOS';
    if ($upper === 'FRAIS GENERAUX DIVERS') return 'GASTO GENERAL';
    return $raw; // Mantener el valor original para otros casos
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Ejecutivo de Proyectos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/btn-ver-detalle-custom.css">
    <style>
        .container {
            max-width: 95% !important;
            margin: 0 auto;
            padding: 0 15px;
        }
        /* Compact executive-style summary cards */
        .top-cards .card { padding: 10px; border-radius: 8px; }
        .summary-card h6, .assign-card h6 { font-size: 1rem; margin-bottom: 0.75rem; }
        .summary-card .label, .assign-card .label { color:#2f6f36; font-weight:600; font-size:0.92rem; }
        .assign-card .label { color:#2f6f8f; }
        .value-box { background:#fff; padding:5px 10px; border-radius:4px; min-width:90px; text-align:right; display:inline-block; font-weight:600; }
        .small-row { gap:8px; }
        @media (max-width: 992px) {
            .value-box { min-width:80px; }
        }
        .table-responsive {
            overflow-x: auto;
            margin: 0 auto 2.5rem 0; /* add bottom margin to separate table from footer */
            width: 100%;
            padding-bottom: 1.5rem; /* extra breathing room on small screens */
        }
        .resumen-table th, .resumen-table td {
            border-right: 1px solid rgba(225, 225, 225, 0.5);
            vertical-align: middle;
            padding: 8px 12px;
            min-width: 100px;
            position: relative;
        }
        /* Línea divisoria sutil entre columnas */
        .resumen-table td:not(:last-child):after,
        .resumen-table th:not(:last-child):after {
            content: '';
            position: absolute;
            right: 0;
            top: 25%;
            height: 50%;
            width: 1px;
            background: rgba(200, 200, 200, 0.3);
        }
        .resumen-table th {
          background: #4C8AA3;
          color: #fff !important;
          font-weight: 600;
          white-space: normal;
          text-align: center;
          font-size: 0.85rem;
          padding: 4px 6px;
        }
        .resumen-table td {
            color: #111 !important;
        }
        .resumen-table tbody tr:nth-child(even) {
            background: #f4f7fb;
        }
        .resumen-table tbody tr:nth-child(odd) {
            background: #fff;
        }
        /* Ajustes específicos para columnas */
        .resumen-table th:nth-child(4),
        .resumen-table td:nth-child(4) {
            min-width: 200px; /* Columna nombre proyecto */
        }
        .resumen-table th:nth-child(9),
        .resumen-table td:nth-child(9),
        .resumen-table th:nth-child(10),
        .resumen-table td:nth-child(10) {
            min-width: 120px; /* Columnas de porcentajes */
        }
    </style>
</head>
<?php include 'menu.php'; ?>
<body>
<div class="main-content container mt-5">
    <div class="text-center mb-4">
        <h4 class="mb-4" style="color: #343a40; font-weight: 700; background: #f8f9fa; display: inline-block; padding: 12px 32px 12px 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(60,72,88,.06);">
            <i class="bi bi-shield-check" style="font-size: 2.2rem; color: #4dc18f; vertical-align: middle; margin-right: 16px;"></i>
            Control y Aprobación de Costos Cargados
        </h4>
    </div>
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div class="d-flex flex-column align-items-start">
            <a href="Balance.php" class="btn btn-outline-secondary" style="background: #f3f6fb; color: #44474f; border: 1.5px solid #b6c6e6; font-weight: 600;">
                <i class="bi bi-arrow-left"></i> Regresar
            </a>
        </div>
        <div class="d-flex flex-column align-items-end">
            <!-- Botón cerrar sesión eliminado -->
            <div style="display:none">
                <div class="text-end d-flex align-items-center" style="background: #f8f9fa; padding: 10px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="font-size: 0.9rem; color: #666;" class="d-flex gap-3">
                        <span>
                            <span style="color: #4C8AA3;">Usuario:</span> 
                            <strong><?= htmlspecialchars(!empty($nombre_usuario) ? $nombre_usuario : $usuario_logueado) ?></strong>
                        </span>
                        <?php if (!empty($area_funcional)): ?>
                            <span>
                                <span style="color: #4C8AA3;">Área:</span> 
                                <strong><?= htmlspecialchars($area_funcional) ?></strong>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($rol_usuario)): ?>
                            <span>
                                <span style="color: #4C8AA3;">Rol:</span> 
                                <strong><?= htmlspecialchars($rol_usuario) ?></strong>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Rewind result set by fetching into an array so we can compute totals and render the table
    $rows = [];
    $sum_bac = 0.0; // presupuesto a terminación
    $sum_ac = 0.0;  // costo valorizado (actual)
    $sum_tiempo_imputado = 0.0; // costo cargado mes
        $sum_costo_aprobado = 0.0; // costo aprobado por coordinador
    $sum_nuevo_costo_actual = 0.0; // nuevo costo actual (AC + monto aprobado)
    $sum_etc = 0.0; // costo por ejecutar
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
            $sum_bac += (float)$r['total_costo'];
            $nature_upper = strtoupper(trim($r['nature_imputation'] ?? ''));
            $nature_upper = preg_replace('/\s+/', ' ', $nature_upper);
            // Si es FRAIS GENERAUX DIVERS, el AC es 0; en PROYECTOS sumar costo real aprobado
            $ac_value = ($nature_upper === 'FRAIS GENERAUX DIVERS') ? 0 : ((float)$r['total_valorizado_2025'] + (float)($r['costo_real_aprobado'] ?? 0));
            $sum_ac += $ac_value;
            $sum_tiempo_imputado += (float)($r['tiempo_imputado_costo'] ?? 0);
            $sum_costo_aprobado += (float)($r['costo_aprobado'] ?? 0);
            $sum_nuevo_costo_actual += ((float)$r['total_valorizado_2025'] + (float)($r['costo_real_aprobado'] ?? 0) + (float)($r['costo_aprobado'] ?? 0));
            // Si es FRAIS GENERAUX DIVERS, no sumar al ETC
            if ($nature_upper !== 'FRAIS GENERAUX DIVERS') {
                $sum_etc += ((float)$r['total_costo'] - ((float)$r['total_valorizado_2025'] + (float)($r['costo_real_aprobado'] ?? 0)));
            }
        }
    }
    // Ensure numeric values
    $sum_bac = (float)$sum_bac;
    $sum_ac = (float)$sum_ac;
    $sum_tiempo_imputado = (float)$sum_tiempo_imputado;
    $sum_costo_aprobado = (float)$sum_costo_aprobado;
    $sum_nuevo_costo_actual = (float)$sum_nuevo_costo_actual;
    $sum_etc = (float)$sum_etc;
    
    // Separar registros por tipo de imputación
    $rows_proyectos = [];
    $rows_gasto_general = [];
    
    $sum_bac_proyectos = 0.0;
    $sum_ac_proyectos = 0.0;
    $sum_etc_proyectos = 0.0;
    $sum_tiempo_imputado_proyectos = 0.0;
    $sum_costo_aprobado_proyectos = 0.0;
    $sum_costo_real_aprobado_proyectos = 0.0;
    $sum_nuevo_costo_actual_proyectos = 0.0;
    
    $sum_bac_gasto = 0.0;
    $sum_ac_gasto = 0.0;
    $sum_etc_gasto = 0.0;
    $sum_tiempo_imputado_gasto = 0.0;
    $sum_costo_aprobado_gasto = 0.0;
    $sum_nuevo_costo_actual_gasto = 0.0;
    
    foreach($rows as $row) {
    // Solo mostrar áreas permitidas según el usuario
    if (!in_array($row['ÁREA FUNCIONAL'], $areas)) continue;
        $nature_upper = strtoupper(trim($row['nature_imputation'] ?? ''));
        $nature_upper = preg_replace('/\s+/', ' ', $nature_upper);
        if ($nature_upper === 'AFFAIRE') {
            if ((float)($row['tiempo_imputado_costo'] ?? 0) == 0.0) {
                continue;
            }
            $rows_proyectos[] = $row;
            $sum_bac_proyectos += (float)$row['total_costo'];
            $sum_ac_proyectos += ((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0));
            $sum_etc_proyectos += ((float)$row['total_costo'] - ((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0)));
            $sum_tiempo_imputado_proyectos += (float)($row['tiempo_imputado_costo'] ?? 0);
            $sum_costo_aprobado_proyectos += (float)($row['costo_aprobado'] ?? 0);
            $sum_costo_real_aprobado_proyectos += (float)($row['costo_real_aprobado'] ?? 0);
            $sum_nuevo_costo_actual_proyectos += ((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0) + (float)($row['costo_aprobado'] ?? 0));
        } elseif ($nature_upper === 'FRAIS GENERAUX DIVERS') {
            $rows_gasto_general[] = $row;
            $sum_bac_gasto += (float)$row['total_costo'];
            $sum_ac_gasto += 0; // AC es 0 para gasto general
            $sum_etc_gasto += 0; // ETC es 0 para gasto general
            $sum_tiempo_imputado_gasto += (float)($row['tiempo_imputado_costo'] ?? 0);
            $sum_costo_aprobado_gasto += (float)($row['costo_aprobado'] ?? 0);
            $sum_nuevo_costo_actual_gasto += ((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0) + (float)($row['costo_aprobado'] ?? 0));
        }
            // AUSENCIAS
            elseif ($nature_upper === 'ABSENCE') {
                $rows_ausencias[] = $row;
            }
    }
    // Ordenar proyectos por COSTO CARGADO MES (mayor a menor)
    usort($rows_proyectos, function($a, $b) {
        $va = (float)($a['tiempo_imputado_costo'] ?? 0);
        $vb = (float)($b['tiempo_imputado_costo'] ?? 0);
        return $vb <=> $va;
    });
    ?>

    <!-- Left stacked cards + right tall chart (match screenshot) -->
    <!-- Tarjetas de resumen eliminadas -->

    <div class="mb-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto" style="min-width:250px;">
                <label class="form-label" style="color: #44474f; font-weight: 700;">
                    <i class="bi bi-people-fill" style="margin-right: 8px; color: #4C8AA3; font-size: 1.35rem; vertical-align: middle;"></i>
                    Área Funcional
                </label>
                    <select id="area_funcional_select" name="area_funcional" class="form-select" style="width:100%"<?= ($rol_usuario === 'SUPER') ? '' : ((($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') && !empty($area_funcional)) ? ' disabled' : '') ?>>
                    <?php if (!empty($mostrar_todas)): ?>
                        <option value=""<?= ($area_filter === '') ? ' selected' : '' ?>>-- Todas --</option>
                    <?php endif; ?>
                    <?php foreach($areas as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= ($a === $area_filter) ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') && !empty($area_funcional)): ?>
                    <input type="hidden" name="area_funcional" value="<?= htmlspecialchars($area_funcional) ?>">
                <?php endif; ?>
            </div>
            <div class="col-auto" style="min-width:250px;">
                <label class="form-label" style="color: #44474f; font-weight: 700;">
                    <i class="bi bi-folder2-open" style="margin-right: 8px; color: #4C8AA3; font-size: 1.35rem; vertical-align: middle;"></i>
                    Nombre Proyecto
                </label>
                <select id="nombre_proyecto_select" name="nombre_proyecto" class="form-select" style="width:100%">
                    <option value="">-- Todos --</option>
                    <?php 
                    $nombres_proyectos = [];
                    // Modificamos la consulta para asegurar que obtenemos todos los proyectos cargados
                    // Nombres de proyecto desde ambas fuentes (gastos_personal y horas_dia)
                    $where_area = "";
                    // Si el ROL es MIX1, MIX2 o COORD, filtrar por área funcional del usuario
                    if ((in_array($rol_usuario, ['MIX1', 'MIX2', 'COORD'])) && !empty($area_funcional)) {
                        $where_area = " AND gp.`ÁREA FUNCIONAL` = '" . $conn->real_escape_string($area_funcional) . "'";
                        $where_area_ar = " AND ar.area_funcional = '" . $conn->real_escape_string($area_funcional) . "'";
                    } else {
                        $where_area_ar = "";
                    }
                    $sql_nombres = "SELECT DISTINCT nombre_proyecto FROM (
                        SELECT p.nombre_proyecto
                        FROM gastos_personal gp
                        INNER JOIN proyectos p ON gp.PROYECTO = p.centro_costos
                        WHERE p.nombre_proyecto IS NOT NULL" . $where_area . "
                        UNION
                        SELECT p2.nombre_proyecto
                        FROM horas_dia ar
                        LEFT JOIN proyectos p2 ON ar.codigo_affaire = p2.centro_costos
                        WHERE p2.nombre_proyecto IS NOT NULL" . (isset($where_area_ar) ? $where_area_ar : "") . "
                    ) t
                    ORDER BY nombre_proyecto ASC";
                    
                    $res_nombres = $conn->query($sql_nombres);
                    if ($res_nombres) {
                        while ($np = $res_nombres->fetch_assoc()) {
                            if (!empty($np['nombre_proyecto'])) {
                                $nombres_proyectos[] = $np['nombre_proyecto'];
                            }
                        }
                    }
                    foreach($nombres_proyectos as $np): ?>
                        <option value="<?= htmlspecialchars($np) ?>" <?= (isset($name_filter) && $np === $name_filter) ? 'selected' : '' ?>><?= htmlspecialchars($np) ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
                <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
                <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                <script>
                $(document).ready(function() {
                    $('#area_funcional_select').select2({
                        placeholder: 'Buscar área funcional...',
                        allowClear: true,
                        width: '100%',
                        language: {
                            noResults: function() {
                                return "No hay coincidencias";
                            }
                        }
                    }).on('change', function() {
                        this.form.submit();
                    });
                    
                    $('#nombre_proyecto_select').select2({
                        placeholder: 'Buscar nombre de proyecto...',
                        allowClear: true,
                        width: '100%',
                        minimumResultsForSearch: 0,
                        minimumInputLength: 0,
                        language: {
                            noResults: function() {
                                return "No hay coincidencias";
                            },
                            searching: function() {
                                return "Buscando...";
                            }
                        }
                    }).on('change', function() {
                        this.form.submit();
                    });
                });
                </script>
            <div class="col-auto">
                <a href="Balance.php" class="btn btn-outline-secondary" style="background: #f3f6fb; color: #44474f; border: 1.5px solid #b6c6e6; font-weight: 600;">Limpiar filtros</a>
            </div>
        </form>
    </div>

    <!-- Gráfica de Presupuesto por Proyecto -->
    <div class="card mb-4" style="box-shadow: 0 2px 15px 0 rgba(60,72,88,.08);">
        <div class="card-body">
            <div class="row g-3 align-items-stretch">
                <div class="col-12 col-lg-8">
                    <h5 class="text-center mb-3" style="color: #44474f; font-weight: 700; font-size: 1rem; background: #f8f9fa; display: inline-block; padding: 8px 24px 8px 18px; border-radius: 10px;">
                        <i class="bi bi-bar-chart-line" style="font-size: 1.2rem; color: #495057; vertical-align: middle; margin-right: 10px;"></i>
                        PRESUPUESTO A TERMINACIÓN (BAC) VS COSTO ACTUAL (AC) + COSTO CARGADO MES
                    </h5>
                    <div style="height: <?= max(300, min(60 * count($rows_proyectos), 900)) ?>px; position: relative;">
                        <canvas id="projectBudgetChart"></canvas>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="h-100 d-flex flex-column">
                        <h6 class="text-center mb-2" style="color: #44474f; font-weight: 700; font-size: 1rem; background: #f8f9fa; display: inline-block; padding: 7px 20px 7px 15px; border-radius: 10px;">
                            <i class="bi bi-pie-chart" style="font-size: 1.1rem; color: #495057; vertical-align: middle; margin-right: 8px;"></i>
                            DISTRIBUCIÓN COSTO CARGADO MES
                        </h6>
                        <div style="height: 260px; min-height: 220px; position: relative;">
                            <canvas id="summaryPieChart"></canvas>
                        </div>
                        
                        <!-- Tabla resumen debajo de la gráfica de torta -->
                        <div class="mt-3">
                            <table class="table table-sm" style="font-size: 0.95rem; margin-bottom: 0; background: #e0e7f6; border-radius: 12px; overflow: hidden; border: 2px solid #b6c6e6; box-shadow: 0 2px 8px rgba(76,138,163,0.08);">
                                <thead style="background: #f3f6fb; color: #22242a; font-weight: 700;">
                                    <tr>
                                        <th style="text-align: center; padding: 8px; border-right: 1.5px solid #b6c6e6; background: #f3f6fb;">CATEGORÍA</th>
                                        <th style="text-align: center; padding: 8px; border-right: 1.5px solid #b6c6e6; background: #f3f6fb;">COSTO</th>
                                        <th style="text-align: center; padding: 8px; background: #f3f6fb;">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="background: #e0e7f6;">
                                        <td style="padding: 8px; font-weight: 600; color: #17823d; border-right: 1.5px solid #b6c6e6; text-align: center;">Proyectos</td>
                                        <td style="text-align: center; padding: 8px; border-right: 1.5px solid #b6c6e6;">$ <?= number_format($sum_tiempo_imputado_proyectos, 0, '', '.') ?></td>
                                        <td style="text-align: center; padding: 8px; font-weight: 600;">
                                            <?php 
                                            $total_general = $sum_tiempo_imputado_proyectos + $sum_tiempo_imputado_gasto;
                                            $pct_proyectos = $total_general > 0 ? ($sum_tiempo_imputado_proyectos / $total_general) * 100 : 0;
                                            echo number_format($pct_proyectos, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr style="background: #e0e7f6;">
                                        <td style="padding: 8px; font-weight: 600; color: #4A6FA5; border-right: 1.5px solid #b6c6e6; text-align: center;">ÁREAS ADMINISTRATIVAS</td>
                                        <td style="text-align: center; padding: 8px; border-right: 1.5px solid #b6c6e6;">$ <?= number_format($sum_tiempo_imputado_gasto, 0, '', '.') ?></td>
                                        <td style="text-align: center; padding: 8px; font-weight: 600;">
                                            <?php 
                                            $pct_gasto = $total_general > 0 ? ($sum_tiempo_imputado_gasto / $total_general) * 100 : 0;
                                            echo number_format($pct_gasto, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr style="background: #dbe6f7; font-weight: 800; color: #22242a;">
                                        <td style="padding: 8px; border-right: 1.5px solid #b6c6e6; text-align: center;">TOTAL</td>
                                        <td style="text-align: center; padding: 8px; border-right: 1.5px solid #b6c6e6;">$ <?= number_format($total_general, 0, '', '.') ?></td>
                                        <td style="text-align: center; padding: 8px;">100%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <script>
        Chart.register(window.ChartDataLabels);
        
        const ctx = document.getElementById('projectBudgetChart').getContext('2d');
        
        // Verificar si hay filtro de área activo
        const areaFilter = <?= json_encode($area_filter) ?>;
        
        // Preparar datos desde PHP - Solo PROYECTOS (AFFAIRE)
        const rawData = [
            <?php foreach($rows_proyectos as $row): ?>
                {
                    proyecto: '<?= addslashes($row['PROYECTO']) ?>',
                    nombre: '<?= addslashes((isset($row['nombre_proyecto']) && $row['nombre_proyecto'] !== '') ? $row['nombre_proyecto'] : $row['PROYECTO']) ?>',
                    area: '<?= addslashes($row['ÁREA FUNCIONAL']) ?>',
                    bac: <?= (float)$row['total_costo'] ?>,
                    ac: <?= ((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0)) ?>,
                    imputado: <?= (float)($row['tiempo_imputado_costo'] ?? 0) ?>
                },
            <?php endforeach; ?>
        ];
        
        // Agrupar datos si no hay filtro de área
        let aggregatedData = {};
        
        if (!areaFilter || areaFilter === '') {
            // Agrupar por proyecto (sumar todas las áreas)
            rawData.forEach(item => {
                const key = item.proyecto;
                if (!aggregatedData[key]) {
                    aggregatedData[key] = {
                        nombre: item.nombre,
                        bac: 0,
                        ac: 0,
                        imputado: 0
                    };
                }
                aggregatedData[key].bac += item.bac;
                aggregatedData[key].ac += item.ac;
                aggregatedData[key].imputado += item.imputado;
            });
        } else {
            // Mantener detalle por área cuando hay filtro activo
            rawData.forEach(item => {
                const key = item.proyecto + '_' + item.area;
                aggregatedData[key] = {
                    // Ocultar área funcional en la etiqueta visible para el usuario
                    nombre: item.nombre, // Solo nombre del proyecto
                    // Si necesitas mostrar el área internamente, puedes agregar: area: item.area
                    bac: item.bac,
                    ac: item.ac,
                    imputado: item.imputado
                };
            });
        }
        
        // Extraer arrays para Chart.js
        const chartDataArray = Object.values(aggregatedData).map(d => ({
            nombre: d.nombre,
            bac: d.bac,
            ac: d.ac,
            imputado: d.imputado,
            sumaAcImputado: d.ac + d.imputado
        }));
        
        // Ordenar de mayor a menor por la suma de AC + Imputado
        chartDataArray.sort((a, b) => b.sumaAcImputado - a.sumaAcImputado);
        
        // Extraer los datos ordenados
        const labels = chartDataArray.map(d => d.nombre);
        const bacData = chartDataArray.map(d => d.bac);
        const acData = chartDataArray.map(d => d.ac);
        const imputadoData = chartDataArray.map(d => d.imputado);
        
        const chartData = {
            labels: labels,
            datasets: [
                {
                    label: 'PTO A TERMINACIÓN (BAC)',
                    data: bacData,
                    backgroundColor: 'rgba(76, 138, 163, 0.85)', // azul original más intenso
                    borderColor: 'rgba(76, 138, 163, 1)',
                    borderWidth: 2,
                    stack: 'stack0'
                },
                {
                    label: 'COSTO ACTUAL (AC)',
                    data: acData,
                    backgroundColor: '#3ec97a', // verde pastel intenso
                    borderColor: '#3ec97a',
                    borderWidth: 2,
                    stack: 'stack1'
                },
                {
                    label: 'COSTO CARGADO MES',
                    data: imputadoData,
                    backgroundColor: 'rgba(236, 167, 107, 0.85)', // naranja original más intenso
                    borderColor: 'rgba(236, 167, 107, 1)',
                    borderWidth: 2,
                    stack: 'stack1'
                }
            ]
        };

        const config = {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', // make bars horizontal
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                // In horizontal bars, numeric value is on X axis
                                label += '$ ' + context.parsed.x.toLocaleString('es-CL');
                                return label;
                            }
                        }
                    },
                    datalabels: {
                        display: false
                    }
                },
                scales: {
                    // With indexAxis 'y', X is the numeric axis
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$ ' + value.toLocaleString('es-CL');
                            }
                        }
                    },
                    // Y is the category axis (project names)
                    y: {
                        stacked: true,
                        ticks: {
                            autoSkip: false,
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        };

        const projectChart = new Chart(ctx, config);

    // Pie/Donut chart: Proyectos vs Gasto General (Costo cargado mes)
        const pieCtx = document.getElementById('summaryPieChart').getContext('2d');
        
        // Datos de Gasto General (raw data)
        const gastoGeneralRaw = [
            <?php foreach($rows_gasto_general as $row): ?>
                {
                    proyecto: '<?= addslashes($row['PROYECTO']) ?>',
                    nombre: '<?= addslashes((isset($row['nombre_proyecto']) && $row['nombre_proyecto'] !== '') ? $row['nombre_proyecto'] : $row['PROYECTO']) ?>',
                    area: '<?= addslashes($row['ÁREA FUNCIONAL']) ?>',
                    imputado: <?= (float)($row['tiempo_imputado_costo'] ?? 0) ?>
                },
            <?php endforeach; ?>
        ];
        
        // Agrupar Gasto General si no hay filtro de área
        let gastoAggregated = {};
        
        if (!areaFilter || areaFilter === '') {
            // Agrupar por proyecto
            gastoGeneralRaw.forEach(item => {
                const key = item.proyecto;
                if (!gastoAggregated[key]) {
                    gastoAggregated[key] = {
                        nombre: item.nombre,
                        imputado: 0
                    };
                }
                gastoAggregated[key].imputado += item.imputado;
            });
        } else {
            // Mantener detalle por área
            gastoGeneralRaw.forEach(item => {
                const key = item.proyecto + '_' + item.area;
                gastoAggregated[key] = {
                    // Ocultar área funcional en la etiqueta visible para el usuario
                    nombre: item.nombre, // Solo nombre del proyecto
                    // Si necesitas mostrar el área internamente, puedes agregar: area: item.area
                    imputado: item.imputado
                };
            });
        }
        
        // Preparar labels y data para el pie chart
        const gastoLabels = Object.values(gastoAggregated).map(d => d.nombre);
        const gastoData = Object.values(gastoAggregated).map(d => d.imputado);
        
        // Suma total de proyectos (ya está agregada en PHP)
        const proyectosValor = <?= (float)$sum_tiempo_imputado_proyectos ?>;
        
        const labelsPie = ['Proyectos', ...gastoLabels];
        const dataPie = [proyectosValor, ...gastoData];
        const totalPie = Math.max(0.000001, dataPie.reduce((a,b) => a + b, 0));
        
        // Colores profesionales para gasto general
        const professionalColors = [
            '#4A6FA5', // Azul corporativo
            '#E08E45', // Naranja cálido
            '#7D5BA6', // Púrpura elegante
            '#C1666B', // Terracota
            '#48A9A6', // Turquesa
            '#D4A5A5', // Rosa empolvado
            '#9BA17B', // Verde oliva
            '#6B7AA1', // Azul grisáceo
            '#B97375', // Rosado mate
            '#7A9B76'  // Verde salvia
        ];
        
        const gastoColors = gastoLabels.map((_, idx) => professionalColors[idx % professionalColors.length]);
        // Usar el mismo verde que la tabla de estado de aprobación por empleado: #4dc18f
        const pieColors = ['#4dc18f', ...gastoColors];
        
        const pieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: labelsPie,
                datasets: [{
                    data: dataPie,
                    backgroundColor: pieColors,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 10, weight: '600' },
                            padding: 8
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const v = context.parsed; // numeric value of the slice
                                const pct = (v / totalPie) * 100;
                                // Mostrar valor sin decimales
                                return `${context.label}: $ ${v.toLocaleString('es-CL', {maximumFractionDigits: 0})} (${pct.toFixed(1)}%)`;
                            }
                        }
                    },
                    datalabels: {
                        display: false
                    }
                }
            },
        });
    </script>

    <?php
    // Obtener hh_teoricas del calendario activo para cálculo de % CARGUE
    $hh_teoricas_activas = 0.0;
    $sql_hh_teoricas = "SELECT hh_teoricas, calendario
                        FROM horas_habiles_calendario
                        WHERE UPPER(TRIM(Estado)) = 'ACTIVO'
                        ORDER BY calendario DESC
                        LIMIT 1";
    $res_hh_teoricas = $conn->query($sql_hh_teoricas);
    if ($res_hh_teoricas && $res_hh_teoricas->num_rows > 0) {
        $row_hh_teoricas = $res_hh_teoricas->fetch_assoc();
        $hh_teoricas_activas = (float)($row_hh_teoricas['hh_teoricas'] ?? 0);
    }

    // Consulta de estado de aprobación por empleado/proyecto
    $sql_estado_aprobacion = "SELECT 
        h.nom,
        h.prenom,
        h.nombre_affaire,
        h.codigo_affaire,
        COALESCE(NULLIF(TRIM(sc.CENTRO_COSTO), ''), TRIM(h.codigo_affaire)) AS centro_costo_general,
        COALESCE(NULLIF(TRIM(p.nombre_proyecto), ''), NULLIF(TRIM(h.nombre_affaire), ''), TRIM(h.codigo_affaire)) AS nombre_centro_costo,
        COALESCE(NULLIF(TRIM(h.cod_sub_ceco), ''), NULLIF(TRIM(sc.SUB_CENTRO), '')) AS subcentro_codigo,
        COALESCE(NULLIF(TRIM(h.nombre_sub_ceco), ''), NULLIF(TRIM(sc.NOMBRE_SUB_CENTRO), '')) AS subcentro_nombre,
        CASE WHEN COALESCE(NULLIF(TRIM(h.cod_sub_ceco), ''), NULLIF(TRIM(sc.SUB_CENTRO), '')) IS NOT NULL THEN 1 ELSE 0 END AS es_subcentro,
        SUM(h.tiempo_imputado_horas) AS horas_imputadas,
        SUM(h.tiempo_imputado_costo) AS costo_imputado,
        SUM(CASE WHEN h.aprobado_coordinador = 1 THEN h.tiempo_imputado_costo ELSE 0 END) AS costo_aprobado,
        ROUND(100 * SUM(CASE WHEN h.aprobado_coordinador = 1 THEN h.tiempo_imputado_horas ELSE 0 END) / NULLIF(SUM(h.tiempo_imputado_horas),0), 1) AS porcentaje_horas_aprobadas,
        h.numero_empleado,
        h.area_funcional
    FROM horas_dia h
    LEFT JOIN sub_centros_costos sc ON TRIM(sc.SUB_CENTRO) = TRIM(COALESCE(NULLIF(h.cod_sub_ceco, ''), h.codigo_affaire))
    LEFT JOIN proyectos p ON TRIM(p.centro_costos) = COALESCE(NULLIF(TRIM(sc.CENTRO_COSTO), ''), TRIM(h.codigo_affaire))
    WHERE h.Estado_Aprobacion = 'Aprobado En Curso'";

    // Filtrar por área funcional si aplica o si el rol es MIX2
    if (!empty($area_filter) && in_array($area_filter, $areas)) {
        $sql_estado_aprobacion .= " AND h.area_funcional = '" . $conn->real_escape_string($area_filter) . "'";
    } elseif (in_array($rol_usuario, ['MIX2']) && !empty($area_funcional)) {
        $sql_estado_aprobacion .= " AND h.area_funcional = '" . $conn->real_escape_string($area_funcional) . "'";
    } else {
        // Si no hay filtro, limitar a las áreas permitidas para el usuario
        $areas_sql = array_map(function($a) use ($conn) { return "'" . $conn->real_escape_string($a) . "'"; }, $areas);
        $sql_estado_aprobacion .= " AND h.area_funcional IN (" . implode(",", $areas_sql) . ") ";
    }

    $sql_estado_aprobacion .= " GROUP BY h.nom, h.prenom, h.nombre_affaire, h.codigo_affaire, centro_costo_general, nombre_centro_costo, subcentro_codigo, subcentro_nombre, es_subcentro, h.numero_empleado, h.area_funcional ORDER BY h.nom, h.prenom, nombre_centro_costo, subcentro_nombre, h.nombre_affaire";

    $result_estado = $conn->query($sql_estado_aprobacion);


    // Agrupar por empleado
    $empleados_matriz = [];
    $total_horas_imputadas_estado = 0;
    $total_costo_imputado_estado = 0;
    $total_costo_aprobado_estado = 0;

    // Obtener ausencias agrupadas por matricula
    //Alex
    $ausencias_por_matricula = [];
    $sql_ausencias = "SELECT matricula, SUM(horas) AS total_ausencias FROM ausencias_empleados GROUP BY matricula";
    $res_ausencias = $conn->query($sql_ausencias);
    if ($res_ausencias && $res_ausencias->num_rows > 0) {
        while ($row_aus = $res_ausencias->fetch_assoc()) {
            $ausencias_por_matricula[trim($row_aus['matricula'])] = (float)$row_aus['total_ausencias'];
        }
    }

    if ($result_estado && $result_estado->num_rows > 0) {
        while ($row_estado = $result_estado->fetch_assoc()) {
            // Filtrar por área funcional permitida (usar $areas, no $areas_permitidas)
            if (!in_array($row_estado['area_funcional'], $areas)) {
                continue;
            }
            $key_empleado = $row_estado['nom'] . '|' . $row_estado['prenom'];
            $matricula_empleado = isset($row_estado['numero_empleado']) ? trim($row_estado['numero_empleado']) : '';
            if (!isset($empleados_matriz[$key_empleado])) {
                $empleados_matriz[$key_empleado] = [
                    'nom' => $row_estado['nom'],
                    'prenom' => $row_estado['prenom'],
                    'matricula' => $matricula_empleado,
                    'ausencias' => isset($ausencias_por_matricula[$matricula_empleado]) ? $ausencias_por_matricula[$matricula_empleado] : 0,
                    'porcentaje_cargue' => 0,
                    'total_horas_imputadas' => 0,
                    'total_costo_imputado' => 0,
                    'total_costo_aprobado' => 0,
                    'proyectos' => []
                ];
            }
            $horas_imp = (float)($row_estado['horas_imputadas'] ?? 0);
            $costo_imp = (float)($row_estado['costo_imputado'] ?? 0);
            $costo_apr = (float)($row_estado['costo_aprobado'] ?? 0);
            $empleados_matriz[$key_empleado]['total_horas_imputadas'] += $horas_imp;
            $empleados_matriz[$key_empleado]['total_costo_imputado'] += $costo_imp;
            $empleados_matriz[$key_empleado]['total_costo_aprobado'] += $costo_apr;
            $centro_costo_general = trim((string)($row_estado['centro_costo_general'] ?? $row_estado['codigo_affaire'] ?? ''));
            $nombre_centro_costo = trim((string)($row_estado['nombre_centro_costo'] ?? $row_estado['nombre_affaire'] ?? $row_estado['codigo_affaire'] ?? ''));
            $subcentro_codigo = trim((string)($row_estado['subcentro_codigo'] ?? ''));
            $subcentro_nombre = trim((string)($row_estado['subcentro_nombre'] ?? ''));
            $es_subcentro = (int)($row_estado['es_subcentro'] ?? 0) === 1;

            if ($es_subcentro) {
                $mismo_codigo_general = $subcentro_codigo !== '' && strcasecmp($subcentro_codigo, $centro_costo_general) === 0;
                $mismo_nombre_general = $subcentro_nombre !== '' && $nombre_centro_costo !== '' && strcasecmp($subcentro_nombre, $nombre_centro_costo) === 0;
                if ($mismo_codigo_general || $mismo_nombre_general) {
                    $es_subcentro = false;
                    $subcentro_codigo = '';
                    $subcentro_nombre = '';
                }
            }

            $nombre_detalle = $nombre_centro_costo !== '' ? $nombre_centro_costo : trim((string)($row_estado['nombre_affaire'] ?? ''));

            if ($es_subcentro) {
                $etiqueta_subcentro = $subcentro_nombre !== '' ? $subcentro_nombre : $subcentro_codigo;
                if ($etiqueta_subcentro !== '') {
                    $nombre_detalle .= ' / ' . $etiqueta_subcentro;
                }
            }

            $empleados_matriz[$key_empleado]['proyectos'][] = [
                'nombre_proyecto' => $nombre_detalle,
                'codigo_affaire' => trim((string)($row_estado['codigo_affaire'] ?? '')),
                'es_subcentro' => $es_subcentro,
                'subcentro_codigo' => $subcentro_codigo,
                'horas_imputadas' => $horas_imp,
                'costo_imputado' => $costo_imp,
                'costo_aprobado' => $costo_apr,
                'porcentaje_horas_aprobadas' => (float)($row_estado['porcentaje_horas_aprobadas'] ?? 0)
            ];
            $total_horas_imputadas_estado += $horas_imp;
            $total_costo_imputado_estado += $costo_imp;
            $total_costo_aprobado_estado += $costo_apr;
        }
    }

    // Calcular % CARGUE por empleado: horas imputadas / hh_teoricas activas
    if (!empty($empleados_matriz)) {
        foreach ($empleados_matriz as $key_emp => $empleado) {
            $porcentaje_cargue_calc = ($hh_teoricas_activas > 0)
                ? (($empleado['total_horas_imputadas'] / $hh_teoricas_activas) * 100)
                : 0;
            $empleados_matriz[$key_emp]['porcentaje_cargue'] = $porcentaje_cargue_calc;
        }
    }

    $porcentaje_total_estado = $total_costo_imputado_estado > 0 ? ($total_costo_aprobado_estado / $total_costo_imputado_estado) * 100 : 0;
    ?>

    <!-- TABLA 3: ESTADO DE APROBACIÓN POR COLABORADOR -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-2" style="display: grid; grid-template-columns: 1fr;">
            <h4 class="mb-3 d-flex align-items-center gap-2" style="color: #4C8AA3; font-weight: 600; background: #e3e7ef; border-radius: 10px; padding: 10px 24px;">
                <i class="bi bi-person-check" style="font-size: 1.5rem; color: #4dc18f; margin-right: 10px;"></i>
                ESTADO DE APROBACIÓN POR COLABORADOR
            </h4>
            <a href="descargar_horas_dia_excel.php" class="btn" style="white-space:nowrap; background-color: #4dc18f; color: #fff; border: none; font-weight: 600;">
                <i class="bi bi-file-earmark-excel"></i> Descargar reporte Excel horas_dia
            </a>
        </div>
        <div class="table-responsive">
            <table class="table tabla-empleados align-middle mb-0" style="background:#fff; width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="width: 50px; background-color: #4dc18f; color: white; padding: 12px; text-align: center; border: none;"></th>
                        <th style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">APELLIDO</th>
                        <th style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">NOMBRE</th>
                        <th class="d-none" style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">AUSENCIAS</th>
                        <th class="d-none" style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">HH TEÓRICAS</th>
                        <th style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">% CARGUE</th>
                        <th style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">HORAS CARGADAS MES</th>
                        <th style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">COSTO CARGADO MES</th>
                        <th style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">COSTO APROBADO MES</th>
                        <th style="background-color: #4dc18f; color: white; padding: 12px; text-align: center; font-weight: 600; border: none;">% HORAS APROBADAS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($empleados_matriz)): ?>
                        <?php 
                        $empleado_index = 0;
                        foreach($empleados_matriz as $key_emp => $empleado): 
                            $empleado_index++;
                            $porc_empleado = $empleado['total_costo_imputado'] > 0 ? 
                                ($empleado['total_costo_aprobado'] / $empleado['total_costo_imputado']) * 100 : 0;
                            $color_empleado = percentToColor($porc_empleado);
                            $tiene_aprobacion_cero = ($porc_empleado == 0 && $empleado['total_costo_imputado'] > 0);
                            $bg_empleado = $tiene_aprobacion_cero ? 'background: #ffe4cc !important;' : '';
                            // Traer ausencias reales por empleado
                            $ausencias = isset($empleado['ausencias']) ? $empleado['ausencias'] : 0;
                            // Traer hh_teoricas activas
                            $hh_teoricas_empleado = isset($hh_teoricas_activas) ? $hh_teoricas_activas : 0;
                            // Calcular % cargue
                            $porcentaje_cargue = ($hh_teoricas_empleado > 0) ? (($ausencias + $empleado['total_horas_imputadas']) / $hh_teoricas_empleado) * 100 : 0;
                        ?>
                            <!-- Fila principal del empleado -->
                            <tr class="empleado-row <?= $tiene_aprobacion_cero ? 'sin-aprobacion' : '' ?>" 
                                data-empleado-id="<?= $empleado_index ?>"
                                onclick="toggleEmpleadoDetalle(<?= $empleado_index ?>)"
                                style="<?= $bg_empleado ?>">
                                <td style="text-align: center; padding: 14px 8px; border-bottom: 1px solid #e0e0e0;">
                                    <i class="bi bi-chevron-right toggle-icon" id="toggle-icon-<?= $empleado_index ?>" 
                                       style="font-size: 1.1rem; font-weight: bold; color: #5A8CA7;"></i>
                                </td>
                                <td style="text-align: left; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                    <?= htmlspecialchars($empleado['nom']) ?>
                                </td>
                                <td style="text-align: left; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                    <?= htmlspecialchars($empleado['prenom']) ?>
                                </td>
                                <td class="d-none" style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #a94442; border-bottom: 1px solid #e0e0e0; background: #fff7f7;">
                                    <?= $ausencias ?>
                                </td>
                                <td class="d-none" style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #17823d; border-bottom: 1px solid #e0e0e0; background: #f7fff7;">
                                    <?= $hh_teoricas_empleado ?>
                                </td>
                                <td style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #4C8AA3; border-bottom: 1px solid #e0e0e0; background: #f7faff;">
                                    <?= number_format($porcentaje_cargue, 1) ?>%
                                </td>
                                <td style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                    <?= number_format($empleado['total_horas_imputadas'], 2, ',', '.') ?> hrs
                                </td>
                                <td style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                    $ <?= number_format($empleado['total_costo_imputado'], 0, '', '.') ?>
                                </td>
                                <td style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                    $ <?= number_format($empleado['total_costo_aprobado'], 0, '', '.') ?>
                                </td>
                                <td style="text-align: center; padding: 14px 15px; border-bottom: 1px solid #e0e0e0;">
                                    <span style="color: <?= $color_empleado ?>; font-weight: 700; font-size: 0.95rem;">
                                        <?= number_format($porc_empleado, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                            <!-- Filas de detalle de proyectos (ocultas inicialmente) -->
                            <?php foreach($empleado['proyectos'] as $idx => $proyecto): 
                                $porc_proyecto = (float)$proyecto['porcentaje_horas_aprobadas'];
                                $color_proyecto = percentToColor($porc_proyecto);
                                $tiene_aprobacion_cero_proy = ($porc_proyecto == 0 && $proyecto['costo_imputado'] > 0);
                                $bg_proyecto = $tiene_aprobacion_cero_proy ? 'background: #fff3e6 !important;' : 'background: #fafafa;';
                            ?>
                                <tr class="detalle-proyecto detalle-empleado-<?= $empleado_index ?> <?= $tiene_aprobacion_cero_proy ? 'proyecto-sin-aprobacion' : '' ?>" style="display: none;">
                                    <td style="<?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;"></td>
                                    <td colspan="2" style="text-align: left; padding: 10px 15px 10px 50px; font-size: 0.88rem; color: #555; <?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;">
                                        <i class="bi bi-arrow-return-right" style="color: #999; margin-right: 8px;"></i>
                                        <span style="color: #444;"><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></span>
                                        <?php if (!empty($proyecto['es_subcentro'])): ?>
                                            <span style="display: inline-block; margin-left: 8px; padding: 2px 8px; border-radius: 999px; background: #eef6fb; color: #4C8AA3; font-size: 0.76rem; font-weight: 600;">SUBCENTRO</span>
                                            <?php if (!empty($proyecto['subcentro_codigo'])): ?>
                                                <div style="font-size: 0.76rem; color: #7f8c8d; margin-top: 3px;">
                                                    Código: <?= htmlspecialchars($proyecto['subcentro_codigo']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (!empty($proyecto['codigo_affaire'])): ?>
                                            <div style="font-size: 0.76rem; color: #7f8c8d; margin-top: 3px;">
                                                Código: <?= htmlspecialchars($proyecto['codigo_affaire']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none" style="<?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;"></td>
                                    <td class="d-none" style="<?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;"></td>
                                    <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;"></td>
                                    <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;">
                                        <?= number_format($proyecto['horas_imputadas'], 2, ',', '.') ?> hrs
                                    </td>
                                    <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;">
                                        $ <?= number_format($proyecto['costo_imputado'], 0, '', '.') ?>
                                    </td>
                                    <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;">
                                        $ <?= number_format($proyecto['costo_aprobado'], 0, '', '.') ?>
                                    </td>
                                    <td style="text-align: center; padding: 10px 15px; <?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;">
                                        <span style="color: <?= $color_proyecto ?>; font-weight: 600; font-size: 0.88rem;">
                                            <?= number_format($porc_proyecto, 1) ?>%
                                        </span>
                                    </td>
                                    <td class="d-none" style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= $bg_proyecto ?> border-bottom: 1px solid #f0f0f0;"></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <!-- Fila de totales -->
                        <tr style="background: linear-gradient(to right, #e8f4f8, #d4e9f2); font-weight: 700; border-top: 3px solid #4C8AA3;">
                            <td colspan="3" style="text-align: right; padding: 15px 20px; font-size: 1rem; color: #2c3e50;">
                                TOTAL:
                            </td>
                            <td style="text-align: center; padding: 15px; font-size: 1rem; color: #a94442; background: #fff7f7;">-</td>
                            <td style="text-align: center; padding: 15px; font-size: 1rem; color: #2c3e50;">
                                <?= number_format($total_horas_imputadas_estado, 2, ',', '.') ?> hrs
                            </td>
                            <td style="text-align: center; padding: 15px; font-size: 1rem; color: #2c3e50;">
                                $ <?= number_format($total_costo_imputado_estado, 0, '', '.') ?>
                            </td>
                            <td style="text-align: center; padding: 15px; font-size: 1rem; color: #2c3e50;">
                                $ <?= number_format($total_costo_aprobado_estado, 0, '', '.') ?>
                            </td>
                            <td style="text-align: center; padding: 15px;">
                                <span style="color: <?= percentToColor($porcentaje_total_estado) ?>; font-weight: 700; font-size: 1rem;">
                                    <?= number_format($porcentaje_total_estado, 1) ?>%
                                </span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center" style="padding: 30px;">No hay datos de estado de aprobación para mostrar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TABLA 1: PROYECTOS (AFFAIRE) -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3" style="display: grid; grid-template-columns: 1fr;">
            <h4 class="mb-0 d-flex align-items-center gap-2" style="color: #4C8AA3; font-weight: 600; background: #e3e7ef; border-radius: 10px; padding: 10px 24px;">
                <i class="bi bi-kanban" style="font-size: 1.5rem; color: #4dc18f; margin-right: 10px;"></i>
                PROYECTOS
            </h4>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="ordenarTablaProyectos('area')" style="color: #1D4459; border-color: #1D4459;">
                    <i class="bi bi-sort-alpha-down"></i> Ordenar por Área
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="ordenarTablaProyectos('costo')" style="color: #1D4459; border-color: #1D4459;">
                    <i class="bi bi-sort-numeric-down"></i> Ordenar por Costo
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table id="tablaProyectos" class="table resumen-table align-middle text-center mb-0" style="background:#fff; width: 100%;">
                <thead class="resumen-thead">
                    <tr>
                        <th<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? ' class="d-none"' : '') ?>>ÁREA FUNCIONAL</th>
                        <th>AVISO</th>
                        <th class="d-none">NATURE IMPUTATION</th>
                        <th class="d-none">CECO</th>
                        <th>NOMBRE PROYECTO</th>
                        <th style="background-color: #4C8AA3; color: white;">PTO A TERMINACIÓN (BAC)</th>
                        <th style="background-color: rgba(139, 195, 139, 0.85); color: white;">COSTO ACTUAL (AC)</th>
                        <th style="background-color: rgba(139, 195, 139, 0.85); color: white;">COSTO POR EJECUTAR (ETC)</th>
                        <th style="background-color: #ECA76B; color: white;">COSTO CARGADO MES</th>
                        <th class="d-none">COSTO APROBADO</th>
                        <th style="background-color: #ECA76B; color: white;">% COSTO APROBADO MES</th>
                        <th style="background-color: #A6C2C9; color: white;">NUEVO COSTO ACTUAL</th>
                        <th class="d-none">COSTO TEORICO</th>
                        <th class="d-none">% AVANCE FÍSICO EJECUTADO</th>
                        <th class="d-none">% AVANCE FÍSICO PROGRAMADO</th>
                        <th class="d-none">% COSTO COAN EJECUTADO</th>
                        <th class="d-none">CPI</th>
                        <th class="d-none">SPI</th>
                        <th>VER DETALLE</th>
                        <th style="background-color: #17823D; color: white;" class="text-nowrap d-none">COSTO REAL APROBADO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows_proyectos)): ?>
                        <?php foreach($rows_proyectos as $row): ?>
                            <tr>
                                <td<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? ' class="d-none"' : '') ?>>
                                    <?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '-') ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $tiene_presupuesto = (float)$row['total_costo'] > 0;
                                    $tiempo_imputado_val = (float)($row['tiempo_imputado_costo'] ?? 0);
                                    $etc_val = (float)$row['total_costo'] - ((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0));
                                    $supera_saldo = $tiempo_imputado_val > $etc_val;
                                    
                                    if (!$tiene_presupuesto): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-danger" 
                                           style="font-size: 1.3rem; cursor: pointer;" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="¡Este proyecto no tiene presupuesto, por tanto estas HH no se podrán aprobar!"></i>
                                    <?php elseif ($supera_saldo): ?>
                                        <i class="bi bi-exclamation-circle-fill text-warning" 
                                           style="font-size: 1.3rem; cursor: pointer;" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="¡El costo cargado supera el saldo por ejecutar, por tanto estas HH no se podrán aprobar!"></i>
                                    <?php else: ?>
                                        <i class="bi bi-check-circle-fill text-success" 
                                           style="font-size: 1.3rem;" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="Proyecto con presupuesto aprobado"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none">
                                    <?php $ni = mapNatureImputation($row['nature_imputation'] ?? ''); echo htmlspecialchars($ni !== '' ? $ni : '-'); ?>
                                </td>
                                <td class="d-none">
                                    <span class="project-name"><?= htmlspecialchars($row['PROYECTO']) ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['nombre_proyecto'] ?? '-') ?>
                                </td>
                                <td class="text-success">$ <?= number_format((float)$row['total_costo'], 0, '', '.') ?></td>
                                <td class="text-info">$ <?= number_format(((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0)), 0, '', '.') ?></td>
                                <td class="text-warning">$ <?= number_format((float)$row['total_costo'] - ((float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0)), 0, '', '.') ?></td>
                                <td class="text-secondary">$ <?= number_format((float)($row['tiempo_imputado_costo'] ?? 0), 0, '', '.') ?></td>
                                <td class="text-primary d-none">$ <?= number_format((float)($row['costo_aprobado'] ?? 0), 0, '', '.') ?></td>
                                <?php 
                                    $costo_aprobado = (float)($row['costo_aprobado'] ?? 0);
                                    $tiempo_imputado = (float)($row['tiempo_imputado_costo'] ?? 0);
                                    // $tiempo_imputado ya solo suma Estado_Aprobacion = 'Aprobado En Curso', así que el cálculo es correcto
                                    $porc_aprobado_mes = ($tiempo_imputado > 0) ? ($costo_aprobado / $tiempo_imputado) * 100 : 0;
                                    $color_aprobado = percentToColor($porc_aprobado_mes);
                                ?>
                                <td><span style="color: <?= $color_aprobado ?>; font-weight: 600;"><?= number_format($porc_aprobado_mes, 0) ?>%</span></td>
                                <?php 
                                    $nuevo_costo_actual = (float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0) + (float)($row['costo_aprobado'] ?? 0);
                                ?>
                                <td class="text-success">$ <?= number_format($nuevo_costo_actual, 0, '', '.') ?></td>
                                <?php 
                                    $costo_teorico = 0;
                                    $pct_ejecutado = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'];
                                    if ((float)$row['total_costo'] > 0) {
                                        $costo_teorico = ($pct_ejecutado / 100.0) * (float)$row['total_costo'];
                                    }
                                ?>
                                <td class="d-none">$ <?= number_format($costo_teorico, 0, '', '.') ?></td>
                                <td class="d-none"><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'], 0) ?>%</td>
                                <td class="d-none"><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'], 0) ?>%</td>
                                <td class="d-none"><?php 
                                    $porcentaje_coan = 0;
                                    if ((float)$row['total_costo'] > 0) {
                                        $porcentaje_coan = ((float)$row['total_valorizado_2025'] / (float)$row['total_costo']) * 100;
                                    }
                                    echo number_format($porcentaje_coan, 0) . '%';
                                ?></td>
                                <td class="d-none text-center"><?php
                                    $cpi = 0;
                                    $ac = (float)$row['total_valorizado_2025'];
                                    if ($ac > 0) {
                                        $cpi = $costo_teorico / $ac;
                                    }
                                    echo number_format($cpi, 2);
                                ?></td>
                                <td class="d-none text-center"><?php
                                    $spi = 0;
                                    if ((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'] > 0) {
                                        $spi = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'] / (float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'];
                                    }
                                    echo number_format($spi, 2);
                                ?></td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-ver-detalle" 
                                            data-proyecto="<?= htmlspecialchars($row['PROYECTO']) ?>" 
                                            data-area="<?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '') ?>"
                                            data-nombre="<?= htmlspecialchars($row['nombre_proyecto'] ?? $row['PROYECTO']) ?>"
                                            title="Ver Detalle">
                                        <i class="bi bi-eye" style="color: #4C8AA3;"></i>
                                    </button>
                                </td>
                                <td class="text-success text-nowrap d-none">$ <?= number_format((float)($row['costo_real_aprobado'] ?? 0), 0, '', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Fila de totales PROYECTOS -->
                        <tr style="background: #e8f4f8; font-weight: 600; border-top: 2px solid #4C8AA3;">
                            <td colspan="<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? '2' : '3') ?>" style="text-align: right; padding-right: 15px;">TOTAL PROYECTOS:</td>
                            <td class="text-success">$ <?= number_format($sum_bac_proyectos, 0, '', '.') ?></td>
                            <td class="text-info">$ <?= number_format($sum_ac_proyectos, 0, '', '.') ?></td>
                            <td class="text-warning">$ <?= number_format($sum_etc_proyectos, 0, '', '.') ?></td>
                            <td class="text-secondary">$ <?= number_format($sum_tiempo_imputado_proyectos, 0, '', '.') ?></td>
                            <td class="text-primary d-none">$ <?= number_format($sum_costo_aprobado_proyectos, 0, '', '.') ?></td>
                            <?php 
                                // $sum_tiempo_imputado_proyectos ya solo suma Estado_Aprobacion = 'Aprobado En Curso', así que el cálculo es correcto
                                $total_porc_aprobado_mes_proyectos = ($sum_tiempo_imputado_proyectos > 0) ? ($sum_costo_aprobado_proyectos / $sum_tiempo_imputado_proyectos) * 100 : 0;
                                $color_total_aprobado_proyectos = percentToColor($total_porc_aprobado_mes_proyectos);
                            ?>
                            <td><span style="color: <?= $color_total_aprobado_proyectos ?>; font-weight: 600;"><?= number_format($total_porc_aprobado_mes_proyectos, 0) ?>%</span></td>
                            <td class="text-success">$ <?= number_format($sum_nuevo_costo_actual_proyectos, 0, '', '.') ?></td>
                            <td class="d-none">-</td>
                            <td class="d-none" colspan="4">-</td>
                            <td>-</td>
                            <td class="text-success text-nowrap d-none">$ <?= number_format($sum_costo_real_aprobado_proyectos, 0, '', '.') ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? '11' : '12') ?>" class="text-center">No hay proyectos para mostrar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TABLA 2: GASTO GENERAL (FRAIS GENERAUX DIVERS) -->
    <div class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3" style="display: grid; grid-template-columns: 1fr;">
            <h4 class="mb-0 d-flex align-items-center gap-2" style="color: #4C8AA3; font-weight: 600; background: #e3e7ef; border-radius: 10px; padding: 10px 24px;">
                <i class="bi bi-cash-coin" style="font-size: 1.5rem; color: #4dc18f; margin-right: 10px;"></i>
                ÁREAS ADMINISTRATIVAS
            </h4>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="ordenarTablaGasto('area')" style="color: #1D4459; border-color: #1D4459;">
                    <i class="bi bi-sort-alpha-down"></i> Ordenar por Área
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="ordenarTablaGasto('costo')" style="color: #1D4459; border-color: #1D4459;">
                    <i class="bi bi-sort-numeric-down"></i> Ordenar por Costo
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table id="tablaGasto" class="table resumen-table align-middle text-center mb-0" style="background:#fff; width: 100%;">
                <thead class="resumen-thead">
                    <tr style="background: #6C3483;">
                        <th style="background: #6C3483; color: #fff; font-weight: 700;"<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? ' class=\"d-none\"' : '') ?>>ÁREA FUNCIONAL</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">NATURE IMPUTATION</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">CECO</th>
                        <th style="background: #6C3483; color: #fff; font-weight: 700;">NOMBRE PROYECTO</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">PTO A TERMINACIÓN (BAC)</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">COSTO ACTUAL (AC)</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">COSTO POR EJECUTAR (ETC)</th>
                        <th style="background: #6C3483; color: #fff; font-weight: 700;">COSTO CARGADO MES</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">COSTO APROBADO</th>
                        <th style="background: #6C3483; color: #fff; font-weight: 700;">% COSTO APROBADO MES</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">NUEVO COSTO ACTUAL</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">COSTO TEORICO</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">% AVANCE FÍSICO EJECUTADO</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">% AVANCE FÍSICO PROGRAMADO</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">% COSTO COAN EJECUTADO</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">CPI</th>
                        <th class="d-none" style="background: #6C3483; color: #fff;">SPI</th>
                        <th style="background: #6C3483; color: #fff; font-weight: 700;">VER DETALLE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows_gasto_general)): ?>
                        <?php foreach($rows_gasto_general as $row): ?>
                            <?php if ((float)($row['tiempo_imputado_costo'] ?? 0) != 0): ?>
                                <tr>
                                    <td<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? ' class="d-none"' : '') ?>>
                                        <?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '-') ?>
                                    </td>
                                    <td class="d-none">
                                        <?php $ni = mapNatureImputation($row['nature_imputation'] ?? ''); echo htmlspecialchars($ni !== '' ? $ni : '-'); ?>
                                    </td>
                                    <td class="d-none">
                                        <span class="project-name"><?= htmlspecialchars($row['PROYECTO']) ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['nombre_proyecto'] ?? '-') ?>
                                    </td>
                                    <td class="text-success d-none">$ <?= number_format((float)$row['total_costo'], 0, '', '.') ?></td>
                                    <td class="text-info d-none">$ <?= number_format(0, 0, '', '.') ?></td>
                                    <td class="text-warning d-none">$ <?= number_format(0, 0, '', '.') ?></td>
                                    <td class="text-secondary">$ <?= number_format((float)($row['tiempo_imputado_costo'] ?? 0), 0, '', '.') ?></td>
                                    <td class="text-primary d-none">$ <?= number_format((float)($row['costo_aprobado'] ?? 0), 0, '', '.') ?></td>
                                    <?php 
                                        $costo_aprobado = (float)($row['costo_aprobado'] ?? 0);
                                        $tiempo_imputado = (float)($row['tiempo_imputado_costo'] ?? 0);
                                        $porc_aprobado_mes = ($tiempo_imputado > 0) ? ($costo_aprobado / $tiempo_imputado) * 100 : 0;
                                        $color_aprobado = percentToColor($porc_aprobado_mes);
                                    ?>
                                    <td><span style="color: <?= $color_aprobado ?>; font-weight: 600;"><?= number_format($porc_aprobado_mes, 0) ?>%</span></td>
                                    <?php 
                                        $nuevo_costo_actual = (float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0) + (float)($row['costo_aprobado'] ?? 0);
                                    ?>
                                    <td class="text-success d-none">$ <?= number_format($nuevo_costo_actual, 0, '', '.') ?></td>
                                    <?php 
                                        $costo_teorico = 0;
                                        $pct_ejecutado = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'];
                                        if ((float)$row['total_costo'] > 0) {
                                            $costo_teorico = ($pct_ejecutado / 100.0) * (float)$row['total_costo'];
                                        }
                                    ?>
                                    <td class="d-none">$ <?= number_format($costo_teorico, 0, '', '.') ?></td>
                                    <td class="d-none"><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'], 0) ?>%</td>
                                    <td class="d-none"><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'], 0) ?>%</td>
                                    <td class="d-none"><?php 
                                        $porcentaje_coan = 0;
                                        if ((float)$row['total_costo'] > 0) {
                                            $porcentaje_coan = ((float)$row['total_valorizado_2025'] / (float)$row['total_costo']) * 100;
                                        }
                                        echo number_format($porcentaje_coan, 0) . '%';
                                    ?></td>
                                    <td class="d-none text-center"><?php
                                        $cpi = 0;
                                        $ac = (float)$row['total_valorizado_2025'];
                                        if ($ac > 0) {
                                            $cpi = $costo_teorico / $ac;
                                        }
                                        echo number_format($cpi, 2);
                                    ?></td>
                                    <td class="d-none text-center"><?php
                                        $spi = 0;
                                        if ((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'] > 0) {
                                            $spi = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'] / (float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'];
                                        }
                                        echo number_format($spi, 2);
                                    ?></td>
                                    <td class="text-center">
                                        <button type="button" 
                                                class="btn btn-sm btn-ver-detalle" 
                                                data-proyecto="<?= htmlspecialchars($row['PROYECTO']) ?>" 
                                                data-area="<?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '') ?>"
                                                data-nombre="<?= htmlspecialchars($row['nombre_proyecto'] ?? $row['PROYECTO']) ?>"
                                                title="Ver Detalle"
                                                style="background: #fff; color: #4527A0; border: 2px solid #7E57C2; font-weight: 600;">
                                            <i class="bi bi-eye" style="color: #311B92;"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <!-- Fila de totales GASTO GENERAL -->
                        <tr style="background: #e8f4f8; font-weight: 600; border-top: 2px solid #4C8AA3;">
                            <td colspan="<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? '1' : '2') ?>" style="text-align: right; padding-right: 15px;">TOTAL GASTO GENERAL:</td>
                            <td class="text-success d-none">$ <?= number_format($sum_bac_gasto, 0, '', '.') ?></td>
                            <td class="text-info d-none">$ <?= number_format($sum_ac_gasto, 0, '', '.') ?></td>
                            <td class="text-warning d-none">$ <?= number_format($sum_etc_gasto, 0, '', '.') ?></td>
                            <td class="text-secondary">$ <?= number_format($sum_tiempo_imputado_gasto, 0, '', '.') ?></td>
                            <td class="text-primary d-none">$ <?= number_format($sum_costo_aprobado_gasto, 0, '', '.') ?></td>
                            <?php 
                                $total_porc_aprobado_mes_gasto = ($sum_tiempo_imputado_gasto > 0) ? ($sum_costo_aprobado_gasto / $sum_tiempo_imputado_gasto) * 100 : 0;
                                $color_total_aprobado_gasto = percentToColor($total_porc_aprobado_mes_gasto);
                            ?>
                            <td><span style="color: <?= $color_total_aprobado_gasto ?>; font-weight: 600;"><?= number_format($total_porc_aprobado_mes_gasto, 0) ?>%</span></td>
                            <td class="text-success d-none">$ <?= number_format($sum_nuevo_costo_actual_gasto, 0, '', '.') ?></td>
                            <td class="d-none">-</td>
                            <td class="d-none" colspan="4">-</td>
                            <td>-</td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? '3' : '4') ?>" class="text-center">No hay gastos generales para mostrar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

        <!-- TABLA 3: AUSENCIAS (ABSENCE) -->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0 d-flex align-items-center gap-2" style="color: #4C8AA3; font-weight: 600; background: #e3e7ef; border-radius: 10px; padding: 10px 24px;">
                    <i class="bi bi-calendar-x" style="font-size: 1.5rem; color: #4dc18f; margin-right: 10px;"></i>
                    AUSENCIAS
                </h4>
            </div>
            <div class="table-responsive">
                <table id="tablaAusencias" class="table resumen-table align-middle text-center mb-0" style="background:#fff; width: 100%;">
                    <thead class="resumen-thead">
                        <tr style="background: #6C3483;">
                            <th style="background: #6C3483; color: #fff;"<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? ' class="d-none"' : '') ?>>ÁREA FUNCIONAL</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">NATURE IMPUTATION</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">CECO</th>
                            <th style="background: #6C3483; color: #fff;">NOMBRE PROYECTO</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">PTO A TERMINACIÓN (BAC)</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">COSTO ACTUAL (AC)</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">COSTO POR EJECUTAR (ETC)</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">COSTO CARGADO MES</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">COSTO APROBADO</th>
                            <th style="background: #6C3483; color: #fff;">% COSTO APROBADO MES</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">NUEVO COSTO ACTUAL</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">COSTO TEORICO</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">% AVANCE FÍSICO EJECUTADO</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">% AVANCE FÍSICO PROGRAMADO</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">% COSTO COAN EJECUTADO</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">CPI</th>
                            <th class="d-none" style="background: #6C3483; color: #fff;">SPI</th>
                            <th style="background: #6C3483; color: #fff;">VER DETALLE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows_ausencias)): ?>
                            <?php foreach($rows_ausencias as $row): ?>
                                <?php if ((float)($row['tiempo_imputado_costo'] ?? 0) != 0): ?>
                                    <tr>
                                        <td<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? ' class="d-none"' : '') ?>>
                                            <?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '-') ?>
                                        </td>
                                        <td class="d-none">
                                            <?php $ni = mapNatureImputation($row['nature_imputation'] ?? ''); echo htmlspecialchars($ni !== '' ? $ni : '-'); ?>
                                        </td>
                                        <td class="d-none">
                                            <span class="project-name"><?= htmlspecialchars($row['PROYECTO']) ?></span>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['nombre_proyecto'] ?? '-') ?>
                                        </td>
                                        <td class="text-success d-none">$ <?= number_format((float)$row['total_costo'], 0, '', '.') ?></td>
                                        <td class="text-info d-none">$ <?= number_format(0, 0, '', '.') ?></td>
                                        <td class="text-warning d-none">$ <?= number_format(0, 0, '', '.') ?></td>
                                        <td class="text-secondary d-none">$ <?= number_format((float)($row['tiempo_imputado_costo'] ?? 0), 0, '', '.') ?></td>
                                        <td class="text-primary d-none">$ <?= number_format((float)($row['costo_aprobado'] ?? 0), 0, '', '.') ?></td>
                                        <?php 
                                            $costo_aprobado = (float)($row['costo_aprobado'] ?? 0);
                                            $tiempo_imputado = (float)($row['tiempo_imputado_costo'] ?? 0);
                                            $porc_aprobado_mes = ($tiempo_imputado > 0) ? ($costo_aprobado / $tiempo_imputado) * 100 : 0;
                                            $color_aprobado = percentToColor($porc_aprobado_mes);
                                        ?>
                                        <td><span style="color: <?= $color_aprobado ?>; font-weight: 600;"><?= number_format($porc_aprobado_mes, 0) ?>%</span></td>
                                        <?php 
                                            $nuevo_costo_actual = (float)$row['total_valorizado_2025'] + (float)($row['costo_real_aprobado'] ?? 0) + (float)($row['costo_aprobado'] ?? 0);
                                        ?>
                                        <td class="text-success d-none">$ <?= number_format($nuevo_costo_actual, 0, '', '.') ?></td>
                                        <?php 
                                            $costo_teorico = 0;
                                            $pct_ejecutado = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'];
                                            if ((float)$row['total_costo'] > 0) {
                                                $costo_teorico = ($pct_ejecutado / 100.0) * (float)$row['total_costo'];
                                            }
                                        ?>
                                        <td class="d-none">$ <?= number_format($costo_teorico, 0, '', '.') ?></td>
                                        <td class="d-none"><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'], 0) ?>%</td>
                                        <td class="d-none"><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'], 0) ?>%</td>
                                        <td class="d-none"><?php 
                                            $porcentaje_coan = 0;
                                            if ((float)$row['total_costo'] > 0) {
                                                $porcentaje_coan = ((float)$row['total_valorizado_2025'] / (float)$row['total_costo']) * 100;
                                            }
                                            echo number_format($porcentaje_coan, 0) . '%';
                                        ?></td>
                                        <td class="d-none text-center"><?php
                                            $cpi = 0;
                                            $ac = (float)$row['total_valorizado_2025'];
                                            if ($ac > 0) {
                                                $cpi = $costo_teorico / $ac;
                                            }
                                            echo number_format($cpi, 2);
                                        ?></td>
                                        <td class="d-none text-center"><?php
                                            $spi = 0;
                                            if ((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'] > 0) {
                                                $spi = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'] / (float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'];
                                            }
                                            echo number_format($spi, 2);
                                        ?></td>
                                        <td class="text-center">
                                            <button type="button" 
                                                    class="btn btn-sm btn-ver-detalle" 
                                                    data-proyecto="<?= htmlspecialchars($row['PROYECTO']) ?>" 
                                                    data-area="<?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '') ?>"
                                                    data-nombre="<?= htmlspecialchars($row['nombre_proyecto'] ?? $row['PROYECTO']) ?>"
                                                    title="Ver Detalle">
                                                <i class="bi bi-eye" style="color: #4C8AA3;"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="<?= (($rol_usuario === 'MIX1' || $rol_usuario === 'COORD') ? '3' : '4') ?>" class="text-center">No hay ausencias para mostrar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div> <!-- Cierre de main-content -->
    <!-- Mostrar los registros de mes_activo con estado 'APROBACION EN CURSO' debajo de la tabla de estado de aprobación por empleado -->
    <?php
    $mes_activo_query = "SELECT fecha_inicio, fecha_fin, estado FROM mes_activo WHERE UPPER(TRIM(estado)) = 'APROBACION EN CURSO' ORDER BY id DESC";
    $mes_activo_result = $conn->query($mes_activo_query);
    // Bloque oculto visualmente para el usuario (comentado)
    /*
    if ($mes_activo_result && $mes_activo_result->num_rows > 0) {
        echo '<div class="mb-4 text-center">';
        while ($mes_activo_row = $mes_activo_result->fetch_assoc()) {
            echo '<div class="mb-2" style="font-size:1.1rem; color:#4C8AA3; font-weight:600;">';
            echo 'Mes activo: ';
            echo 'Fecha inicio: <span style="color:#17823d">' . htmlspecialchars($mes_activo_row['fecha_inicio']) . '</span> | ';
            echo 'Fecha fin: <span style="color:#17823d">' . htmlspecialchars($mes_activo_row['fecha_fin']) . '</span> | ';
            echo 'Estado: <span style="color:#E08E45">' . htmlspecialchars($mes_activo_row['estado']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }
    */

    // Tabla de fechas de Horas_dias
    // Obtener el mes activo en estado 'APROBACION EN CURSO'
    $mes_activo_query = "SELECT fecha_inicio, fecha_fin FROM mes_activo WHERE UPPER(TRIM(estado)) = 'APROBACION EN CURSO' ORDER BY id DESC LIMIT 1";
    $mes_activo_result = $conn->query($mes_activo_query);
    $fecha_inicio_activo = null;
    $fecha_fin_activo = null;
    if ($mes_activo_result && $mes_activo_result->num_rows > 0) {
        $mes_activo_row = $mes_activo_result->fetch_assoc();
        $fecha_inicio_activo = $mes_activo_row['fecha_inicio'];
        $fecha_fin_activo = $mes_activo_row['fecha_fin'];
    }
    // Solo mostrar el mes y fechas de Horas_dia que estén dentro del rango activo
    $horas_dia_minmax_mes_query = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, MIN(fecha) AS fecha_minima, MAX(fecha) AS fecha_maxima FROM horas_dia WHERE fecha IS NOT NULL AND fecha >= '$fecha_inicio_activo' AND fecha <= '$fecha_fin_activo' GROUP BY mes HAVING mes <> '0000-00' ORDER BY mes DESC";
    $horas_dia_minmax_mes_result = $conn->query($horas_dia_minmax_mes_query);
    echo '<div class="mb-5">';
    echo '<div class="mb-5 d-flex justify-content-center">';
    // Bloque de fechas mínimas y máximas por mes oculto visualmente (comentado)
    /*
    echo '<div style="width: 600px;">';
    echo '<h4 class="mb-3 text-center" style="color: #4C8AA3; font-weight: 600;">Fechas mínimas y máximas por mes</h4>';
    echo '<div class="table-responsive">';
    echo '<table class="resumen-table align-middle mb-0" style="background:#fff; width: 100%; max-width:600px; margin:auto; border-radius: 12px; overflow:hidden;">';
    echo '<thead><tr>';
    echo '<th>Mes</th>';
    echo '<th>Fecha Mínima</th>';
    echo '<th>Fecha Máxima</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    if ($horas_dia_minmax_mes_result && $horas_dia_minmax_mes_result->num_rows > 0) {
        while ($row = $horas_dia_minmax_mes_result->fetch_assoc()) {
            echo '<tr>';
            echo '<td style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">' . htmlspecialchars($row['mes']) . '</td>';
            echo '<td style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">' . htmlspecialchars($row['fecha_minima']) . '</td>';
            echo '<td style="text-align: center; padding: 14px 15px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">' . htmlspecialchars($row['fecha_maxima']) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="3" class="text-center" style="padding: 30px;">No hay fechas para mostrar.</td></tr>';
    }
    echo '</tbody>';
    echo '</table>';
    */
    echo '</div>';
    ?>

    <script>
        function toggleEmpleadoDetalle(id) {
            // Ocultar todos los detalles excepto el seleccionado
            document.querySelectorAll('.detalle-proyecto').forEach(function(row) {
                if (!row.classList.contains('detalle-empleado-' + id)) {
                    row.style.display = 'none';
                }
            });
            // Reset icon para todos excepto el seleccionado
            document.querySelectorAll('.toggle-icon').forEach(function(icon) {
                if (icon.id !== 'toggle-icon-' + id) {
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                }
            });
            // Alternar el detalle del empleado seleccionado
            var rows = document.querySelectorAll('.detalle-empleado-' + id);
            var icon = document.getElementById('toggle-icon-' + id);
            var isOpen = rows.length > 0 && rows[0].style.display !== 'none';
            if (isOpen) {
                rows.forEach(function(row) { row.style.display = 'none'; });
                if (icon) {
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                }
            } else {
                rows.forEach(function(row) { row.style.display = ''; });
                if (icon) {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-down');
                }
            }
        }

        // Efecto hover mejorado
        document.addEventListener('DOMContentLoaded', function() {
            const empleadoRows = document.querySelectorAll('.empleado-row');
            empleadoRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('expanded')) {
                        // Si tiene aprobación cero, mantener el naranja pero más oscuro en hover
                        if (this.classList.contains('sin-aprobacion')) {
                            this.style.backgroundColor = '#ffd4a8';
                        } else {
                            this.style.backgroundColor = '#f0f7fd';
                        }
                    }
                });
                row.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('expanded')) {
                        // Restaurar el color original
                        if (this.classList.contains('sin-aprobacion')) {
                            this.style.backgroundColor = '#ffe4cc';
                        } else {
                            this.style.backgroundColor = '';
                        }
                    }
                });
            });
        });
    </script>

    <style>
        .tabla-empleados {
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .empleado-row {
            cursor: pointer;
            background: #fff;
            transition: all 0.2s ease;
        }
        
        .empleado-row:hover {
            background: #f0f7fd !important;
            box-shadow: 0 2px 8px rgba(76, 138, 163, 0.15);
        }
        
        .empleado-row.sin-aprobacion {
            background: #ffe4cc !important;
        }
        
        .empleado-row.sin-aprobacion:hover {
            background: #ffd4a8 !important;
            box-shadow: 0 2px 8px rgba(255, 138, 50, 0.2);
        }
        
        .empleado-row.expanded {
            background: #e8f4f8 !important;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .empleado-row.sin-aprobacion.expanded {
            background: #ffd4a8 !important;
        }
        
        .detalle-proyecto {
            transition: background 0.2s ease;
        }
        
        .detalle-proyecto:hover {
            background: #f5f9fc !important;
        }
        
        .detalle-proyecto.proyecto-sin-aprobacion:hover {
            background: #ffe8cc !important;
        }
        
        .tabla-empleados tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Mejorar visualización en móviles */
        @media (max-width: 768px) {
            .tabla-empleados th,
            .tabla-empleados td {
                font-size: 0.85rem !important;
                padding: 10px 8px !important;
            }
            
            .empleado-row td:nth-child(2),
            .empleado-row td:nth-child(3) {
                font-size: 0.9rem !important;
            }
        }
    </style>
    
    <!-- Modal para Ver Detalle -->
    <div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-scrollable" style="max-width: 90%;">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4C8AA3 0%, #17823d 100%); color: white;">
                    <h5 class="modal-title" id="detalleModalLabel">Detalle del Proyecto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="detalleContenido">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnCerrarModal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle (requerido para modales) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Función global para sincronizar los checkboxes de aprobación/rechazo en el modal
        function sincronizarCheckboxesAprobacion() {
            document.querySelectorAll('tbody tr').forEach(function(row) {
                var aprobado = row.querySelector('[data-campo="aprobado_coordinador"]');
                var rechazado = row.querySelector('[data-campo="rechazado_coordinador"]');
                var comentario = row.querySelector('[data-campo="comentario_coordinador"]');
                if (aprobado && rechazado) {
                    if (aprobado.checked) {
                        aprobado.disabled = true;
                        rechazado.checked = false;
                        rechazado.disabled = true;
                        if (comentario) comentario.disabled = true;
                    } else if (rechazado.checked) {
                        rechazado.disabled = true;
                        aprobado.checked = false;
                        aprobado.disabled = true;
                        if (comentario) comentario.disabled = true;
                    } else {
                        aprobado.disabled = false;
                        rechazado.disabled = false;
                        if (comentario) comentario.disabled = false;
                    }
                }
            });
        }

        // Inicializar tooltips de Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Manejar clic en botones "Ver Detalle"
        document.addEventListener('DOMContentLoaded', function() {

            const btnsVerDetalle = document.querySelectorAll('.btn-ver-detalle');
            const modalElement = document.getElementById('detalleModal');
            const modalTitle = document.getElementById('detalleModalLabel');
            const detalleContenido = document.getElementById('detalleContenido');
            // Mantener referencia a la fila que abrió el modal y claves
            let currentRowInList = null;
            let currentProyecto = null;
            let currentArea = null;

            // Delegación de eventos: permitir editar dentro del contenido cargado en el modal
            if (detalleContenido) {
                detalleContenido.addEventListener('change', function(e) {
                    // ...existing code...
                });
            }

            // Utilidades para recalcular y formatear valores dentro del modal
            function parseMoney(text) {
                if (typeof text !== 'string') return 0;
                const n = parseInt(text.replace(/[^\d-]/g, ''), 10);
                return isNaN(n) ? 0 : n;
            }
            function formatMoney(n) {
                try { return '$ ' + Math.round(n).toLocaleString('es-ES'); } catch { return '$ ' + Math.round(n); }
            }
            function recalcTotalesModal(root) {
                const tbody = root.querySelector('#tabla-imputaciones tbody');
                if (!tbody) return;
                let totalAsignado = 0;
                let totalAprobado = 0;
                tbody.querySelectorAll('tr').forEach(tr => {
                    const costoInp = tr.querySelector('input.editable-input[data-campo="tiempo_imputado_costo"]');
                    const aprobadoChk = tr.querySelector('input.editable-input[data-campo="aprobado_coordinador"]');
                    const costo = parseFloat(costoInp && costoInp.value ? costoInp.value : '0') || 0;
                    totalAsignado += costo;
                    if (aprobadoChk && aprobadoChk.checked) {
                        totalAprobado += costo;
                    }
                });

                // Actualizar UI
                const elTiempo = root.querySelector('#valor-tiempo-mes');
                const elAprobado = root.querySelector('#valor-aprobado-mes');
                const elPorcentaje = root.querySelector('#valor-porcentaje-aprobado');
                const elNuevo = root.querySelector('#valor-nuevo-costo-actual');
                const baseAcInp = root.querySelector('#base-ac');
                const baseAc = baseAcInp ? parseInt(baseAcInp.value || '0', 10) || 0 : 0;

                if (elTiempo) elTiempo.textContent = formatMoney(totalAsignado);
                if (elAprobado) elAprobado.textContent = formatMoney(totalAprobado);
                if (elPorcentaje) {
                    const pct = totalAsignado > 0 ? (totalAprobado / totalAsignado) * 100 : 0;
                    elPorcentaje.textContent = (Math.round(pct * 10) / 10).toLocaleString('es-ES') + '%';
                }
                if (elNuevo) elNuevo.textContent = formatMoney(baseAc + totalAprobado);

                // También refrescar la fila del listado principal si existe
                try { 
                    updateMainRowFromModal(totalAsignado, totalAprobado);
                    updateTotalsForTableFromRow();
                } catch (e) { /* noop */ }
            }

            function percentToColorJS(p) {
                if (p >= 80) return '#198754'; // success
                if (p >= 50) return '#ffc107'; // warning
                if (p > 0) return '#fd7e14';   // orange
                return '#dc3545';              // danger
            }

            function updateMainRowFromModal(totalAsignado, totalAprobado) {
                if (!currentRowInList) return;
                
                // Actualizar COSTO CARGADO MES
                const tdTiempo = currentRowInList.querySelector('td.text-secondary');
                if (tdTiempo) {
                    tdTiempo.textContent = formatMoney(totalAsignado);
                }
                
                // Actualizar COSTO APROBADO (oculto)
                const tdCostoAprobado = currentRowInList.querySelector('td.text-primary');
                if (tdCostoAprobado) {
                    tdCostoAprobado.textContent = formatMoney(totalAprobado);
                }
                
                // Calcular y actualizar % COSTO APROBADO MES
                const pct = totalAsignado > 0 ? (totalAprobado / totalAsignado) * 100 : 0;
                
                // Buscar la celda de porcentaje (tiene % y span)
                const allTds = currentRowInList.querySelectorAll('td');
                for (let td of allTds) {
                    const text = td.textContent || '';
                    if (text.includes('%') && td.querySelector('span')) {
                        const span = td.querySelector('span');
                        span.textContent = Math.round(pct) + '%';
                        span.style.color = percentToColorJS(pct);
                        span.style.fontWeight = '600';
                        break;
                    }
                }
                
                // Actualizar NUEVO COSTO ACTUAL
                const tdAc = currentRowInList.querySelector('td.text-info');
                const acRow = tdAc ? parseMoney(tdAc.textContent) : 0;
                const nuevoCosto = acRow + totalAprobado;

                // Recalcular y actualizar COSTO POR EJECUTAR (ETC) = BAC - NUEVO COSTO ACTUAL
                const tdBac = currentRowInList.querySelector('td.text-success');
                const bacRow = tdBac ? parseMoney(tdBac.textContent) : 0;
                const nuevoEtc = bacRow - nuevoCosto;
                const tdEtc = currentRowInList.querySelector('td.text-warning');
                if (tdEtc) {
                    tdEtc.textContent = formatMoney(nuevoEtc);
                }
                
                // Buscar siguiente celda después del porcentaje que sea texto verde
                let foundPct = false;
                for (let td of allTds) {
                    if (foundPct && !td.classList.contains('d-none')) {
                        td.textContent = formatMoney(nuevoCosto);
                        td.classList.add('text-success');
                        td.style.fontWeight = '600';
                        break;
                    }
                    if (td.textContent.includes('%')) {
                        foundPct = true;
                    }
                }
            }

            function headerIndexByText(table, headerText) {
                const ths = table.querySelectorAll('thead tr th');
                for (let i = 0; i < ths.length; i++) {
                    const t = (ths[i].textContent || '').trim().toUpperCase();
                    if (t === headerText.toUpperCase()) return i;
                }
                return -1;
            }

            function mapTotalsRowCells(table, totalsRow) {
                const ths = Array.from(table.querySelectorAll('thead tr th'));
                const tds = Array.from(totalsRow.querySelectorAll('td'));
                const map = new Map(); // headerIndex -> td
                let col = 0;
                for (const td of tds) {
                    const spanCols = td.colSpan && td.colSpan > 0 ? td.colSpan : 1;
                    for (let k = 0; k < spanCols; k++) {
                        if (col < ths.length) {
                            map.set(col, td);
                        }
                        col++;
                    }
                }
                return map;
            }

            function updateTotalsForTableFromRow() {
                if (!currentRowInList) return;
                const table = currentRowInList.closest('table');
                if (!table) return;
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (rows.length < 2) return;
                const totalsRow = rows[rows.length - 1];
                const totalsMap = mapTotalsRowCells(table, totalsRow);

                // Obtener índices de columnas relevantes
                const idxTiempo = headerIndexByText(table, 'COSTO CARGADO MES');
                const idxCostoAprobado = headerIndexByText(table, 'COSTO APROBADO');
                const idxPct = headerIndexByText(table, '% COSTO APROBADO MES');
                const idxNuevo = headerIndexByText(table, 'NUEVO COSTO ACTUAL');
                const idxEtc = headerIndexByText(table, 'COSTO POR EJECUTAR (ETC)');

                let sumTiempo = 0;
                let sumAprobado = 0;
                let sumNuevo = 0;
                let sumEtc = 0;

                // Sumar sobre filas de datos (todas menos la última que es totales)
                for (let i = 0; i < rows.length - 1; i++) {
                    const tds = rows[i].querySelectorAll('td');
                    if (idxTiempo >= 0 && tds[idxTiempo]) sumTiempo += parseMoney(tds[idxTiempo].textContent);
                    if (idxCostoAprobado >= 0 && tds[idxCostoAprobado]) sumAprobado += parseMoney(tds[idxCostoAprobado].textContent);
                    if (idxNuevo >= 0 && tds[idxNuevo]) sumNuevo += parseMoney(tds[idxNuevo].textContent);
                    if (idxEtc >= 0 && tds[idxEtc]) sumEtc += parseMoney(tds[idxEtc].textContent);
                }

                // Tiempo imputado total
                if (idxTiempo >= 0 && totalsMap.has(idxTiempo)) {
                    const td = totalsMap.get(idxTiempo);
                    td.textContent = formatMoney(sumTiempo);
                    td.classList.add('text-secondary');
                }
                // Costo aprobado total (oculto d-none pero lo actualizamos)
                if (idxCostoAprobado >= 0 && totalsMap.has(idxCostoAprobado)) {
                    const td = totalsMap.get(idxCostoAprobado);
                    td.textContent = formatMoney(sumAprobado);
                    td.classList.add('text-primary');
                    td.classList.add('d-none');
                }
                // Porcentaje total
                if (idxPct >= 0 && totalsMap.has(idxPct)) {
                    const td = totalsMap.get(idxPct);
                    const pctTotal = sumTiempo > 0 ? (sumAprobado / sumTiempo) * 100 : 0;
                    const span = td.querySelector('span') || td;
                    span.textContent = (Math.round(pctTotal)).toLocaleString('es-ES') + '%';
                    span.style.color = percentToColorJS(pctTotal);
                    span.style.fontWeight = '600';
                }
                // Nuevo costo actual total
                if (idxNuevo >= 0 && totalsMap.has(idxNuevo)) {
                    const td = totalsMap.get(idxNuevo);
                    td.textContent = formatMoney(sumNuevo);
                    td.classList.add('text-success');
                    td.style.fontWeight = '600';
                }
                // ETC total
                if (idxEtc >= 0 && totalsMap.has(idxEtc)) {
                    const td = totalsMap.get(idxEtc);
                    td.textContent = formatMoney(sumEtc);
                    td.classList.add('text-warning');
                }
            }

            // Agregar listener para recargar la página cuando se cierre el modal
            const btnCerrarModal = document.getElementById('btnCerrarModal');
            const btnCloseX = modalElement.querySelector('.btn-close');
            
            if (btnCerrarModal) {
                btnCerrarModal.addEventListener('click', function() {
                    window.location.reload();
                });
            }
            
            if (btnCloseX) {
                btnCloseX.addEventListener('click', function() {
                    window.location.reload();
                });
            }

            btnsVerDetalle.forEach(btn => {
                btn.addEventListener('click', function() {
                    const proyecto = this.dataset.proyecto;
                    const area = this.dataset.area;
                    const nombre = this.dataset.nombre;
                    currentRowInList = this.closest('tr');
                    currentProyecto = proyecto;
                    currentArea = area;

                    // Buscar el valor de nature_imputation en la fila (está en una columna oculta d-none)
                    let natureImputation = '';
                    let row = this.closest('tr');
                    if (row) {
                        // Buscar la celda con la clase d-none que contiene el valor
                        let tds = row.querySelectorAll('td.d-none');
                        if (tds.length > 0) {
                            // Buscar la celda que contiene 'PROYECTOS' o 'GASTO GENERAL' o el valor original
                            for (let td of tds) {
                                let val = (td.textContent || '').trim().toUpperCase();
                                if (val === 'PROYECTOS' || val === 'GASTO GENERAL' || val === 'AFFAIRE' || val === 'FRAIS GENERAUX DIVERS' || val === 'ABSENCE' || val === 'AUSENCIAS' || val === 'AUSENCIA') {
                                    natureImputation = val;
                                    break;
                                }
                            }
                        }
                    }

                    // Actualizar título del modal
                    modalTitle.textContent = `Detalle: ${nombre}`;

                    // Mostrar spinner
                    detalleContenido.innerHTML = `
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    `;

                    // Abrir modal
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();

                    // Decidir qué archivo cargar según el tipo
                    let url = '';
                    if (natureImputation === 'PROYECTOS' || natureImputation === 'AFFAIRE') {
                        url = `Aprobacion_lucca_Proyecto.php?proyecto=${encodeURIComponent(proyecto)}&area=${encodeURIComponent(area)}&nombre=${encodeURIComponent(nombre)}`;
                    } else if (natureImputation === 'GASTO GENERAL' || natureImputation === 'FRAIS GENERAUX DIVERS') {
                        url = `Aprobacion_lucca_Gasto_General.php?proyecto=${encodeURIComponent(proyecto)}&area=${encodeURIComponent(area)}&nombre=${encodeURIComponent(nombre)}`;
                    } else if (natureImputation === 'ABSENCE' || natureImputation === 'AUSENCIAS' || natureImputation === 'AUSENCIA') {
                        url = `Aprobacion_lucca_Proyecto.php?proyecto=${encodeURIComponent(proyecto)}&area=${encodeURIComponent(area)}&nombre=${encodeURIComponent(nombre)}&tipo=ausencia`;
                    } else {
                        // Por defecto, abrir el de proyectos
                        url = `Aprobacion_lucca_Proyecto.php?proyecto=${encodeURIComponent(proyecto)}&area=${encodeURIComponent(area)}&nombre=${encodeURIComponent(nombre)}`;
                    }

                    fetch(url)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.text();
                        })
                        .then(html => {
                            detalleContenido.innerHTML = html;
                            // Ejecutar scripts embebidos
                            const scripts = detalleContenido.querySelectorAll('script');
                            scripts.forEach(oldScript => { 
                                const newScript = document.createElement('script');
                                if (oldScript.src) {
                                    newScript.src = oldScript.src;
                                } else {
                                    newScript.textContent = oldScript.textContent;
                                }
                                document.body.appendChild(newScript);
                                oldScript.remove();
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            detalleContenido.innerHTML = `
                                <div class="alert alert-danger" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i> Error al cargar los datos: ${error.message}
                                </div>
                            `;
                        });
                });
            });

            // Escuchar mensajes del modal para actualizar en tiempo real
            window.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'actualizarTotales') {
                    const totalAsignado = parseFloat(event.data.tiempo_imputado) || 0;
                    const totalAprobado = parseFloat(event.data.costo_aprobado) || 0;
                    
                    if (currentRowInList) {
                        updateMainRowFromModal(totalAsignado, totalAprobado);
                        updateTotalsForTableFromRow();
                    }
                }
            });
        });
    </script>
    
    <!-- Script para ordenar tablas -->
    <script>
        function parseMoneyCosto(text) {
            if (!text) return 0;
            const cleaned = text.replace(/[^\d]/g, '');
            return parseInt(cleaned, 10) || 0;
        }

        function ordenarTablaProyectos(tipo) {
            const tabla = document.getElementById('tablaProyectos');
            const tbody = tabla.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Separar la última fila (totales)
            const filaTotal = rows[rows.length - 1];
            const filasData = rows.slice(0, -1);
            
            // Determinar índice de columnas
            const ths = Array.from(tabla.querySelectorAll('thead th'));
            let idxArea = -1;
            let idxTiempo = -1;
            
            for (let i = 0; i < ths.length; i++) {
                const texto = ths[i].textContent.trim().toUpperCase();
                if (texto === 'ÁREA FUNCIONAL') idxArea = i;
                if (texto === 'COSTO CARGADO MES') idxTiempo = i;
            }
            
            filasData.sort((a, b) => {
                const tdsA = a.querySelectorAll('td');
                const tdsB = b.querySelectorAll('td');
                
                if (tipo === 'area' && idxArea >= 0) {
                    const areaA = tdsA[idxArea] ? tdsA[idxArea].textContent.trim() : '';
                    const areaB = tdsB[idxArea] ? tdsB[idxArea].textContent.trim() : '';
                    return areaA.localeCompare(areaB);
                } else if (tipo === 'costo' && idxTiempo >= 0) {
                    const costoA = parseMoneyCosto(tdsA[idxTiempo] ? tdsA[idxTiempo].textContent : '0');
                    const costoB = parseMoneyCosto(tdsB[idxTiempo] ? tdsB[idxTiempo].textContent : '0');
                    return costoB - costoA; // Mayor a menor
                }
                return 0;
            });
            
            // Reconstruir tbody
            tbody.innerHTML = '';
            filasData.forEach(fila => tbody.appendChild(fila));
            tbody.appendChild(filaTotal);
        }

        function ordenarTablaGasto(tipo) {
            const tabla = document.getElementById('tablaGasto');
            const tbody = tabla.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Separar la última fila (totales)
            const filaTotal = rows[rows.length - 1];
            const filasData = rows.slice(0, -1);
            
            // Determinar índice de columnas
            const ths = Array.from(tabla.querySelectorAll('thead th'));
            let idxArea = -1;
            let idxTiempo = -1;
            
            for (let i = 0; i < ths.length; i++) {
                const texto = ths[i].textContent.trim().toUpperCase();
                if (texto === 'ÁREA FUNCIONAL') idxArea = i;
                if (texto === 'TIEMPO IMPUTADO COSTO') idxTiempo = i;
            }
            
            filasData.sort((a, b) => {
                const tdsA = a.querySelectorAll('td');
                const tdsB = b.querySelectorAll('td');
                
                if (tipo === 'area' && idxArea >= 0) {
                    const areaA = tdsA[idxArea] ? tdsA[idxArea].textContent.trim() : '';
                    const areaB = tdsB[idxArea] ? tdsB[idxArea].textContent.trim() : '';
                    return areaA.localeCompare(areaB);
                } else if (tipo === 'costo' && idxTiempo >= 0) {
                    const costoA = parseMoneyCosto(tdsA[idxTiempo] ? tdsA[idxTiempo].textContent : '0');
                    const costoB = parseMoneyCosto(tdsB[idxTiempo] ? tdsB[idxTiempo].textContent : '0');
                    return costoB - costoA; // Mayor a menor
                }
                return 0;
            });
            
            // Reconstruir tbody
            tbody.innerHTML = '';
            filasData.forEach(fila => tbody.appendChild(fila));
            tbody.appendChild(filaTotal);
        }
    </script>
    
    <!-- Gráficas eliminadas -->
</body>
<script>
// Hacer el menú transparente cuando se abre un modal de Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var observer = function(show) {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        if (show) {
            sidebar.classList.add('menu-transparent');
        } else {
            sidebar.classList.remove('menu-transparent');
        }
    };
    // Bootstrap 5: escuchar eventos globales de modal
    document.body.addEventListener('show.bs.modal', function() { observer(true); });
    document.body.addEventListener('hidden.bs.modal', function() { observer(false); });
});
</script>
</html>
