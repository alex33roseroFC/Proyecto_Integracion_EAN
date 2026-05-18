<?php
// resumen_proyectos.php
// Este archivo muestra un resumen ejecutivo por proyecto cargado

require_once __DIR__ . '/vendor/autoload.php';

// Iniciar sesión antes de enviar salida y verificar login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}



// Incluir el menú principal
include 'menu.php';
echo '<div class="main-content">';


// Incluir archivos de configuración y conexión centralizada

// Validar existencia de include.php y config.php
if (!file_exists('include.php')) {
    die("Error: No se encuentra el archivo include.php.");
}
if (!file_exists('config.php')) {
    die("Error: No se encuentra el archivo config.php.");
}
require_once 'include.php';
require_once 'config.php';
// $conn debe estar definido en config.php
if (!isset($conn) || !$conn) {
    die("Error: No se pudo establecer la conexión a la base de datos.");
}
if (!function_exists('get_logged_user_context')) {
    function get_logged_user_context($mysqli, $usuario, $matricula = '') {
        $context = [
            'usuario' => trim((string)$usuario),
            'matricula' => trim((string)$matricula),
            'nombre_usuario' => '',
            'area_funcional' => '',
            'rol' => '',
            'found' => false,
        ];

        if (!($mysqli instanceof mysqli) || $mysqli->connect_error) {
            return $context;
        }

        $userQueries = [];
        if ($context['usuario'] !== '') {
            $userQueries[] = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE Usuario = ? LIMIT 1";
            $userQueries[] = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE TRIM(Usuario) = TRIM(?) LIMIT 1";
            $userQueries[] = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE UPPER(TRIM(Usuario)) = UPPER(TRIM(?)) LIMIT 1";
        }

        foreach ($userQueries as $sqlUser) {
            $stmtUser = $mysqli->prepare($sqlUser);
            if (!$stmtUser) {
                continue;
            }

            $stmtUser->bind_param('s', $context['usuario']);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $rowUser = $resultUser ? $resultUser->fetch_assoc() : null;
            $stmtUser->close();

            if (!$rowUser) {
                continue;
            }

            $context['found'] = true;
            $context['usuario'] = isset($rowUser['Usuario']) ? trim((string)$rowUser['Usuario']) : $context['usuario'];
            if ($context['matricula'] === '' && isset($rowUser['Matricula'])) {
                $context['matricula'] = trim((string)$rowUser['Matricula']);
            }
            $context['nombre_usuario'] = isset($rowUser['Nombre_Usuario']) ? trim((string)$rowUser['Nombre_Usuario']) : '';
            $context['area_funcional'] = isset($rowUser['area_funcional']) ? trim((string)$rowUser['area_funcional']) : '';
            $context['rol'] = isset($rowUser['ROL']) ? trim((string)$rowUser['ROL']) : '';
            break;
        }

        if (!$context['found'] && $context['matricula'] !== '') {
            $sqlMatricula = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE TRIM(Matricula) = TRIM(?) LIMIT 1";
            $stmtMatricula = $mysqli->prepare($sqlMatricula);
            if ($stmtMatricula) {
                $stmtMatricula->bind_param('s', $context['matricula']);
                $stmtMatricula->execute();
                $resultMatricula = $stmtMatricula->get_result();
                $rowMatricula = $resultMatricula ? $resultMatricula->fetch_assoc() : null;
                $stmtMatricula->close();

                if ($rowMatricula) {
                    $context['found'] = true;
                    $context['usuario'] = isset($rowMatricula['Usuario']) ? trim((string)$rowMatricula['Usuario']) : $context['usuario'];
                    $context['matricula'] = isset($rowMatricula['Matricula']) ? trim((string)$rowMatricula['Matricula']) : $context['matricula'];
                    $context['nombre_usuario'] = isset($rowMatricula['Nombre_Usuario']) ? trim((string)$rowMatricula['Nombre_Usuario']) : '';
                    $context['area_funcional'] = isset($rowMatricula['area_funcional']) ? trim((string)$rowMatricula['area_funcional']) : '';
                    $context['rol'] = isset($rowMatricula['ROL']) ? trim((string)$rowMatricula['ROL']) : '';
                }
            }
        }

        return $context;
    }
}
if (!function_exists('resolve_existing_table_name')) {
    function resolve_existing_table_name($mysqli, $candidates) {
        if (!($mysqli instanceof mysqli) || $mysqli->connect_error) {
            return is_array($candidates) && isset($candidates[0]) ? $candidates[0] : '';
        }

        if (!is_array($candidates)) {
            $candidates = [$candidates];
        }

        $dbName = '';
        $dbResult = $mysqli->query('SELECT DATABASE() AS db_name');
        if ($dbResult) {
            $dbRow = $dbResult->fetch_assoc();
            $dbName = isset($dbRow['db_name']) ? trim((string)$dbRow['db_name']) : '';
            $dbResult->free();
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') {
                continue;
            }

            if ($dbName !== '') {
                $sqlCheck = "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                $stmtCheck = $mysqli->prepare($sqlCheck);
                if ($stmtCheck) {
                    $stmtCheck->bind_param('ss', $dbName, $candidate);
                    $stmtCheck->execute();
                    $checkResult = $stmtCheck->get_result();
                    $checkRow = $checkResult ? $checkResult->fetch_assoc() : null;
                    $stmtCheck->close();

                    if ($checkRow && !empty($checkRow['cnt'])) {
                        return $candidate;
                    }
                }
            }

            $escapedCandidate = $mysqli->real_escape_string($candidate);
            $showResult = $mysqli->query("SHOW TABLES LIKE '" . $escapedCandidate . "'");
            if ($showResult) {
                $exists = $showResult->num_rows > 0;
                $showResult->free();
                if ($exists) {
                    return $candidate;
                }
            }
        }

        return isset($candidates[0]) ? $candidates[0] : '';
    }
}
// Asegurar la codificación para evitar problemas con acentos/caracteres especiales
$conn->set_charset('utf8mb4');
$usuario_logueado = isset($_SESSION['usuario']) ? trim((string)$_SESSION['usuario']) : '';
// Obtener Área_Funcional, Nombre_Usuario y ROL del usuario logueado desde la tabla login_usuarios

$area_funcional = '';
$nombre_usuario = '';
$rol_usuario = isset($_SESSION['rol']) ? trim((string)$_SESSION['rol']) : '';
$matricula_usuario = isset($_SESSION['matricula']) ? trim((string)$_SESSION['matricula']) : '';
$logged_user_context = get_logged_user_context($conn, $usuario_logueado, $matricula_usuario);
$tabla_asignacion_resuelta = resolve_existing_table_name($conn, ['asignación', 'asignacion']);
if (!empty($logged_user_context['area_funcional'])) {
    $area_funcional = $logged_user_context['area_funcional'];
}
if (!empty($logged_user_context['nombre_usuario'])) {
    $nombre_usuario = $logged_user_context['nombre_usuario'];
}
if (!empty($logged_user_context['rol'])) {
    $rol_usuario = $logged_user_context['rol'];
}
if (!empty($logged_user_context['matricula'])) {
    $matricula_usuario = $logged_user_context['matricula'];
}
if (!$logged_user_context['found'] && $usuario_logueado !== '') {
    error_log('Colaborador.php: no se encontro login_usuarios para usuario de sesion [' . $usuario_logueado . '] con matricula [' . $matricula_usuario . ']');
}

// --- Filtros por GET: Área Funcional y Nombre Proyecto ---
$area_filter = isset($_GET['area_funcional']) ? $conn->real_escape_string($_GET['area_funcional']) : '';
$name_filter = isset($_GET['nombre_proyecto']) ? $conn->real_escape_string($_GET['nombre_proyecto']) : '';

// Si el rol es COORD o MIX2, filtrar automáticamente por el área funcional del usuario
if (($rol_usuario === 'COORD' || $rol_usuario === 'MIX2') && !empty($area_funcional)) {
    $area_filter = $area_funcional;
}

// Obtener lista de áreas funcionales disponibles (para el select)
$areas = [];
$areas_sql = "SELECT DISTINCT `ÁREA FUNCIONAL` AS area FROM gastos_personal";
// Si el ROL es COORD o MIX2, solo mostrar el área funcional del usuario
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
    ) AS total_costo
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
LEFT JOIN avance_fisico_ejecutado_programado afep ON gp.PROYECTO = afep.PROYECTO AND gp.`ÁREA FUNCIONAL` = afep.AREA_FUNCIONAL
WHERE 1=1 ";


// Si el rol es SUPER, no aplicar ningún filtro (ver toda la información)
if ($rol_usuario === 'SUPER') {
    // No se agrega ningún filtro
} else if (($rol_usuario === 'COORD' || $rol_usuario === 'MIX2') && !empty($area_funcional)) {
    $sql .= " AND gp.`ÁREA FUNCIONAL` = '" . $conn->real_escape_string($area_funcional) . "' ";
} else {
    // Si no es COORD ni MIX2 ni SUPER, aplicar el filtro solo si se seleccionó específicamente
    if ($area_filter !== '') {
        $sql .= " AND gp.`ÁREA FUNCIONAL` = '" . $area_filter . "' ";
    }
}

if ($name_filter !== '') {
    $sql .= " AND p.nombre_proyecto = '" . $name_filter . "' ";
}


$sql .= "GROUP BY gp.PROYECTO, p.nombre_proyecto, p.nature_imputation, gp.`ÁREA FUNCIONAL`\nORDER BY gp.PROYECTO ASC, gp.`ÁREA FUNCIONAL` ASC;";

