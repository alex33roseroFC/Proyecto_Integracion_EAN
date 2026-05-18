<?php
// resumen_proyectos.php
// Este archivo muestra un resumen ejecutivo por proyecto cargado

session_start();

// Cargar autoload si es necesario (para librerías externas)
require_once __DIR__ . '/vendor/autoload.php';

// Incluir configuración centralizada y conexión
require_once __DIR__ . '/include.php';
require_once __DIR__ . '/config.php';

// $conn ya está definido en config.php
if (!isset($conn) || !$conn) {
    die("Error conexión: No se pudo establecer la conexión a la base de datos.");
}
// Asegurar la codificación para evitar problemas con acentos/caracteres especiales
$conn->set_charset('utf8mb4');

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

// --- Filtros por GET: Área Funcional y Nombre Proyecto ---
$area_filter = isset($_GET['area_funcional']) ? $conn->real_escape_string($_GET['area_funcional']) : '';
$name_filter = isset($_GET['nombre_proyecto']) ? $conn->real_escape_string($_GET['nombre_proyecto']) : '';

// Si el rol es COORD o MIX2, filtrar automáticamente por el área funcional del usuario
if (($rol_usuario === 'COORD' || $rol_usuario === 'MIX2') && !empty($area_funcional)) {
    $area_filter = $area_funcional;
}

// Áreas permitidas para usuarios especiales MIX
$usuarios_areas_especiales = [
    'JGELVEZ' => ['Arquitectura y Urbanismo', 'Estructuras'],
    // Ejemplo: 'OTROUSER' => ['Área X', 'Área Y'],
];

