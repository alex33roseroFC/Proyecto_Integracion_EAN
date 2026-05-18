
<?php
session_start();
require_once 'include.php';

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sesión no válida.';
    exit();
}

$usuario = $_SESSION['usuario'];
$area_funcional = '';
$rol_usuario = '';

if ($stmt = $conn->prepare("SELECT Área_Funcional, ROL FROM login_usuarios WHERE Usuario = ? LIMIT 1")) {
    $stmt->bind_param('s', $usuario);
    if ($stmt->execute()) {
        $stmt->bind_result($area_funcional_result, $rol_usuario_result);
        if ($stmt->fetch()) {
            $area_funcional = (string)$area_funcional_result;
            $rol_usuario = (string)$rol_usuario_result;
        }
    }
    $stmt->close();
}

$normalized_role = strtoupper(trim((string)$rol_usuario));
$is_super_user = ($normalized_role === 'SUPER');
$normalized_user = strtoupper(trim((string)$usuario));
$requested_area_filter = isset($_GET['area_funcional']) ? trim((string)$_GET['area_funcional']) : '';
$name_filter = isset($_GET['nombre_proyecto']) ? trim((string)$_GET['nombre_proyecto']) : '';

$base_dropdown_areas = [
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
$excluded_dropdown_areas = [
    'Planificación y Control',
    'Área_Prueba',
    'Tecnología'
];
$areas = array_values(array_filter($base_dropdown_areas, function($area) use ($excluded_dropdown_areas) {
    return !in_array($area, $excluded_dropdown_areas, true);
}));
$can_select_multiple_areas = $is_super_user;
$default_to_all_visible_areas = $is_super_user;

// dropdown especificos
if ($normalized_user === 'RARAQUE') {
    $areas = ['BIM', 'Vías y Topografía'];
    $can_select_multiple_areas = true;
    $default_to_all_visible_areas = true;
}

$build_area_sql_filter = function($connection, $column) use (&$area_filter, $default_to_all_visible_areas, $areas) {
    if (!empty($area_filter)) {
        return " AND $column = '" . $connection->real_escape_string($area_filter) . "' ";
    }
    if ($default_to_all_visible_areas && !empty($areas)) {
        $escaped = array_map(function($area) use ($connection) {
            return "'" . $connection->real_escape_string($area) . "'";
        }, $areas);
        return " AND $column IN (" . implode(', ', $escaped) . ") ";
    }
    return '';
};

$areas = array_values(array_unique($areas));
if (!$can_select_multiple_areas && !empty($area_funcional) && !in_array($area_funcional, $areas, true)) {
    array_unshift($areas, $area_funcional);
}

$area_filter = !empty($area_funcional) ? $area_funcional : '';
if ($default_to_all_visible_areas) {
    $area_filter = '';
    if ($requested_area_filter !== '' && in_array($requested_area_filter, $areas, true)) {
        $area_filter = $requested_area_filter;
    }
}

$areas_for_dropdown = $is_super_user ? $areas : array_values(array_unique(array_filter([$area_filter])));
if (empty($areas_for_dropdown)) {
    $areas_for_dropdown = $areas;
}

$sql_resumen = "SELECT 
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
    COALESCE(cv.total_valorizado_2025, 0) AS total_valorizado_2025,
    (SELECT SUM(
        (`ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
        `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
        `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
        `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`) * `TARIFA COAN 2`)
    FROM gastos_personal gp2
    WHERE gp2.PROYECTO = gp.PROYECTO AND gp2.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`
    ) AS total_costo,
    COALESCE(car.`Total_Costo_Asignado`, 0) AS TOTAL_COSTO_ASIGNADO,
    COALESCE((
        SELECT SUM(hd.`tiempo_imputado_costo`)
        FROM horas_dia hd
        WHERE hd.`codigo_affaire` = gp.PROYECTO
          AND hd.`area_funcional` = gp.`ÁREA FUNCIONAL`
          AND hd.`Estado_Aprobacion` = 'Aprobado'
    ), 0) AS total_costo_imputado_aprobado
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
LEFT JOIN `costo_asignado_resumen` car ON p.centro_costos = car.centro_costos AND gp.`ÁREA FUNCIONAL` = car.area_funcional
LEFT JOIN (
    SELECT 
        CECO_CONEXION, 
        `ÁREA FUNCIONAL`,
        SUM(`ene_25`+`feb_25`+`mar_25`+`abr_25`+`may_25`+`jun_25`+`jul_25`+`ago_25`+`sep_25`+`oct_25`+`nov_25`+`dic_25`) AS total_valorizado_2025
    FROM costo_valorizado
    GROUP BY CECO_CONEXION, `ÁREA FUNCIONAL`
) cv ON gp.PROYECTO = cv.CECO_CONEXION AND gp.`ÁREA FUNCIONAL` = cv.`ÁREA FUNCIONAL`
LEFT JOIN avance_fisico_ejecutado_programado afep ON gp.PROYECTO = afep.PROYECTO AND gp.`ÁREA FUNCIONAL` = afep.AREA_FUNCIONAL
WHERE 1=1 ";

$sql_resumen .= $build_area_sql_filter($conn, 'gp.`ÁREA FUNCIONAL`');

if ($name_filter !== '') {
    $sql_resumen .= " AND p.nombre_proyecto = '" . $conn->real_escape_string($name_filter) . "' ";
}

$sql_resumen .= "GROUP BY gp.PROYECTO, p.nombre_proyecto, p.nature_imputation, gp.`ÁREA FUNCIONAL`
ORDER BY gp.PROYECTO ASC, gp.`ÁREA FUNCIONAL` ASC;";

$result_resumen = $conn->query($sql_resumen);
$resumen_rows = [];
if ($result_resumen && $result_resumen->num_rows > 0) {
    while ($r = $result_resumen->fetch_assoc()) {
        $resumen_rows[] = $r;
    }
    $result_resumen->free();
}

$nombres_proyectos = [];
if (!empty($resumen_rows)) {
    foreach ($resumen_rows as $row) {
        if (!empty($row['nombre_proyecto']) && !empty($row['total_costo'])) {
            $nombres_proyectos[] = $row['nombre_proyecto'];
            break;
        }
    }
}
$nombres_proyectos = array_unique($nombres_proyectos);

ob_start();
?>
<div class="card p-4 mt-4" id="resumen-card">
    <div class="card-body">
        <h5 class="card-title mb-3">Resumen Ejecutivo de Proyectos</h5>
        <div class="mb-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto" style="min-width:250px;">
                    <label class="form-label">Área Funcional</label>
                    <select id="area_funcional_select" name="area_funcional" class="form-select" style="width:100%" onchange="this.form.submit()" <?= $can_select_multiple_areas ? '' : 'disabled' ?>>
                        <?php if ($default_to_all_visible_areas): ?>
                            <option value="" <?= $area_filter === '' ? 'selected' : '' ?>>-- Todas --</option>
                        <?php endif; ?>
                        <?php if (!empty($areas_for_dropdown)): ?>
                            <?php foreach($areas_for_dropdown as $area_option): ?>
                                <option value="<?= htmlspecialchars($area_option) ?>" <?= ($area_option === $area_filter) ? 'selected' : '' ?>><?= htmlspecialchars($area_option) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">Sin áreas disponibles</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$can_select_multiple_areas && $area_filter !== ''): ?>
                        <input type="hidden" name="area_funcional" value="<?= htmlspecialchars($area_filter) ?>">
                    <?php endif; ?>
                </div>
                <div class="col-auto" style="min-width:250px;">
                    <label class="form-label">Nombre Proyecto</label>
                    <select id="nombre_proyecto_select" name="nombre_proyecto" class="form-select" style="width:100%" onchange="this.form.submit()">
                        <option value="">-- Todos --</option>
                        <?php foreach($nombres_proyectos as $np): ?>
                            <option value="<?= htmlspecialchars($np) ?>" <?= ($np === $name_filter) ? 'selected' : '' ?>><?= htmlspecialchars($np) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <a href="Coordinador.php" class="btn btn-outline-secondary">Limpiar filtros</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table id="resumen-proyectos-table" class="table resumen-table align-middle text-center mb-0" style="background:#fff; width: 100%;">
                <style>
                #resumen-proyectos-table th:nth-child(2),
                #resumen-proyectos-table td:nth-child(2) {
                    width: auto;
                    max-width: none;
                    min-width: 40px;
                    text-align: center;
                    white-space: nowrap;
                }
                </style>
                <thead class="resumen-thead">
                    <tr>
                        <th>ÁREA FUNCIONAL</th>
                        <th>CECO</th>
                        <th>NOMBRE PROYECTO</th>
                        <th>NATURE IMPUTATION</th>
                        <th>TOTAL_COSTO_ASIGNADO</th>
                        <th>PTO A TERMINACIÓN (BAC)</th>
                        <th>COSTO ACTUAL (AC)</th>
                        <th>COSTO TEORICO</th>
                        <th>COSTO POR EJECUTAR (ETC)</th>
                        <th>% AVANCE FÍSICO EJECUTADO</th>
                        <th>% AVANCE FÍSICO PROGRAMADO</th>
                        <th>% COSTO COAN EJECUTADO</th>
                        <th>CPI</th>
                        <th>SPI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($resumen_rows)): ?>
                        <?php foreach($resumen_rows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '-') ?></td>
                                <td><span class="project-name"><?= htmlspecialchars($row['PROYECTO']) ?></span></td>
                                <td><?= htmlspecialchars($row['nombre_proyecto'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nature_imputation'] ?? '-') ?></td>
                                <?php
                                    $total_asignado = isset($row['TOTAL_COSTO_ASIGNADO']) ? (float)$row['TOTAL_COSTO_ASIGNADO'] : 0.0;
                                    $ac_ajustado_fila = (float)$row['total_valorizado_2025'] + (float)$row['total_costo_imputado_aprobado'];
                                ?>
                                <td class="text-success">$ <?= number_format($total_asignado, 0, '', '.') ?></td>
                                <td class="text-success">$ <?= number_format((float)$row['total_costo'], 0, '', '.') ?></td>
                                <td class="text-info">$ <?= number_format($ac_ajustado_fila, 0, '', '.') ?></td>
                                <?php 
                                    $costo_teorico = 0;
                                    $pct_ejecutado = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'];
                                    if ((float)$row['total_costo'] > 0) {
                                        $costo_teorico = ($pct_ejecutado / 100.0) * (float)$row['total_costo'];
                                    }
                                ?>
                                <td class="text-secondary">$ <?= number_format($costo_teorico, 0, '', '.') ?></td>
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
                                <td class="text-center" style="background-color: <?php 
                                    $cpi = 0;
                                    $ac = $ac_ajustado_fila;
                                    if ($ac > 0) {
                                        $cpi = $costo_teorico / $ac;
                                    }
                                    if ($cpi == 0) echo '#f8f9fa';
                                    else if ($cpi >= 1) echo '#98d8a7';
                                    else echo '#ffb3b3';
                                ?>; color: <?php 
                                    if ($cpi == 0) echo '#666666';
                                    else if ($cpi >= 1) echo '#2d5a3a';
                                    else echo '#8b0000';
                                ?>; font-weight: bold; padding: 5px;"><?php
                                    echo number_format($cpi, 2);
                                ?></td>
                                <td class="text-center" style="background-color: <?php 
                                    $spi = 0;
                                    if ((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'] > 0) {
                                        $spi = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'] / (float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'];
                                    }
                                    if ($spi == 0) echo '#f8f9fa';
                                    else if ($spi >= 1) echo '#98d8a7';
                                    else echo '#ffb3b3';
                                ?>; color: <?php 
                                    if ($spi == 0) echo '#666666';
                                    else if ($spi >= 1) echo '#2d5a3a';
                                    else echo '#8b0000';
                                ?>; font-weight: bold; padding: 5px;"><?php
                                    echo number_format($spi, 2);
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="14" class="text-center">No hay datos para mostrar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$html = ob_get_clean();
header('Content-Type: text/html; charset=utf-8');
echo $html;
exit();
?>