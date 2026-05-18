<?php
// resumen_proyectos.php
// Este archivo muestra un resumen ejecutivo por proyecto cargado

require_once __DIR__ . '/vendor/autoload.php';

// Incluir archivos de configuración y conexión centralizada
require_once 'include.php';
require_once 'config.php';
// $conn debe estar definido en config.php
if (!isset($conn) || !$conn) {
    die("Error: No se pudo establecer la conexión a la base de datos.");
}
// Asegurar la codificación para evitar problemas con acentos/caracteres especiales
$conn->set_charset('utf8mb4');

// -------------------- ACTIVAR SESIÓN --------------------
//session_start();


session_start();
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

// Obtener lista de áreas funcionales disponibles (para el select)

$areas = [];
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
} else if ($rol_usuario === 'MIX') {
     // Mostrar solo BIM y 'Vías y Topografía' para MIX
     $sql .= " AND (gp.`ÁREA FUNCIONAL` = 'BIM' OR gp.`ÁREA FUNCIONAL` = 'Vías y Topografía') ";
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
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            padding: 4px 10px; /* Reducido el padding vertical */
            width: 1%;
            white-space: nowrap;
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
            background: #A6C2C9;
            color: #fff;
            font-weight: 600;
            white-space: normal;
            text-align: center !important;
            vertical-align: middle;
        }
        .resumen-table tbody tr:nth-child(even) {
            background: #f4f7fb;
        }
        .resumen-table tbody tr:nth-child(odd) {
            background: #fff;
        }
        /* Ajustes específicos para columnas */
        /* Eliminar min-width para que el ancho se ajuste al texto del encabezado */
   
        /* Botones de acción en la tabla de presupuesto compartido */
        .btn-editar .bi-pencil-square {
            color: #17823d;
            transition: color 0.2s;
        }
        .btn-eliminar .bi-trash-fill {
            color: #b02a37;
            transition: color 0.2s;
        }
        .btn-editar:hover {
            background: #17823d !important;
            border-color: #17823d !important;
        }
        .btn-editar:hover .bi-pencil-square {
            color: #fff;
        }
        .btn-eliminar:hover {
            background: #b02a37 !important;
            border-color: #b02a37 !important;
        }
        .btn-eliminar:hover .bi-trash-fill {
            color: #fff;
        }

         </style>
