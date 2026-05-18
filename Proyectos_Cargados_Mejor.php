<?php
// resumen_proyectos.php
// Este archivo muestra un resumen ejecutivo por proyecto cargado


require_once __DIR__ . '/vendor/autoload.php';
// Incluye la configuración centralizada para compatibilidad de entorno
require_once __DIR__ . '/include.php'; // crea $conn
if (!$conn || $conn->connect_error) {
    die("Error conexión: " . ($conn ? $conn->connect_error : 'No se pudo establecer la conexión.'));
}

// -------------------- ACTIVAR SESIÓN --------------------
//session_start();


session_start();
//echo '<pre>dashboard.php: '; print_r($_SESSION); echo '</pre>';
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}



$usuario_logueado = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
$rol_usuario = isset($_SESSION['rol']) ? strtoupper(trim($_SESSION['rol'])) : '';

// Obtener Nombre_Usuario y Área_Funcional desde la tabla login_usuarios
$nombre_usuario = '';
$area_funcional = '';
if ($usuario_logueado) {
    $sql_user = "SELECT Nombre_Usuario, Área_Funcional FROM login_usuarios WHERE usuario = '" . $conn->real_escape_string($usuario_logueado) . "' LIMIT 1";
    $result_user = $conn->query($sql_user);
    if ($result_user && $result_user->num_rows > 0) {
        $row_user = $result_user->fetch_assoc();
        $nombre_usuario = $row_user['Nombre_Usuario'];
        $area_funcional = $row_user['Área_Funcional'];
    }
}

// Filtro por coincidencias parciales de nombre de proyecto
$filtro_nombre = isset($_GET['filtro_nombre']) ? trim($_GET['filtro_nombre']) : '';
// Si el ROL es SUPER, no aplicar filtro por usuario (ver toda la información)
$where_user = ($rol_usuario === 'SUPER')
    ? ''
    : "WHERE gp.USUARIO = '" . $conn->real_escape_string($usuario_logueado) . "' ";

// Añadir filtro por nombre de proyecto si se proporciona
if ($filtro_nombre !== '') {
    $filtro_sql = " AND p.nombre_proyecto LIKE '%" . $conn->real_escape_string($filtro_nombre) . "%' ";
    if ($where_user === '') {
        $where_user = "WHERE 1=1" . $filtro_sql;
    } else {
        $where_user .= $filtro_sql;
    }
}


