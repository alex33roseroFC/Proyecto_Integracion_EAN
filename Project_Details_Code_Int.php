<?php
// Detalle_Proyecto.php
// Muestra el detalle de un proyecto específico

require_once __DIR__ . '/include.php';
require_once __DIR__ . '/config.php';
// $conn ya está definido correctamente según el entorno

$proyecto = isset($_GET['proyecto']) ? $conn->real_escape_string($_GET['proyecto']) : '';
if (!$proyecto) {
    die("Proyecto no especificado.");
}
// Obtener el nombre del proyecto
$nombre_proyecto = '';
$sql_nombre = "SELECT nombre_proyecto FROM proyectos WHERE centro_costos = '".$proyecto."' LIMIT 1";
$result_nombre = $conn->query($sql_nombre);
if ($result_nombre && $row_nombre = $result_nombre->fetch_assoc()) {
    $nombre_proyecto = $row_nombre['nombre_proyecto'];
}
// Traer todas las filas del proyecto
$sql = "SELECT * FROM gastos_personal WHERE PROYECTO = '".$proyecto."'";
$result = $conn->query($sql);

$meses = [
    'ene25','feb25','mar25','abr25','may25','jun25','jul25','ago25','sep25','oct25','nov25','dic25',
    'ene26','feb26','mar26','abr26','may26','jun26','jul26','ago26','sep26','oct26','nov26','dic26',
    'ene27','feb27','mar27','abr27','may27','jun27','jul27','ago27','sep27','oct27','nov27','dic27',
    'ene28','feb28','mar28','abr28','may28','jun28','jul28','ago28','sep28','oct28','nov28','dic28'
];



$sql_costo_valorizado_areas = "SELECT 
    CECO_CONEXION, 
    `ÁREA FUNCIONAL`,
    SUM(acum_año_anterior + ene_25 + feb_25 + mar_25 + abr_25 + may_25 + jun_25 + jul_25 + ago_25 + sep_25 + oct_25 + nov_25 + dic_25) AS costo_total
FROM costo_valorizado
GROUP BY CECO_CONEXION, `ÁREA FUNCIONAL`";
$costos_actuales_areas = [];
$result_costo_areas = $conn->query($sql_costo_valorizado_areas);
if ($result_costo_areas && $result_costo_areas->num_rows > 0) {
    while($row_costo = $result_costo_areas->fetch_assoc()) {
        $ceco = $row_costo['CECO_CONEXION'];
        $area = isset($row_costo['ÁREA FUNCIONAL']) ? $row_costo['ÁREA FUNCIONAL'] : '';
        if (!isset($costos_actuales_areas[$ceco])) $costos_actuales_areas[$ceco] = [];
        $costos_actuales_areas[$ceco][$area] = $row_costo['costo_total'];
    }
}

// Costo real aprobado por CECO y área funcional desde horas_dia
$sql_costo_real_aprobado_areas = "SELECT 
    codigo_affaire AS CECO_CONEXION,
    area_funcional,
    SUM(tiempo_imputado_costo) AS costo_real_aprobado
FROM horas_dia
WHERE Estado_Aprobacion = 'Aprobado'
GROUP BY codigo_affaire, area_funcional";
$costos_reales_aprobados_areas = [];
$result_costo_real_areas = $conn->query($sql_costo_real_aprobado_areas);
if ($result_costo_real_areas && $result_costo_real_areas->num_rows > 0) {
    while($row_real_area = $result_costo_real_areas->fetch_assoc()) {
        $ceco = $row_real_area['CECO_CONEXION'];
        $area = isset($row_real_area['area_funcional']) ? $row_real_area['area_funcional'] : '';
        if (!isset($costos_reales_aprobados_areas[$ceco])) $costos_reales_aprobados_areas[$ceco] = [];
        $costos_reales_aprobados_areas[$ceco][$area] = $row_real_area['costo_real_aprobado'];
    }
}

// Consulta para obtener el total_aprobado por área funcional desde la vista aprobacion_area_funcional_proyecto

$sql_costo_imputado_aprobado = "SELECT 
    area_funcional,
    SUM(total_imputado) AS total_imputado
FROM aprobacion_area_funcional_proyecto
WHERE PROYECTO = '".$proyecto."'
GROUP BY area_funcional";
$costos_imputados_aprobados = [];
$result_aprobado = $conn->query($sql_costo_imputado_aprobado);
if ($result_aprobado && $result_aprobado->num_rows > 0) {
    while($row_aprobado = $result_aprobado->fetch_assoc()) {
        $area = isset($row_aprobado['area_funcional']) ? $row_aprobado['area_funcional'] : '';
        $costos_imputados_aprobados[$area] = $row_aprobado['total_imputado'];
    }
}


// Traer porcentajes de avance físico ejecutado y programado por área funcional para el proyecto desde la tabla correcta
$sql_avance = "SELECT AREA_FUNCIONAL, PORCENTAJE_AVANCE_FISICO_EJECUTADO, PORCENTAJE_AVANCE_FISICO_PROGRAMADO FROM avance_fisico_ejecutado_programado WHERE PROYECTO = '".$proyecto."'";
$avance_porcentajes = [];
$result_avance = $conn->query($sql_avance);
if ($result_avance && $result_avance->num_rows > 0) {
    while($row = $result_avance->fetch_assoc()) {
        $area = $row['AREA_FUNCIONAL'];
        $avance_porcentajes[$area] = [
            'ejecutado' => (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'],
            'programado' => (float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO']
        ];
    }
}

// --- Preparar tabla para mostrar ---
$cols_mostrar = [
    'PROYECTO','VERSION','FECHA VERSION','FECHA INICIO PROYECTO','FECHA FIN PROYECTO',
    'CATEGORIA','NOMBRE CATEGORIA','TARIFA COAN 2','ÁREA','ÁREA FUNCIONAL'
];
foreach ($meses as $m) { $cols_mostrar[] = $m; }
$filas = [];
$totales_col = array_fill_keys($cols_mostrar, 0);
$total_horas = 0;
$total_plata = 0;
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $filas[] = $row;
    }
}
?>
<!-- Encabezado con logo FORZA y botón Volver -->
<div class="mb-3" style="display:flex; justify-content:space-between; align-items:flex-start;">
    <div></div>
    <div class="card shadow-sm border-0" style="padding:0.5rem 1.2rem; border-radius:1rem; background:#fff; display:flex; align-items:center;">
        <a href="Proyectos_Cargados.php" class="btn" style="background:#38b48b; color:#fff; border:none; font-weight:bold; display:flex;align-items:center;gap:7px;">
            <i class="bi bi-arrow-bar-left" style="font-size:1.1rem;color:#fff;"></i>
            <span>Volver al resumen</span>
        </a>
    </div>
</div>