$result = $conn->query($sql);
if ($result === false) {
    error_log('Colaborador.php: fallo consulta resumen colaborador: ' . $conn->error);
}
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
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
                        /* Encabezados tabla de aprobación */
                        .tabla-empleados thead th, .tabla-empleados thead tr th {
                            background-color: #17823d !important;
                            color: #fff !important;
                        }
                /* Encabezados tabla de asignación */
                #asignacion .table thead th, #asignacion .table thead tr th {
                    background-color: #4C8AA3 !important;
                    color: #fff !important;
                }
        body {
            overflow-x: hidden !important;
        }

        /* --- Multipage Tabs Custom --- */
        .nav-tabs .nav-link {
            background-color: #e9ecef !important; /* gris claro */
            color: #333 !important;
            border: 1px solid #dee2e6 !important;
            border-bottom: none !important;
            margin-right: 2px;
            transition: background 0.2s;
        }
        .nav-tabs .nav-link.active {
            background-color: #17823d !important; /* verde */
            color: #fff !important;
            border-color: #17823d #17823d #fff #17823d !important;
            font-weight: bold;
        }
        .tab-content > .tab-pane {
            display: none;
            background: #f8f9fa !important; /* fondo gris claro para el contenido */
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 24px 18px 18px 18px;
        }
        .tab-content > .tab-pane.show.active {
            display: block;
        }
        .table-responsive {
            max-width: 100vw !important;
            overflow-x: auto;
        }
        table.table {
            width: 100% !important;
            min-width: unset !important;
            max-width: 100% !important;
        }
    </style>
        <body>
            <div style="width:100%; display:flex; align-items:center; justify-content:flex-start; margin-top:10px; margin-bottom:20px;">
                <img src="logofza2.png" alt="Logo FZA" style="max-width:220px; height:auto; margin-left:30px; display:none;">
            </div>
    <title>Resumen Ejecutivo de Proyectos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilo específico para la tabla de aprobación */
        .tabla-empleados th, .tabla-empleados td {
            padding: 6px 8px !important;
            font-size: 0.97rem;
            vertical-align: middle;
            word-break: normal;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .tabla-empleados th {
            background: #5A8CA7 !important;
            color: #fff !important;
            font-weight: 600;
            text-align: center;
        }
        .tabla-empleados td {
            text-align: center;
        }
        .tabla-empleados tr {
            border-bottom: 1px solid #e0e0e0;
        }
        .tabla-empleados tbody tr:nth-child(even) {
            background: #f7fafc;
        }
        .tabla-empleados tbody tr:nth-child(odd) {
            background: #fff;
        }
        .tabla-empleados td:first-child, .tabla-empleados th:first-child {
            width: 36px;
            min-width: 36px;
            max-width: 36px;
        }
        @media (max-width: 900px) {
            .tabla-empleados th, .tabla-empleados td {
                font-size: 0.90rem;
                padding: 4px 4px !important;
            }
        }
        html, body, .container, .container-fluid {
            font-family: 'Segoe UI', 'Arial', 'sans-serif' !important;
        }
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
            color: #fff;
            font-weight: 600;
            white-space: normal;
            text-align: center;
        }
        .resumen-table tbody tr:nth-child(even) {
            background: #f4f7fb;
        }
        .resumen-table tbody tr:nth-child(odd) {
            background: #fff;
        }
        /* Ajustes específicos para columnas */
        .resumen-table th:nth-child(2),
        .resumen-table td:nth-child(2) {
            min-width: 200px; /* Columna nombre proyecto */
        }
        .resumen-table th:nth-child(8),
        .resumen-table td:nth-child(8),
        .resumen-table th:nth-child(9),
        .resumen-table td:nth-child(9) {
            min-width: 120px; /* Columnas de porcentajes */
        }
    </style>
