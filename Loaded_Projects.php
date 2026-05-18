<?php
// resumen_proyectos.php
// Este archivo muestra un resumen ejecutivo por proyecto cargado

require_once 'include.php';
require_once 'config.php';

if (!isset($conn) || !$conn) {
    die("Error conexión: No se pudo conectar a la base de datos.");
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
    ) * `TARIFA COAN 2`) AS total_costo
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
" . $where_user . "
GROUP BY gp.PROYECTO, p.nombre_proyecto
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

// Obtener costo real aprobado desde horas_dia por CECO (codigo_affaire)
$sql_acum_real = "SELECT codigo_affaire AS ceco_conexion, SUM(tiempo_imputado_costo) AS costo_real
                  FROM horas_dia
                  WHERE Estado_Aprobacion = 'Aprobado'
                  GROUP BY codigo_affaire";
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
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* Burbujas animadas (efecto visual de fondo) */
        .bubbles-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .bubble {
            position: absolute;
            bottom: -120px;
            left: var(--left);
            width: calc(var(--size) * 1.3);
            height: calc(var(--size) * 1.3);
            background: rgba(23, 130, 61, 0.22); /* verde translúcido, menos notorio */
            border-radius: 50%;
            box-shadow: 0 8px 48px 0 rgba(23, 130, 61, 0.10), 0 0 32px 12px rgba(23, 130, 61, 0.07);
            filter: blur(2.5px) brightness(1.08); /* menos brillo y más blur */
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
                opacity: 0.5;
            }
            100% {
                transform: translateY(-110vh) scale(1.12);
                opacity: 0;
            }
        }
        .resumen-table th, .resumen-table td {
            border-right: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        .resumen-table th {
            background: #6FCF97; /* verde pastel oscuro, amigable y profesional */
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
<body class="container mt-5" style="background:#f4f6fa; position:relative; z-index:1;">
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
    <div class="mb-4" style="width:100%;">
    <div style="background:#fff; border-radius:18px; box-shadow:0 2px 12px 0 rgba(60,72,100,.08); padding:4px 64px 4px 0; display:flex; align-items:center; min-height:32px; width:100%; max-width:1600px;">
        <!-- <img src="logofza2.PNG" alt="Logo Forza" style="height:48px; width:auto; display:block; margin-left:12px;"> -->
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
        $regresar_href = 'Index2.php';
        if ($rol_usuario === 'SUPER') {
            $regresar_href = 'puente1.php';
        } elseif ($rol_usuario === 'MIX2') {
            $regresar_href = 'puente2.php';
        }
        ?>
        <a href="<?= $regresar_href ?>" class="btn btn-light border shadow-sm d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; color: #17823d; background: #eafaf1;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#17823d" viewBox="0 0 24 24">
                <path d="M15.5 5l-7 7 7 7" stroke="#17823d" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Regresar</span>
        </a>
        <a href="Index2.php" class="btn btn-success d-flex align-items-center gap-2" style="border-radius: 8px; font-weight: 500; background: linear-gradient(90deg, #6FCF97 0%, #17823d 100%); border: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#fff" viewBox="0 0 24 24">
                <path d="M12 16V4M12 4l-4 4m4-4l4 4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                <rect x="4" y="16" width="16" height="4" rx="2" fill="#fff" fill-opacity="0.2"/>
            </svg>
            <span style="color:#fff;">Subir Presupuesto</span>
        </a>
    </div>
    <h5 class="mb-4 d-flex align-items-center gap-2" style="font-weight:700; color:#495057; background: #e9ecef; padding: 10px 18px; border-radius: 8px; margin-top:32px;">
        <i class="bi bi-briefcase-fill" style="font-size: 1.4em; color: #495057;"></i>
        Presupuesto (BAC) Cargado por Proyecto
    </h5>
    <div class="mb-3 d-flex gap-2" style="max-width:600px;">
        <input type="text" id="buscador-proyecto" class="form-control" placeholder="Buscar por nombre de proyecto...">
        <a href="Proyectos_Cargados.php" class="btn btn-outline-secondary">Limpiar</a>
    </div>
    <div>
        <table class="table resumen-table align-middle text-center mb-0" id="tabla-proyectos" style="background:#fff; width: 100%;">
            <thead class="resumen-thead">
                <tr>
                    <th>PROYECTO</th>
                    <th>NOMBRE PROYECTO</th>
                    <th>TOTAL HORAS</th>
                    <th>PTO A TERMINACIÓN (BAC)</th>
                    <th>COSTO ACTUAL (AC)</th>
                    <th>SALDO POR EJECUTAR</th>
                    <th>% POR EJECUTAR</th>
                    <th>FECHA INICIO</th>
                    <th>FECHA FIN</th>
                    <th></th>
                    <th style="background-color:#17823d; color:#fff;" class="d-none">COSTO REAL APROBADO</th>
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
                            <td class="text-success">$ <?= number_format((int)$row['total_costo'], 0, '', '.') ?></td>
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
                                    $pto_val = isset($row['total_costo']) ? (float)$row['total_costo'] : null;
                                    if (!is_null($pto_val) && ($costo_actual > 0 || $costo_real > 0)) {
                                        $saldo = $pto_val - ((float)$costo_actual + (float)$costo_real);
                                        echo '$ ' . number_format((float)$saldo, 0, ',', '.');
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                ?>
                            </td>
                            <td class="text-center">
                                <?php
                                    // % por ejecutar = (SALDO POR EJECUTAR / PTO) * 100
                                    if (!is_null($pto_val) && $pto_val > 0 && ($costo_actual > 0 || $costo_real > 0)) {
                                        $saldo = $pto_val - ((float)$costo_actual + (float)$costo_real);
                                        $porcentaje = ($saldo / (float)$pto_val) * 100;
                                        $porInt = (int) round($porcentaje);
                                        $color = percentToColor($porInt);
                                        echo '<span style="color:'.htmlspecialchars($color).';font-weight:600;">' . $porInt . ' %</span>';
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['fecha_inicio']) ?></td>
                            <td><?= htmlspecialchars($row['fecha_fin']) ?></td>
                            <td>
                                <a href="Detalle_Proyecto.php?proyecto=<?= urlencode($row['PROYECTO']) ?>" class="btn btn-light border shadow-sm" title="Ver detalle" style="border-radius:50%;padding:6px 10px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#17823d" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zm-8 4.5c-2.485 0-4.5-2.015-4.5-4.5S5.515 3.5 8 3.5 12.5 5.515 12.5 8 10.485 12.5 8 12.5zm0-1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                                    </svg>
                                </a>
                            </td>
                            <td class="text-success d-none">
                                <?php
                                    if ($costo_real > 0) {
                                        echo '$ ' . number_format((float)$costo_real, 0, ',', '.');
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center">No hay datos para mostrar.</td></tr>
                <?php endif; ?>
                    <style>
                    .resumen-table {
                        background: #fff;
                        border-radius: 1rem;
                        overflow: hidden;
                        box-shadow: none;
                    }
                    .resumen-thead th {
                        background: #6FCF97; /* verde pastel oscuro, amigable y profesional */
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
                        min-width: 300px;
                        max-width: none;
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
                        min-width: 300px;
                        max-width: none;
                    }
                    .resumen-table th:nth-child(11),
                    .resumen-table td:nth-child(11) {
                        min-width: 170px;
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