</head>
<body>
    <?php include_once __DIR__ . '/menu.php'; ?>
    <div class="main-content container mt-3" style="margin-top: 1.2rem !important;">
    <div class="text-center mb-2">
        <h2 class="mb-2" style="color: #6F6F6F; font-weight: 700;">BALANCE PRESUPUESTO</h2>
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
            // Si hay filtro de área funcional, sumar solo los que coincidan
            if ($area_filter !== '') {
                if ($r['ÁREA FUNCIONAL'] === $area_filter) {
                    $sum_bac += (float)$r['total_costo'];
                    $sum_ac += (float)$r['total_valorizado_2025'];
                    $sum_etc += ((float)$r['total_costo'] - (float)$r['total_valorizado_2025']);
                }
            } else {
                $sum_bac += (float)$r['total_costo'];
                $sum_ac += (float)$r['total_valorizado_2025'];
                $sum_etc += ((float)$r['total_costo'] - (float)$r['total_valorizado_2025']);
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

    <!-- ...existing code... -->

    <!-- Left stacked cards + right tall chart (match screenshot) -->
    <!-- ...existing code... -->

    <div class="table-responsive">
        <table class="table resumen-table align-middle mb-0" style="background:#fff; width: 100%;">
                    <thead class="resumen-thead">
                        <tr>
                            <?php if (in_array($rol_usuario, ['SUPER', 'ADMIN', 'DIR', 'MIX'])): ?>
                                <th class="th-area">ÁREA FUNCIONAL</th>
                            <?php endif; ?>
                            <th class="th-ceco">CECO</th>
                            <th class="th-proyecto">NOMBRE PROYECTO</th>
                            <th class="th-presupuesto">PTO A TERMINACIÓN<br><small style="font-size: 0.75rem; font-weight: 400;">(BAC)</small></th>
                            <th class="th-cedido">MONTO CEDIDO</th>
                            <th class="th-accion">ACCIÓN</th>
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
                            
                            $total_monto_cedido = 0;
                            foreach($rows as $row): 
    // Filtrar por área funcional si el filtro está activo
    if ($area_filter !== '' && $row['ÁREA FUNCIONAL'] !== $area_filter) {
        continue;
    }
                                // Si el rol es MIX, mostrar solo BIM y 'Vías y Topografía'
                                if ($rol_usuario === 'MIX' && !in_array($row['ÁREA FUNCIONAL'], ['BIM', 'Vías y Topografía'])) {
                                    continue;
                                }
                                $costo_teorico = 0;
                                $pct_ejecutado = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'];
                                if ((float)$row['total_costo'] > 0) {
                                    $costo_teorico = ($pct_ejecutado / 100.0) * (float)$row['total_costo'];
                                }
                                // Acumular totales
                                $total_bac_table += (float)$row['total_costo'];
                                $total_ac_table += (float)$row['total_valorizado_2025'];
                                $total_costo_teorico += $costo_teorico;
                                $total_etc_table += ((float)$row['total_costo'] - (float)$row['total_valorizado_2025']);
                                // Calcular monto cedido por fila
                                $monto_cedido = 0;
                                $sql_cedido = "SELECT SUM(Monto_Prestado) AS total_cedido FROM compartir_presupuesto WHERE Centro_Costo = '" . $conn->real_escape_string($row['PROYECTO']) . "' AND Area_Funcional = '" . $conn->real_escape_string($row['ÁREA FUNCIONAL']) . "'";
                                $res_cedido = $conn->query($sql_cedido);
                                if ($res_cedido && $res_cedido->num_rows > 0) {
                                    $cedido_row = $res_cedido->fetch_assoc();
                                    $monto_cedido = (float)($cedido_row['total_cedido'] ?? 0);
                                }
                                $total_monto_cedido += $monto_cedido;
                            ?>
                                <tr>
                                    <?php if (in_array($rol_usuario, ['SUPER', 'ADMIN', 'DIR', 'MIX'])): ?>
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
                                    <td class="text-success">$ <?= number_format((float)$row['total_costo'], 0, '', '.') ?></td>
                                    <?php
                                    // Calcular el monto cedido cruzando Centro_Costo y Área Funcional
                                    $monto_cedido = 0;
                                    $sql_cedido = "SELECT SUM(Monto_Prestado) AS total_cedido FROM compartir_presupuesto WHERE Centro_Costo = '" . $conn->real_escape_string($row['PROYECTO']) . "' AND Area_Funcional = '" . $conn->real_escape_string($row['ÁREA FUNCIONAL']) . "'";
                                    $res_cedido = $conn->query($sql_cedido);
                                    if ($res_cedido && $res_cedido->num_rows > 0) {
                                        $cedido_row = $res_cedido->fetch_assoc();
                                        $monto_cedido = (float)($cedido_row['total_cedido'] ?? 0);
                                    }
                                    ?>
                                    <td class="text-primary text-center"><?php if ($monto_cedido > 0): ?>$ <?= number_format($monto_cedido, 0, '', '.') ?><?php else: ?>&nbsp;<?php endif; ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-success btn-sm btn-modal-presupuesto" 
                                            data-centro_costo="<?= htmlspecialchars($row['PROYECTO']) ?>" 
                                            data-area_funcional="<?= htmlspecialchars($row['ÁREA FUNCIONAL']) ?>"
                                            title="Presupuesto" data-bs-toggle="modal" data-bs-target="#modalPresupuesto">
                                                                                        <!-- Icono de ceder/prestar (Bootstrap arrow-right-circle) -->
                                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#17823d" class="bi bi-arrow-right-circle" viewBox="0 0 16 16">
                                                                                            <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"/>
                                                                                            <path d="M8.5 11a.5.5 0 0 1-.5-.5V9H5.5a.5.5 0 0 1 0-1H8V5.5a.5.5 0 0 1 1 0V8h2.5a.5.5 0 0 1 0 1H9v1.5a.5.5 0 0 1-.5.5z"/>
                                                                                        </svg>
                                        </button>
                                    </td>
                                    <!-- <td class="text-info">$ <?= number_format((float)$row['total_valorizado_2025'], 0, '', '.') ?></td> -->
                                    <!-- <td class="text-secondary">$ <?= number_format($costo_teorico, 0, '', '.') ?></td> -->
                                    <!-- <td class="text-warning">$ <?= number_format((float)$row['total_costo'] - (float)$row['total_valorizado_2025'], 0, '', '.') ?></td> -->
                                    <!-- <td><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'], 0) ?>%</td> -->
                                    <!-- <td><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'], 0) ?>%</td> -->
                                    <!-- <td><?php 
                                        $porcentaje_coan = 0;
                                        if ((float)$row['total_costo'] > 0) {
                                            $porcentaje_coan = ((float)$row['total_valorizado_2025'] / (float)$row['total_costo']) * 100;
                                        }
                                        echo number_format($porcentaje_coan, 0) . '%';
                                    ?></td> -->
                                    <!-- <td class="text-center">CPI</td> -->
                                </tr>
                                </tr>
                            <?php endforeach; ?>
                            <!-- Fila de totales -->
                            <tr style="background-color: #A6C2C9; color: #fff; font-weight: bold;">
                                <?php if (in_array($rol_usuario, ['SUPER', 'ADMIN', 'DIR', 'MIX'])): ?>
                                    <td colspan="3" style="text-align: right; padding: 12px;">TOTALES:</td>
                                <?php else: ?>
                                    <td colspan="2" style="text-align: right; padding: 12px;">TOTALES:</td>
                                <?php endif; ?>
                                <td class="text-center">$ <?= number_format($total_bac_table, 0, '', '.') ?></td>
                                <td class="text-primary text-center"><?php if ($total_monto_cedido > 0): ?>$ <?= number_format($total_monto_cedido, 0, '', '.') ?><?php else: ?>&nbsp;<?php endif; ?></td>
                                <td></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="13" class="text-center">No hay datos para mostrar.</td></tr>
                        <?php endif; ?>
                    <style>
                    .resumen-table {
                        background: #fff;
                        border-radius: 1rem;
                        overflow: hidden;
                        box-shadow: none;
                    }
                    .resumen-thead th {
                        background: #4C8AA3;
                        color: #fff;
                        font-weight: 600;
                        border: none;
                        font-size: 0.85rem;
                        letter-spacing:0.01em;
                        padding-top: 10px;
                        padding-bottom: 10px;
                        transition: background 0.2s, color 0.2s;
                        text-align: center !important;
                        padding-left: 15px;
                        vertical-align: middle;
                    }
                    .resumen-table tbody tr {
                        border-bottom: 1px solid #f0f0f0;
                        transition: box-shadow 0.18s, background 0.18s, transform 0.18s;
                    }
                    .resumen-table tbody tr:hover {
                        background: #f3f6fa;
                        box-shadow: 0 6px 24px 0 rgba(60,72,88,.16);
                        transform: translateY(-2px) scale(1.01);
                    }
                    .resumen-table td {
                        border: none;
                        vertical-align: middle;
                        font-size: 0.9rem;
                        background: #fff;
                        padding-top: 4px;
                        padding-bottom: 4px;
                        text-align: left;
                        padding-left: 15px;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    /* Mantener centrados los valores numéricos mediante clases */
                    .resumen-table td.text-success,
                    .resumen-table td.text-info,
                    .resumen-table td.text-warning {
                        text-align: center;
                        padding-left: 10px;
                        padding-right: 10px;
                    }
                    /* Eliminar min-width para que el ancho se ajuste al texto del encabezado */
                    /* Forzar que los registros del cuerpo aparezcan en negro y sin negrilla */
                    .resumen-table tbody td {
                        color: #000 !important;
                        font-weight: 400 !important;
                    }
                    .resumen-table tbody td .fw-bold {
                        font-weight: 400 !important;
                        color: inherit !important;
                    }
                    /* Estilo específico para el nombre del proyecto */
                    .project-name {
                        color: #17823d !important;
                        font-weight: 400 !important;
                    }
                    /* Estilo para la fila de totales */
                    .resumen-table tbody tr:last-child td {
                        color: #fff !important;
                        font-weight: 700 !important;
                        border-top: 3px solid #17823d;
                    }
                    .card {
                        border-radius: 1rem;
                        box-shadow: 0 2px 15px 0 rgba(60,72,88,.08);
                        border: none;
                    }
                    /* Estilos para evitar saltos de línea y mejorar la visualización */
                    .table-responsive {
                                        -webkit-overflow-scrolling: touch;
                                        padding-bottom: 2.5rem; /* space below the table to separate from footer */
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
                    /* Eliminar min-width para que el ancho se ajuste al texto del encabezado */
                    </style>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function(){
            // Pass PHP data into JS
            var projectData = <?= json_encode(array_map(function($r) {
                return [
                    'proyecto' => $r['PROYECTO'],
                    'nombre' => $r['nombre_proyecto'] ?? $r['PROYECTO'],
                    'area' => $r['ÁREA FUNCIONAL'],
                    'bac' => (float)$r['total_costo'],
                    'ac' => (float)$r['total_valorizado_2025']
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

            // Gráfica 'COMPARATIVO POR PROYECTO' eliminada
        })();
    </script>
</div>

        <!-- Modal para Presupuesto Compartido -->
        <div class="modal fade" id="modalPresupuesto" tabindex="-1" aria-labelledby="modalPresupuestoLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content" style="border: none; border-radius: 20px; box-shadow: 0 10px 50px rgba(0,0,0,0.2);">
                    <!-- Header mejorado -->
                    <div class="modal-header" style="background: linear-gradient(135deg, #17823d 0%, #2fa84f 100%); border: none; border-radius: 20px 20px 0 0; padding: 10px 20px;">
                        <div class="d-flex align-items-center gap-3" style="width: 100%;">
                            <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="white" class="bi bi-wallet2" viewBox="0 0 16 16">
                                    <path d="M12.136.326A1.5 1.5 0 0 1 14 1.78V3h.5A1.5 1.5 0 0 1 16 4.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 13.5v-9a1.5 1.5 0 0 1 1.432-1.499L12.136.326zM5.562 3H13V1.78a.5.5 0 0 0-.621-.484L5.562 3zM1.5 4a.5.5 0 0 0-.5.5v9a.5.5 0 0 0 .5.5h13a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 0-.5-.5h-13z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="modal-title" id="modalPresupuestoLabel" style="color: white; font-weight: 700; margin: 0; font-size: 1.15rem; letter-spacing: -0.5px;">CEDER PRESUPUESTO</h4>
                               <!-- <p style="color: rgba(255,255,255,0.9); font-size: 0.75rem; margin: 0; font-weight: 400;">Administre y registre las cesiones presupuestales entre áreas</p> -->
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" style="font-size: 1.2rem;"></button>
                    </div>
                    
                    <div class="modal-body" style="padding: 12px 18px; background: #fafbfc;">
                        <!-- Información del Proyecto - Mejorada -->
                        <div id="info-proyecto-presupuesto" class="row mb-2" style="display:none;">
                            <div class="col-12">
                                <div style="background: linear-gradient(135deg, #f0f9f6 0%, #e8f5ff 100%); padding: 10px 12px; border-radius: 10px; border: 1px solid #e0f2e9; box-shadow: 0 1px 8px rgba(23, 130, 61, 0.06);">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div style="background: #17823d; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <i class="bi bi-building" style="color: white; font-size: 1.2rem;"></i>
                                                </div>
                                                <div>
                                                    <p style="color: #777; font-size: 0.8rem; margin: 0 0 3px 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Proyecto</p>
                                                    <p id="nombreProyectoPresupuesto" style="color: #17823d; font-weight: 700; font-size: 1.15rem; margin: 0; line-height: 1.3;"></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center gap-3 justify-content-md-center mt-3 mt-md-0">
                                                <div style="background: #4C8AA3; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <i class="bi bi-piggy-bank" style="color: white; font-size: 1.5rem;"></i>
                                                </div>
                                                <div>
                                                    <p style="color: #777; font-size: 0.8rem; margin: 0 0 3px 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Presupuesto Total</p>
                                                    <p id="bacProyectoPresupuesto" style="color: #4C8AA3; font-weight: 700; font-size: 1.15rem; margin: 0;"></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center gap-3 justify-content-md-end mt-3 mt-md-0">
                                                <div style="background: #E1832F; width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <i class="bi bi-arrow-left-right" style="color: white; font-size: 1.5rem;"></i>
                                                </div>
                                                <div>
                                                    <p style="color: #777; font-size: 0.8rem; margin: 0 0 3px 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Presupuesto Cedido</p>
                                                    <p id="montoPresupuestoCedido" style="color: #E1832F; font-weight: 700; font-size: 1.15rem; margin: 0;">$ 0</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulario de Cesión - Mejorado -->
                        <div style="background: white; padding: 15px 20px; border-radius: 12px; margin-bottom: 15px; box-shadow: 0 2px 12px rgba(0,0,0,0.05);">
                            <div class="d-flex align-items-center gap-3 mb-4" style="padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                <div style="background: linear-gradient(135deg, #17823d 0%, #2fa84f 100%); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-plus-circle" style="color: white; font-size: 1.3rem;"></i>
                                </div>
                                <div>
                                  
                                    <p style="color: #888; font-size: 0.85rem; margin: 0;">Complete los datos para ceder presupuesto a otra área</p>
                                </div>
                            </div>
                            
                            <form id="form-insertar-presupuesto">
                                <input type="hidden" name="Centro_Costo" id="inputCentroCosto">
                                <input type="hidden" name="Area_Funcional" id="inputAreaFuncional" value="<?= htmlspecialchars($area_funcional) ?>">
                                
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label for="inputMontoPrestado" class="form-label d-flex align-items-center gap-2" style="color: #333; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                                            <i class="bi bi-cash-stack" style="color: #17823d; font-size: 1.2rem;"></i> 
                                            <span>Monto a Ceder <span style="color: #dc3545;">*</span></span>
                                        </label>
                                        <div style="position: relative;">
                                            <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #17823d; font-weight: 700; font-size: 1.1rem;">$</span>
                                            <input type="number" class="form-control" name="Monto_Prestado" id="inputMontoPrestado" required min="1" placeholder="Ingrese el monto" 
                                                style="padding: 14px 15px 14px 35px; border: 2px solid #e0e0e0; border-radius: 10px; font-weight: 600; font-size: 1rem; transition: all 0.3s;">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="inputAreaFuncionalSel" class="form-label d-flex align-items-center gap-2" style="color: #333; font-weight: 600; margin-bottom: 10px; font-size: 0.95rem;">
                                            <i class="bi bi-arrow-right-circle" style="color: #4C8AA3; font-size: 1.2rem;"></i> 
                                            <span>Área Destino <span style="color: #dc3545;">*</span></span>
                                        </label>
                                        <select class="form-select" name="Area_Funcional_Seleccionada" id="inputAreaFuncionalSel" required 
                                            style="border: 2px solid #e0e0e0; border-radius: 10px; padding: 14px 15px; font-weight: 500; font-size: 0.95rem; transition: all 0.3s;">
                                            <option value="" selected>Seleccione el área que recibirá el presupuesto</option>
                                            <option value="Vías y Topografía">Vías y Topografía</option>
                                            <option value="Geotecnia y Pavimentos">Geotecnia y Pavimentos</option>
                                            <option value="Eléctrica">Eléctrica</option>
                                            <option value="Hidráulica y Medio Ambiente">Hidráulica y Medio Ambiente</option>
                                            <option value="Arquitectura y Urbanismo">Arquitectura y Urbanismo</option>
                                            <option value="Mecánica">Mecánica</option>
                                            <option value="Estructuras">Estructuras</option>
                                            <option value="Dirección de Proyectos">Dirección de Proyectos</option>
                                            <option value="Vías">Vías</option>
                                            <option value="BIM">BIM</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3" style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #f0f0f0;">
                                    <button type="button" class="btn btn-lg" style="background: #e8e8e8; color: #555; border: none; padding: 8px 22px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s;" data-bs-dismiss="modal">
                                        <i class="bi bi-x-circle" style="margin-right: 8px;"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-lg" style="background: linear-gradient(135deg, #17823d 0%, #2fa84f 100%); color: white; border: none; padding: 8px 28px; border-radius: 10px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(23, 130, 61, 0.3); transition: all 0.3s;">
                                        <i class="bi bi-check2-circle" style="margin-right: 8px;"></i> Guardar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tabla de Cedencias Registradas - Mejorada -->
                        <div style="background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.05);">
                            <div class="d-flex align-items-center gap-3 mb-4" style="padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                <div style="background: linear-gradient(135deg, #4C8AA3 0%, #6ba8c4 100%); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-table" style="color: white; font-size: 1.3rem;"></i>
                                </div>
                                <div>
                                    <h5 style="color: #333; font-weight: 700; margin: 0; font-size: 1.1rem;">Montos Cedidos</h5>
                                    <p style="color: #888; font-size: 0.85rem; margin: 0;"></p>
                                </div>
                            </div>
                            
                            <div id="presupuesto-table-container" style="margin-top: 15px;">
                                <div class="text-center" style="padding: 60px 20px; color: #aaa;">
                                    <div style="animation: spin 1s linear infinite; display: inline-block;">
                                        <i class="bi bi-arrow-repeat" style="font-size: 3rem; opacity: 0.4;"></i>
                                    </div>
                                    <p style="margin-top: 15px; font-size: 0.95rem;">Cargando historial de cesiones...</p>
                                </div>
                                <style>
                                    @keyframes spin {
                                        from { transform: rotate(0deg); }
                                        to { transform: rotate(360deg); }
                                    }
                                </style>
                            <style>
                            /* Estilos mejorados para tabla presupuesto compartido */
                            #presupuesto-table-container {
                                max-height: 150px;
                                overflow-y: auto;
                            }
                            #presupuesto-table-container table {
                                width: 100%;
                                background: #fff;
                                border-radius: 12px;
                                overflow: hidden;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                                font-size: 0.92rem;
                                margin-bottom: 0;
                            }
                            #presupuesto-table-container th, #presupuesto-table-container td {
                                text-align: center;
                                vertical-align: middle;
                                padding: 14px 12px;
                                border: none;
                            }
                            #presupuesto-table-container thead {
                                position: sticky;
                                top: 0;
                                z-index: 10;
                            }
                            #presupuesto-table-container th {
                                background-color: #A6C2C9 !important;
                                color: #fff !important;
                                font-weight: 600;
                                font-size: 0.88rem;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            }
                            #presupuesto-table-container tbody tr {
                                border-bottom: 1px solid #f0f0f0;
                                transition: all 0.2s ease;
                            }
                            #presupuesto-table-container tbody tr:hover {
                                background: #f8fbfd !important;
                                transform: translateX(3px);
                                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                            }
                            #presupuesto-table-container tr:nth-child(even) {
                                background: #fafbfc;
                            }
                            #presupuesto-table-container tr:nth-child(odd) {
                                background: #fff;
                            }
                            #presupuesto-table-container td {
                                color: #333;
                                font-weight: 500;
                            }
                            #presupuesto-table-container td.td-monto_prestado {
                                color: #17823d;
                                font-weight: 700;
                                font-size: 1rem;
                            }
                            #presupuesto-table-container .btn-editar,
                            #presupuesto-table-container .btn-eliminar {
                                padding: 8px 12px;
                                border-radius: 8px;
                                border: 2px solid transparent;
                                transition: all 0.3s;
                            }
                            #presupuesto-table-container .btn-editar {
                                background: #e8f5e9;
                                border-color: #c8e6c9;
                            }
                            #presupuesto-table-container .btn-editar .bi-pencil-square {
                                color: #17823d;
                                font-size: 1.1rem;
                            }
                            #presupuesto-table-container .btn-eliminar {
                                background: #ffebee;
                                border-color: #ffcdd2;
                            }
                            #presupuesto-table-container .btn-eliminar .bi-trash-fill {
                                color: #c62828;
                                font-size: 1.1rem;
                            }
                            #presupuesto-table-container .btn-editar:hover {
                                background: #17823d !important;
                                border-color: #17823d !important;
                                transform: translateY(-2px);
                                box-shadow: 0 4px 12px rgba(23, 130, 61, 0.3);
                            }
                            #presupuesto-table-container .btn-editar:hover .bi-pencil-square {
                                color: #fff;
                            }
                            #presupuesto-table-container .btn-eliminar:hover {
                                background: #c62828 !important;
                                border-color: #c62828 !important;
                                transform: translateY(-2px);
                                box-shadow: 0 4px 12px rgba(198, 40, 40, 0.3);
                            }
                            #presupuesto-table-container .btn-eliminar:hover .bi-trash-fill {
                                color: #fff;
                            }
                            /* Estilos para botones de Guardar y Cancelar en edición */
                            #presupuesto-table-container .btn-guardar {
                                background: linear-gradient(135deg, #17823d 0%, #2fa84f 100%);
                                color: white;
                                border: none;
                                padding: 8px 16px;
                                border-radius: 8px;
                                cursor: pointer;
                                font-weight: 600;
                                box-shadow: 0 2px 8px rgba(23, 130, 61, 0.25);
                                transition: all 0.3s;
                            }
                            #presupuesto-table-container .btn-guardar:hover {
                                transform: translateY(-2px);
                                box-shadow: 0 4px 15px rgba(23, 130, 61, 0.4);
                            }
                            #presupuesto-table-container .btn-cancelar {
                                background: #e8e8e8;
                                color: #555;
                                border: none;
                                padding: 8px 16px;
                                border-radius: 8px;
                                cursor: pointer;
                                font-weight: 600;
                                transition: all 0.3s;
                            }
                            #presupuesto-table-container .btn-cancelar:hover {
                                background: #d0d0d0;
                            }
                            /* Estilos mejorados del Modal */
                            .modal-content {
                                border: none;
                                border-radius: 20px;
                                box-shadow: 0 10px 50px rgba(0,0,0,0.2);
                            }
                            .modal-header {
                                background: linear-gradient(135deg, #17823d 0%, #2fa84f 100%);
                                border: none;
                                border-radius: 20px 20px 0 0;
                                padding: 30px 35px;
                            }
                            .modal-header .btn-close-white {
                                filter: brightness(0) invert(1);
                                opacity: 1;
                            }
                            .modal-body {
                                padding: 35px;
                                background: #fafbfc;
                            }
                            .form-control, .form-select {
                                border: 2px solid #e0e0e0;
                                border-radius: 10px;
                                padding: 14px 15px;
                                font-weight: 500;
                                transition: all 0.3s;
                            }
                            .form-control:focus, .form-select:focus {
                                border-color: #17823d;
                                box-shadow: 0 0 0 4px rgba(23, 130, 61, 0.1);
                                outline: none;
                            }
                            .form-label {
                                color: #333;
                                font-weight: 600;
                                margin-bottom: 10px;
                                font-size: 0.95rem;
                            }
                            .modal-body .alert {
                                border-radius: 10px;
                                border: none;
                                padding: 15px 20px;
                                font-weight: 500;
                                margin-bottom: 20px;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                            }
                            .alert-success {
                                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                                color: #155724;
                            }
                            /* Mensaje vacío mejorado */
                            .mensaje-vacio {
                                text-align: center;
                                padding: 50px 20px;
                                color: #999;
                            }
                            .mensaje-vacio i {
                                font-size: 3rem;
                                opacity: 0.3;
                                margin-bottom: 15px;
                                display: block;
                            }
                            .mensaje-vacio p {
                                font-size: 1rem;
                                margin: 0;
                            }
                            </style>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS Bundle (incluye Popper) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('modalPresupuesto');
            var tableContainer = document.getElementById('presupuesto-table-container');
            var form = document.getElementById('form-insertar-presupuesto');
            var inputCentroCosto = document.getElementById('inputCentroCosto');
            var inputAreaFuncional = document.getElementById('inputAreaFuncional');
            var inputAreaFuncionalSel = document.getElementById('inputAreaFuncionalSel');
            var inputMontoPrestado = document.getElementById('inputMontoPrestado');

            // Al abrir el modal, cargar datos y setear campos
            var infoProyectoDiv = document.getElementById('info-proyecto-presupuesto');
            var nombreProyectoDiv = document.getElementById('nombreProyectoPresupuesto');
            var bacProyectoDiv = document.getElementById('bacProyectoPresupuesto');
            var montoPresupuestoCedidoDiv = document.getElementById('montoPresupuestoCedido');

            document.querySelectorAll('.btn-modal-presupuesto').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var centroCosto = btn.getAttribute('data-centro_costo');
                    var areaFuncional = btn.getAttribute('data-area_funcional');
                    var tr = btn.closest('tr');
                    // Buscar nombre del proyecto, BAC y monto cedido en la fila
                    var nombreProyecto = '';
                    var bac = '';
                    var montoCedido = '';
                    if (tr) {
                        var tds = tr.querySelectorAll('td');
                        // Si hay columna de área funcional
                        if (tds.length === 6) {
                            // [0]=area, [1]=ceco, [2]=nombre, [3]=bac, [4]=monto cedido, [5]=accion
                            nombreProyecto = tds[2].innerText;
                            bac = tds[3].innerText;
                            montoCedido = tds[4].innerText;
                        } else if (tds.length === 5) {
                            // [0]=ceco, [1]=nombre, [2]=bac, [3]=monto cedido, [4]=accion
                            nombreProyecto = tds[1].innerText;
                            bac = tds[2].innerText;
                            montoCedido = tds[3].innerText;
                        }
                    }
                    nombreProyectoDiv.textContent = nombreProyecto;
                    bacProyectoDiv.textContent = bac;
                    montoPresupuestoCedidoDiv.textContent = montoCedido !== '&nbsp;' && montoCedido.trim() !== '' ? montoCedido : '$ 0';
                    infoProyectoDiv.style.display = 'flex';
                    inputCentroCosto.value = centroCosto;
                    inputAreaFuncionalSel.value = '';
                    inputMontoPrestado.value = '';
                    form.reset();
                    cargarTablaPresupuesto(centroCosto);
                });
            });

            // Cargar tabla AJAX
            function cargarTablaPresupuesto(centroCosto) {
                tableContainer.innerHTML = '<div class="text-center" style="padding: 40px 20px; color: #999;"><i class="bi bi-hourglass-split" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>Cargando información...</div>';
                fetch('obtener_presupuesto_compartido.php?centro_costo=' + encodeURIComponent(centroCosto))
                    .then(res => res.text())
                    .then(html => {
                        tableContainer.innerHTML = html;
                        inicializarAccionesPresupuesto();
                    })
                    .catch(() => {
                        tableContainer.innerHTML = '<div class="alert alert-danger" style="padding: 25px; text-align: center; border-radius: 10px; margin: 20px 0;"><i class="bi bi-exclamation-triangle-fill" style="margin-right: 10px; font-size: 1.3rem;"></i><strong>Error de conexión</strong><br><span style="font-size: 0.9rem;">No se pudieron cargar los datos. Intente nuevamente.</span></div>';
                    });
            }

            // Inicializar acciones de editar y eliminar
            function inicializarAccionesPresupuesto() {
                // Eliminar
                tableContainer.querySelectorAll('.btn-eliminar').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        if (!confirm('⚠️ ¿Está seguro de eliminar esta cesión?\n\nEsta acción no se puede deshacer.')) return;
                        var tr = btn.closest('tr');
                        var id = tr.getAttribute('data-id');
                        fetch('eliminar_presupuesto_compartido.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'id=' + encodeURIComponent(id)
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                tr.remove();
                                // Mostrar mensaje de éxito
                                var messageDiv = document.createElement('div');
                                messageDiv.className = 'alert alert-success alert-dismissible fade show';
                                messageDiv.style = 'border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(21, 87, 36, 0.15);';
                                messageDiv.innerHTML = '<i class="bi bi-check-circle-fill" style="margin-right: 8px; font-size: 1.1rem;"></i> <strong>¡Eliminado!</strong> El registro se eliminó correctamente. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                                tableContainer.parentElement.insertBefore(messageDiv, tableContainer);
                                setTimeout(() => { messageDiv.remove(); location.reload(); }, 3000);
                            } else {
                                var errorDiv = document.createElement('div');
                                errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                                errorDiv.style = 'border-radius: 10px; margin-bottom: 20px;';
                                errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i> <strong>Error:</strong> ' + (data.message || 'No se pudo eliminar el registro') + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                                tableContainer.parentElement.insertBefore(errorDiv, tableContainer);
                                setTimeout(() => errorDiv.remove(), 4000);
                            }
                        })
                        .catch(() => alert('Error al eliminar.'));
                    });
                });
                // Editar
                tableContainer.querySelectorAll('.btn-editar').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var tr = btn.closest('tr');
                        var id = tr.getAttribute('data-id');
                        var areaFuncional = tr.querySelector('.td-area_funcional').innerText;
                        var montoPrestado = tr.querySelector('.td-monto_prestado').innerText.replace(/[^\d]/g, '');
                        var areaFuncionalSel = tr.querySelector('.td-area_funcional_sel').innerText;
                        // Reemplazar solo el monto por input, las demás columnas quedan como texto
                        tr.querySelector('.td-monto_prestado').innerHTML = '<input type="number" class="form-control form-control-sm" value="'+montoPrestado+'" min="1" style="border: 2px solid #17823d; font-weight: 600;">';
                        // Cambiar botones
                        btn.style.display = 'none';
                        var btnEliminar = tr.querySelector('.btn-eliminar');
                        btnEliminar.style.display = 'none';
                        var btnGuardar = document.createElement('button');
                        btnGuardar.className = 'btn btn-sm me-1 btn-guardar';
                        btnGuardar.style = 'background: #17823d; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600;';
                        btnGuardar.type = 'button';
                        btnGuardar.title = 'Guardar cambios';
                        btnGuardar.innerHTML = '<i class="bi bi-check2" style="margin-right: 3px;"></i> Guardar';
                        var btnCancelar = document.createElement('button');
                        btnCancelar.className = 'btn btn-sm btn-cancelar';
                        btnCancelar.style = 'background: #e0e0e0; color: #333; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600;';
                        btnCancelar.type = 'button';
                        btnCancelar.title = 'Cancelar edición';
                        btnCancelar.innerHTML = '<i class="bi bi-x" style="margin-right: 3px;"></i> Cancelar';
                        var tdAccion = btn.parentElement;
                        tdAccion.appendChild(btnGuardar);
                        tdAccion.appendChild(btnCancelar);
                        // Guardar cambios
                        btnGuardar.addEventListener('click', function() {
                            var montoPrestadoVal = tr.querySelector('.td-monto_prestado input').value;
                            fetch('actualizar_presupuesto_compartido.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'id='+encodeURIComponent(id)+'&Area_Funcional='+encodeURIComponent(areaFuncional)+'&Monto_Prestado='+encodeURIComponent(montoPrestadoVal)+'&Area_Funcional_Seleccionada='+encodeURIComponent(areaFuncionalSel)
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    tr.querySelector('.td-monto_prestado').innerText = '$ ' + parseInt(montoPrestadoVal).toLocaleString('es-CO');
                                    btn.style.display = '';
                                    btnEliminar.style.display = '';
                                    btnGuardar.remove();
                                    btnCancelar.remove();
                                    // Mostrar mensaje de éxito
                                    var messageDiv = document.createElement('div');
                                    messageDiv.className = 'alert alert-success alert-dismissible fade show';
                                    messageDiv.style = 'border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(21, 87, 36, 0.15);';
                                    messageDiv.innerHTML = '<i class="bi bi-check-circle-fill" style="margin-right: 8px; font-size: 1.1rem;"></i> <strong>¡Actualizado!</strong> Los cambios se guardaron correctamente. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                                    tableContainer.parentElement.insertBefore(messageDiv, tableContainer);
                                    setTimeout(() => { messageDiv.remove(); location.reload(); }, 3000);
                                } else {
                                    var errorDiv = document.createElement('div');
                                    errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                                    errorDiv.style = 'border-radius: 10px; margin-bottom: 20px;';
                                    errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i> <strong>Error:</strong> ' + (data.message || 'No se pudo actualizar el registro') + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                                    tableContainer.parentElement.insertBefore(errorDiv, tableContainer);
                                    setTimeout(() => errorDiv.remove(), 4000);
                                }
                            })
                            .catch(() => {
                                var errorDiv = document.createElement('div');
                                errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                                errorDiv.style = 'border-radius: 10px; margin-bottom: 20px;';
                                errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i> <strong>Error de conexión:</strong> No se pudo actualizar el registro. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                                tableContainer.parentElement.insertBefore(errorDiv, tableContainer);
                                setTimeout(() => errorDiv.remove(), 4000);
                            });
                        });
                        // Cancelar edición
                        btnCancelar.addEventListener('click', function() {
                            tr.querySelector('.td-monto_prestado').innerText = '$ ' + parseInt(montoPrestado).toLocaleString('es-CO');
                            btn.style.display = '';
                            btnEliminar.style.display = '';
                            btnGuardar.remove();
                            btnCancelar.remove();
                        });
                    });
                });
            }

            // Insertar registro AJAX
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(form);
                fetch('insertar_presupuesto_compartido.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        cargarTablaPresupuesto(inputCentroCosto.value);
                        form.reset();
                        inputCentroCosto.value = inputCentroCosto.value; // mantener el centro de costo
                        inputAreaFuncional.value = inputAreaFuncional.value; // mantener área funcional
                        // Mostrar mensaje de éxito
                        var messageDiv = document.createElement('div');
                        messageDiv.className = 'alert alert-success alert-dismissible fade show';
                        messageDiv.style = 'border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(21, 87, 36, 0.15);';
                        messageDiv.innerHTML = '<i class="bi bi-check-circle-fill" style="margin-right: 8px; font-size: 1.1rem;"></i> <strong>¡Cesión exitosa!</strong> El presupuesto se cedió correctamente. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        form.parentElement.insertBefore(messageDiv, form);
                        setTimeout(() => { messageDiv.remove(); location.reload(); }, 3000);
                    } else {
                        var errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                        errorDiv.style = 'border-radius: 10px; margin-bottom: 20px;';
                        errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i> <strong>Error:</strong> ' + (data.message || 'No se pudo registrar la cesión') + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                        form.parentElement.insertBefore(errorDiv, form);
                        setTimeout(() => errorDiv.remove(), 4000);
                    }
                })
                .catch(() => {
                    var errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger alert-dismissible fade show';
                    errorDiv.style = 'border-radius: 10px; margin-bottom: 20px;';
                    errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="margin-right: 8px;"></i> <strong>Error de conexión:</strong> No se pudo enviar los datos. Intente nuevamente. <button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    form.parentElement.insertBefore(errorDiv, form);
                    setTimeout(() => errorDiv.remove(), 4000);
                });
            });
        });
        </script>
</body>
</html>