</head>


        <!-- Botón de cerrar sesión -->
        <div style="position: absolute; top: 18px; right: 32px; z-index: 1000;">
            <a href="logout.php" class="btn btn-outline-danger" style="font-weight:500; border-radius: 8px; padding: 7px 18px; font-size: 1rem;">
                Cerrar sesión
            </a>
        </div>

        <!-- Información de usuario -->

        <div style="background:#fff; border-radius:18px; box-shadow:0 2px 16px #e0e0e0; padding:36px 32px 32px 32px; margin-bottom: 32px;">
            <!-- Título centrado encima del multipage -->
            <div class="row mb-2">
                <div class="col-12 text-center">
                    <h1 style="color: #17823D; font-weight: 800; font-size: 2.2rem; display: inline-block; padding: 0.2em 1.2em; border-radius: 0.2em; letter-spacing: 1px; margin-top: 38px; background: none;">MODULO COLABORADOR</h1>
                </div>
            </div>
            <!-- Consulta rápida para saber si existen horas rechazadas (debe ir antes del multipage para el icono)-->
            <?php
            $sql_rechazadas = "SELECT COUNT(*) AS total_rechazadas FROM horas_dia WHERE numero_empleado = '" . $conn->real_escape_string($matricula_usuario) . "' AND rechazado_coordinador = 1 ";
            $res_rechazadas = $conn->query($sql_rechazadas);
            $tiene_rechazadas = false;
            if ($res_rechazadas && $row_rech = $res_rechazadas->fetch_assoc()) {
                $tiene_rechazadas = ((int)$row_rech['total_rechazadas'] > 0);
            }
            ?>
            <!-- Menú multipage Bootstrap centrado y angosto -->
            <div class="d-flex justify-content-center mb-4">
                <div style="position:relative; width:900px; margin:0 auto;">
                <?php if (isset($tiene_rechazadas) && $tiene_rechazadas): ?>
                    <span class="notificacion-rechazo" id="iconoAdvertenciaFlotante" title="Existen horas rechazadas; revisa tu hoja de tiempo;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </span>
                    <style>
                        .notificacion-rechazo {
                            position: absolute;
                            top: -30px;
                            right: 18px;
                            z-index: 11000;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .notificacion-rechazo i {
                            color: #e74c3c;
                            font-size: 2.2em;
                            transform: none;
                            filter: drop-shadow(0 1px 2px #0005);
                        }
                    </style>
                    <script>
                        // Oculta el icono flotante cuando el modal está abierto
                        document.addEventListener('DOMContentLoaded', function() {
                            var icono = document.getElementById('iconoAdvertenciaFlotante');
                            var modal = document.getElementById('modalHorasDia');
                            if (icono && modal) {
                                modal.addEventListener('show.bs.modal', function() {
                                    icono.style.display = 'none';
                                });
                                modal.addEventListener('hidden.bs.modal', function() {
                                    icono.style.display = 'flex';
                                });
                            }
                        });
                    </script>
                <?php endif; ?>
                <ul class="nav nav-tabs" id="colabTabs" role="tablist" style="width: 100%; z-index:1;">
                    <li class="nav-item" role="presentation" style="flex:1;">
                        <button class="nav-link active w-100" id="asignacion-tab" data-bs-toggle="tab" data-bs-target="#asignacion" type="button" role="tab" aria-controls="asignacion" aria-selected="true" onclick="
                            document.querySelectorAll('#colabTabs .nav-link').forEach(function(btn){
                                btn.classList.remove('active');
                                btn.setAttribute('aria-selected','false');
                            });
                            document.querySelectorAll('#colabTabsContent .tab-pane').forEach(function(pane){
                                pane.classList.remove('show','active');
                            });
                            this.classList.add('active');
                            this.setAttribute('aria-selected','true');
                            var asignacion = document.getElementById('asignacion');
                            if (asignacion) { asignacion.classList.add('show','active'); }
                            history.replaceState(null,'','#asignacion');
                            return false;">ASIGNACIÓN</button>
                    </li>
                    <li class="nav-item" role="presentation" style="flex:1; position:relative;">
                        <button class="nav-link w-100" id="aprobacion-tab" data-bs-toggle="tab" data-bs-target="#aprobacion" type="button" role="tab" aria-controls="aprobacion" aria-selected="false" onclick="
                            document.querySelectorAll('#colabTabs .nav-link').forEach(function(btn){
                                btn.classList.remove('active');
                                btn.setAttribute('aria-selected','false');
                            });
                            document.querySelectorAll('#colabTabsContent .tab-pane').forEach(function(pane){
                                pane.classList.remove('show','active');
                            });
                            this.classList.add('active');
                            this.setAttribute('aria-selected','true');
                            var aprobacion = document.getElementById('aprobacion');
                            if (aprobacion) { aprobacion.classList.add('show','active'); }
                            history.replaceState(null,'','#aprobacion');
                            return false;">
                            APROBACIÓN
                        </button>
                    </li>
                </ul>
                </div>
            </div>
            <div class="tab-content" id="colabTabsContent" style="max-width: 1200px; margin: 0 auto;">
            <div class="tab-pane fade show active" id="asignacion" role="tabpanel" aria-labelledby="asignacion-tab">

                <div class="text-center mb-4" style="margin-top: 40px;">
                    <h2 class="mb-4" style="color: #4C8AA3; font-weight: 700;">ASIGNACIÓN DE PERSONAL</h2>
                </div>

                <!-- TABLA CAPACIDAD INSTALADA POR EMPLEADO OCULTA -->
                <div style="display:none">
                <!-- ...tabla de capacidad instalada oculta... -->
                <?php
                $sql_capacidad = "SELECT matricula, nom, prenom, fecha_ingreso, fechas_retiro, horas_diarias, coordinador_area, cat_coan, tarifa_coan, ene_2025, feb_2025, mar_2025, abr_2025, may_2025, jun_2025, jul_2025, ago_2025, sep_2025, oct_2025, nov_2025, dic_2025, ene_2026, feb_2026, mar_2026, abr_2026, may_2026, jun_2026, jul_2026, ago_2026, sep_2026 FROM capacidad_instalada_por_cada_empleado WHERE matricula = '" . $conn->real_escape_string($matricula_usuario) . "'";
                $result_capacidad = false;
                try {
                    $result_capacidad = $conn->query($sql_capacidad);
                } catch (Throwable $e) {
                    error_log('Colaborador.php: fallo consulta capacidad para matricula [' . $matricula_usuario . ']: ' . $e->getMessage());
                }
                ?>
                </div>

                    <!-- TABLA DE ASIGNACIÓN -->
                    <?php
                    // Consulta para traer la tabla de asignación (ajusta el nombre de la tabla si es necesario)
                    $sql_asignacion = "SELECT ID, matricula, Nombre_Empleado_Completo, centro_costos, nombre_proyecto,
                        Nov_2025, Dic_2025,
                        Ene_2026, Feb_2026, Mar_2026, Abr_2026, May_2026, Jun_2026, Jul_2026, Ago_2026, Sep_2026, Oct_2026, Nov_2026, Dic_2026,
                        Ene_2027, Feb_2027, Mar_2027, Abr_2027, May_2027, Jun_2027, Jul_2027, Ago_2027, Sep_2027, Oct_2027, Nov_2027, Dic_2027,
                        Ene_2028, Feb_2028, Mar_2028, Abr_2028, May_2028, Jun_2028, Jul_2028, Ago_2028, Sep_2028, Oct_2028, Nov_2028, Dic_2028,
                        Ene_2029, Feb_2029, Mar_2029, Abr_2029, May_2029, Jun_2029, Jul_2029, Ago_2029, Sep_2029, Oct_2029, Nov_2029, Dic_2029,
                        Ene_2030, Feb_2030, Mar_2030, Abr_2030, May_2030, Jun_2030, Jul_2030, Ago_2030, Sep_2030, Oct_2030, Nov_2030, Dic_2030
                        FROM `" . $conn->real_escape_string($tabla_asignacion_resuelta) . "`
                        WHERE matricula = '" . $conn->real_escape_string($matricula_usuario) . "'";
                    $result_asignacion = false;
                    try {
                        $result_asignacion = $conn->query($sql_asignacion);
                    } catch (Throwable $e) {
                        error_log('Colaborador.php: fallo consulta asignacion en tabla [' . $tabla_asignacion_resuelta . '] para matricula [' . $matricula_usuario . ']: ' . $e->getMessage());
                    }
                    if ($result_asignacion === false) {
                        error_log('Colaborador.php: consulta asignacion sin resultados ejecutables en tabla [' . $tabla_asignacion_resuelta . '] para matricula [' . $matricula_usuario . ']');
                    }

                    // Detectar columnas de meses con al menos un valor distinto de 0.00
                    // Solo meses de 2026
                    $meses = [
                        'Ene_2026','Feb_2026','Mar_2026','Abr_2026','May_2026','Jun_2026','Jul_2026','Ago_2026','Sep_2026','Oct_2026','Nov_2026','Dic_2026',
                    ];
                    $asignacion_rows = [];
                    $meses_con_valor = array_fill_keys($meses, false);
                    if ($result_asignacion && $result_asignacion->num_rows > 0) {
                        while($row = $result_asignacion->fetch_assoc()) {
                            $asignacion_rows[] = $row;
                            foreach ($meses as $mes) {
                                if (isset($row[$mes]) && floatval($row[$mes]) != 0.0) {
                                    $meses_con_valor[$mes] = true;
                                }
                            }
                        }
                    }
                    $meses_a_mostrar = array_keys(array_filter($meses_con_valor));
                    $assignment_month_to_sql_date = function($columnName) {
                        $parts = explode('_', (string)$columnName);
                        $monthMap = [
                            'Ene' => '01', 'Feb' => '02', 'Mar' => '03', 'Abr' => '04',
                            'May' => '05', 'Jun' => '06', 'Jul' => '07', 'Ago' => '08',
                            'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dic' => '12'
                        ];
                        $month = $parts[0] ?? '';
                        $year = $parts[1] ?? '';
                        if ($month === '' || $year === '' || !isset($monthMap[$month])) {
                            return '';
                        }
                        return $year . '-' . $monthMap[$month] . '-01';
                    };
                    $assignment_comments_map = [];
                    if ($matricula_usuario !== '' && $conn instanceof mysqli && !$conn->connect_error) {
                        $sql_assignment_comments = "SELECT numero_de_empleado, codigo_affaire, fecha, comentario FROM comentarios_asignacion WHERE numero_de_empleado = ?";
                        if ($stmt_assignment_comments = $conn->prepare($sql_assignment_comments)) {
                            $stmt_assignment_comments->bind_param('s', $matricula_usuario);
                            if ($stmt_assignment_comments->execute()) {
                                $res_assignment_comments = $stmt_assignment_comments->get_result();
                                while ($comment_row = $res_assignment_comments->fetch_assoc()) {
                                    $comment_employee = trim((string)($comment_row['numero_de_empleado'] ?? ''));
                                    $comment_affaire = strtoupper(trim((string)($comment_row['codigo_affaire'] ?? '')));
                                    $comment_date = trim((string)($comment_row['fecha'] ?? ''));
                                    if ($comment_employee === '' || $comment_affaire === '' || $comment_date === '') {
                                        continue;
                                    }
                                    $comment_key = $comment_employee . '|' . $comment_affaire . '|' . $comment_date;
                                    $assignment_comments_map[$comment_key] = [
                                        'comentario' => (string)($comment_row['comentario'] ?? '')
                                    ];
                                }
                                $res_assignment_comments->free();
                            }
                            $stmt_assignment_comments->close();
                        }
                    }
                    // Calcular totales por mes para porcentajes
                    $totales_mes = array_fill_keys($meses_a_mostrar, 0.0);
                    foreach ($asignacion_rows as $row) {
                        foreach ($meses_a_mostrar as $mes) {
                            $totales_mes[$mes] += floatval($row[$mes]);
                        }
                    }
                    ?>
                    <style>
                        .collab-assignment-cell {
                            position: relative;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            min-width: 84px;
                            padding: 0.2rem 1.15rem 0.2rem 0.2rem;
                        }
                        .collab-assignment-value {
                            display: inline-block;
                            width: 100%;
                            text-align: center;
                            font-weight: 600;
                        }
                        .collab-assignment-comment-trigger {
                            position: absolute;
                            top: 50%;
                            right: 0.2rem;
                            width: 18px;
                            height: 18px;
                            border: 0;
                            border-radius: 999px;
                            background: rgba(255, 255, 255, 0.95);
                            color: #17823d;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            opacity: 0;
                            transform: translateY(-50%);
                            transition: opacity 0.18s ease, background 0.18s ease, color 0.18s ease;
                            cursor: pointer;
                            z-index: 3;
                            box-shadow: 0 0 0 1px rgba(23, 130, 61, 0.15);
                        }
                        .collab-assignment-cell:hover .collab-assignment-comment-trigger,
                        .collab-assignment-cell:focus-within .collab-assignment-comment-trigger {
                            opacity: 1;
                        }
                        .collab-assignment-comment-trigger:hover {
                            background: #17823d;
                            color: #fff;
                        }
                        .collab-assignment-corner {
                            position: absolute;
                            top: 0;
                            right: 0;
                            width: 0;
                            height: 0;
                            border-top: 14px solid #17823d;
                            border-left: 14px solid transparent;
                            border-right: none;
                            pointer-events: none;
                            z-index: 2;
                        }
                        #colaborador-assignment-comment-modal .modal-header {
                            background: linear-gradient(135deg, #4C8AA3 0%, #17823d 100%);
                            color: #fff;
                        }
                        #colaborador-assignment-comment-modal .comment-meta {
                            background: #f6faf8;
                            border: 1px solid #d5e9dc;
                            border-radius: 10px;
                            padding: 0.9rem 1rem;
                            margin-bottom: 1rem;
                        }
                        #colaborador-assignment-comment-modal .comment-meta-label {
                            font-size: 0.78rem;
                            font-weight: 700;
                            color: #5f6b76;
                            text-transform: uppercase;
                            letter-spacing: 0.04em;
                        }
                        #colaborador-assignment-comment-modal .comment-meta-value {
                            font-size: 0.95rem;
                            font-weight: 600;
                            color: #23313d;
                        }
                    </style>
                    <h5 style="color:#4C8AA3; font-weight:600; text-align:left; margin-bottom:12px;">HORAS ASIGNADAS POR PROYECTO</h5>
                    <div class="table-responsive" style="margin-top: 30px; max-width: 100vw; overflow-x: auto;">
                        <table class="table table-bordered table-striped align-middle" style="background:#fff; min-width: 900px; max-width: 100%;">
                            <thead class="table-primary">
                                <tr>
                                    <!-- <th>ID</th> -->
                                    <!-- <th>NÚMERO EMPLEADO</th> -->
                                    <th>CECO</th>
                                    <th>NOMBRE PROYECTO</th>
                                    <?php foreach($meses_a_mostrar as $mes): ?>
                                        <th><?= htmlspecialchars(str_replace('_', ' ', $mes)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($asignacion_rows) > 0): ?>
                                    <?php foreach($asignacion_rows as $row): ?>
                                        <?php
                                            // Verificar si el proyecto tiene al menos una hora asignada en algún mes
                                            $tiene_horas = false;
                                            foreach($meses_a_mostrar as $mes) {
                                                if (isset($row[$mes]) && floatval($row[$mes]) != 0.0) {
                                                    $tiene_horas = true;
                                                    break;
                                                }
                                            }
                                            if (!$tiene_horas) continue;
                                        ?>
                                        <tr>
                                            <!-- <td><?= htmlspecialchars($row['ID']) ?></td> -->
                                            <!-- <td><?= htmlspecialchars($row['matricula']) ?></td> -->
                                            <td><?= htmlspecialchars($row['centro_costos']) ?></td>
                                            <td><?= htmlspecialchars($row['nombre_proyecto']) ?></td>
                                            <?php foreach($meses_a_mostrar as $mes): ?>
                                                <?php 
                                                    $valor = isset($row[$mes]) && $row[$mes] !== null && $row[$mes] !== '' ? (float)$row[$mes] : 0.0;
                                                    // Sin color de fondo para valores > 0
                                                    // Texto verde solo si hay valor
                                                    $style = ($valor != 0.0) ? 'color: #17823d; font-weight: bold;' : '';
                                                    $comment_date = $assignment_month_to_sql_date($mes);
                                                    $comment_key = trim((string)$matricula_usuario) . '|' . strtoupper(trim((string)($row['centro_costos'] ?? ''))) . '|' . $comment_date;
                                                    $has_comment = isset($assignment_comments_map[$comment_key]) && trim((string)($assignment_comments_map[$comment_key]['comentario'] ?? '')) !== '';
                                                    echo '<td style="' . $style . '">';
                                                    echo '<div class="collab-assignment-cell">';
                                                    echo '<span class="collab-assignment-value">' . ($valor == 0.0 ? '' : number_format($valor, 2)) . '</span>';
                                                    if ($has_comment) {
                                                        echo '<span class="collab-assignment-corner"></span>';
                                                        echo '<button type="button" class="collab-assignment-comment-trigger" onclick="openColabAssignmentCommentModal(this); return false;"'
                                                            . ' data-numero-empleado="' . htmlspecialchars($matricula_usuario) . '"'
                                                            . ' data-nom="' . htmlspecialchars((string)($logged_user_context['nombre_usuario'] ?? $nombre_usuario)) . '"'
                                                            . ' data-prenom=""'
                                                            . ' data-full-name="' . htmlspecialchars((string)($logged_user_context['nombre_usuario'] ?? $nombre_usuario)) . '"'
                                                            . ' data-codigo-affaire="' . htmlspecialchars((string)($row['centro_costos'] ?? '')) . '"'
                                                            . ' data-project-name="' . htmlspecialchars((string)($row['nombre_proyecto'] ?? '')) . '"'
                                                            . ' data-month-column="' . htmlspecialchars($mes) . '"'
                                                            . ' aria-label="Ver comentario">'
                                                            . '<i class="bi bi-chat-left-text"></i></button>';
                                                    }
                                                    echo '</div>';
                                                    echo '</td>';
                                                ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- Fila de totales -->
                                    <tr style="font-weight:bold; background:#e3e3e3;">
                                        <td colspan="2" class="text-end">TOTAL</td>
                                        <?php foreach($meses_a_mostrar as $mes): ?>
                                            <?php 
                                                $valor = isset($totales_mes[$mes]) && $totales_mes[$mes] !== null && $totales_mes[$mes] !== '' ? (float)$totales_mes[$mes] : 0.0;
                                                echo '<td>' . ($valor == 0.0 ? '0.00' : number_format($valor, 2)) . '</td>';
                                            ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="<?= 4 + count($meses_a_mostrar) ?>" class="text-center">No hay datos de asignación para mostrar.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- TABLA DE PORCENTAJE DE HORAS POR CECO SOLO EN ASIGNACIÓN -->
                    <h5 style="color:#4C8AA3; font-weight:600; text-align:left; margin-bottom:12px;">% ASIGNADO POR PROYECTO</h5>
                    <div class="table-responsive" style="margin-top: 20px; max-width: 100vw; overflow-x: auto;">
                        <table class="table table-bordered table-striped align-middle" style="background:#fff; min-width: 900px; max-width: 100%;">
                            <thead class="table-primary">
                                <tr>
                                    <!-- <th>NÚMERO EMPLEADO</th> -->
                                    <th>CECO</th>
                                    <th>NOMBRE PROYECTO</th>
                                    <?php foreach($meses_a_mostrar as $mes): ?>
                                        <th><?= htmlspecialchars(str_replace('_', ' ', $mes)) ?> (%)</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Definir las horas del mes de la imagen adjunta (enero-diciembre 2026)
                                $horas_mes_2026 = [
                                    'Ene_2026' => 175,
                                    'Feb_2026' => 176,
                                    'Mar_2026' => 185,
                                    'Abr_2026' => 177,
                                    'May_2026' => 167,
                                    'Jun_2026' => 167,
                                    'Jul_2026' => 185,
                                    'Ago_2026' => 160,
                                    'Sep_2026' => 185,
                                    'Oct_2026' => 176,
                                    'Nov_2026' => 160,
                                    'Dic_2026' => 177
                                ];
                                // Calcular totales de porcentaje por mes
                                $totales_porcentaje = array_fill_keys($meses_a_mostrar, 0.0);
                                ?>
                                <?php if (count($asignacion_rows) > 0): ?>
                                    <?php foreach($asignacion_rows as $row): ?>
                                        <tr>
                                            <!-- <td><?= htmlspecialchars($row['matricula']) ?></td> -->
                                            <td><?= htmlspecialchars($row['centro_costos']) ?></td>
                                            <td><?= htmlspecialchars($row['nombre_proyecto']) ?></td>
                                            <?php foreach($meses_a_mostrar as $mes): ?>
                                                <?php
                                                    $horas_mes = isset($horas_mes_2026[$mes]) ? $horas_mes_2026[$mes] : 0;
                                                    $valor = floatval($row[$mes]);
                                                    $porc = ($horas_mes > 0) ? ($valor / $horas_mes) * 100 : 0;
                                                    $totales_porcentaje[$mes] += $porc;
                                                    // Si el porcentaje es 0, mostrar celda en blanco (sin color ni texto)
                                                    if ($porc == 0) {
                                                        // Mostrar celda en blanco si el porcentaje es 0
                                                        echo '<td style="background:#fff; color:#222; font-weight:400;"></td>';
                                                    } else {
                                                        // Escala de colores pastel
                                                        if ($porc <= 50) {
                                                            $bg = '#FFF9C4'; // amarillo pastel suave
                                                        } elseif ($porc <= 80) {
                                                            $bg = '#FFE082'; // amarillo intenso
                                                        } elseif ($porc < 100) {
                                                            $bg = '#B7E6C9'; // verde claro pastel
                                                        } elseif ($porc == 100) {
                                                            $bg = '#7ed957'; // verde intenso pastel
                                                        } else {
                                                            $bg = '#f7b2b7'; // rojo pastel
                                                        }
                                                        echo '<td style="background:' . $bg . '; font-weight:600;">' . ($horas_mes > 0 ? number_format($porc, 1) . '%' : '-') . '</td>';
                                                    }
                                                ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- Fila de totales para porcentaje (suma real de porcentajes) -->
                                    <tr style="font-weight:bold; background:#e3e3e3;">
                                        <td colspan="2" class="text-end">TOTAL</td>
                                        <?php foreach($meses_a_mostrar as $mes): ?>
                                            <td><?= $totales_porcentaje[$mes] > 0 ? number_format($totales_porcentaje[$mes], 1) . '%' : '-' ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="<?= 4 + count($meses_a_mostrar) ?>" class="text-center">No hay datos de asignación para mostrar.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="modal fade" id="colaborador-assignment-comment-modal" tabindex="-1" aria-labelledby="colaborador-assignment-comment-modal-label" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="colaborador-assignment-comment-modal-label">Comentario de Asignación</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="comment-meta">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="comment-meta-label">Colaborador</div>
                                                <div class="comment-meta-value" id="colab-assignment-comment-employee">-</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="comment-meta-label">Proyecto</div>
                                                <div class="comment-meta-value" id="colab-assignment-comment-project">-</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="comment-meta-label">Centro de costo</div>
                                                <div class="comment-meta-value" id="colab-assignment-comment-affaire">-</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="comment-meta-label">Fecha</div>
                                                <div class="comment-meta-value" id="colab-assignment-comment-date">-</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="colab-assignment-comment-text" class="form-label fw-semibold">Comentario</label>
                                        <textarea id="colab-assignment-comment-text" class="form-control" rows="5" placeholder="Escriba el comentario para esta asignación..."></textarea>
                                    </div>
                                    <div id="colab-assignment-comment-feedback" class="small text-muted"></div>
                                </div>
                                <div class="modal-footer justify-content-end gap-2">
                                    <button type="button" class="btn btn-danger" id="colab-assignment-comment-delete-btn">Eliminar comentario</button>
                                    <button type="button" class="btn btn-success" id="colab-assignment-comment-save-btn" style="background-color: #17823d; border-color: #17823d;">Guardar comentario</button>
                                </div>
                            </div>
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
            $sum_bac += (float)$r['total_costo'];
            $sum_ac += (float)$r['total_valorizado_2025'];
            $sum_etc += ((float)$r['total_costo'] - (float)$r['total_valorizado_2025']);
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

        <!-- ...existing code... -->
      </div>
            <div class="tab-pane fade" id="aprobacion" role="tabpanel" aria-labelledby="aprobacion-tab">
                                        <!-- Aviso informativo con botón para abrir el modal -->