// Obtener lista de áreas funcionales disponibles (para el select)
$areas = [];
if ($rol_usuario === 'MIX') {
    $identificador_usuario = strtoupper(trim($usuario_logueado));
    if (array_key_exists($identificador_usuario, $usuarios_areas_especiales)) {
        $areas = $usuarios_areas_especiales[$identificador_usuario];
    } else {
        $areas = ['BIM', 'Vías y Topografía'];
    }
} else {
    $areas_sql = "SELECT DISTINCT `ÁREA FUNCIONAL` AS area FROM gastos_personal";
    if (($rol_usuario === 'COORD' || $rol_usuario === 'MIX2') && !empty($area_funcional)) {
        $areas_sql .= " WHERE `ÁREA FUNCIONAL` = '" . $conn->real_escape_string($area_funcional) . "'";
    }
    $areas_sql .= " ORDER BY `ÁREA FUNCIONAL` ASC";
    $areas_res = $conn->query($areas_sql);
    if ($areas_res) {
        while ($a = $areas_res->fetch_assoc()) {
            $areas[] = $a['area'];
        }
    }
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
    (SELECT SUM(cv2.`acum_año_anterior`+cv2.`ene_25`+cv2.`feb_25`+cv2.`mar_25`+cv2.`abr_25`+cv2.`may_25`+cv2.`jun_25`+cv2.`jul_25`+cv2.`ago_25`+cv2.`sep_25`+cv2.`oct_25`+cv2.`nov_25`+cv2.`dic_25`)
     FROM costo_valorizado cv2
     WHERE cv2.CECO_CONEXION = gp.PROYECTO AND cv2.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`
     LIMIT 1
    ) AS total_valorizado_2025,
    (SELECT SUM(
        (`ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
        `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
        `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
        `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`) * `TARIFA COAN 2`)
    FROM gastos_personal gp2
    WHERE gp2.PROYECTO = gp.PROYECTO AND gp2.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`
    ) AS total_costo,
    COALESCE((
        SELECT SUM(hd.`tiempo_imputado_costo`)
        FROM horas_dia hd
        WHERE hd.`codigo_affaire` = gp.PROYECTO
          AND hd.`area_funcional` = gp.`ÁREA FUNCIONAL`
          AND hd.`Estado_Aprobacion` = 'Aprobado'
    ), 0) AS total_tiempo_imputado_costo_aprobado
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
LEFT JOIN avance_fisico_ejecutado_programado afep ON gp.PROYECTO = afep.PROYECTO AND gp.`ÁREA FUNCIONAL` = afep.AREA_FUNCIONAL
WHERE 1=1 ";


// Si el rol es SUPER, no aplicar ningún filtro (ver toda la información)
if ($rol_usuario === 'SUPER') {
    // No se agrega ningún filtro
} else if (($rol_usuario === 'COORD' || $rol_usuario === 'MIX2') && !empty($area_funcional)) {
    $sql .= " AND gp.`ÁREA FUNCIONAL` = '" . $conn->real_escape_string($area_funcional) . "' ";
} else if ($rol_usuario === 'MIX') {
    $identificador_usuario = strtoupper(trim($usuario_logueado));
    $areas_mix = array_key_exists($identificador_usuario, $usuarios_areas_especiales) ? $usuarios_areas_especiales[$identificador_usuario] : ['BIM', 'Vías y Topografía'];
    if ($area_filter !== '' && in_array($area_filter, $areas_mix)) {
        $sql .= " AND gp.`ÁREA FUNCIONAL` = '" . $conn->real_escape_string($area_filter) . "' ";
    } else {
        // Consolidado: mostrar solo las áreas permitidas
        $areas_sql_in = array_map(function($a) use ($conn) { return "'" . $conn->real_escape_string($a) . "'"; }, $areas_mix);
        $sql .= " AND gp.`ÁREA FUNCIONAL` IN (" . implode(",", $areas_sql_in) . ") ";
    }
} else {
    // Para otros roles, aplicar el filtro si se seleccionó área
    if ($area_filter !== '') {
        $sql .= " AND gp.`ÁREA FUNCIONAL` = '" . $area_filter . "' ";
    }
}

if ($name_filter !== '') {
    $sql .= " AND p.nombre_proyecto = '" . $name_filter . "' ";
}


$sql .= "GROUP BY gp.PROYECTO, p.nombre_proyecto, p.nature_imputation, gp.`ÁREA FUNCIONAL`\nORDER BY gp.PROYECTO ASC, gp.`ÁREA FUNCIONAL` ASC;";

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Ejecutivo de Proyectos</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Remix Icon CDN para íconos modernos -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <style>
                /* Mejorar títulos e íconos de los filtros */
                .form-label {
                    font-weight: 600;
                    color: #5a5a6e; /* Gris oscuro pastel */
                    font-size: 0.95rem;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
        body {
            background: linear-gradient(120deg, #f6fafd 0%, #eaf6f3 100%);
            min-height: 100vh;
        }
                .bubbles-background {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100vw;
                    height: 100vh;
                    z-index: -2;
                    overflow: hidden;
                    pointer-events: none;
                }
                .bubble {
                    position: absolute;
                    bottom: -120px;
                    left: var(--left);
                    width: calc(var(--size) * 1.3);
                    height: calc(var(--size) * 1.3);
                    background: rgba(91, 141, 184, 0.18); /* menos opacidad */
                    border-radius: 50%;
                    box-shadow: 0 8px 48px 0 rgba(91, 141, 184, 0.10), 0 0 32px 12px rgba(91, 141, 184, 0.07); /* menos notorio */
                    filter: blur(2.5px) brightness(1.05); /* menos brillo */
                    animation: bubbleUp var(--duration) linear infinite;
                    animation-delay: var(--delay);
                    z-index: 0;
                    transition: background 0.3s;
                }
                @keyframes bubbleUp {
                    0% {
                        transform: translateY(0) scale(1);
                        opacity: 0.7;
                    }
                    80% {
                        opacity: 0.8;
                    }
                    100% {
                        transform: translateY(-110vh) scale(1.12);
                        opacity: 0.1;
                    }
                }
        .container {
            max-width: 97% !important;
            margin: 0 auto;
            padding: 0 10px;
        }
        .main-content {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 32px 0 rgba(60,72,88,.10);
            padding: 2.2rem 2.2rem 1.5rem 2.2rem;
        }
        .top-cards .card, .card {
            border-radius: 1rem;
            box-shadow: 0 2px 15px 0 rgba(60,72,88,.08);
            border: none;
        }
        .summary-card h6, .assign-card h6 {
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        .summary-card .label, .assign-card .label {
            color:#2f6f36;
            font-weight:600;
            font-size:0.92rem;
        }
        .assign-card .label { color:#2f6f8f; }
        .value-box {
            background:#fff;
            padding:5px 10px;
            border-radius:4px;
            min-width:90px;
            text-align:right;
            display:inline-block;
            font-weight:600;
        }
        .small-row { gap:8px; }
        @media (max-width: 992px) {
            .main-content { padding: 1.2rem 0.5rem; }
            .value-box { min-width:80px; }
        }
        .table-responsive {
            overflow-x: auto;
            margin: 0 auto 2.5rem 0;
            width: 100%;
            padding-bottom: 1.5rem;
            position: relative;
            z-index: 2;
        }
        .resumen-table {
            border-collapse: separate !important;
            border-spacing: 0;
            background: #fff;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
            box-shadow: none;
        }
        .resumen-table th, .resumen-table td {
            border-right: 1px solid #e5e7eb !important;
            vertical-align: middle;
            padding: 7px 8px;
            width: 1%;
            white-space: nowrap;
            background-clip: padding-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .resumen-table th:last-child, .resumen-table td:last-child {
            border-right: none !important;
        }
        .resumen-table tbody tr {
            border-bottom: 1px solid #e5e7eb !important;
        }
        .resumen-table th {
            background: #495057;
            color: #fff;
            font-weight: 700;
            white-space: normal;
            text-align: center !important;
            vertical-align: middle;
            border: none;
            font-size: 0.98rem;
            letter-spacing:0.01em;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .resumen-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .resumen-table tbody tr:nth-child(odd) {
            background: #fff;
        }
        .resumen-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: none;
        }
        .resumen-table tbody tr:hover {
            background: #f1f5f9;
        }
        .resumen-table td {
            border: none;
            vertical-align: middle;
            font-size: 0.97rem;
            background: #fff;
            padding-top: 7px;
            padding-bottom: 7px;
            text-align: left;
            padding-left: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .resumen-table td.text-success,
        .resumen-table td.text-info,
        .resumen-table td.text-warning {
            text-align: center;
            padding-left: 6px;
            padding-right: 6px;
        }
        .resumen-table tbody td {
            color: #222 !important;
            font-weight: 400 !important;
        }
        .resumen-table tbody td .fw-bold {
            font-weight: 400 !important;
            color: inherit !important;
        }
        .project-name {
            color: #17823d !important;
            font-weight: 600 !important;
            font-size: 0.98rem;
        }
        .resumen-table tbody tr:last-child td {
            color: #222 !important;
            font-weight: 700 !important;
            border-top: 1.5px solid #17823d;
            background: #fff !important;
            font-size: 0.98rem;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 2px 15px 0 rgba(60,72,88,.08);
            border: none;
        }
        .table-responsive {
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2.5rem;
            margin-bottom: 2.5rem;
        }
        .resumen-table.nowrap {
            white-space: nowrap;
        }
        .resumen-table th {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* Botón limpiar filtros y selects */
        .btn-outline-secondary {
            border-radius: 8px;
            border-width: 2px;
            font-weight: 600;
            color: #4C8AA3;
            border-color: #4C8AA3;
            background: #fafdff;
            transition: background 0.18s, color 0.18s, border 0.18s;
        }
        .btn-outline-secondary:hover {
            background: #4C8AA3;
            color: #fff;
        }
        .form-select {
            border-radius: 8px;
            border: 1.5px solid #b0cbe6;
            font-size: 1rem;
            color: #2d2d2d;
            background: #fafdff;
            transition: border 0.18s, box-shadow 0.18s;
        }
        .form-select:focus {
            border-color: #4C8AA3;
            box-shadow: 0 0 0 2px #b0cbe6;
        }
        .select2-container--default .select2-selection--single {
            border-radius: 8px;
            border: 1.5px solid #b0cbe6;
            font-size: 1rem;
            color: #2d2d2d;
            background: #fafdff;
            height: 38px;
            padding: 4px 8px;
        }
        .select2-container--default .select2-selection--single:focus {
            border-color: #4C8AA3;
            box-shadow: 0 0 0 2px #b0cbe6;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #2d2d2d;
            line-height: 36px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .select2-dropdown {
            border-radius: 8px;
            border: 1.5px solid #b0cbe6;
        }
        /* Responsive tweaks */
        @media (max-width: 768px) {
            .main-content { padding: 0.5rem 0.2rem; }
            .row.mb-4.align-items-stretch { flex-direction: column; }
            .col-md-4, .col-md-8 { padding: 0 !important; }
            .col-md-4 { border-right: none !important; border-bottom: 2px solid #e0e0e0; margin-bottom: 1.5rem; }
            .col-md-8 { padding-top: 1.5rem !important; }
        }

                /* Líneas horizontales solo entre filas, no fuera de la tabla */
        .resumen-table thead th {
            border-bottom: 2px solid #b0cbe6 !important;
        }
        .resumen-table tbody td {
            border-bottom: 1px solid #d2e0ea !important;
        }
        .resumen-table tbody tr:last-child td {
            border-bottom: none !important;
        }
    </style>
</head>
<body>
        <!-- Fondo de burbujas animadas -->
        <div class="bubbles-background">
            <div class="bubble" style="--size:60px; --left:10%; --duration:18s; --delay:0s;"></div>
            <div class="bubble" style="--size:40px; --left:20%; --duration:12s; --delay:2s;"></div>
            <div class="bubble" style="--size:80px; --left:35%; --duration:22s; --delay:4s;"></div>
            <div class="bubble" style="--size:30px; --left:50%; --duration:10s; --delay:1s;"></div>
            <div class="bubble" style="--size:50px; --left:65%; --duration:16s; --delay:3s;"></div>
            <div class="bubble" style="--size:70px; --left:80%; --duration:20s; --delay:5s;"></div>
            <div class="bubble" style="--size:35px; --left:90%; --duration:14s; --delay:2.5s;"></div>
            <div class="bubble" style="--size:55px; --left:75%; --duration:17s; --delay:6s;"></div>
            <div class="bubble" style="--size:45px; --left:60%; --duration:13s; --delay:1.5s;"></div>
            <div class="bubble" style="--size:65px; --left:25%; --duration:19s; --delay:3.5s;"></div>
        </div>
        <?php include_once __DIR__ . '/menu.php'; ?>
    <div class="main-content container mt-3" style="margin-top: 1.2rem !important;">
    <div class="text-center mb-2">
        <h2 class="mb-2" style="color: #5a5a6e; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#5a5a6e" viewBox="0 0 24 24" style="margin-right: 8px;"><path d="M12 2a10 10 0 1 0 10 10h-8a2 2 0 0 1-2-2V2zm2 2.07V10h7.93A8.001 8.001 0 0 0 14 4.07z"/></svg>
            BALANCE PRESUPUESTO
        </h2>
    </div>
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div class="d-flex gap-2">
            <!-- Botón 'Regresar a inicio' oculto
            <?php if (isset($rol_usuario) && $rol_usuario === 'MIX2'): ?>
                <a href="puente2.php" class="btn btn-outline-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M15 8a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L2.707 7.5H14.5A.5.5 0 0 1 15 8z"/>
                    </svg>
                    Regresar a inicio
                </a>
            <?php endif; ?>
            -->
            <a href="Detalle_Presupuesto.php" class="btn btn-success" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-spreadsheet" viewBox="0 0 16 16">
                    <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                    <path d="M3 9h10v1H3zm0-3h10v1H3zm0 6h10v1H3z"/>
                </svg>
                Detalle Presupuesto
            </a>
            <a href="Capacidad_Instalada.php" class="btn btn-success" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-graph-up" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M0 0h1v15h15v1H0V0Zm14.817 3.113a.5.5 0 0 1 .07.704l-4.5 5.5a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61 4.15-5.073a.5.5 0 0 1 .704-.07Z"/>
                </svg>
                Capacidad Instalada
            </a>
            <a href="Aprobacion_Lucca.php" class="btn" style="background-color: #E1832F; border-color: #E1832F; color: #ffffff; display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;">
                    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M4.5 8.5 L7 11 L11.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Aprobación Lucca
            </a>
        </div>
        <div class="d-flex flex-column align-items-end">
            <!-- Botón 'Cerrar sesión' oculto
            <a href="logout.php" class="btn btn-danger mb-2" style="font-size: 0.9rem; padding: 4px 12px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                    <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                </svg>
                Cerrar sesión
            </a>
            -->
            <!--
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
            -->
        </div>
    </div>

    <?php
    // Rewind result set by fetching into an array so we can compute totals and render the table
    $rows = [];
    $sum_bac = 0.0; // presupuesto a terminación
    $sum_ac = 0.0;  // costo valorizado (actual)
    $sum_etc = 0.0; // costo por ejecutar
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $rows[] = $r;
            $ac_ajustado = (float)$r['total_valorizado_2025'] + (float)$r['total_tiempo_imputado_costo_aprobado'];
            // Si hay filtro de área funcional, sumar solo los que coincidan
            if ($area_filter !== '') {
                if ($r['ÁREA FUNCIONAL'] === $area_filter) {
                    $sum_bac += (float)$r['total_costo'];
                    $sum_ac += $ac_ajustado;
                    $sum_etc += ((float)$r['total_costo'] - $ac_ajustado);
                }
            } else {
                $sum_bac += (float)$r['total_costo'];
                $sum_ac += $ac_ajustado;
                $sum_etc += ((float)$r['total_costo'] - $ac_ajustado);
            }
        }
        // Ordenar $rows por área funcional (alfabéticamente)
        usort($rows, function($a, $b) {
            return strcmp($a['ÁREA FUNCIONAL'], $b['ÁREA FUNCIONAL']);
        });
    }
    // Ensure numeric values
    $sum_bac = (float)$sum_bac;
    $sum_ac = (float)$sum_ac;
    $sum_etc = (float)$sum_etc;
    ?>

    <div class="mb-4">
        <div class="card shadow-sm border-0" style="border-radius: 1rem; max-width: 1400px; margin: 0 auto;">
            <div class="card-body py-5 px-5">
                <form method="get">
                    <div class="d-flex flex-wrap gap-4 align-items-end justify-content-center" style="width:100%;">
                        <div style="flex:1 1 400px; min-width:260px; max-width:100%;">
                            <label class="form-label fw-semibold mb-2" for="area_funcional_select">
                                <i class="ri-group-fill" style="font-size: 1.6rem; color: #5a5a6e;"></i>
                                Área Funcional
                            </label>
                            <select id="area_funcional_select" name="area_funcional" class="form-select filtro-select" style="width:100%" <?php echo (($rol_usuario === 'COORD' || $rol_usuario === 'MIX2') ? 'disabled' : ''); ?> >
                                <?php
                                if ($rol_usuario !== 'COORD' && $rol_usuario !== 'MIX2') {
                                    echo '<option value=""'.($area_filter === '' ? ' selected' : '').'>-- Todas --</option>';
                                }
                                foreach($areas as $a) {
                                    $selected = '';
                                    if (($rol_usuario === 'COORD' || $rol_usuario === 'MIX2') && $a === $area_funcional) {
                                        $selected = 'selected';
                                    } else if ($a === $area_filter) {
                                        $selected = 'selected';
                                    }
                                    echo '<option value="'.htmlspecialchars($a).'" '.$selected.'>'.htmlspecialchars($a).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div style="flex:1 1 400px; min-width:260px; max-width:100%;">
                            <label class="form-label fw-semibold mb-2" for="nombre_proyecto_select">
                                <i class="ri-folder-open-fill" style="font-size: 1.6rem; color: #5a5a6e;"></i>
                                Nombre Proyecto
                            </label>
                            <select id="nombre_proyecto_select" name="nombre_proyecto" class="form-select filtro-select" style="width:100%">
                                <option value="">-- Todos --</option>
                                <?php 
                                $nombres_proyectos = [];
                                foreach ($rows as $row) {
                                    if (!empty($row['nombre_proyecto']) && !in_array($row['nombre_proyecto'], $nombres_proyectos)) {
                                        $nombres_proyectos[] = $row['nombre_proyecto'];
                                    }
                                }
                                sort($nombres_proyectos, SORT_NATURAL | SORT_FLAG_CASE);
                                foreach($nombres_proyectos as $np): ?>
                                    <option value="<?= htmlspecialchars($np) ?>" <?= (isset($name_filter) && $np === $name_filter) ? 'selected' : '' ?>><?= htmlspecialchars($np) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex:0 0 180px; min-width:160px; max-width:220px; display:flex; align-items:end; justify-content:center; margin-top:8px;">
                            <a href="Balance.php" class="btn px-4 py-2 w-100" style="border-radius: 8px; font-weight: 600; background:#5a5a6e; color:#fff; border:1.5px solid #5a5a6e; transition: background 0.18s, color 0.18s;">
                                <i class="ri-close-circle-line" style="font-size: 1.2rem; color: #fff; margin-right:5px; vertical-align:-3px;"></i>
                                Limpiar filtros
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <style>
        .btn[style*='background:#4C8AA3'] {
            background: #4C8AA3 !important;
            color: #fff !important;
        }
        .btn[style*='background:#4C8AA3']:hover, .btn[style*='background:#4C8AA3']:focus {
            background: #35607a !important;
            color: #fff !important;
        }
        .filtro-select {
            padding-left: 18px !important;
            padding-right: 10px !important;
            font-size: 1.08rem;
        }
        </style>
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
    </div>

    <!-- Resumen ejecutivo BAC, AC, ETC en cards independientes -->
    <?php
    // Calcular margen en pesos y porcentaje
    $margen_valor = $sum_bac - $sum_ac;
    $margen_pct = ($sum_bac > 0) ? ($margen_valor / $sum_bac) * 100 : 0;
    ?>

    <!-- Tarjetas compactas y modernas tipo Power BI -->
    <style>
    .dashboard-cards {
        display: flex;
        gap: 18px;
        justify-content: center;
        align-items: stretch;
        margin: 0 0 28px 0;
        flex-wrap: wrap;
    }
    .dashboard-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(60,60,60,0.07);
        padding: 18px 22px 14px 22px;
        min-width: 220px;
        max-width: 260px;
        flex: 1 1 220px;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: box-shadow 0.2s;
        border-top: 4px solid transparent;
        position: relative;
    }
    .dashboard-card:hover {
        box-shadow: 0 4px 20px rgba(60,60,60,0.13);
    }
    .dashboard-card .card-icon {
        font-size: 2.2rem;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
    }
    .dashboard-card .card-label {
        font-size: 1.05rem;
        color: #555;
        margin-bottom: 6px;
        text-align: center;
        font-weight: 500;
    }
    .dashboard-card .card-value {
        font-size: 1.35rem;
        font-weight: bold;
        color: #5a5a6e;
        margin-bottom: 2px;
        text-align: center;
    }
    .dashboard-card .card-percent {
        font-size: 1rem;
        color: #888;
        margin-left: 4px;
    }
    .dashboard-card.green { background: #eafaf1; }
    .dashboard-card.blue { background: #eaf3fa; }
    .dashboard-card.orange { background: #fff7e6; }
    .dashboard-card.purple { background: #f3edfa; }
    .dashboard-card.green { border-top-color: #17823d; }
    .dashboard-card.blue { border-top-color: #4C8AA3; }
    .dashboard-card.orange { border-top-color: #ffa500; }
    .dashboard-card.purple { border-top-color: #6f42c1; }
    .dashboard-card.green .card-icon { color: #17823d; }
    .dashboard-card.blue .card-icon { color: #4C8AA3; }
    .dashboard-card.orange .card-icon { color: #ffa500; }
    .dashboard-card.purple .card-icon { color: #6f42c1; }
    @media (max-width: 1100px) {
        .dashboard-cards { gap: 10px; }
        .dashboard-card { min-width: 180px; max-width: 100%; padding: 14px 10px; }
    }
    @media (max-width: 700px) {
        .dashboard-cards { flex-direction: column; align-items: stretch; }
        .dashboard-card { min-width: 0; width: 100%; margin-bottom: 10px; }
    }
    </style>
    <div class="dashboard-cards">
        <div class="dashboard-card green">
            <div class="card-icon">
                <i class="ri-briefcase-4-line" style="font-size: 2.2rem; color: #17823d;"></i>
            </div>
            <div class="card-label">Presupuesto a Terminación (BAC)</div>
            <div class="card-value">$ <?= number_format(round($sum_bac/1000000), 0, '', '.') ?> M</div>
        </div>
        <div class="dashboard-card blue">
            <div class="card-icon">
                <i class="ri-file-list-3-line" style="font-size: 2.2rem; color: #4C8AA3;"></i>
            </div>
            <div class="card-label">Costo Actual (AC)</div>
            <div class="card-value">$ <?= number_format(round($sum_ac/1000000), 0, '', '.') ?> M</div>
        </div>
        <div class="dashboard-card orange">
            <div class="card-icon">
                <i class="ri-calendar-check-line" style="font-size: 2.2rem; color: #ffa500;"></i>
            </div>
            <div class="card-label">Costo por Ejecutar (ETC)</div>
            <div class="card-value">$ <?= number_format(round($sum_etc/1000000), 0, '', '.') ?> M</div>
        </div>
        <div class="dashboard-card purple">
            <div class="card-icon">
                <i class="ri-pie-chart-2-line" style="font-size: 2.2rem; color: #6f42c1;"></i>
            </div>
            <div class="card-label">Margen</div>
            <div class="card-value">$ <?= number_format(round($margen_valor/1000000), 0, '', '.') ?> M <span class="card-percent"><?= number_format($margen_pct, 1) ?>%</span></div>
        </div>
    </div>

    <!-- Gráfica ocupando todo el ancho -->
    <div class="row mb-4 align-items-stretch">
        <div class="col-12">
            <div class="card p-3" style="border-radius:8px; width:100%; height:100%;">
                <h6 class="mb-2 text-center" style="font-weight:700; color:#5a5a6e; font-size:1.25rem; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <i class="ri-bar-chart-2-line" style="font-size:1.5rem; color:#5a5a6e;"></i>
                    <span style="font-weight:700; color:#5a5a6e;">COMPARATIVO POR PROYECTO</span>
                </h6>
                <div style="height:100%; min-height:480px; display:flex; align-items:center; overflow-y:auto; max-height:600px;">
                    <canvas id="balanceChart" style="width:100%; display:block;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php $has_area_column = in_array($rol_usuario, ['SUPER', 'ADMIN', 'DIR', 'MIX']); ?>
    <div class="table-responsive">
        <table class="table resumen-table align-middle text-center mb-0" style="background:#fff; width: 100%;">
                    <thead class="resumen-thead">
                        <tr>
                            <?php if ($has_area_column): ?>
                                <th>ÁREA FUNCIONAL</th>
                            <?php endif; ?>
                            <th>CECO</th>
                            <th>NOMBRE PROYECTO</th>
                            <!-- <th>NATURE IMPUTATION</th> -->
                            <th>CPI</th>
                            <th>SPI</th>
                            <th>PTO A TERMINACIÓN (BAC)</th>
                            <th>COSTO ACTUAL (AC)</th>
                            <!-- <th>COSTO TEORICO</th> -->
                            <th>COSTO POR EJECUTAR (ETC)</th>
                            <th>% AVANCE FÍSICO EJECUTADO</th>
                            <th>% AVANCE FÍSICO PROGRAMADO</th>
                            <th>% COSTO ASIGNADO EJECUTADO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php 
                            // Variables para totales
                            $total_bac_table = 0;
                            $total_ac_table = 0;
                            $total_costo_teorico = 0;
                            $total_etc_table = 0;
                            
                            foreach($rows as $row): 
    // Filtrar por área funcional si el filtro está activo
    if ($area_filter !== '' && $row['ÁREA FUNCIONAL'] !== $area_filter) {
        continue;
    }
                                // Si el rol es MIX, filtrar áreas según usuario
                                if ($rol_usuario === 'MIX') {
                                    if (strtoupper($usuario_logueado) === 'JGELVEZ') {
                                        if (!in_array($row['ÁREA FUNCIONAL'], ['Arquitectura y Urbanismo', 'Estructuras'])) {
                                            continue;
                                        }
                                    } else {
                                        if (!in_array($row['ÁREA FUNCIONAL'], ['BIM', 'Vías y Topografía'])) {
                                            continue;
                                        }
                                    }
                                }
                                $costo_teorico = 0;
                                $pct_ejecutado = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'];
                                $ac_ajustado_fila = (float)$row['total_valorizado_2025'] + (float)$row['total_tiempo_imputado_costo_aprobado'];
                                if ((float)$row['total_costo'] > 0) {
                                    $costo_teorico = ($pct_ejecutado / 100.0) * (float)$row['total_costo'];
                                }
                                
                                // Acumular totales
                                $total_bac_table += (float)$row['total_costo'];
                                $total_ac_table += $ac_ajustado_fila;
                                $total_costo_teorico += $costo_teorico;
                                $total_etc_table += ((float)$row['total_costo'] - $ac_ajustado_fila);
                            ?>
                                <tr>
                                    <?php if ($has_area_column): ?>
                                    <td>
                                        <?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '-') ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="project-name"><?= htmlspecialchars($row['PROYECTO']) ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['nombre_proyecto'] ?? '-') ?>
                                    </td>
                                    <!-- <td>
                                        <?= htmlspecialchars($row['nature_imputation'] ?? '-') ?>
                                    </td> -->
                                    <td class="text-center" style="background-color: <?php 
                                        $cpi = 0;
                                        $ac = $ac_ajustado_fila; // Costo Actual (AC)
                                        if ($ac > 0) {
                                            $cpi = $costo_teorico / $ac;
                                        }
                                        if ($cpi == 0) echo '#f8f9fa';
                                        else if ($cpi >= 1) echo '#4cd964';  // Verde pastel intenso
                                        else echo '#ff7675';  // Rojo pastel intenso
                                    ?>; color: #fff !important; font-weight: bold; padding: 5px;">
                                        <?php echo number_format($cpi, 2); ?>
                                    </td>
                                    <td class="text-center" style="background-color: <?php 
                                        $spi = 0;
                                        if ((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'] > 0) {
                                            $spi = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'] / (float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'];
                                        }
                                        if ($spi == 0) echo '#f8f9fa';
                                        else if ($spi >= 1) echo '#4cd964';  // Verde pastel intenso
                                        else echo '#ff7675';  // Rojo pastel intenso
                                    ?>; color: #fff !important; font-weight: bold; padding: 5px;">
                                        <?php echo number_format($spi, 2); ?>
                                    </td>
                                    <td class="text-success">$ <?= number_format((float)$row['total_costo'], 0, '', '.') ?></td>
                                    <td class="text-info">$ <?= number_format($ac_ajustado_fila, 0, '', '.') ?></td>
                                    <!-- <td class="text-secondary">$ <?= number_format($costo_teorico, 0, '', '.') ?></td> -->
                                    <td class="text-warning">$ <?= number_format((float)$row['total_costo'] - $ac_ajustado_fila, 0, '', '.') ?></td>
                                    <td><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'], 0) ?>%</td>
                                    <td><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'], 0) ?>%</td>
                                    <td><?php 
                                        $porcentaje_coan = 0;
                                        if ((float)$row['total_costo'] > 0) {
                                            $porcentaje_coan = ($ac_ajustado_fila / (float)$row['total_costo']) * 100;
                                        }
                                        echo number_format($porcentaje_coan, 0) . '%';
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Fila de totales -->
                            <tr style="background: #f1f3f6; color: #222; font-weight: bold;">
                                <?php if ($has_area_column): ?>
                                    <td colspan="3" style="text-align: right; padding: 10px;">TOTALES:</td>
                                <?php else: ?>
                                    <td colspan="2" style="text-align: right; padding: 10px;">TOTALES:</td>
                                <?php endif; ?>
                                <td class="text-center">-</td> <!-- CPI total -->
                                <td class="text-center">-</td> <!-- SPI total -->
                                <td class="text-center">$ <?= number_format($total_bac_table, 0, '', '.') ?></td>
                                <td class="text-center">$ <?= number_format($total_ac_table, 0, '', '.') ?></td>
                                <td class="text-center">$ <?= number_format($total_etc_table, 0, '', '.') ?></td>
                                <td colspan="4"></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="<?= $has_area_column ? 11 : 10 ?>" class="text-center">No hay datos para mostrar.</td></tr>
                        <?php endif; ?>
                    <!-- Estilos duplicados eliminados para evitar sobreescribir el diseño moderno -->
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script>
        (function(){
            // Pass PHP data into JS
            var projectData = <?= json_encode(array_map(function($r) {
                return [
                    'proyecto' => $r['PROYECTO'],
                    'nombre' => $r['nombre_proyecto'] ?? $r['PROYECTO'],
                    'area' => $r['ÁREA FUNCIONAL'],
                    'bac' => (float)$r['total_costo'],
                    'ac' => ((float)$r['total_valorizado_2025'] + (float)$r['total_tiempo_imputado_costo_aprobado'])
                ];
            }, $rows)) ?>;

            // Check if there's an area filter active
            var areaFilter = <?= json_encode($area_filter) ?>;
            
            // Aggregate by project if no area filter is active
            var aggregatedData = {};
            
            if (!areaFilter || areaFilter === '') {
                // Group by project (sum all areas)
                projectData.forEach(function(item) {
                    var key = item.proyecto;
                    if (!aggregatedData[key]) {
                        aggregatedData[key] = {
                            proyecto: item.proyecto,
                            nombre: item.nombre,
                            bac: 0,
                            ac: 0
                        };
                    }
                    aggregatedData[key].bac += item.bac;
                    aggregatedData[key].ac += item.ac;
                });
            } else {
                // Filtrar solo los datos que coincidan con el área seleccionada
                projectData.forEach(function(item) {
                    if (item.area === areaFilter) {
                        var key = item.proyecto + '_' + item.area;
                        aggregatedData[key] = {
                            proyecto: item.proyecto,
                            nombre: item.nombre,
                            area: item.area,
                            bac: item.bac,
                            ac: item.ac
                        };
                    }
                });
            }

            // Prepare labels and datasets
            var labels = [];
            var bacData = [];
            var acData = [];

            Object.values(aggregatedData).forEach(function(item) {
                // Etiqueta solo con el nombre del proyecto
                var label = item.nombre.substring(0, 35) + (item.nombre.length > 35 ? '...' : '');
                labels.push(label);
                // Convertir a millones
                bacData.push(+(item.bac / 1000000).toFixed(2));
                acData.push(+(item.ac / 1000000).toFixed(2));
            });

            var ctx = document.getElementById('balanceChart');
            if (!ctx) return;

            // Destroy previous chart instance if exists (safe reload)
            if (ctx._chartInstance) { ctx._chartInstance.destroy(); }

            // Calculate appropriate height based on number of items
            var itemCount = labels.length;
            var minHeight = 700; // Más espacio mínimo
            var heightPerItem = 75; // Más espacio por barra
            var legendSpace = 80; // Más espacio para la leyenda
            var calculatedHeight = Math.max(minHeight, itemCount * heightPerItem) + legendSpace;
            // Mejorar calidad visual usando devicePixelRatio
            var dpr = window.devicePixelRatio || 1;
            ctx.width = ctx.offsetWidth * dpr;
            ctx.height = calculatedHeight * dpr;
            ctx.style.width = ctx.offsetWidth + 'px';
            ctx.style.height = calculatedHeight + 'px';

            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'BAC (Presupuesto)',
                            data: bacData,
                            backgroundColor: 'rgba(76, 138, 163, 0.90)', // azul intenso
                            borderColor: 'rgba(76, 138, 163, 1)',
                            borderWidth: 2,
                            borderRadius: 2,
                            barPercentage: 0.8,
                        },
                        {
                            label: 'AC (Costo Actual)',
                            data: acData,
                            backgroundColor: 'rgba(23, 130, 61, 0.85)', // verde intenso
                            borderColor: 'rgba(23, 130, 61, 1)',
                            borderWidth: 2,
                            borderRadius: 2,
                            barPercentage: 0.8,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    animation: {
                        duration: 1200,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Comparativo Presupuestal por Proyecto',
                            font: {
                                size: 22,
                                weight: 'bold',
                                family: 'Segoe UI, Arial, sans-serif'
                            },
                            color: '#4C8AA3',
                            padding: {bottom: 8}
                        },
                        subtitle: {
                            display: true,
                            text: 'Comparación entre presupuesto (BAC) y costo actual (AC) de cada proyecto',
                            font: {
                                size: 15,
                                family: 'Segoe UI, Arial, sans-serif'
                            },
                            color: '#17823d',
                            padding: {bottom: 18}
                        },
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'center',
                            labels: {
                                font: {
                                    size: 16,
                                    family: 'Segoe UI, Arial, sans-serif',
                                    lineHeight: 1.2
                                },
                                color: '#2d2d2d',
                                usePointStyle: true,
                                pointStyle: 'rect',
                                padding: 20,
                                boxWidth: 20,
                                boxHeight: 20,
                                borderRadius: 0,
                                // Fondo blanco y borde gris para cada ítem de leyenda
                                generateLabels: function(chart) {
                                    const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                    const labels = original(chart);
                                    return labels.map(label => ({
                                        ...label,
                                        fillStyle: label.fillStyle,
                                        strokeStyle: '#b0b0b0',
                                        lineWidth: 1.5,
                                        borderRadius: 0
                                    }));
                                }
                            }
                        },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(255,255,255,0.97)',
                            borderColor: '#4C8AA3',
                            borderWidth: 1.5,
                            titleColor: '#17823d',
                            bodyColor: '#2d2d2d',
                            bodyFont: { size: 15 },
                            padding: 14,
                            displayColors: true,
                            callbacks: {
                                title: function(context) {
                                    return context[0].label;
                                },
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    label += '$' + context.parsed.x.toLocaleString('es-CO') + ' millones';
                                    return label;
                                },
                                afterLabel: function(context) {
                                    if (context.dataset.label === 'BAC (Presupuesto)') {
                                        return 'Presupuesto total asignado al proyecto.';
                                    } else if (context.dataset.label === 'AC (Costo Actual)') {
                                        return 'Costo acumulado ejecutado hasta la fecha.';
                                    }
                                    return '';
                                }
                            }
                        },
                        datalabels: {
                            display: false
                        }
                    },
                    layout: {
                        padding: {
                            left: 10,
                            right: 10,
                            top: 10,
                            bottom: 20
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: { 
                                display: true, 
                                text: 'Millones de Pesos',
                                font: {
                                    size: 15,
                                    family: 'Segoe UI, Arial, sans-serif'
                                },
                                color: '#17823d'
                            },
                            grid: {
                                color: '#e0e0e0',
                                borderColor: '#b0b0b0',
                                borderWidth: 1
                            },
                            ticks: {
                                font: {
                                    size: 13,
                                    family: 'Segoe UI, Arial, sans-serif'
                                },
                                color: '#2d2d2d',
                                callback: function(value) {
                                    return '$' + value.toLocaleString('es-CO');
                                }
                            }
                        },
                        y: {
                            ticks: {
                                font: {
                                    size: 13,
                                    family: 'Segoe UI, Arial, sans-serif'
                                },
                                color: '#2d2d2d',
                                autoSkip: false,
                                maxRotation: 0,
                                minRotation: 0,
                                padding: 10
                            },
                            grid: {
                                color: '#e0e0e0',
                                borderColor: '#b0b0b0',
                                borderWidth: 1
                            }
                        }
                    }
                },
                plugins: [window.ChartDataLabels]
            });

            // store reference
            ctx._chartInstance = chart;
        })();
    </script>
</div>
</body>
</html>