$sql = "SELECT 
    gp.PROYECTO,
    p.nombre_proyecto,
    MIN(gp.`FECHA INICIO PROYECTO`) AS fecha_inicio,
    MAX(gp.`FECHA FIN PROYECTO`) AS fecha_fin,
    SUM(
        gp.`ene25`+gp.`feb25`+gp.`mar25`+gp.`abr25`+gp.`may25`+gp.`jun25`+gp.`jul25`+gp.`ago25`+gp.`sep25`+gp.`oct25`+gp.`nov25`+gp.`dic25`+
        gp.`ene26`+gp.`feb26`+gp.`mar26`+gp.`abr26`+gp.`may26`+gp.`jun26`+gp.`jul26`+gp.`ago26`+gp.`sep26`+gp.`oct26`+gp.`nov26`+gp.`dic26`+
        gp.`ene27`+gp.`feb27`+gp.`mar27`+gp.`abr27`+gp.`may27`+gp.`jun27`+gp.`jul27`+gp.`ago27`+gp.`sep27`+gp.`oct27`+gp.`nov27`+gp.`dic27`+
        gp.`ene28`+gp.`feb28`+gp.`mar28`+gp.`abr28`+gp.`may28`+gp.`jun28`+gp.`jul28`+gp.`ago28`+gp.`sep28`+gp.`oct28`+gp.`nov28`+gp.`dic28`
    ) AS total_horas,
    SUM((
        `ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
        `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
        `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
        `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`
    ) * `TARIFA COAN 2`) AS total_costo,
    v.COSTO_TOTAL_MAX_VERSION,
    v.DIFERENCIA_COSTO_TOTAL,
    v.VERSION,
    v.`ESTADO APROBACION`
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
LEFT JOIN vw_validar_presupuesto v ON gp.PROYECTO = v.PROYECTO
" . $where_user . "
GROUP BY gp.PROYECTO, p.nombre_proyecto, v.COSTO_TOTAL_MAX_VERSION, v.DIFERENCIA_COSTO_TOTAL, v.VERSION, v.`ESTADO APROBACION`
ORDER BY fecha_inicio ASC;";



// Obtener costos actuales de costo_valorizado
$sql_costo_valorizado = "SELECT 
    CECO_CONEXION, 
    SUM(acum_año_anterior + ene_25 + feb_25 + mar_25 + abr_25 + may_25 + jun_25 + jul_25 + ago_25 + sep_25 + oct_25 + nov_25 + dic_25) AS costo_total
FROM costo_valorizado
GROUP BY CECO_CONEXION";
$costos_actuales = [];
$result_costo = $conn->query($sql_costo_valorizado);
if ($result_costo && $result_costo->num_rows > 0) {
    while($row_costo = $result_costo->fetch_assoc()) {
        $costos_actuales[$row_costo['CECO_CONEXION']] = $row_costo['costo_total'];
    }
}

// Obtener costos acumulados reales de acum_real_proyectos
$sql_acum_real = "SELECT ceco_conexion, SUM(costo) AS costo_real FROM acum_real_proyectos GROUP BY ceco_conexion";
$costos_reales = [];
$result_acum = $conn->query($sql_acum_real);
if ($result_acum && $result_acum->num_rows > 0) {
    while($row_real = $result_acum->fetch_assoc()) {
        $costos_reales[$row_real['ceco_conexion']] = $row_real['costo_real'];
    }
}


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
    
    <style>
                /* Ajustar celdas para evitar scroll horizontal y hacer la tabla flexible */
                .resumen-table {
                    width: 100% !important;
                    table-layout: auto !important;
                }
                .resumen-table th, .resumen-table td {
                    white-space: nowrap;
                    max-width: 160px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
        .resumen-table th, .resumen-table td {
            border-right: 0.25px solid #f0f0f0 !important; /* Línea divisoria gris muy clara y ultra delgada */
            border-left: 0.25px solid #f0f0f0 !important;  /* Línea divisoria gris muy clara y ultra delgada */
            vertical-align: middle;
        }
        .resumen-table th:first-child, .resumen-table td:first-child {
            border-left: none !important;
        }
        .resumen-table th:last-child, .resumen-table td:last-child {
            border-right: none !important;
        }
        .resumen-table th {
            background: #4C8AA3;
            color: #fff;
            font-weight: 600;
        }
        .resumen-table tbody tr:nth-child(even) {
            background: #f4f7fb;
        }
        .resumen-table tbody tr:nth-child(odd) {
            background: #fff;
        }
    </style>
</head>
<body class="container mt-5" style="background:#f4f6fa;">
    <div class="mb-4" style="width:100%;">
    <div style="background:#fff; border-radius:18px; box-shadow:0 2px 12px 0 rgba(60,72,100,.08); padding:4px 64px 4px 0; display:flex; align-items:center; min-height:32px; width:100%; max-width:1600px;">
        <img src="logofza2.PNG" alt="Logo Forza" style="height:48px; width:auto; display:block; margin-left:12px;">
        <div style="margin-left:auto; text-align:right; min-width:220px; display:flex; align-items:center; gap:16px;">
            <span style="font-weight:600; color:#4C8AA3; font-size:0.92em;">
                Nombre: <?php echo htmlspecialchars($nombre_usuario); ?>
            </span>
            <span style="color:#888; font-size:0.85em; margin-left:12px;">
                Área Funcional: <?php echo htmlspecialchars($area_funcional); ?>
            </span>
            <a href="logout.php" class="btn btn-danger" style="display:flex;align-items:center;gap:6px;padding:8px 18px;font-weight:500;font-size:1em;border-radius:6px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M10.146 12.354a.5.5 0 0 1 0-.708L12.293 9H5.5a.5.5 0 0 1 0-1h6.793l-2.147-2.146a.5.5 0 0 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0z"/>
                                            <path fill-rule="evenodd" d="M13 8a.5.5 0 0 1-.5.5H2.5a.5.5 0 0 1 0-1h10a.5.5 0 0 1 .5.5z"/>
                                        </svg>
                                        <span style="font-size:0.90em;">Cerrar sesión</span>
            </a>
        </div>
    </div>
    <h2 class="mb-4"> </h2>
    <div class="mb-3 d-flex gap-2">
        <?php
        $regresar_href = 'index2.php';
        if ($rol_usuario === 'SUPER') {
            $regresar_href = 'Puente1.php';
        } elseif ($rol_usuario === 'MIX2') {
            $regresar_href = 'Puente2.php';
        }
        ?>
        <a href="<?= $regresar_href ?>" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L2.707 7.5H14.5A.5.5 0 0 1 15 8z"/>
            </svg>
            Regresar
        </a>
        <a href="index2.php" class="btn btn-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-upload" viewBox="0 0 16 16">
                <path d="M.5 9.9a.5.5 0 0 1 .5-.5h4.5V1.5a.5.5 0 0 1 1 0v7.9h4.5a.5.5 0 0 1 0 1H6.5v7.1a.5.5 0 0 1-1 0V10.9H1a.5.5 0 0 1-.5-.5z"/>
                <path d="M7.646 4.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 5.707V13.5a.5.5 0 0 1-1 0V5.707L5.354 7.854a.5.5 0 1 1-.708-.708l3-3z"/>
            </svg>
            Subir Presupuesto
        </a>
    </div>
    <h5 class="mb-4" style="font-weight:600; color:#6F6F6F; margin-top:32px;">Presupuesto (BAC) Cargado por Proyecto</h5>
    <div class="mb-3 d-flex gap-2" style="max-width:600px;">
        <input type="text" id="buscador-proyecto" class="form-control" placeholder="Buscar por nombre de proyecto...">
        <a href="Proyectos_Cargados.php" class="btn btn-outline-secondary">Limpiar</a>
    </div>
    <div class="table-responsive" style="max-width:100vw;overflow-x:auto;">
        <table class="table resumen-table align-middle text-center mb-0" id="tabla-proyectos" style="background:#fff;">
            <thead class="resumen-thead">
                <tr>
                    <th>PROYECTO</th>
                    <th>NOMBRE PROYECTO</th>
                    <th>TOTAL HORAS</th>
                    <th>PTO A TERMINACIÓN (BAC)</th>
                    <th>COSTO ACTUAL (AC)</th>
                    <th>SALDO POR EJECUTAR</th>
                    <th>% POR EJECUTAR</th>
                    <th style="display:none;">COSTO TOTAL MAX VERSION</th>
                    <th style="display:none;">DIFERENCIA COSTO TOTAL</th>
                    <th style="display:none;">VERSION</th>
                    <th style="display:none;">ESTADO APROBACION</th>
                    
                    <th>FECHA INICIO</th>
                    <th>FECHA FIN</th>
                    <th>ACCIÓN</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="project-name"><?= htmlspecialchars($row['PROYECTO']) ?></span>
                            </td>
                            <td class="nombre-proyecto">
                                <?= htmlspecialchars($row['nombre_proyecto'] ?? '-') ?>
                            </td>
                            <td class="text-primary"><?= number_format((int)$row['total_horas'], 0, '', '.') ?></td>
                            <td class="text-success">
                                <?php
                                $costo_max_set = isset($row['COSTO_TOTAL_MAX_VERSION']) && $row['COSTO_TOTAL_MAX_VERSION'] !== '' && $row['COSTO_TOTAL_MAX_VERSION'] !== null;
                                $diferencia_set = isset($row['DIFERENCIA_COSTO_TOTAL']) && $row['DIFERENCIA_COSTO_TOTAL'] !== '' && $row['DIFERENCIA_COSTO_TOTAL'] !== null;
                                $estado_aprobacion = isset($row['ESTADO APROBACION']) ? trim($row['ESTADO APROBACION']) : '';
                                // Determinar el valor BAC mostrado
                                if ($costo_max_set && $diferencia_set && strcasecmp($estado_aprobacion, 'Aprobado') !== 0) {
                                    $pto_bac = (float)$row['DIFERENCIA_COSTO_TOTAL'];
                                    echo '$ ' . number_format($pto_bac, 0, '', '.');
                                } else {
                                    $pto_bac = (float)$row['total_costo'];
                                    echo '$ ' . number_format($pto_bac, 0, '', '.');
                                }
                                ?>
                            </td>
                            <td class="text-info">
                                <?php
                                    $costo_actual = isset($costos_actuales[$row['PROYECTO']]) ? $costos_actuales[$row['PROYECTO']] : 0;
                                    $costo_real = isset($costos_reales[$row['PROYECTO']]) ? $costos_reales[$row['PROYECTO']] : 0;
                                    $ac_total = (float)$costo_actual + (float)$costo_real;
                                    if ($ac_total > 0) {
                                        echo '$ ' . number_format($ac_total, 0, ',', '.');
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                ?>
                            </td>
                            <td class="text-warning">
                                <?php
                                    // Usar el mismo valor de BAC mostrado para el saldo
                                    if (isset($pto_bac)) {
                                        $saldo = $pto_bac - ((float)$costo_actual + (float)$costo_real);
                                        echo '$ ' . number_format((float)$saldo, 0, ',', '.');
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                ?>
                            </td>
                            <td class="text-center">
                                <?php
                                    // % por ejecutar = (costo_actual / BAC) * 100
                                    if (isset($pto_bac) && $pto_bac > 0 && ($costo_actual > 0 || $costo_real > 0)) {
                                        $porcentaje = ((float)$costo_actual + (float)$costo_real) / (float)$pto_bac * 100;
                                        $porInt = (int) round($porcentaje);
                                        $color = percentToColor($porInt);
                                        echo '<span style="color:'.htmlspecialchars($color).';font-weight:600;">' . $porInt . ' %</span>';
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                ?>
                            </td>
                            <td class="text-secondary" style="display:none;">
                                <?= isset($row['COSTO_TOTAL_MAX_VERSION']) ? '$ ' . number_format((float)$row['COSTO_TOTAL_MAX_VERSION'], 0, ',', '.') : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-secondary" style="display:none;">
                                <?= isset($row['DIFERENCIA_COSTO_TOTAL']) ? '$ ' . number_format((float)$row['DIFERENCIA_COSTO_TOTAL'], 0, ',', '.') : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="text-secondary" style="display:none;">
                                <?= htmlspecialchars($row['VERSION'] ?? '-') ?>
                            </td>
                            <td class="text-secondary" style="display:none;">
                                <?= htmlspecialchars($row['ESTADO APROBACION'] ?? '-') ?>
                            </td>
                            
                            <td><?= htmlspecialchars($row['fecha_inicio']) ?></td>
                            <td><?= htmlspecialchars($row['fecha_fin']) ?></td>
                            <td>
                                <a href="Detalle_Proyecto_2.php?proyecto=<?= urlencode($row['PROYECTO']) ?>" class="btn btn-light border shadow-sm" title="Ver detalle" style="border-radius:6px;padding:6px 10px;min-width:36px;min-height:36px;display:inline-flex;align-items:center;justify-content:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#17823d" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zm-8 4.5c-2.485 0-4.5-2.015-4.5-4.5S5.515 3.5 8 3.5 12.5 5.515 12.5 8 10.485 12.5 8 12.5zm0-1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                                    </svg>
                                </a>
                                <?php
                                $costo_max_set = isset($row['COSTO_TOTAL_MAX_VERSION']) && $row['COSTO_TOTAL_MAX_VERSION'] !== '' && $row['COSTO_TOTAL_MAX_VERSION'] !== null;
                                $diferencia_set = isset($row['DIFERENCIA_COSTO_TOTAL']) && $row['DIFERENCIA_COSTO_TOTAL'] !== '' && $row['DIFERENCIA_COSTO_TOTAL'] !== null;
                                $costo_max = $costo_max_set ? (float)$row['COSTO_TOTAL_MAX_VERSION'] : null;
                                $diferencia = $diferencia_set ? (float)$row['DIFERENCIA_COSTO_TOTAL'] : null;
                                $estado_aprobacion = isset($row['ESTADO APROBACION']) ? trim($row['ESTADO APROBACION']) : '';
                                if ($costo_max_set && $diferencia_set && $costo_max > $diferencia && strcasecmp($estado_aprobacion, 'Aprobado') !== 0) {
                                ?>
                                <a href="Detalle_Proyecto_para_Aprobar_pto.php?proyecto=<?= urlencode($row['PROYECTO']) ?>" class="btn btn-light border shadow-sm ms-2" title="Aprobación pendiente" style="border-radius:6px;padding:6px 10px;min-width:36px;min-height:36px;display:inline-flex;align-items:center;justify-content:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#f44336" viewBox="0 0 16 16">
                                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.707c.89 0 1.438-.99.982-1.767L8.982 1.566zm-1.482.874a.13.13 0 0 1 .232 0l6.853 11.667a.13.13 0 0 1-.116.193H1.531a.13.13 0 0 1-.116-.193L8.5 2.44zM8 5c-.535 0-.954.462-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 5zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                                    </svg>
                                </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center">No hay datos para mostrar.</td></tr>
                <?php endif; ?>
                    <style>
                    .resumen-table {
                        background: #fff;
                        border-radius: 1rem;
                        overflow: hidden;
                        box-shadow: none;
                        min-width: 800px;
                        width: auto;
                        max-width: 100vw;
                        table-layout: auto;
                    }
                    .resumen-thead th {
                        background: #4C8AA3;
                        color: #fff;
                        font-weight: 600;
                        border: none;
                        font-size: 0.85rem;
                        letter-spacing: .01em;
                        padding-top: 10px;
                        padding-bottom: 10px;
                        transition: background 0.2s, color 0.2s;
                        text-align: center;
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
                        padding-top: 10px;
                        padding-bottom: 10px;
                        text-align: center;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    /* Ajuste específico para la columna de nombre de proyecto */
                    .resumen-table td:nth-child(2) {
                        min-width: 180px;
                        max-width: 350px;
                        white-space: normal;
                        word-wrap: break-word;
                    }
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
                    .card {
                        border-radius: 1rem;
                        box-shadow: 0 2px 15px 0 rgba(60,72,88,.08);
                        border: none;
                    }
                    /* Estilos para evitar saltos de línea y mejorar la visualización */
                    .table-responsive {
                        -webkit-overflow-scrolling: touch;
                    }
                    .resumen-table.nowrap {
                        white-space: nowrap;
                    }
                    .resumen-table th {
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    /* Ajuste específico para el encabezado de nombre de proyecto */
                    .resumen-table th:nth-child(2) {
                        min-width: 180px;
                        max-width: 350px;
                    }
                    </style>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const buscador = document.getElementById('buscador-proyecto');
                        const tabla = document.getElementById('tabla-proyectos');
                        buscador.addEventListener('input', function() {
                            const filtro = buscador.value.toLowerCase();
                            const filas = tabla.querySelectorAll('tbody tr');
                            filas.forEach(fila => {
                                const nombre = fila.querySelector('.nombre-proyecto');
                                if (nombre && nombre.textContent.toLowerCase().includes(filtro)) {
                                    fila.style.display = '';
                                } else {
                                    fila.style.display = 'none';
                                }
                            });
                        });
                    });
                    </script>