<?php
// Consulta rápida para saber si existen horas rechazadas
$sql_rechazadas = "SELECT COUNT(*) AS total_rechazadas FROM horas_dia WHERE numero_empleado = '" . $conn->real_escape_string($matricula_usuario) . "' AND rechazado_coordinador = 1 ";
$res_rechazadas = $conn->query($sql_rechazadas);
$tiene_rechazadas = false;
if ($res_rechazadas && $row_rech = $res_rechazadas->fetch_assoc()) {
    $tiene_rechazadas = ((int)$row_rech['total_rechazadas'] > 0);
}
?>
<div class="alert d-flex align-items-center justify-content-between" style="margin-bottom: 18px; border-radius: 9px; box-shadow: 0 2px 8px rgba(44,62,80,0.06); <?php echo $tiene_rechazadas ? 'background:#ff8a8a;border-color:#d35454;' : 'background:#eaf7f0;border-color:#b7e6c9;'; ?>; padding: 10px 18px 10px 14px; min-height: 48px;">
    <div style="display: flex; align-items: center; gap: 10px; width:100%; min-height:38px;">
        <?php if ($tiene_rechazadas): ?>
            <span style="font-size: 1.08rem; font-weight: 500; color:#fff; display:flex; align-items:center; gap:10px;">
                Horas rechazadas en tu hoja de tiempo. Revisa el detalle de horas cargadas.
            </span>
        <?php else: ?>
            <span style="font-size: 1.3rem; color: #17823D; margin-right: 6px;">
                <i class="bi bi-info-circle-fill"></i>
            </span>
            <span style="font-size: 1rem; font-weight: 500; color:#17823D;">
                Detalle de horas cargadas.
            </span>
        <?php endif; ?>
    </div>
    <button class="btn" style="font-weight: 500; border-radius: 8px; padding: 4px 14px; font-size:0.97rem; background:#fff; border:1.2px solid #bdbdbd; color:#444; box-shadow:0 1px 4px #0001; transition:background 0.2s;" onclick="mostrarModalHorasDia()" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='#fff'">Ver detalle</button>