<div class="row g-4 mb-4 align-items-stretch justify-content-center resumen-cards-row">
    <!-- Tarjeta Proyecto -->
    <div class="col-lg-4 col-md-8 col-10 d-flex">
        <div class="card border-0 resumen-card h-100 w-100" style="background:linear-gradient(135deg,#e0f0f7 60%,#b2e2d1 100%);">
            <div class="card-body d-flex flex-column align-items-center justify-content-center" style="min-height:46px;">
                <?php if (!empty($nombre_proyecto)): ?>
                    <span class="fw-bold text-center" style="font-size:0.85rem; letter-spacing:0.5px; color:#6F6F6F;"> <?= htmlspecialchars($proyecto) ?> </span>
                    <span class="fw-bold text-success text-center mt-1" style="font-size:clamp(0.75rem, 1.2vw, 1rem); line-height:1.1; width:96%; word-break:break-word;"> <?= htmlspecialchars($nombre_proyecto) ?> </span>
                <?php else: ?>
                    <span class="fw-bold text-secondary">Proyecto no encontrado</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    // Calcular totales generales de horas y costo (todas las versiones)
    $total_horas = 0;
    $total_costo = 0;
    foreach ($filas as $f) {
        $tarifa = isset($f['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$f['TARIFA COAN 2'])) : 0;
        $suma_horas = 0;
        foreach ($meses as $m) {
            $c = isset($f[$m]) ? $f[$m] : '';
            $c_num = str_replace(",", ".", trim((string)$c));
            if (is_numeric($c_num)) {
                $suma_horas += (float)$c_num;
            }
        }
        $total_horas += $suma_horas;
        $total_costo += $suma_horas * (is_numeric($tarifa) ? (float)$tarifa : 0);
    }

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

// Costo real aprobado total por CECO desde horas_dia
$sql_costo_real_aprobado_total = "SELECT codigo_affaire AS CECO_CONEXION, SUM(tiempo_imputado_costo) AS costo_real_aprobado
FROM horas_dia
WHERE Estado_Aprobacion = 'Aprobado'
GROUP BY codigo_affaire";
$costos_reales_aprobados = [];
$result_costo_real_total = $conn->query($sql_costo_real_aprobado_total);
if ($result_costo_real_total && $result_costo_real_total->num_rows > 0) {
    while($row_real_total = $result_costo_real_total->fetch_assoc()) {
        $costos_reales_aprobados[$row_real_total['CECO_CONEXION']] = $row_real_total['costo_real_aprobado'];
    }
}
    ?>
    <!-- Tarjeta Horas -->  
    <div class="col-lg-2 col-md-4 col-6 d-flex">
        <div class="card border-0 resumen-card h-100 w-100" style="background:linear-gradient(135deg,#e0f0f7 60%,#b2e2d1 100%);">
            <div class="card-body text-center">
                <div class="mb-2" style="font-size:1.7rem;color:#16a34a;"><i class="bi bi-hourglass-split"></i></div>
                <div class="fw-bold text-secondary" style="font-size:0.95rem;">Horas</div>
                <div class="fs-5 fw-bold text-success"><?= number_format($total_horas,0,',','.') ?></div>
            </div>
        </div>
    </div>
    <!-- Tarjeta Costo -->
    <div class="col-lg-2 col-md-4 col-6 d-flex">
        <div class="card border-0 resumen-card h-100 w-100" style="background:linear-gradient(135deg,#fffbe6 60%,#ffe082 100%);">
            <div class="card-body text-center">
                <div class="mb-2" style="font-size:1.7rem;color:#f59e42;"><i class="bi bi-wallet2"></i></div>
                <div class="fw-bold text-secondary" style="font-size:0.95rem;">PTO (BAC)</div>
                <div class="fs-5 fw-bold text-warning"><?= '$ '.number_format($total_costo,0,',','.') ?></div>
            </div>
        </div>
    </div>
    <!-- Tarjeta Costo Actual -->
    <div class="col-lg-2 col-md-4 col-6 d-flex">
        <div class="card border-0 resumen-card h-100 w-100" style="background:linear-gradient(135deg,#e0f0f7 60%,#81d4fa 100%);">
            <div class="card-body text-center">
                <div class="mb-2" style="font-size:1.7rem;color:#0ea5e9;"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="fw-bold text-secondary" style="font-size:0.95rem;">Costo Actual (AC + Real Aprobado)</div>
                <div class="fs-5 fw-bold text-info">
                    <?php 
                        // Mostrar el costo actual total solo por CECO_CONEXION (proyecto)
                        $costo_actual_base = isset($costos_actuales[$proyecto]) ? (float)$costos_actuales[$proyecto] : 0;
                        $costo_real_aprobado = isset($costos_reales_aprobados[$proyecto]) ? (float)$costos_reales_aprobados[$proyecto] : 0;
                        $costo_actual = $costo_actual_base + $costo_real_aprobado;
                        if ($costo_actual > 0) {
                            echo '$ ' . number_format($costo_actual, 0, ',', '.');
                        } else {
                            echo '<span class="text-muted">-</span>';
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Tarjeta Saldo por Ejecutar -->
    <div class="col-lg-2 col-md-4 col-6 d-flex">
        <div class="card border-0 resumen-card h-100 w-100" style="background:linear-gradient(135deg,#e0f0f7 60%,#b2e2d1 100%);">
            <div class="card-body text-center">
                <div class="mb-2" style="font-size:1.7rem;color:#16a34a;"><i class="bi bi-bar-chart-line"></i></div>
                <div class="fw-bold text-secondary" style="font-size:0.95rem;">Saldo por Ejecutar</div>
                <div class="fs-5 fw-bold text-success">
                    <?php 
                        // Mostrar el saldo solo por CECO_CONEXION (proyecto)
                        $costo_actual_base = isset($costos_actuales[$proyecto]) ? (float)$costos_actuales[$proyecto] : 0;
                        $costo_real_aprobado = isset($costos_reales_aprobados[$proyecto]) ? (float)$costos_reales_aprobados[$proyecto] : 0;
                        $costo_actual = $costo_actual_base + $costo_real_aprobado;
                        $saldo = $total_costo - $costo_actual;
                        echo '$ ' . number_format($saldo, 0, ',', '.');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Gráficas principales y tarjeta de indicadores -->
<style>
.dashboard-flex-row {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: stretch;
}
.dashboard-flex-row > .grafica-col {
    flex: 1 1 0;
    min-width: 320px;
    display: flex;
    align-items: stretch;
}
.dashboard-flex-row > .indicadores-col {
    flex: 0 0 240px;
    max-width: 260px;
    min-width: 200px;
    display: flex;
    align-items: stretch;
}
.indicadores-card {
    background: #f5f8fc;
    width: 100%;
    min-width: 180px;
    max-width: 260px;
    min-height: 340px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 2px 12px 0 rgba(60,72,88,.10);
    border-radius: 1rem;
    border: none;
}
.indicadores-card .form-control,
.indicadores-card input[type="text"] {
    min-width: 80px;
    max-width: 100%;
    min-height: 25px;
    max-height: 32px;
    font-size: 1rem;
    text-align: center;
    font-weight: bold;
}
.indicadores-card .d-block {
    font-size: 1rem;
    font-weight: bold;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (max-width: 900px) {
    .dashboard-flex-row {
        flex-direction: column;
    }
    .dashboard-flex-row > .grafica-col, .dashboard-flex-row > .indicadores-col {
        min-width: 0;
        max-width: 100%;
    }
}
</style>
<div class="dashboard-flex-row mb-3">
    <div class="grafica-col">
        <div class="card h-100 w-100 grafica-card" style="background:linear-gradient(135deg,#f4f8fb 60%,#e6f7f1 100%);">
            <div class="card-body">
                <h6 class="card-title mb-3" style="font-size:1.1rem; color:#3a3a3a; font-weight:bold; display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-bar-chart-fill" style="font-size:1.2rem;color:#2563eb;"></i>
                    <span style="color:#3a3a3a;">Control de ejecución de costo de personal</span>
                </h6>
                <canvas id="graficaTotalArea" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="grafica-col">
        <div class="card h-100 w-100 grafica-card" style="background:linear-gradient(135deg,#f4f8fb 60%,#e6f7f1 100%);">
            <div class="card-body">
                <h6 class="card-title mb-3" style="font-size:1.1rem; color:#3a3a3a; font-weight:bold; display:flex;align-items:center;gap:8px;">
                    <i class="bi bi-graph-up-arrow" style="font-size:1.2rem;color:#16a34a;"></i>
                    <span style="color:#3a3a3a;">Control de Cambios por Área Funcional y Versión (Costo)</span>
                </h6>
                <canvas id="graficaControlCambios" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="indicadores-col">
        <div class="card border-0 shadow-sm indicadores-card" style="background:linear-gradient(135deg,#f4f8fb 60%,#e6f7f1 100%);">
            <div class="card-body" style="padding: 0.8rem;">
                <?php
                // Obtener todos los datos del cuadro, pero solo el campo programado es dinámico
                $avance_pdt = [
                    'programado' => null,
                    'ejecutado' => null,
                    'cpi' => null,
                    'spi' => null
                ];
                $sql_pdt = "SELECT porcentaje_avance_fisico_programado, porcentaje_avance_fisico_ejecutado FROM avance_pdt_proyecto WHERE Proyecto = '".$proyecto."' LIMIT 1";
                $result_pdt = $conn->query($sql_pdt);
                if ($result_pdt && $result_pdt->num_rows > 0) {
                    $row_pdt = $result_pdt->fetch_assoc();
                    $avance_pdt['programado'] = $row_pdt['porcentaje_avance_fisico_programado'];
                    $avance_pdt['ejecutado'] = $row_pdt['porcentaje_avance_fisico_ejecutado'];
                    // Calcular SPI (ejecutado / programado)
                    if (is_numeric($avance_pdt['programado']) && $avance_pdt['programado'] != 0) {
                        $avance_pdt['spi'] = $avance_pdt['ejecutado'] / $avance_pdt['programado'];
                    } else {
                        $avance_pdt['spi'] = null;
                    }
                    // Calcular CPI = (ejecutado_fraction * presupuesto_total) / costo_actual
                    // Asegurarse de que $total_costo y $costo_actual estén definidos
                    $costo_actual_base = isset($costos_actuales[$proyecto]) ? (float)$costos_actuales[$proyecto] : 0;
                    $costo_real_aprobado = isset($costos_reales_aprobados[$proyecto]) ? (float)$costos_reales_aprobados[$proyecto] : 0;
                    $costo_actual = $costo_actual_base + $costo_real_aprobado;
                    // convertir ejecutado a fracción si viene en 0..100
                    $ejec = $avance_pdt['ejecutado'];
                    if (is_numeric($ejec)) {
                        $ejec_num = (float)$ejec;
                        if ($ejec_num > 1) $ejec_num = $ejec_num / 100.0;
                    } else {
                        $ejec_num = null;
                    }
                    if (is_numeric($ejec_num) && $ejec_num !== null && $costo_actual > 0) {
                        // $total_costo ya calculado más arriba como PTO (BAC)
                        $avance_pdt['cpi'] = ($ejec_num * $total_costo) / $costo_actual;
                    } else {
                        $avance_pdt['cpi'] = null;
                    }
                }
                function format_percent($val) {
                    if (!is_numeric($val)) return '-';
                    $num = (float)$val;
                    // Si el valor está entre 0 y 1, se interpreta como proporción y se multiplica por 100
                    if ($num > 0 && $num <= 1) {
                        $num = $num * 100;
                    }
                    // Mostrar sin decimales
                    return number_format($num, 0, ',', '.') . '%';
                }
                function format_value($val) {
                    return is_numeric($val) ? number_format($val, 2, ',', '.') : $val;
                }
                ?>
                <div class="mb-2 text-center d-flex justify-content-center align-items-center gap-2" style="font-size:0.75rem;color:#4C8AA3;font-weight:600;background:#e6f7f1;border-radius:6px;padding:4px 0 2px 0;">
                    <i class="bi bi-calendar2-check" style="font-size:1rem;color:#4C8AA3;"></i>
                    <span>AVANCE PROGRAMADO PDT</span>
                </div>
                <input type="text" class="form-control text-center mb-2 fw-bold" value="<?= format_percent($avance_pdt['programado']) ?>" readonly style="font-size:0.85rem;height:25px;padding:0.15rem;background:#e6f7f1;border-radius:6px;">
                <div class="mb-2 text-center d-flex justify-content-center align-items-center gap-2" style="font-size:0.75rem;color:#4C8AA3;font-weight:600;background:#e6f4fb;border-radius:6px;padding:4px 0 2px 0;">
                    <i class="bi bi-flag-fill" style="font-size:1rem;color:#4C8AA3;"></i>
                    <span>AVANCE EJECUTADO PDT</span>
                </div>
                <input type="text" class="form-control text-center mb-2 fw-bold" value="<?= format_percent($avance_pdt['ejecutado']) ?>" readonly style="font-size:0.85rem;height:25px;padding:0.15rem;background:#e6f4fb;border-radius:6px;">
                <div class="mb-2 text-center d-flex justify-content-center align-items-center gap-2" style="font-size:0.75rem;color:#4C8AA3;font-weight:600;">
                    <i class="bi bi-speedometer2" style="font-size:1rem;color:#4C8AA3;"></i>
                    <span>CPI</span>
                </div>
                <div class="mb-2">
                    <?php
                    $cpi_bg = (is_numeric($avance_pdt['cpi']) && $avance_pdt['cpi'] < 1) ? '#e34c4c' : '#17823d';
                    ?>
                    <div style="background:<?= $cpi_bg ?>;border-radius:4px;height:25px;line-height:25px;">
                        <span class="d-block text-center fw-bold" style="color:#fff;font-size:0.85rem;"><?= format_value($avance_pdt['cpi']) ?></span>
                    </div>
                </div>
                <div class="mb-2 text-center d-flex justify-content-center align-items-center gap-2" style="font-size:0.85rem;color:#4C8AA3;font-weight:600;">
                    <i class="bi bi-graph-up" style="font-size:1rem;color:#4C8AA3;"></i>
                    <span>SPI</span>
                </div>
                <div>
                    <?php
                    $spi_bg = (is_numeric($avance_pdt['spi']) && $avance_pdt['spi'] < 1) ? '#e34c4c' : '#17823d';
                    ?>
                    <div style="background:<?= $spi_bg ?>;border-radius:4px;height:30px;line-height:30px;">
                        <span class="d-block text-center fw-bold" style="color:#fff;font-size:0.95rem;"><?= format_value($avance_pdt['spi']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Bootstrap Icons AR -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<?php

// Calcular el total por área funcional (sin versiones)
$totales_area = [];
foreach ($filas as $f) {
    $area = $f['ÁREA FUNCIONAL'];
    $tarifa = isset($f['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$f['TARIFA COAN 2'])) : 0;
    $suma_horas = 0;
    foreach ($meses as $m) {
        $c = isset($f[$m]) ? $f[$m] : '';
        $c_num = str_replace(",", ".", trim((string)$c));
        if (is_numeric($c_num)) {
            $suma_horas += (float)$c_num;
        }
    }
    if (!isset($totales_area[$area])) $totales_area[$area] = 0;
    $totales_area[$area] += $suma_horas * (is_numeric($tarifa) ? (float)$tarifa : 0);
}
// Unir todas las áreas posibles
$areas = array_keys($totales_area);
$areas_actual_valorizado = (isset($costos_actuales_areas[$proyecto]) && is_array($costos_actuales_areas[$proyecto])) ? array_keys($costos_actuales_areas[$proyecto]) : [];
$areas_actual_real = (isset($costos_reales_aprobados_areas[$proyecto]) && is_array($costos_reales_aprobados_areas[$proyecto])) ? array_keys($costos_reales_aprobados_areas[$proyecto]) : [];
$all_areas = array_unique(array_merge($areas, $areas_actual_valorizado, $areas_actual_real));
sort($all_areas);
$valores = array_map(function($a) use ($totales_area) {
    return (int)round((isset($totales_area[$a]) ? $totales_area[$a] : 0)/1000000,0);
}, $all_areas);
$valores_actual = array_map(function($a) use ($costos_actuales_areas, $costos_reales_aprobados_areas, $proyecto) {
    $ac_base_area = (isset($costos_actuales_areas[$proyecto][$a]) ? (float)$costos_actuales_areas[$proyecto][$a] : 0);
    $ac_real_aprobado_area = (isset($costos_reales_aprobados_areas[$proyecto][$a]) ? (float)$costos_reales_aprobados_areas[$proyecto][$a] : 0);
    return (int)round(($ac_base_area + $ac_real_aprobado_area)/1000000,0);
}, $all_areas);
// Nuevas series: Presupuesto x % Avance Físico Ejecutado y Programado
$valores_ejecutado = array_map(function($a) use ($totales_area, $avance_porcentajes) {
    $pto = isset($totales_area[$a]) ? (float)$totales_area[$a] : 0.0;
    $porc = isset($avance_porcentajes[$a]) ? (float)$avance_porcentajes[$a]['ejecutado'] : 0.0;
    // Si el porcentaje es mayor a 1, se asume que viene como 20 para 20%, y se divide entre 100
    if ($porc > 1) $porc = $porc / 100.0;
    $millones = ($pto * $porc) / 1000000.0;
    return $millones > 0 ? (int)round($millones, 0) : 0;
}, $all_areas);
$valores_programado = array_map(function($a) use ($totales_area, $avance_porcentajes) {
    $pto = isset($totales_area[$a]) ? (float)$totales_area[$a] : 0.0;
    $porc = isset($avance_porcentajes[$a]) ? (float)$avance_porcentajes[$a]['programado'] : 0.0;
    if ($porc > 1) $porc = $porc / 100.0;
    $millones = ($pto * $porc) / 1000000.0;
    return $millones > 0 ? (int)round($millones, 0) : 0;
}, $all_areas);
// Nueva serie: Costo Imputado Mes Aprobado
$valores_imputado_aprobado = array_map(function($a) use ($costos_imputados_aprobados) {
    $valor = isset($costos_imputados_aprobados[$a]) ? (float)$costos_imputados_aprobados[$a] : 0.0;
    return $valor > 0 ? (int)round($valor / 1000000.0, 0) : 0;
}, $all_areas);
if (count($all_areas) > 0 && (array_sum($valores) > 0 || array_sum($valores_actual) > 0)) {
    ?>

    <style>
    .resumen-card {
        box-shadow: 0 2px 12px 0 rgba(60,72,88,.10);
        border-radius: 1rem;
        transition: box-shadow 0.2s, transform 0.2s;
        background: #fff;
        border: none;
    }
    .resumen-card:hover {
        box-shadow: 0 8px 32px 0 rgba(60,72,88,.18);
        transform: scale(1.04);
        z-index: 2;
    }
    .grafica-card {
        box-shadow: 0 2px 12px 0 rgba(60,72,88,.10);
        border-radius: 1rem;
        transition: box-shadow 0.2s, transform 0.2s;
        background: #fff;
        border: none;
    }
    .grafica-card .card-title {
        transition: color 0.2s, text-shadow 0.2s;
    }
    .grafica-card:hover {
        box-shadow: 0 8px 32px 0 rgba(60,72,88,.18);
        transform: scale(1.025);
        z-index: 2;
    }
    .grafica-card:hover .card-title {
        color: #2563eb;
        text-shadow: 0 2px 12px rgba(60,72,88,.18);
    }
    @media (min-width: 768px) {
        .row.justify-content-center.mb-3 {
            display: flex;
            flex-wrap: nowrap;
        }
        .row.justify-content-center.mb-3 > [class^='col-'] {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }
    </style>
    <style>
    @media (min-width: 768px) {
        .row.justify-content-center.mb-3 > [class^='col-'] {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const datosTotalArea = {
            labels: <?php echo json_encode($all_areas); ?>,
            datasets: [
                {
                    label: 'Presupuesto (BAC)',
                    backgroundColor: '#23408e',
                    data: <?php echo json_encode($valores); ?>,
                    stack: 'stack0'
                },
                {
                    label: 'Costo Actual (AC + Real Aprobado)',
                    backgroundColor: '#3ec97a',
                    data: <?php echo json_encode($valores_actual); ?>,
                    stack: 'stack1'
                },
                {
                    label: 'Costo Cargado Mes',
                    backgroundColor: '#ff8c00',
                    data: <?php echo json_encode($valores_imputado_aprobado); ?>,
                    stack: 'stack1'
                },
                {
                    label: 'Costo Ejecutado PDT',
                    backgroundColor: '#84b3c9ff',
                    data: <?php echo json_encode($valores_ejecutado); ?>,
                    stack: 'stack2'
                },
                {
                    label: 'Costo Programado PDT',
                    backgroundColor: '#8a6cc4da', 
                    data: <?php echo json_encode($valores_programado); ?>,
                    stack: 'stack3'
                }
            ]
        };
        if (document.getElementById('graficaTotalArea')) {
            new Chart(document.getElementById('graficaTotalArea').getContext('2d'), {
                type: 'bar',
                data: datosTotalArea,
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: { display: true },
                        title: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.parsed.x;
                                    let formatted = '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                                    return `${label}: ${formatted}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: { display: false },
                            ticks: {
                                callback: v => '$' + v,
                                precision: 0
                            }
                        },
                        y: {
                            title: { display: false }
                        }
                    }
                }
            });
        }
    });
    </script>
    <?php
?>
<!-- La segunda gráfica ahora está al lado de la primera -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const datosGrafica = <?php echo json_encode([
        'labels' => $areas,
        'datasets' => $datasets
    ]); ?>;
    if (document.getElementById('graficaControlCambios')) {
        new Chart(document.getElementById('graficaControlCambios').getContext('2d'), {
            type: 'bar',
            data: datosGrafica,
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.parsed.x;
                                let formatted = '$' + value.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                                return `${label}: ${formatted}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Pesos ($)' },
                        ticks: { callback: v => '$' + v.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }
                    },
                    y: {
                        title: { display: false }
                    }
                }
            }
        });
    }
});
</script>
<?php } ?>
    <!-- Sección multipage (MENU 1 / MENU 2) - ubicada debajo de las gráficas -->
    <div class="mb-4" style="width: 100%; max-width: 100%; padding: 0; margin: 0; background: #f4f8fb; border-radius: 0.7rem;">
        <ul class="nav nav-tabs" id="multiMenuTabs" role="tablist" style="width: 100%;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="menu1-tab" data-tab="menu1" type="button" role="tab" aria-controls="menu1" aria-selected="true" style="display:flex;align-items:center;gap:6px;">
                    <i class="bi bi-clipboard-data" style="font-size:1.1rem;"></i>
                    <span>RESUMEN PRESUPUESTO CARGADO</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="menu2-tab" data-tab="menu2" type="button" role="tab" aria-controls="menu2" aria-selected="false" style="display:flex;align-items:center;gap:6px;">
                    <i class="bi bi-patch-check" style="font-size:1.1rem;"></i>
                    <span>APROBACIÓN</span>
                </button>
            </li>
        </ul>
        <div class="border border-top-0 rounded-bottom p-3" style="width: 100%; max-width: 100%; background: #fff;">
            <div id="menu1" class="tab-pane-content" style="width: 100%;">
                <!-- Contenido de MENU 1: se inserta dinámicamente el bloque de tablas y filtros -->
            </div>
            <div id="menu2" class="tab-pane-content d-none" style="width: 100%;">
                <?php
                            // Cargar desde la vista los campos solicitados para el proyecto actual
                            $vista_sql = "SELECT PROYECTO, nombre_proyecto, area_funcional, total_imputado, total_aprobado, porcentaje_aprobacion, porcentaje_aprobacion_director FROM aprobacion_area_funcional_proyecto WHERE PROYECTO = '" . $conn->real_escape_string($proyecto) . "'";
                            $vista_rs = $conn->query($vista_sql);
                            if ($vista_rs === false) {
                                echo '<div class="alert alert-danger">No se pudo consultar la vista: aprobacion_area_funcional_proyecto</div>';
                            } else if ($vista_rs->num_rows === 0) {
                                echo '<div class="alert alert-warning">Sin registros para este proyecto en la vista.</div>';
                            } else {
                                echo '<div class="table-responsive">';
                                // Estilo para filas inhabilitadas (0%)
                                echo '<style>.row-disabled{opacity:.6;filter:grayscale(100%);} .row-disabled td{color:#6c757d !important;}</style>';
                                echo '<table class="table table-sm table-bordered mb-0">';
                                echo '<thead class="table-light"><tr>';
                                // Ocultos para el usuario: PROYECTO, NOMBRE PROYECTO, TOTAL APROBADO
                                echo '<th>ÁREA FUNCIONAL</th>';
                                echo '<th>PRESUPUESTO (BAC)</th>';
                                echo '<th>COSTO ACTUAL (AC + REAL APROBADO)</th>';
                                echo '<th>TOTAL COSTO CARGADO</th>';
                                // echo '<th>% APROBACIÓN</th>'; // Ocultada
                                echo '<th style="background:#f8f9fa;">APROBACIÓN COORDINADOR</th>';
                                echo '<th style="background:#fff;">APROBACIÓN DIRECTOR</th>';
                                echo '<th>ACCIÓN</th>';
                                echo '</tr></thead><tbody>';
                                while ($r = $vista_rs->fetch_assoc()) {
                                    $p   = isset($r['PROYECTO']) ? $r['PROYECTO'] : '';
                                    $np  = isset($r['nombre_proyecto']) ? $r['nombre_proyecto'] : '';
                                    $af  = isset($r['area_funcional']) ? $r['area_funcional'] : '';
                                    $ti  = isset($r['total_imputado']) ? $r['total_imputado'] : null;
                                    $ta  = isset($r['total_aprobado']) ? $r['total_aprobado'] : null;
                                    $pa  = isset($r['porcentaje_aprobacion']) ? $r['porcentaje_aprobacion'] : null;
                                    $pad = isset($r['porcentaje_aprobacion_director']) ? $r['porcentaje_aprobacion_director'] : null;
                                    // Fallback: si la vista no trae porcentaje_aprobacion_director, calcularlo desde app_reporte_inputhh por proyecto + área
                                    if (!is_numeric($pad) && !empty($p) && !empty($af)) {
                                        $p_safe = $conn->real_escape_string($p);
                                        $af_safe = $conn->real_escape_string($af);
                                        $sql_pad = "SELECT CASE WHEN COUNT(*)>0 THEN (SUM(CASE WHEN aprobado_director=1 THEN 1 ELSE 0 END)/COUNT(*))*100 ELSE NULL END AS pct FROM app_reporte_inputhh WHERE codigo_affaire='".$p_safe."' AND area_funcional='".$af_safe."'";
                                        if ($rs_pad = $conn->query($sql_pad)) {
                                            if ($row_pad = $rs_pad->fetch_assoc()) {
                                                $pad = $row_pad['pct'];
                                            }
                                            $rs_pad->free();
                                        }
                                    }

                                    $fmt_ti = is_numeric($ti) ? '$ ' . number_format((float)$ti, 0, ',', '.') : '';
                                    $fmt_ta = is_numeric($ta) ? '$ ' . number_format((float)$ta, 0, ',', '.') : '';
                                    // Mostrar porcentaje como entero con símbolo %. Si viene en fracción (0..1), convertir a 0..100.
                                    $pa_num_cmp = null;
                                    if (is_numeric($pa)) {
                                        $pa_num_cmp = (float)$pa;
                                        if ($pa_num_cmp > 0 && $pa_num_cmp <= 1) { $pa_num_cmp = $pa_num_cmp * 100; }
                                        $fmt_pa = number_format($pa_num_cmp, 0, ',', '.') . '%';
                                    } else {
                                        $fmt_pa = '';
                                    }

                                    $is_zero = (is_numeric($pa_num_cmp) && (float)$pa_num_cmp == 0.0);
                                    $is_hundred = (is_numeric($pa_num_cmp) && (float)$pa_num_cmp == 100.0);
                                    $row_class = $is_zero ? 'row-disabled' : '';

                                    echo '<tr class="' . $row_class . '">';
                                    // Ocultos: PROYECTO ($p), NOMBRE PROYECTO ($np), TOTAL APROBADO ($fmt_ta)
                                    echo '<td>' . htmlspecialchars($af, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
                                    // Presupuesto (BAC) por área funcional
                                    $bac_area = isset($totales_area[$af]) ? (float)$totales_area[$af] : 0;
                                    $fmt_bac  = $bac_area > 0 ? ('$ ' . number_format($bac_area, 0, ',', '.')) : '<span class="text-muted">-</span>';
                                    echo '<td>' . $fmt_bac . '</td>';
                                    // Costo Actual (AC) por área funcional: costo_valorizado + horas_dia aprobado
                                    $ac_base_area = (isset($costos_actuales_areas[$proyecto]) && isset($costos_actuales_areas[$proyecto][$af])) ? (float)$costos_actuales_areas[$proyecto][$af] : 0;
                                    $ac_real_aprobado_area = (isset($costos_reales_aprobados_areas[$proyecto]) && isset($costos_reales_aprobados_areas[$proyecto][$af])) ? (float)$costos_reales_aprobados_areas[$proyecto][$af] : 0;
                                    $ac_area = $ac_base_area + $ac_real_aprobado_area;
                                    $fmt_ac  = $ac_area > 0 ? ('$ ' . number_format($ac_area, 0, ',', '.')) : '<span class="text-muted">-</span>';
                                    echo '<td>' . $fmt_ac . '</td>';
                                    // Total imputado (de la vista)
                                    echo '<td style=\"color:#17823d; font-weight:600;\">' . $fmt_ti . '</td>';
                                    // Columna APROBACIÓN COORDINADOR
                                    $bgcolor = $is_hundred ? '#d4edda' : '#ffe5b4'; // verde claro o naranja claro
                                    $check = $is_hundred ? '✔' : '';
                                    echo '<td style="background:' . $bgcolor . ';font-size:1.2em;text-align:center;">' . $check . '</td>';

                                    // Columna % APROBACIÓN DIRECTOR con check y fondo
                                    $pad_num_cmp = null;
                                    if (is_numeric($pad)) {
                                        $pad_num_cmp = (float)$pad;
                                        if ($pad_num_cmp > 0 && $pad_num_cmp <= 1) { $pad_num_cmp = $pad_num_cmp * 100; }
                                        $is_hundred_director = ((float)$pad_num_cmp == 100.0);
                                        $bgcolor_director = $is_hundred_director ? '#d4edda' : '#ffe5b4';
                                        $check_director = $is_hundred_director ? '✔' : '';
                                        echo '<td style="background:' . $bgcolor_director . ';font-size:1.2em;text-align:center;">' . $check_director . '</td>';
                                    } else {
                                        echo '<td style="background:#ffe5b4;text-align:center;"></td>';
                                    }
                                    // Botón con icono: activo solo si 100%
                                    if ($is_hundred) {
                                        echo '<td><button type="button" class="btn btn-sm btn-primary btn-open-director" data-np="' . htmlspecialchars($np, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-p="' . htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-af="' . htmlspecialchars($af, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-bs-toggle="modal" data-bs-target="#modalAprobacionDirector" title="Ver detalle"><i class="bi bi-eye"></i></button></td>';
                                    } else {
                                        echo '<td><button type="button" class="btn btn-sm btn-primary disabled" disabled title="Disponible al 100%"><i class="bi bi-eye"></i></button></td>';
                                    }
                                    echo '</tr>';
                                }
                                echo '</tbody></table></div>';
                            }
                            ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            var tabs = document.querySelectorAll('#multiMenuTabs .nav-link');
                
                // Restaurar la pestaña activa si existe en localStorage
                var savedTab = localStorage.getItem('activeTab');
                if (savedTab) {
                    tabs.forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
                    var savedButton = document.querySelector('#multiMenuTabs .nav-link[data-tab="' + savedTab + '"]');
                    if (savedButton) {
                        savedButton.classList.add('active');
                        savedButton.setAttribute('aria-selected','true');
                        document.querySelectorAll('.tab-pane-content').forEach(function(p){ p.classList.add('d-none'); });
                        var pane = document.getElementById(savedTab);
                        if (pane) { pane.classList.remove('d-none'); }
                    }
                    // Limpiar el localStorage después de restaurar
                    localStorage.removeItem('activeTab');
                }
                
                tabs.forEach(function(btn){
                    btn.addEventListener('click', function(){
                        // activar pestañas
                        tabs.forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
                        this.classList.add('active');
                        this.setAttribute('aria-selected','true');

                        // mostrar/ocultar contenidos
                        var target = this.getAttribute('data-tab');
                        document.querySelectorAll('.tab-pane-content').forEach(function(p){ p.classList.add('d-none'); });
                        var pane = document.getElementById(target);
                        if (pane) { pane.classList.remove('d-none'); }
                    });
                });

                // Mover el bloque existente de tablas y filtros dentro de MENU 1
                var source = document.getElementById('menu1_source_block');
                var menu1 = document.getElementById('menu1');
                if (source && menu1) {
                    try {
                        menu1.appendChild(source);
                    } catch (e) {
                        console.warn('No se pudo mover el bloque de tablas al MENU 1:', e);
                    }
                }
            });
        </script>

    <div class="mb-4" id="menu1_source_block" style="width: 100%; max-width: 100%;">
        <h5 class="mb-3" style="color:#3a3a3a;font-weight:bold;display:flex;align-items:center;gap:8px;">
            <i class="bi bi-clipboard-data" style="font-size:1.3rem;color:#6c757d;"></i>
            <span style="color:#3a3a3a;font-weight:bold;">RESUMEN PRESUPUESTO CARGADO</span>
        </h5>
        <!-- Filtros -->
        <div class="row mb-3">
            <div class="col-md-4">
                            <label for="filtroVersion" class="form-label mb-1" style="color:#3a3a3a;font-weight:bold;display:flex;align-items:center;gap:6px;">
                                <i class="bi bi-layers" style="font-size:1rem;color:#6c757d;"></i>
                                Filtrar por Versión
                            </label>
                            <select id="filtroVersion" class="form-select form-select-sm" style="color:#3a3a3a;font-weight:bold;">
                                <option value="">Todas</option>
                                <?php
                                $versiones = array_unique(array_column($filas, 'VERSION'));
                                sort($versiones);
                                foreach ($versiones as $ver) {
                                    echo '<option value="' . htmlspecialchars($ver) . '">' . htmlspecialchars($ver) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filtroAreaFuncional" class="form-label mb-1" style="color:#3a3a3a;font-weight:bold;display:flex;align-items:center;gap:6px;">
                                <i class="bi bi-diagram-3" style="font-size:1rem;color:#6c757d;"></i>
                                Filtrar por Área Funcional
                            </label>
                            <select id="filtroAreaFuncional" class="form-select form-select-sm" style="color:#3a3a3a;font-weight:bold;">
                                <option value="">Todas</option>
                                <?php
                                $areas = array_unique(array_column($filas, 'ÁREA FUNCIONAL'));
                                sort($areas);
                                foreach ($areas as $area) {
                                    echo '<option value="' . htmlspecialchars($area) . '">' . htmlspecialchars($area) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filtroCategoria" class="form-label mb-1" style="color:#3a3a3a;font-weight:bold;display:flex;align-items:center;gap:6px;">
                                <i class="bi bi-tags" style="font-size:1rem;color:#6c757d;"></i>
                                Filtrar por Categoría
                            </label>
                            <select id="filtroCategoria" class="form-select form-select-sm" style="color:#3a3a3a;font-weight:bold;">
                                <option value="">Todas</option>
                                <?php
                                $categorias = array_unique(array_column($filas, 'NOMBRE CATEGORIA'));
                                sort($categorias);
                                foreach ($categorias as $cat) {
                                    echo '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Tabla resumen por versión -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover align-middle shadow-sm" id="tablaResumenVersion">
                            <thead style="background: #7fd8b5;">
                                <tr>
                                    <th style="background:#38b48b;color:#fff;font-weight:bold;">PROYECTO</th>
                                    <th style="background:#38b48b;color:#fff;font-weight:bold;">VERSIÓN</th>
                                    <th style="background:#38b48b;color:#fff;font-weight:bold;">FECHA VERSIÓN</th>
                                    <th style="background:#38b48b;color:#fff;font-weight:bold;">HORAS</th>
                                    <th style="background:#38b48b;color:#fff;font-weight:bold;">COSTO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $resumenes = [];
                                foreach ($filas as $f) {
                                    $ver = $f['VERSION'];
                                    if (!isset($resumenes[$ver])) {
                                        $resumenes[$ver] = [
                                            'PROYECTO' => $f['PROYECTO'],
                                            'VERSION' => $ver,
                                            'FECHA VERSION' => $f['FECHA VERSION'],
                                            'HORAS' => 0,
                                            'COSTO' => 0
                                        ];
                                    }
                                    $tarifa = isset($f['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$f['TARIFA COAN 2'])) : 0;
                                    $suma_horas = 0;
                                    foreach ($meses as $m) {
                                        $c = isset($f[$m]) ? $f[$m] : '';
                                        $c_num = str_replace(",", ".", trim((string)$c));
                                        if (is_numeric($c_num)) {
                                            $suma_horas += (float)$c_num;
                                        }
                                    }
                                    $resumenes[$ver]['HORAS'] += $suma_horas;
                                    $resumenes[$ver]['COSTO'] += $suma_horas * (is_numeric($tarifa) ? (float)$tarifa : 0);
                                }
                                foreach ($resumenes as $r) {
                                    echo '<tr>';
                                    echo '<td>' . htmlspecialchars($r['PROYECTO']) . '</td>';
                                    echo '<td>' . htmlspecialchars($r['VERSION']) . '</td>';
                                    // Formatear la fecha de versión a d-m-Y si es válida
                                    $fecha_version = $r['FECHA VERSION'];
                                    $fecha_formateada = $fecha_version;
                                    if (!empty($fecha_version)) {
                                        $timestamp = strtotime($fecha_version);
                                        if ($timestamp !== false) {
                                            $fecha_formateada = date('d-m-Y', $timestamp);
                                        }
                                    }
                                    echo '<td>' . htmlspecialchars($fecha_formateada) . '</td>';
                                    echo '<td style="color:#17823d; font-weight:bold;">' . (int)$r['HORAS'] . '</td>';
                                    echo '<td style="color:#17823d; font-weight:bold;">$ ' . number_format((int)$r['COSTO'], 0, '', '.') . '</td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <h5 class="card-title mt-4 mb-3" style="color:#3a3a3a;font-weight:bold;display:flex;align-items:center;gap:8px;">
                        <i class="bi bi-clock-history" style="font-size:1.2rem;color:#6c757d;"></i>
                        <span style="color:#3a3a3a;font-weight:bold;">DETALLE PRESUPUESTO CARGADO EN HH</span>
                    </h5>
                    <div class="table-responsive mb-4" id="tablasDetalle" style="background:#f7fafc; border-radius:0.7rem; padding:1.2rem 0.7rem;">
                        <?php
                        // --- Tabla de detalle por horas (igual que antes) ---
                        $cols_mostrar = [
                            'PROYECTO','VERSION','FECHA VERSION','CATEGORIA','TARIFA COAN 2','NOMBRE CATEGORIA','ÁREA FUNCIONAL'
                        ];
                        $meses = [
                            'ene25','feb25','mar25','abr25','may25','jun25','jul25','ago25','sep25','oct25','nov25','dic25',
                            'ene26','feb26','mar26','abr26','may26','jun26','jul26','ago26','sep26','oct26','nov26','dic26',
                            'ene27','feb27','mar27','abr27','may27','jun27','jul27','ago27','sep27','oct27','nov27','dic27',
                            'ene28','feb28','mar28','abr28','may28','jun28','jul28','ago28','sep28','oct28','nov28','dic28'
                        ];
                        foreach ($meses as $m) { $cols_mostrar[] = $m; }
                        // Calcular totales por columna de mes (horas)
                        $totales_col = array_fill_keys($cols_mostrar, 0);
                        foreach ($filas as $cols) {
                            foreach ($meses as $m) {
                                $c = isset($cols[$m]) ? $cols[$m] : '';
                                $c_num = str_replace(",", ".", trim((string)$c));
                                if (is_numeric($c_num)) {
                                    $totales_col[$m] += (float)$c_num;
                                }
                            }
                        }
                        // Determinar meses a mostrar (solo los que tienen total distinto de 0)
                        $meses_mostrar = array_filter($meses, function($m) use ($totales_col) { return isset($totales_col[$m]) && $totales_col[$m] != 0; });
                        $cols_mostrar_final = array_merge([
                            'PROYECTO','VERSION','FECHA VERSION','CATEGORIA','TARIFA COAN 2','NOMBRE CATEGORIA','ÁREA FUNCIONAL'
                        ], $meses_mostrar);
                        $thead = "<tr>";
                        foreach ($cols_mostrar_final as $colname) {
                            $nombre_columna = $colname === 'TARIFA COAN 2' ? 'TARIFA COAN' : $colname;
                            $thead .= "<th style='background-color:#425466; color:#fff; font-weight:bold; white-space:nowrap; overflow:auto; word-break:break-all;'>" . htmlspecialchars($nombre_columna, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</th>";
                        }
                        $thead .= "</tr>";
                        $tbody = "";
                        $total_horas = 0;
                        $total_plata = 0;
                        if (empty($filas)) {
                            echo '<div class="alert alert-warning">No hay datos para este proyecto.</div>';
                        } else {
                        foreach ($filas as $cols) {
                            $col_h = isset($cols['TARIFA COAN 2']) ? trim((string)$cols['TARIFA COAN 2']) : '';
                            $row_html = ["<tr>"];
                            $suma_meses = 0;
                            foreach ($cols_mostrar_final as $colname) {
                                $c = isset($cols[$colname]) ? $cols[$colname] : '';
                                $c_trim = trim((string)$c);
                                $td_style = "white-space:nowrap; overflow:auto; word-break:break-all;";
                                if ($colname == 'FECHA VERSION') {
                                    $fecha_version = $c_trim;
                                    $fecha_formateada = $fecha_version;
                                    if (!empty($fecha_version)) {
                                        $timestamp = strtotime($fecha_version);
                                        if ($timestamp !== false) {
                                            $fecha_formateada = date('d-m-Y', $timestamp);
                                        }
                                    }
                                    $row_html[] = "<td style='$td_style'>" . htmlspecialchars($fecha_formateada, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                } else if (in_array($colname, $meses)) {
                                    $c_num = str_replace(",", ".", $c_trim);
                                    if (is_numeric($c_num) && (float)$c_num != 0) {
                                        $row_html[] = "<td style='color:#17823d; font-weight:bold; background-color:#d6e3dd; $td_style'>" . (int)$c_num . "</td>";
                                        $totales_col[$colname] += (float)$c_num;
                                        $total_horas += (float)$c_num;
                                        $suma_meses += (float)$c_num;
                                    } else {
                                        $row_html[] = "<td style='$td_style'>" . (is_numeric($c_num) ? (int)$c_num : htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</td>";
                                    }
                                } else if ($colname == 'TARIFA COAN 2') {
                                    $c_num = str_replace(",", ".", $c_trim);
                                    $row_html[] = "<td style='$td_style'>" . (is_numeric($c_num) ? '$ ' . number_format((int)$c_num, 0, '', '.') : htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</td>";
                                } else {
                                    $row_html[] = "<td style='$td_style'>" . htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                }
                            }
                            $row_html[] = "</tr>";
                            $tbody .= implode('', $row_html);
                            $tarifa = isset($cols['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$cols['TARIFA COAN 2'])) : 0;
                            $plata_fila = $suma_meses * (is_numeric($tarifa) ? (float)$tarifa : 0);
                            $total_plata += $plata_fila;
                        }
                        // Fila de totales por columna de mes
                        $row_total = ["<tr style='background-color:#e9ecef; font-weight:bold;'>"];
                        foreach ($cols_mostrar_final as $colname) {
                            if (in_array($colname, $meses)) {
                                $row_total[] = "<td style='white-space:nowrap; overflow:auto; word-break:break-all;'>" . (int)$totales_col[$colname] . "</td>";
                            } else {
                                $row_total[] = "<td style='white-space:nowrap; overflow:auto; word-break:break-all;'></td>";
                            }
                        }
                        $row_total[] = "</tr>";
                        $tbody .= implode('', $row_total);
                        echo '<div class="card shadow-sm rounded-4 mb-4 table-card"><div class="table-responsive table-detalle-scroll"><table class="table table-striped detalle-table align-middle mb-0 tabla-filtro" data-tipo="horas"><thead>' . $thead . '</thead><tbody>' . $tbody . '</tbody></table></div></div>';
                        }
                        ?>
                    </div>

                    <!-- Tabla de detalle por costo -->
                    <div class="table-responsive mt-5">
                        <?php
                        // --- Tabla de detalle por costo (tarifa * horas) ---
                        // Calcular totales por columna de mes (costo)
                        $totales_col_costo = array_fill_keys($cols_mostrar, 0);
                        foreach ($filas as $cols) {
                            $tarifa = isset($cols['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$cols['TARIFA COAN 2'])) : 0;
                            foreach ($meses as $m) {
                                $c = isset($cols[$m]) ? $cols[$m] : '';
                                $c_num = str_replace(",", ".", trim((string)$c));
                                $costo = (is_numeric($c_num) && is_numeric($tarifa)) ? ((float)$c_num * (float)$tarifa) : 0;
                                $totales_col_costo[$m] += $costo;
                            }
                        }
                        // Determinar meses a mostrar (solo los que tienen total distinto de 0 en costo)
                        $meses_mostrar_costo = array_filter($meses, function($m) use ($totales_col_costo) { return isset($totales_col_costo[$m]) && $totales_col_costo[$m] != 0; });
                        $cols_mostrar_final_costo = array_merge([
                            'PROYECTO','VERSION','FECHA VERSION','CATEGORIA','TARIFA COAN 2','NOMBRE CATEGORIA','ÁREA FUNCIONAL'
                        ], $meses_mostrar_costo);
                        $thead_costo = "<tr>";
                        foreach ($cols_mostrar_final_costo as $colname) {
                            $nombre_columna = $colname === 'TARIFA COAN 2' ? 'TARIFA COAN' : $colname;
                            $thead_costo .= "<th style='background-color:#17696a; color:#fff; font-weight:bold; white-space:nowrap; overflow:auto; word-break:break-all;'>" . htmlspecialchars($nombre_columna, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</th>";
                        }
                        $thead_costo .= "</tr>";
                        $tbody_costo = "";
                        $total_plata_costo = 0;
                        if (!empty($filas)) {
                            foreach ($filas as $cols) {
                                $tarifa = isset($cols['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$cols['TARIFA COAN 2'])) : 0;
                                $row_html = ["<tr>"];
                                foreach ($cols_mostrar_final_costo as $colname) {
                                    $c = isset($cols[$colname]) ? $cols[$colname] : '';
                                    $c_trim = trim((string)$c);
                                    $td_style = "white-space:nowrap; overflow:auto; word-break:break-all;";
                                    if ($colname == 'FECHA VERSION') {
                                        $fecha_version = $c_trim;
                                        $fecha_formateada = $fecha_version;
                                        if (!empty($fecha_version)) {
                                            $timestamp = strtotime($fecha_version);
                                            if ($timestamp !== false) {
                                                $fecha_formateada = date('d-m-Y', $timestamp);
                                            }
                                        }
                                        $row_html[] = "<td style='$td_style'>" . htmlspecialchars($fecha_formateada, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                    } else if (in_array($colname, $meses)) {
                                        $c_num = str_replace(",", ".", $c_trim);
                                        $costo = (is_numeric($c_num) && is_numeric($tarifa)) ? ((float)$c_num * (float)$tarifa) : 0;
                                        if ($costo > 0) {
                                            $row_html[] = "<td style='color:#17823d; font-weight:bold; background-color:#d6e3dd; $td_style'>$ " . number_format((int)$costo, 0, '', '.') . "</td>";
                                            $total_plata_costo += $costo;
                                        } else {
                                            $row_html[] = "<td style='$td_style'>$ " . (is_numeric($costo) ? (int)$costo : 0) . "</td>";
                                        }
                                    } else if ($colname == 'TARIFA COAN 2') {
                                        $c_num = str_replace(",", ".", $c_trim);
                                        $row_html[] = "<td style='$td_style'>" . (is_numeric($c_num) ? '$ ' . number_format((int)$c_num, 0, '', '.') : htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</td>";
                                    } else {
                                        $row_html[] = "<td style='$td_style'>" . htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                    }
                                }
                                $row_html[] = "</tr>";
                                $tbody_costo .= implode('', $row_html);
                            }
                            // Fila de totales por columna de mes (costo)
                            $row_total_costo = ["<tr style='background-color:#e9ecef; font-weight:bold;'>"];
                            foreach ($cols_mostrar_final_costo as $colname) {
                                if (in_array($colname, $meses)) {
                                    $row_total_costo[] = "<td style='white-space:nowrap; overflow:auto; word-break:break-all;'>$ " . number_format((int)$totales_col_costo[$colname], 0, '', '.') . "</td>";
                                } else {
                                    $row_total_costo[] = "<td style='white-space:nowrap; overflow:auto; word-break:break-all;'></td>";
                                }
                            }
                            $row_total_costo[] = "</tr>";
                            $tbody_costo .= implode('', $row_total_costo);
                            echo '<h5 class="card-title mt-4 mb-3" style="color:#3a3a3a;font-weight:bold;display:flex;align-items:center;gap:8px;">'
                                . '<i class="bi bi-currency-dollar" style="font-size:1.2rem;color:#6c757d;"></i>'
                                . '<span style="color:#3a3a3a;font-weight:bold;">DETALLE PRESUPUESTO CARGADO EN COSTO</span>'
                                . '</h5>';
                            echo '<div class="card shadow-sm rounded-4 mb-4 table-card"><div class="table-responsive mb-4 table-detalle-scroll"><table class="table table-striped detalle-table align-middle mb-0 tabla-filtro" data-tipo="costo"><thead>' . $thead_costo . '</thead><tbody>' . $tbody_costo . '</tbody></table></div></div>';
                        }
                        ?>
                    </div>
                    <script>
                    // Filtro dinámico para ambas tablas y resumen, y actualización de totales
                    document.addEventListener('DOMContentLoaded', function() {
                        function actualizarTotalesTabla(tabla, tipo) {
                            // tipo: 'horas' o 'costo'
                            var filas = tabla.querySelectorAll('tbody tr');
                            if (filas.length === 0) return;
                            // La última fila es la de totales
                            var totalRow = filas[filas.length - 1];
                            // Limpiar totales
                            var totales = Array.from(totalRow.cells).map(() => 0);
                            // Sumar solo filas visibles (excepto la de totales)
                            for (var i = 0; i < filas.length - 1; i++) {
                                if (filas[i].style.display === 'none') continue;
                                var celdas = filas[i].cells;
                                for (var j = 0; j < celdas.length; j++) {
                                    var celda = celdas[j];
                                    var val = celda.textContent.replace(/[^\d,.-]/g, '');
                                    // Si es tipo costo, quitar separadores de miles y convertir a float
                                    if (tipo === 'costo') {
                                        val = val.replace(/\./g, '').replace(/,/g, '.');
                                    }
                                    if (val && !isNaN(val)) {
                                        totales[j] += parseFloat(val);
                                    }
                                }
                            }
                            // Solo mostrar totales en columnas de meses (ene, feb, mar, ...)
                            var thead = tabla.querySelector('thead tr');
                            for (var j = 0; j < totales.length; j++) {
                                var celda = totalRow.cells[j];
                                var th = thead ? thead.cells[j] : null;
                                var esMes = th && /^(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic)\d{2}$/i.test(th.textContent.trim());
                                if (celda) {
                                    if (esMes) {
                                        if (tipo === 'costo') {
                                            celda.textContent = '$ ' + (totales[j] ? Math.round(totales[j]).toLocaleString('es-CO', {maximumFractionDigits:0}) : '0');
                                        } else if (tipo === 'horas') {
                                            celda.textContent = totales[j] ? Math.round(totales[j]) : '0';
                                        } else {
                                            celda.textContent = '';
                                        }
                                    } else {
                                        celda.textContent = '';
                                    }
                                }
                            }
                        }

                        function filtrarTablas() {
                            var ver = document.getElementById('filtroVersion').value.toLowerCase();
                            var cat = document.getElementById('filtroCategoria').value.toLowerCase();
                            var area = document.getElementById('filtroAreaFuncional').value.toLowerCase();
                            // Filtrar detalle
                            document.querySelectorAll('.tabla-filtro').forEach(function(tabla) {
                                var tipo = tabla.getAttribute('data-tipo');
                                var filas = tabla.querySelectorAll('tbody tr');
                                for (var i = 0; i < filas.length; i++) {
                                    // La última fila es la de totales
                                    if (i === filas.length - 1) {
                                        filas[i].style.display = '';
                                        continue;
                                    }
                                    var row = filas[i];
                                    var v = row.querySelector('td:nth-child(2)'); // VERSION
                                    var c = row.querySelector('td:nth-child(6)'); // NOMBRE CATEGORIA
                                    var a = row.querySelector('td:nth-child(7)'); // ÁREA FUNCIONAL
                                    var show = true;
                                    if (ver && v && v.textContent.toLowerCase() !== ver) show = false;
                                    if (cat && c && c.textContent.toLowerCase() !== cat) show = false;
                                    if (area && a && a.textContent.toLowerCase() !== area) show = false;
                                    row.style.display = show ? '' : 'none';
                                }
                                actualizarTotalesTabla(tabla, tipo);
                            });
                            // Filtrar resumen
                            document.querySelectorAll('#tablaResumenVersion tbody tr').forEach(function(row) {
                                var v = row.querySelector('td:nth-child(2)');
                                var show = true;
                                if (ver && v && v.textContent.toLowerCase() !== ver) show = false;
                                row.style.display = show ? '' : 'none';
                            });
                        }
                        document.getElementById('filtroVersion').addEventListener('change', filtrarTablas);
                        document.getElementById('filtroCategoria').addEventListener('change', filtrarTablas);
                        document.getElementById('filtroAreaFuncional').addEventListener('change', filtrarTablas);

                        // Al cargar la página, actualizar los totales de todas las tablas de detalle
                        document.querySelectorAll('.tabla-filtro').forEach(function(tabla) {
                            var tipo = tabla.getAttribute('data-tipo');
                            actualizarTotalesTabla(tabla, tipo);
                        });
                    });
                    </script>
    </div>
    <!-- Gráfica de control de cambios por versión y área funcional (por costo) -->
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const datosGrafica = <?php
            $data = [];
            foreach ($filas as $f) {
                $area = $f['ÁREA FUNCIONAL'];
                $ver = $f['VERSION'];
                $tarifa = isset($f['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$f['TARIFA COAN 2'])) : 0;
                $suma_horas = 0;
                foreach ($meses as $m) {
                    $c = isset($f[$m]) ? $f[$m] : '';
                    $c_num = str_replace(",", ".", trim((string)$c));
                    if (is_numeric($c_num)) {
                        $suma_horas += (float)$c_num;
                    }
                }
                $costo = $suma_horas * (is_numeric($tarifa) ? (float)$tarifa : 0);
                if (!isset($data[$area])) $data[$area] = [];
                if (!isset($data[$area][$ver])) $data[$area][$ver] = 0;
                $data[$area][$ver] += $costo;
            }
            $areas = array_keys($data);
            sort($areas);
            $versiones = [];
            foreach ($data as $area => $vers) {
                foreach (array_keys($vers) as $v) {
                    if (!in_array($v, $versiones)) $versiones[] = $v;
                }
            }
            sort($versiones);
            $datasets = [];
            foreach ($versiones as $i => $ver) {
                $color = [
                    '#17823d','#6cb3edff','#FFC107','#90CAF9','#81b583ff','#BDBDBD','#FF7043','#6D4C41'
                ];
                $datasets[] = [
                    'label' => 'V' . $ver,
                    'backgroundColor' => $color[$i % count($color)],
                    'data' => array_map(function($a) use ($ver, $data) {
                        return isset($data[$a][$ver]) ? round($data[$a][$ver]/1000000,0) : 0;
                    }, $areas)
                ];
            }
            echo json_encode([
                'labels' => $areas,
                'datasets' => $datasets
            ]);
        ?>;
        if (document.getElementById('graficaControlCambios')) {
            new Chart(document.getElementById('graficaControlCambios').getContext('2d'), {
                type: 'bar',
                data: datosGrafica,
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: false }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Millones' },
                            ticks: {
                                callback: v => v,
                                precision: 0
                            }
                        },
                        y: {
                            title: { display: false }
                        }
                    }
                }
            });
        }
    });
    </script>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle del Proyecto <?= htmlspecialchars($proyecto) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Mantener ancho estable del layout (evitar salto al abrir/cerrar modal) */
        html { scrollbar-gutter: stable both-edges; }
        /* Evitar saltos de layout al abrir el modal */
        body { overflow-y: scroll; }
        .modal-open { padding-right: 0 !important; }

        .detalle-table th, .detalle-table td {
            border-right: 1px solid #e0e0e0;
            vertical-align: middle;
            font-size: 0.92rem;
            font-family: 'Segoe UI', 'Roboto', 'Arial', sans-serif;
        }
        .detalle-table th {
            background: #4C8AA3;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 0.97rem;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .detalle-table td {
            color: #222;
            font-weight: 400;
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
            transition: background-color .15s ease, color .15s ease;
        }
        .table-detalle-scroll {
            max-height: 656px;
            overflow-y: auto;
            overflow-x: auto;
        }
        .detalle-table tbody tr:nth-child(even) {
            background: #f4f7fb;
        }
        .detalle-table tbody tr:nth-child(odd) {
            background: #fff;
        }
        .table-card {
            border-radius: 1rem;
            box-shadow: 0 2px 12px 0 rgba(60,72,88,.08);
            transition: box-shadow 0.2s;
            border: none;
        }
        .table-card:hover {
            box-shadow: 0 6px 24px 0 rgba(60,72,88,.16);
        }
        
    </style>
</head>
<body class="container-fluid mt-3" style="max-width: 92vw; padding-left: 3vw; padding-right: 3vw; margin: 0 auto; background:#f0f4fa;">
    
    </div>
    <!-- Tablas antiguas por mes eliminadas, solo se muestran las tablas detalladas -->
    <!-- Fragmento de tabla antigua eliminado: $totales_costo -->
    <!-- Modal: aprobacion_director.php -->
    <div class="modal fade" id="modalAprobacionDirector" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:100vw; margin:0; padding:1.5vw;">
            <div class="modal-content" style="border-radius:18px; box-shadow:0 0 24px 0 rgba(0,0,0,0.12);">
                <div class="modal-header" style="background: linear-gradient(90deg, #2f8aa1 0%, #1f8d5a 100%); color: #fff;">
                    <h5 class="modal-title" id="modalDirectorTitle">aprobacion_director.php</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="padding:2vw 1vw; overflow-x:auto;">
                    <!-- Contenido vacío por ahora -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalle de Empleado (para aprobación director) -->
    <div class="modal fade" id="modalDetalleEmpleado" tabindex="-1" aria-labelledby="modalDetalleEmpleadoLabel" aria-hidden="true" data-bs-backdrop="true" style="background-color: rgba(0, 0, 0, 0.7);">
        <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-scrollable" style="max-width: 95%;">
            <div class="modal-content" style="border: 3px solid #4C8AA3; box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);">
                <div class="modal-header" style="background: linear-gradient(135deg, #4C8AA3 0%, #17823d 100%); color: white; border-bottom: 3px solid #17823d;">
                    <h5 class="modal-title fw-bold" id="modalDetalleEmpleadoLabel">Detalle del Empleado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background-color: #f8f9fa;">
                    <p class="text-muted">Cargando...</p>
                </div>
                <div class="modal-footer" style="background-color: #e9ecef; border-top: 2px solid #dee2e6;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (para el modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función global para abrir el modal de detalle de empleado
        window.abrirModalDetalleEmpleado = function(btn) {
            const nombre = btn.dataset.nombre;
            const apellido = btn.dataset.apellido;
            const codigo = btn.dataset.codigo;
            const area = btn.dataset.area;
            
            // Actualizar título del modal
            let modalTitle = document.getElementById('modalDetalleEmpleadoLabel');
            if (!modalTitle) {
                console.error('No se encontró el título del modal');
                return;
            }
            modalTitle.textContent = 'Detalle: ' + apellido + ' ' + nombre;
            
            // Mostrar spinner mientras carga
            let modalBody = document.querySelector('#modalDetalleEmpleado .modal-body');
            if (!modalBody) {
                console.error('No se encontró el body del modal');
                return;
            }
            
            modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
            
            // Abrir el modal
            const modalElement = document.getElementById('modalDetalleEmpleado');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true
                });
                modal.show();
                
                // Cargar contenido del modal
                const url = 'modal_Detalle_Empleado_Director.php?nombre=' + encodeURIComponent(nombre) + '&apellido=' + encodeURIComponent(apellido) + '&codigo=' + encodeURIComponent(codigo) + '&area=' + encodeURIComponent(area);
                
                fetch(url)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Error al cargar los datos');
                        }
                        return response.text();
                    })
                    .then(function(html) {
                        modalBody.innerHTML = html;
                    })
                    .catch(function(error) {
                        console.error('Error:', error);
                        modalBody.innerHTML = '<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle"></i> Error al cargar el detalle: ' + error.message + '</div>';
                    });
            } else {
                console.error('No se encontró el modal');
            }
        };
        
        // Actualizar título del modal con el nombre del proyecto (o código) al abrirlo
        (function(){
            var modalEl = document.getElementById('modalAprobacionDirector');
            if (!modalEl) return;
            modalEl.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) return;
                var np = button.getAttribute('data-np') || '';
                var p  = button.getAttribute('data-p') || '';
                var af = button.getAttribute('data-af') || '';
                var title = np ? ('Detalle: ' + np) : (p ? ('Detalle: ' + p) : 'aprobacion_director.php');
                var titleNode = modalEl.querySelector('#modalDirectorTitle');
                if (titleNode) titleNode.textContent = title;

                // Cargar contenido del modal desde aprobacion_director.php con filtros
                var body = modalEl.querySelector('.modal-body');
                if (body) {
                    body.innerHTML = '<div class="d-flex justify-content-center align-items-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
                    var url = 'aprobacion_director.php?p=' + encodeURIComponent(p) + (af ? ('&af=' + encodeURIComponent(af)) : '');
                    fetch(url, { cache: 'no-store' })
                        .then(function(r){ return r.text(); })
                        .then(function(html){ body.innerHTML = html; })
                        .catch(function(){ body.innerHTML = '<div class="alert alert-danger">No se pudo cargar el detalle.</div>'; });
                }
            });
            // Forzar recálculo de tamaños de Chart.js al abrir/cerrar el modal
            function triggerChartsResize(){
                try {
                    window.dispatchEvent(new Event('resize'));
                } catch (e) {
                    // IE fallback
                    var evt = document.createEvent('UIEvents');
                    evt.initUIEvent('resize', true, false, window, 0);
                    window.dispatchEvent(evt);
                }
            }
            modalEl.addEventListener('shown.bs.modal', function(){
                document.body.style.paddingRight = '0px';
                triggerChartsResize();
            });
            modalEl.addEventListener('hidden.bs.modal', function(){
                document.body.style.paddingRight = '0px';
                setTimeout(triggerChartsResize, 50);
                // Guardar la pestaña activa antes de recargar
                var activeTab = document.querySelector('#multiMenuTabs .nav-link.active');
                if (activeTab) {
                    localStorage.setItem('activeTab', activeTab.getAttribute('data-tab'));
                }
                // Recargar la página para actualizar los datos de la tabla
                window.location.reload();
            });
        })();
    </script>

    

</body>
</html>
<?php $conn->close(); ?>