</div>

                                        <!-- Modal Horas Día -->
                                        <div class="modal fade" id="modalHorasDia" tabindex="-1" aria-labelledby="modalHorasDiaLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:1300px;">
                                                <div class="modal-content" style="max-width: 1200px; margin: auto; border-radius: 14px;">
                                                    <div class="modal-header" style="background: #17823D; color: #fff;">
                                                        <h5 class="modal-title" id="modalHorasDiaLabel">Detalle de horas cargadas</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                    </div>
                                                    <div class="modal-body" style="background: #f8f9fa;">
                                                                                                                <!-- Filtros Proyecto y Estado -->
                                                                                                                <form id="filtrosHorasDia" class="row g-2 mb-3" style="align-items: end;">
                                                                                                                    <div class="col-md-6 col-12">
                                                                                                                        <label for="filtroProyecto" class="form-label mb-1" style="font-weight:500; color:#17823D;">Proyecto</label>
                                                                                                                        <select class="form-select" id="filtroProyecto">
                                                                                                                            <option value="">Todos</option>
                                                                                                                        </select>
                                                                                                                    </div>
                                                                                                                    <div class="col-md-3 col-6">
                                                                                                                        <label for="filtroEstado" class="form-label mb-1" style="font-weight:500; color:#17823D;">Estado</label>
                                                                                                                        <select class="form-select" id="filtroEstado">
                                                                                                                            <option value="">Todos</option>
                                                                                                                            <option value="Aceptado">Aceptado</option>
                                                                                                                            <option value="Rechazado">Rechazado</option>
                                                                                                                        </select>
                                                                                                                    </div>
                                                                                                                </form>
                                                                                                                <script>
                                                                                                                document.addEventListener('DOMContentLoaded', function() {
                                                                                                                    var selectProyecto = document.getElementById('filtroProyecto');
                                                                                                                    var selectEstado = document.getElementById('filtroEstado');
                                                                                                                    var tabla = document.querySelector('#modalHorasDia table');
                                                                                                                    // Llenar select de proyectos únicos
                                                                                                                    var proyectosSet = new Set();
                                                                                                                    var filas = tabla.querySelectorAll('tbody tr');
                                                                                                                    // Buscar el índice de la columna "Nombre Proyecto"
                                                                                                                    var ths = tabla.querySelectorAll('thead th');
                                                                                                                    var idxNombreProyecto = -1;
                                                                                                                    ths.forEach(function(th, idx) {
                                                                                                                        if (th.textContent.trim() === 'Nombre Proyecto') idxNombreProyecto = idx;
                                                                                                                    });
                                                                                                                    filas.forEach(function(tr) {
                                                                                                                        var tds = tr.querySelectorAll('td');
                                                                                                                        if (idxNombreProyecto > 0 && tds.length > idxNombreProyecto) {
                                                                                                                            var nombreProyecto = tds[idxNombreProyecto].textContent.trim();
                                                                                                                            if (nombreProyecto) proyectosSet.add(nombreProyecto);
                                                                                                                        }
                                                                                                                    });
                                                                                                                    Array.from(proyectosSet).sort().forEach(function(proy) {
                                                                                                                        var opt = document.createElement('option');
                                                                                                                        opt.value = proy;
                                                                                                                        opt.textContent = proy;
                                                                                                                        selectProyecto.appendChild(opt);
                                                                                                                    });
                                                                                                                    function filtrarTabla() {
                                                                                                                        var filtroProyecto = selectProyecto.value;
                                                                                                                        var filtroEstado = selectEstado.value;
                                                                                                                        var filas = tabla.querySelectorAll('tbody tr');
                                                                                                                        filas.forEach(function(tr) {
                                                                                                                            var tds = tr.querySelectorAll('td');
                                                                                                                            var nombreProyecto = (idxNombreProyecto > 0 && tds.length > idxNombreProyecto) ? tds[idxNombreProyecto].textContent.trim() : '';
                                                                                                                            var estado = tds.length > 0 ? tds[tds.length-1].textContent.trim() : '';
                                                                                                                            var visible = true;
                                                                                                                            if (filtroProyecto && nombreProyecto !== filtroProyecto) visible = false;
                                                                                                                            if (filtroEstado && estado !== filtroEstado) visible = false;
                                                                                                                            tr.style.display = visible ? '' : 'none';
                                                                                                                        });
                                                                                                                    }
                                                                                                                    selectProyecto.addEventListener('change', filtrarTabla);
                                                                                                                    selectEstado.addEventListener('change', filtrarTabla);
                                                                                                                });
                                                                                                                </script>
                                                        <div class="table-responsive" style="box-shadow: 0 2px 12px rgba(44,62,80,0.07); border-radius: 10px; overflow-x: auto; max-height: 480px;">
                                                            <table class="table table-striped table-hover table-bordered align-middle" style="font-size: 0.97rem; background: #fff; border-radius: 10px; min-width: 700px;">
                                                                <style>
                                                                #modalHorasDia .table th, #modalHorasDia .table td {
                                                                    vertical-align: middle;
                                                                    text-align: center;
                                                                    padding: 7px 8px;
                                                                    font-size: 0.97rem;
                                                                    border-color: #e0e6ed;
                                                                }
                                                                #modalHorasDia .table th {
                                                                    background: #4C8AA3 !important;
                                                                    color: #fff;
                                                                    font-weight: 700;
                                                                    letter-spacing: 0.5px;
                                                                    border-top: none;
                                                                }
                                                                #modalHorasDia .table-striped > tbody > tr:nth-of-type(odd) {
                                                                    background-color: #f8fafc;
                                                                }
                                                                #modalHorasDia .table-striped > tbody > tr:nth-of-type(even) {
                                                                    background-color: #fff;
                                                                }
                                                                #modalHorasDia .table-hover tbody tr:hover {
                                                                    background-color: #e6f2ed !important;
                                                                    transition: background 0.2s;
                                                                }
                                                                #modalHorasDia .table td.estado-aceptado {
                                                                    background: #d6f5e3 !important;
                                                                    color: #17823D !important;
                                                                    font-weight: 700;
                                                                    border-left: 3px solid #17823D;
                                                                }
                                                                #modalHorasDia .table td.estado-rechazado {
                                                                    background: #ffe0e0 !important;
                                                                    color: #c0392b !important;
                                                                    font-weight: 700;
                                                                    border-left: 3px solid #c0392b;
                                                                }
                                                                #modalHorasDia .table td:last-child, #modalHorasDia .table th:last-child {
                                                                    background: #eaf7f0;
                                                                    font-weight: 600;
                                                                    color: #17823D;
                                                                }
                                                                </style>
                                                                <thead>
                                                                <?php
                                                                // Obtener el mes y año activos de horas_habiles_calendario
                                                                $sql_mes_activo = "SELECT MONTH(fecha) AS mes_activo, YEAR(fecha) AS ano_activo FROM horas_habiles_calendario WHERE UPPER(TRIM(Estado)) = 'ACTIVO' LIMIT 1";
                                                                $res_mes = $conn->query($sql_mes_activo);
                                                                $filtro_fecha = '';
                                                                if ($res_mes && $res_mes->num_rows > 0) {
                                                                    $row_mes = $res_mes->fetch_assoc();
                                                                    $mes_activo = (int)$row_mes['mes_activo'];
                                                                    $ano_activo = (int)$row_mes['ano_activo'];
                                                                    if ($mes_activo && $ano_activo) {
                                                                        $filtro_fecha = " AND MONTH(fecha) = $mes_activo AND YEAR(fecha) = $ano_activo ";
                                                                    }
                                                                }
                                                                // Mostrar solo registros del colaborador logueado y del mes activo
                                                                $sql_horas = "SELECT * FROM horas_dia WHERE numero_empleado = '" . $conn->real_escape_string($matricula_usuario) . "'" . $filtro_fecha . " ORDER BY fecha ASC LIMIT 100";
                                                                $res_horas = $conn->query($sql_horas);
                                                                $ocultar = [
                                                                    'id', 'nature_imputation', 'cat_coan', 'tarifa_coan', 'area_funcional', 'numero_empleado', 'nom', 'prenom',
                                                                    'nombre_sub_ceco', 'cod_sub_ceco', 'aprobado_director', 'rechazado_director', 'comentario_director', 'horas_registradas', 'horas_teoricas', 'Estado_Aprobacion',
                                                                    'aprobado_coordinador', 'rechazado_coordinador', 'nombre_completo', 'tiempo_imputado_costo'
                                                                ];
                                                                $col_map = [
                                                                    'codigo_affaire' => 'Centro Costo',
                                                                    'nombre_affaire' => 'Nombre Proyecto',
                                                                    'fecha' => 'Fecha',
                                                                    'tiempo_imputado_horas' => 'Horas Cargadas',
                                                                    'comentario' => 'Comentario Colaborador',
                                                                    'comentario_coordinador' => 'Comentario Coordinador',
                                                                ];
                                                                // Leer todos los registros y ordenarlos: rechazados primero
                                                                $registros = [];
                                                                if ($res_horas && $res_horas->num_rows > 0) {
                                                                    while ($row = $res_horas->fetch_assoc()) {
                                                                        $registros[] = $row;
                                                                    }
                                                                }
                                                                // Ordenar: rechazados primero, luego aceptados, luego el resto
                                                                usort($registros, function($a, $b) {
                                                                    $rech_a = isset($a['rechazado_coordinador']) ? (int)$a['rechazado_coordinador'] : 0;
                                                                    $rech_b = isset($b['rechazado_coordinador']) ? (int)$b['rechazado_coordinador'] : 0;
                                                                    if ($rech_a !== $rech_b) return $rech_b - $rech_a;
                                                                    $acep_a = isset($a['aprobado_coordinador']) ? (int)$a['aprobado_coordinador'] : 0;
                                                                    $acep_b = isset($b['aprobado_coordinador']) ? (int)$b['aprobado_coordinador'] : 0;
                                                                    if ($acep_a !== $acep_b) return $acep_b - $acep_a;
                                                                    // Si son iguales, ordenar por fecha descendente
                                                                    return strcmp($b['fecha'] ?? '', $a['fecha'] ?? '');
                                                                });
                                                                // Encabezado
                                                                if (count($registros) > 0) {
                                                                    echo '<tr>';
                                                                    echo '<th style="width:52px; min-width:52px; max-width:52px; text-align:center; border-top-left-radius:10px;">Aviso</th>';
                                                                    foreach (array_keys($registros[0]) as $col) {
                                                                        if (in_array($col, $ocultar)) continue;
                                                                        $th_name = isset($col_map[$col]) ? $col_map[$col] : $col;
                                                                        $extra_style = ($col === 'fecha') ? ' style="white-space:nowrap;"' : '';
                                                                        echo '<th' . $extra_style . '>' . htmlspecialchars($th_name ?? '') . '</th>';
                                                                    }
                                                                    echo '<th style="color:#fff;">Estado</th>';
                                                                    echo '</tr>';
                                                                }
                                                                ?>
                                                                </thead>
                                                                <tbody>
                                                                <?php
                                                                if (count($registros) > 0) {
                                                                    foreach ($registros as $registro) {
                                                                        echo '<tr>';
                                                                        // Columna Aviso
                                                                        $icono = '';
                                                                        if (isset($registro['rechazado_coordinador']) && $registro['rechazado_coordinador'] == 1) {
                                                                            $icono = '<i class="bi bi-exclamation-triangle-fill" style="color:#e74c3c;font-size:1.3em;" title="Rechazado"></i>';
                                                                        } elseif (isset($registro['aprobado_coordinador']) && $registro['aprobado_coordinador'] == 1) {
                                                                            $icono = '<i class="bi bi-check-circle-fill" style="color:#17823D;font-size:1.3em;" title="Aceptado"></i>';
                                                                        }
                                                                        echo '<td style="text-align:center; width:52px; min-width:52px; max-width:52px;">' . $icono . '</td>';
                                                                        foreach ($registro as $col => $valor) {
                                                                            if (in_array($col, $ocultar)) continue;
                                                                            if ($col === 'fecha') {
                                                                                // Color según estado: verde si aceptado, rojo si rechazado, negro si ninguno
                                                                                $style = 'white-space:nowrap;';
                                                                                if (isset($registro['aprobado_coordinador']) && $registro['aprobado_coordinador'] == 1) {
                                                                                    $style .= 'color:#17823d;font-weight:bold;';
                                                                                } elseif (isset($registro['rechazado_coordinador']) && $registro['rechazado_coordinador'] == 1) {
                                                                                    $style .= 'color:#e74c3c;font-weight:bold;';
                                                                                }
                                                                                echo '<td style="' . $style . '">' . htmlspecialchars($valor === null ? '' : $valor) . '</td>';
                                                                            } else {
                                                                                echo '<td>' . htmlspecialchars($valor === null ? '' : $valor) . '</td>';
                                                                            }
                                                                        }
                                                                        // Estado para la fila
                                                                        $estado = '';
                                                                        $estado_class = '';
                                                                        if (isset($registro['aprobado_coordinador']) && $registro['aprobado_coordinador'] == 1) {
                                                                            $estado = 'Aceptado';
                                                                            $estado_class = 'estado-aceptado';
                                                                        } elseif (isset($registro['rechazado_coordinador']) && $registro['rechazado_coordinador'] == 1) {
                                                                            $estado = 'Rechazado';
                                                                            $estado_class = 'estado-rechazado';
                                                                        }
                                                                        echo '<td class="' . $estado_class . '">' . $estado . '</td>';
                                                                        echo '</tr>';
                                                                    }
                                                                } else {
                                                                    echo '<tr><td colspan="11" class="text-center">No hay registros de horas para mostrar.</td></tr>';
                                                                }
                                                                ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer" style="background: #f8f9fa;">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <script>
                                        function mostrarModalHorasDia() {
                                                var modal = new bootstrap.Modal(document.getElementById('modalHorasDia'));
                                                modal.show();
                                        }
                                        </script>
                <div class="text-center mb-4" style="margin-top: 40px;">
                    <h2 class="mb-4" style="color: #17923d; font-weight: 700;">ESTADO DE APROBACIÓN POR EMPLEADO</h2>
                </div>
                <?php
                // Obtener hh_teoricas activas para calcular % cargue del colaborador
                $hh_teoricas_activas = 0.0;
                $sql_hh_teoricas = "SELECT hh_teoricas
                                    FROM horas_habiles_calendario
                                    WHERE UPPER(TRIM(Estado)) = 'ACTIVO'
                                    ORDER BY calendario DESC
                                    LIMIT 1";
                $res_hh_teoricas = $conn->query($sql_hh_teoricas);
                if ($res_hh_teoricas && $res_hh_teoricas->num_rows > 0) {
                    $row_hh_teoricas = $res_hh_teoricas->fetch_assoc();
                    $hh_teoricas_activas = (float)($row_hh_teoricas['hh_teoricas'] ?? 0);
                }

                // Filtrar la tabla de aprobación por matrícula del usuario logueado
                $where_matricula = '';
                if (!empty($matricula_usuario)) {
                    $matricula_usuario_sql = $conn->real_escape_string($matricula_usuario);
                    $where_matricula = " AND h.numero_empleado = '" . $matricula_usuario_sql . "' ";
                }

                $sql_estado_aprobacion = "SELECT 
                    h.nom,
                    h.prenom,
                    COALESCE(NULLIF(MAX(h.nombre_affaire), ''), h.codigo_affaire) AS nombre_proyecto,
                    SUM(h.tiempo_imputado_horas) AS horas_imputadas,
                    SUM(h.tiempo_imputado_costo) AS costo_imputado,
                    SUM(CASE WHEN h.aprobado_coordinador = 1 THEN h.tiempo_imputado_costo ELSE 0 END) AS costo_aprobado,
                    ROUND(
                        100 * SUM(CASE WHEN h.aprobado_coordinador = 1 THEN h.tiempo_imputado_horas ELSE 0 END)
                        / NULLIF(SUM(h.tiempo_imputado_horas), 0),
                        1
                    ) AS porcentaje_horas_aprobadas,
                    ROUND(
                        100 * SUM(CASE WHEN h.aprobado_director = 1 THEN h.tiempo_imputado_horas ELSE 0 END)
                        / NULLIF(SUM(h.tiempo_imputado_horas), 0),
                        1
                    ) AS porcentaje_horas_aprobadas_director,
                    h.numero_empleado AS numero_de_empleado,
                    h.area_funcional
                FROM horas_dia h
                WHERE h.Estado_Aprobacion = 'Aprobado En Curso'
                $where_matricula
                GROUP BY h.nom, h.prenom, h.codigo_affaire, h.numero_empleado, h.area_funcional
                ORDER BY h.nom, h.prenom, nombre_proyecto";

                $result_estado = $conn->query($sql_estado_aprobacion);
                if ($result_estado === false) {
                    error_log('Colaborador.php: fallo consulta estado aprobacion para matricula [' . $matricula_usuario . ']: ' . $conn->error);
                }

                // Agrupar por empleado
                $empleados_matriz = [];
                $total_horas_imputadas_estado = 0;
                $total_costo_imputado_estado = 0;
                $total_costo_aprobado_estado = 0;

                if ($result_estado && $result_estado->num_rows > 0) {
                    while ($row_estado = $result_estado->fetch_assoc()) {
                        $key_empleado = $row_estado['nom'] . '|' . $row_estado['prenom'];
                        if (!isset($empleados_matriz[$key_empleado])) {
                            $empleados_matriz[$key_empleado] = [
                                'nom' => $row_estado['nom'],
                                'prenom' => $row_estado['prenom'],
                                'numero_de_empleado' => $row_estado['numero_de_empleado'],
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
                        $empleados_matriz[$key_empleado]['proyectos'][] = [
                            'nombre_proyecto' => $row_estado['nombre_proyecto'],
                            'horas_imputadas' => $horas_imp,
                            'costo_imputado' => $costo_imp,
                            'costo_aprobado' => $costo_apr,
                            'porcentaje_horas_aprobadas' => (float)($row_estado['porcentaje_horas_aprobadas'] ?? 0),
                            'porcentaje_horas_aprobadas_director' => (float)($row_estado['porcentaje_horas_aprobadas_director'] ?? 0),
                            'numero_de_empleado' => $row_estado['numero_de_empleado']
                        ];
                    }
                }

                if (!empty($empleados_matriz) && $hh_teoricas_activas > 0) {
                    foreach ($empleados_matriz as &$empleado_data) {
                        $empleado_data['porcentaje_cargue'] = ($empleado_data['total_horas_imputadas'] / $hh_teoricas_activas) * 100;
                    }
                    unset($empleado_data);
                }

                // Ajustar totales: sumar solo por empleado, no por proyecto
                $total_horas_imputadas_estado = 0;
                $total_costo_imputado_estado = 0;
                $total_costo_aprobado_estado = 0;
                $total_porcentaje_director = 0;
                $total_director_count = 0;
                foreach ($empleados_matriz as $emp) {
                    $total_horas_imputadas_estado += $emp['total_horas_imputadas'];
                    $total_costo_imputado_estado += $emp['total_costo_imputado'];
                    $total_costo_aprobado_estado += $emp['total_costo_aprobado'];
                    // Calcular promedio ponderado de % director por empleado
                    $sum_dir = 0; $sum_dir_horas = 0;
                    foreach ($emp['proyectos'] as $proy) {
                        if (isset($proy['porcentaje_horas_aprobadas_director'])) {
                            $sum_dir += $proy['porcentaje_horas_aprobadas_director'] * $proy['horas_imputadas'];
                            $sum_dir_horas += $proy['horas_imputadas'];
                        }
                    }
                    if ($sum_dir_horas > 0) {
                        $total_porcentaje_director += $sum_dir;
                        $total_director_count += $sum_dir_horas;
                    }
                }
                $porcentaje_total_estado = $total_costo_imputado_estado > 0 ? ($total_costo_aprobado_estado / $total_costo_imputado_estado) * 100 : 0;
                $porcentaje_total_director = $total_director_count > 0 ? ($total_porcentaje_director / $total_director_count) : 0;
                ?>
                <div class="mb-5" style="max-width:1100px; margin: 0 auto; display: flex; gap: 32px; justify-content: center; align-items: stretch;">
                    <!-- Tarjeta Aprobado Coordinador -->
                    <div style="background: #f8fafd; border-radius: 16px; box-shadow: 0 2px 12px 0 rgba(60,60,60,0.06); padding: 18px 28px 18px 28px; min-width: 240px; display: flex; flex-direction: row; align-items: center; gap: 18px;">
                        <span style="display:flex; align-items:center; justify-content:center; background: #ececff; border-radius: 12px; padding: 12px; min-width:48px; min-height:48px;">
                            <!-- Ícono coordinador: persona con estrella -->
                            <svg width="28" height="28" fill="#6c63ff" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h7.5l-1.1-3.3 2.9-2.1 2.9 2.1-1.1 3.3h7.5v-2.4c0-3.2-6.4-4.8-9.6-4.8zm2.9 4.8l-.9-2.7-2-.1 1.6-1.2-.6-1.9 1.6 1.2 1.6-1.2-.6 1.9 1.6 1.2-2-.1-.9 2.7z"/></svg>
                        </span>
                        <div style="display:flex; flex-direction:column; align-items:flex-start; justify-content:center; flex:1;">
                            <span style="color:#6c6f7f; font-size:1.08rem; font-weight:500; margin-bottom:2px;">Aprobado Coordinador</span>
                            <span style="font-size:2.1rem; font-weight:700; color:<?= percentToColor($porcentaje_total_estado) ?>; line-height:1; display:block; text-align:center; width:100%;">
                                <?= number_format($porcentaje_total_estado, 0, '.', ',') ?>%
                            </span>
                        </div>
                    </div>
                    <!-- Tarjeta Aprobado Director -->
                    <div style="background: #f8fafd; border-radius: 16px; box-shadow: 0 2px 12px 0 rgba(60,60,60,0.06); padding: 18px 28px 18px 28px; min-width: 240px; display: flex; flex-direction: row; align-items: center; gap: 18px;">
                        <span style="display:flex; align-items:center; justify-content:center; background: #e6f7f1; border-radius: 12px; padding: 12px; min-width:48px; min-height:48px;">
                            <!-- Ícono director: medalla/trofeo -->
                            <svg width="28" height="28" fill="#1dbf73" viewBox="0 0 24 24"><path d="M12 2C9.24 2 7 4.24 7 7c0 2.38 1.68 4.36 4 4.86V15H8v2h8v-2h-3v-3.14c2.32-.5 4-2.48 4-4.86 0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm-6 7v2c0 2.21 1.79 4 4 4h1v2H7v2h10v-2h-4v-2h1c2.21 0 4-1.79 4-4v-2h-2v2c0 1.1-.9 2-2 2s-2-.9-2-2v-2H6z"/></svg>
                        </span>
                        <div style="display:flex; flex-direction:column; align-items:flex-start; justify-content:center; flex:1;">
                            <span style="color:#6c6f7f; font-size:1.08rem; font-weight:500; margin-bottom:2px;">Aprobado Director</span>
                            <span style="font-size:2.1rem; font-weight:700; color:<?= percentToColor($porcentaje_total_director) ?>; line-height:1; display:block; text-align:center; width:100%;">
                                <?= $total_director_count > 0 ? number_format($porcentaje_total_director, 0, '.', ',') . '%' : '-' ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mb-5" style="max-width:1100px; margin: 0 auto;">
                    <div class="table-responsive" style="border-radius: 12px; box-shadow: 0 2px 12px 0 rgba(60,60,60,0.04);">
                        <table class="table tabla-empleados align-middle mb-0" style="background:#fff; width: 100%; border-collapse: separate; border-spacing: 0;">
                            <thead style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 6px; text-align: center; border: none;"></th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 12px; text-align: center; font-weight: 600; border: none;">APELLIDO</th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 12px; text-align: center; font-weight: 600; border: none;">NOMBRE</th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 8px; text-align: center; font-weight: 600; border: none;">% CARGUE</th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 8px; text-align: center; font-weight: 600; border: none;">HORAS IMPUTADAS</th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 8px; text-align: center; font-weight: 600; border: none;">COSTO IMPUTADO</th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 8px; text-align: center; font-weight: 600; border: none;">COSTO APROBADO</th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 8px; text-align: center; font-weight: 600; border: none;">% HORAS APROBADAS COORD</th>
                                    <th style="background-color: #5A8CA7; color: white; padding: 8px 8px; text-align: center; font-weight: 600; border: none;">% HORAS APROBADAS DIR</th>
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
                                    ?>
                                        <tr class="empleado-row <?= $tiene_aprobacion_cero ? 'sin-aprobacion' : '' ?>" 
                                            data-empleado-id="<?= $empleado_index ?>"
                                            onclick="toggleEmpleadoDetalle(<?= $empleado_index ?>)"
                                            style="<?= $bg_empleado ?>">
                                            <td style="text-align: center; padding: 14px 8px; border-bottom: 1px solid #e0e0e0;">
                                                <i class="bi bi-chevron-right toggle-icon" id="toggle-icon-<?= $empleado_index ?>" 
                                                   style="font-size: 1.1rem; font-weight: bold; color: #5A8CA7;"></i>
                                            </td>
                                            <td style="text-align: left; padding: 8px 8px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                                <?= htmlspecialchars($empleado['nom']) ?>
                                            </td>
                                            <td style="text-align: left; padding: 8px 8px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                                <?= htmlspecialchars($empleado['prenom']) ?>
                                            </td>
                                            <td style="text-align: center; padding: 8px 8px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                                <?php 
                                                    $porc_cargue = (float)($empleado['porcentaje_cargue'] ?? 0);
                                                    $color_cargue = percentToColor($porc_cargue);
                                                ?>
                                                <span style="color: <?= $color_cargue ?>; font-weight: 700;">
                                                    <?= number_format($porc_cargue, 1) ?>%
                                                </span>
                                            </td>
                                            <td style="text-align: center; padding: 8px 8px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                                <?= number_format($empleado['total_horas_imputadas'], 2, ',', '.') ?> hrs
                                            </td>
                                            <td style="text-align: center; padding: 8px 8px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                                $ <?= number_format($empleado['total_costo_imputado'], 0, '', '.') ?>
                                            </td>
                                            <td style="text-align: center; padding: 8px 8px; font-weight: 600; font-size: 0.95rem; color: #2c3e50; border-bottom: 1px solid #e0e0e0;">
                                                $ <?= number_format($empleado['total_costo_aprobado'], 0, '', '.') ?>
                                            </td>
                                            <td style="text-align: center; padding: 8px 8px; border-bottom: 1px solid #e0e0e0;">
                                                <span style="color: <?= $color_empleado ?>; font-weight: 700; font-size: 0.95rem;">
                                                    <?= number_format($porc_empleado, 1) ?>%
                                                </span>
                                            </td>
                                            <td style="text-align: center; padding: 8px 8px; border-bottom: 1px solid #e0e0e0;">
                                                <?php 
                                                    // Promedio ponderado de % director para este empleado
                                                    $sum_dir = 0; $sum_dir_horas = 0;
                                                    foreach ($empleado['proyectos'] as $proy) {
                                                        if (isset($proy['porcentaje_horas_aprobadas_director'])) {
                                                            $sum_dir += $proy['porcentaje_horas_aprobadas_director'] * $proy['horas_imputadas'];
                                                            $sum_dir_horas += $proy['horas_imputadas'];
                                                        }
                                                    }
                                                    $porc_dir = ($sum_dir_horas > 0) ? ($sum_dir / $sum_dir_horas) : 0;
                                                ?>
                                                <span style="color: <?= percentToColor($porc_dir) ?>; font-weight: 700; font-size: 0.95rem;">
                                                    <?= $sum_dir_horas > 0 ? number_format($porc_dir, 1) . '%' : '-' ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php foreach($empleado['proyectos'] as $idx => $proyecto): ?>
                                            <tr class="detalle-proyecto detalle-empleado-<?= $empleado_index ?><?= ($proyecto['porcentaje_horas_aprobadas'] == 0 && $proyecto['costo_imputado'] > 0) ? ' proyecto-sin-aprobacion' : '' ?>" style="display: none;">
                                                <!-- Icono y nombre del proyecto -->
                                                <td style="<?= ($proyecto['porcentaje_horas_aprobadas'] == 0 && $proyecto['costo_imputado'] > 0) ? 'background: #fff3e6 !important;' : 'background: #fafafa;' ?> border-bottom: 1px solid #f0f0f0;"></td>
                                                <td colspan="2" style="text-align: left; padding: 10px 15px 10px 50px; font-size: 0.88rem; color: #555; <?= ($proyecto['porcentaje_horas_aprobadas'] == 0 && $proyecto['costo_imputado'] > 0) ? 'background: #fff3e6 !important;' : 'background: #fafafa;' ?> border-bottom: 1px solid #f0f0f0;">
                                                    <i class="bi bi-arrow-return-right" style="color: #999; margin-right: 8px;"></i>
                                                    <span style="color: #444;"><?= htmlspecialchars($proyecto['nombre_proyecto']) ?></span>
                                                </td>
                                                <!-- % CARGUE vacío -->
                                                <td style="background: #fafafa; border-bottom: 1px solid #f0f0f0;"></td>
                                                <!-- HORAS IMPUTADAS -->
                                                <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= ($proyecto['porcentaje_horas_aprobadas'] == 0 && $proyecto['costo_imputado'] > 0) ? 'background: #fff3e6 !important;' : 'background: #fafafa;' ?> border-bottom: 1px solid #f0f0f0;">
                                                    <?= $proyecto['horas_imputadas'] > 0 ? number_format($proyecto['horas_imputadas'], 2, ',', '.') . ' hrs' : '-' ?>
                                                </td>
                                                <!-- COSTO IMPUTADO -->
                                                <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= ($proyecto['porcentaje_horas_aprobadas'] == 0 && $proyecto['costo_imputado'] > 0) ? 'background: #fff3e6 !important;' : 'background: #fafafa;' ?> border-bottom: 1px solid #f0f0f0;">
                                                    <?= $proyecto['costo_imputado'] > 0 ? '$ ' . number_format($proyecto['costo_imputado'], 0, '', '.') : '-' ?>
                                                </td>
                                                <!-- COSTO APROBADO -->
                                                <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; <?= ($proyecto['porcentaje_horas_aprobadas'] == 0 && $proyecto['costo_imputado'] > 0) ? 'background: #fff3e6 !important;' : 'background: #fafafa;' ?> border-bottom: 1px solid #f0f0f0;">
                                                    <?= $proyecto['costo_aprobado'] > 0 ? '$ ' . number_format($proyecto['costo_aprobado'], 0, '', '.') : '-' ?>
                                                </td>
                                                <!-- % HORAS APROBADAS COORD -->
                                                <td style="text-align: center; padding: 10px 15px; <?= ($proyecto['porcentaje_horas_aprobadas'] == 0 && $proyecto['costo_imputado'] > 0) ? 'background: #fff3e6 !important;' : 'background: #fafafa;' ?> border-bottom: 1px solid #f0f0f0;">
                                                    <span style="color: <?= percentToColor((float)$proyecto['porcentaje_horas_aprobadas']) ?>; font-weight: 600; font-size: 0.88rem;">
                                                        <?= $proyecto['porcentaje_horas_aprobadas'] > 0 ? number_format((float)$proyecto['porcentaje_horas_aprobadas'], 1) . '%' : '-' ?>
                                                    </span>
                                                </td>
                                                <!-- % HORAS APROBADAS DIRECTOR -->
                                                <td style="text-align: center; padding: 10px 15px; <?= ($proyecto['porcentaje_horas_aprobadas_director'] == 0 && $proyecto['costo_imputado'] > 0) ? 'background: #fff3e6 !important;' : 'background: #fafafa;' ?> border-bottom: 1px solid #f0f0f0;">
                                                    <span style="color: <?= percentToColor((float)$proyecto['porcentaje_horas_aprobadas_director']) ?>; font-weight: 600; font-size: 0.88rem;">
                                                        <?= $proyecto['porcentaje_horas_aprobadas_director'] > 0 ? number_format((float)$proyecto['porcentaje_horas_aprobadas_director'], 1) . '%' : '-' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
    <!-- end foreach empleado -->
                                    <tr style="background: linear-gradient(to right, #e8f4f8, #d4e9f2); font-weight: 700; border-top: 3px solid #4C8AA3;">
                                        <td colspan="3" style="text-align: right; padding: 15px 20px; font-size: 1rem; color: #2c3e50;">
                                            TOTAL:
                                        </td>
                                        <td style="text-align: center; padding: 15px; font-size: 1rem; color: #2c3e50;">
                                            -
                                        </td>
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
                                        <td style="text-align: center; padding: 15px;">
                                            <span style="color: <?= percentToColor($porcentaje_total_director) ?>; font-weight: 700; font-size: 1rem;">
                                                <?= $total_director_count > 0 ? number_format($porcentaje_total_director, 1) . '%' : '-' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center" style="padding: 30px;">No hay datos de estado de aprobación para mostrar.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <script>
                    function toggleEmpleadoDetalle(empleadoId) {
                        const detalles = document.querySelectorAll('.detalle-empleado-' + empleadoId);
                        const icon = document.getElementById('toggle-icon-' + empleadoId);
                        const row = document.querySelector(`tr[data-empleado-id="${empleadoId}"]`);
                        if (detalles.length > 0) {
                            const isVisible = detalles[0].style.display !== 'none';
                            detalles.forEach(detalle => {
                                detalle.style.display = isVisible ? 'none' : 'table-row';
                            });
                            if (icon) {
                                if (isVisible) {
                                    icon.classList.remove('bi-chevron-down');
                                    icon.classList.add('bi-chevron-right');
                                    if (row) row.classList.remove('expanded');
                                } else {
                                    icon.classList.remove('bi-chevron-right');
                                    icon.classList.add('bi-chevron-down');
                                    if (row) row.classList.add('expanded');
                                }
                            }
                        }
                    }
                    document.addEventListener('DOMContentLoaded', function() {
                        const empleadoRows = document.querySelectorAll('.empleado-row');
                        empleadoRows.forEach(row => {
                            row.addEventListener('mouseenter', function() {
                                if (!this.classList.contains('expanded')) {
                                    if (this.classList.contains('sin-aprobacion')) {
                                        this.style.backgroundColor = '#ffd4a8';
                                    } else {
                                        this.style.backgroundColor = '#f0f7fd';
                                    }
                                }
                            });
                            row.addEventListener('mouseleave', function() {
                                if (!this.classList.contains('expanded')) {
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
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/colaborador-assignment-comment-modal.js"></script>
    <script>

    function mostrarTabColaborador(hash) {
    function mostrarTabColaborador(hash) {
        if (hash !== '#aprobacion' && hash !== '#asignacion') {
            hash = '#asignacion';
        }

        var tabTrigger = document.querySelector('#colabTabs button[data-bs-target="' + hash + '"]');
        var tabPane = document.querySelector(hash);
        if (!tabTrigger || !tabPane) {
            return;
        }

        if (window.bootstrap && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
            return;
        }

        document.querySelectorAll('#colabTabs .nav-link').forEach(function(button) {
            button.classList.remove('active');
            button.setAttribute('aria-selected', 'false');
        });

        document.querySelectorAll('#colabTabsContent .tab-pane').forEach(function(pane) {
            pane.classList.remove('show', 'active');
        });

        tabTrigger.classList.add('active');
        tabTrigger.setAttribute('aria-selected', 'true');
        tabPane.classList.add('show', 'active');
    }

    function sincronizarHashTab(hash) {
        if (hash === '#aprobacion' || hash === '#asignacion') {
            history.replaceState(null, '', hash);
        }
    }

    window.mostrarTabColaborador = mostrarTabColaborador;
    window.sincronizarHashTab = sincronizarHashTab;

    function inicializarTabsColaborador() {
        if (window.colaboradorTabsInicializados) {
            return;
        }

        var tabButtons = document.querySelectorAll('#colabTabs button[data-bs-target]');
        if (!tabButtons.length) {
            return;
        }

        window.colaboradorTabsInicializados = true;

        tabButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var hash = button.getAttribute('data-bs-target');
                mostrarTabColaborador(hash);
                sincronizarHashTab(hash);
            });

            button.addEventListener('shown.bs.tab', function(event) {
                sincronizarHashTab(event.target.getAttribute('data-bs-target'));
            });
        });

        mostrarTabColaborador(window.location.hash);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarTabsColaborador);
    } else {
        inicializarTabsColaborador();
    }

    window.addEventListener('hashchange', function() {
        mostrarTabColaborador(window.location.hash);
    });
    </script>

    <!-- Left stacked cards + right tall chart (match screenshot) -->
    <div class="row mb-4 align-items-stretch">
        <!-- Tarjetas BALANCE DE PRESUPUESTO y BALANCE DE ASIGNACIÓN DE PERSONAL eliminadas -->

        <!-- Gráfica COMPARATIVO POR PROYECTO eliminada -->
    </div>

    <!-- Tarjetas y tabla de resumen eliminadas -->
    <!-- Gráfica COMPARATIVO POR PROYECTO eliminada -->
</div>
</body>
</html>
