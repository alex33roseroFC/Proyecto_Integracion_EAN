<?php
// Limpiador de caché SiteGround (SG Cache) al inicio absoluto
if (function_exists('sg_cachepress_purge_cache')) {
    sg_cachepress_purge_cache();
}
if (!headers_sent()) {
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}

// Evitar salida de HTML/JS si es petición AJAX
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ajax']) && $_POST['ajax'] === '1'
) {
    if (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    require_once 'include.php';
    require_once 'config.php';
    $response = ['success' => false];
    // ...existing AJAX handling code...
    if (isset($_POST['accion']) && $_POST['accion'] === 'masivo' && isset($_POST['numero_empleado']) && (isset($_POST['aprobado_coordinador']) || isset($_POST['rechazado_coordinador'])) && isset($_POST['codigo_affaire']) && isset($_POST['area_funcional'])) {
        $numero_empleado = $conn->real_escape_string($_POST['numero_empleado']);
        $codigo_affaire = $conn->real_escape_string($_POST['codigo_affaire']);
        $area_funcional = $conn->real_escape_string($_POST['area_funcional']);
        if (isset($_POST['aprobado_coordinador'])) {
            $aprobado = $_POST['aprobado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET aprobado_coordinador=$aprobado, rechazado_coordinador=0 WHERE numero_empleado='$numero_empleado' AND codigo_affaire='$codigo_affaire' AND area_funcional='$area_funcional' AND Estado_Aprobacion = 'Aprobado En Curso'";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        } elseif (isset($_POST['rechazado_coordinador'])) {
            $rechazado = $_POST['rechazado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET rechazado_coordinador=$rechazado, aprobado_coordinador=0 WHERE numero_empleado='$numero_empleado' AND codigo_affaire='$codigo_affaire' AND area_funcional='$area_funcional' AND Estado_Aprobacion = 'Aprobado En Curso'";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        }
    }
    elseif (isset($_POST['id'])) {
        $id = intval($_POST['id']);
        if (isset($_POST['aprobado_coordinador'])) {
            $aprobado = $_POST['aprobado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET aprobado_coordinador=$aprobado, rechazado_coordinador=0 WHERE id=$id";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        } elseif (isset($_POST['rechazado_coordinador'])) {
            $rechazado = $_POST['rechazado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET rechazado_coordinador=$rechazado, aprobado_coordinador=0 WHERE id=$id";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        } elseif (isset($_POST['comentario_coordinador'])) {
            $comentario = $conn->real_escape_string($_POST['comentario_coordinador']);
            $sql = "UPDATE horas_dia SET comentario_coordinador='$comentario' WHERE id=$id";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        }
    }
    echo json_encode($response);
    $conn->close();
    exit;
}
?>
<script>
// Manejo masivo de aprobaciones/rechazos por colaborador
document.querySelectorAll('.aprobado-coord').forEach(function(chk) {
    chk.addEventListener('change', function() {
        var numero_empleado = this.getAttribute('data-numero-empleado');
        var codigo_affaire = this.getAttribute('data-codigo-affaire');
        var area_funcional = this.getAttribute('data-area-funcional');
        var value = this.checked ? '1' : '0';
        if (this.checked) {
            if (!window.confirm('¿Está seguro que desea aprobar todos los registros de este colaborador?')) {
                this.checked = false;
                return;
            }
        }
        // Desmarcar rechazado de la misma fila
        var row = this.closest('tr');
        if (row) {
            var rech = row.querySelector('.rechazado-coord');
            if (this.checked && rech) rech.checked = false;
        }
        // Actualizar todos los registros internos de este colaborador y área/proyecto
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('numero_empleado', numero_empleado);
        formData.append('aprobado_coordinador', value);
        formData.append('accion', 'masivo');
        formData.append('codigo_affaire', codigo_affaire);
        formData.append('area_funcional', area_funcional);
        fetch('Aprobacion_lucca_Gasto_General.php', {method:'POST', body:formData})
            .then(r=>r.json())
            .then(data=>{ showStatus(this, data.success, data.error); actualizarResumenCostos(); })
            .catch(e=>{ showStatus(this, false, e.message); actualizarResumenCostos(); });
    });
});
document.querySelectorAll('.rechazado-coord').forEach(function(chk) {
    chk.addEventListener('change', function() {
        var numero_empleado = this.getAttribute('data-numero-empleado');
        var codigo_affaire = this.getAttribute('data-codigo-affaire');
        var area_funcional = this.getAttribute('data-area-funcional');
        var value = this.checked ? '1' : '0';
        if (this.checked) {
            if (!window.confirm('¿Está seguro que desea rechazar todos los registros de este colaborador?')) {
                this.checked = false;
                return;
            }
        }
        // Desmarcar aprobado de la misma fila
        var row = this.closest('tr');
        if (row) {
            var apr = row.querySelector('.aprobado-coord');
            if (this.checked && apr) apr.checked = false;
        }
        // Actualizar todos los registros internos de este colaborador y área/proyecto
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('numero_empleado', numero_empleado);
        formData.append('rechazado_coordinador', value);
        formData.append('accion', 'masivo');
        formData.append('codigo_affaire', codigo_affaire);
        formData.append('area_funcional', area_funcional);
        fetch('Aprobacion_lucca_Gasto_General.php', {method:'POST', body:formData})
            .then(r=>r.json())
            .then(data=>{ showStatus(this, data.success, data.error); actualizarResumenCostos(); })
            .catch(e=>{ showStatus(this, false, e.message); actualizarResumenCostos(); });
    });
});
</script>
<?php
// Limpiador de caché SiteGround (SG Cache)
if (function_exists('sg_cachepress_purge_cache')) {
    sg_cachepress_purge_cache();
}
// Alternativa: limpiar caché por header si SG Cache no está disponible
if (!headers_sent()) {
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}
// ...existing code...
require_once 'include.php';
require_once 'config.php';
// Endpoint AJAX para guardar (individual o masivo)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ajax']) && $_POST['ajax'] === '1'
) {
    if (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $response = ['success' => false];
    // Actualización masiva por colaborador y área/proyecto
    if (isset($_POST['accion']) && $_POST['accion'] === 'masivo' && isset($_POST['numero_empleado']) && (isset($_POST['aprobado_coordinador']) || isset($_POST['rechazado_coordinador'])) && isset($_POST['codigo_affaire']) && isset($_POST['area_funcional'])) {
        $numero_empleado = $conn->real_escape_string($_POST['numero_empleado']);
        $codigo_affaire = $conn->real_escape_string($_POST['codigo_affaire']);
        $area_funcional = $conn->real_escape_string($_POST['area_funcional']);
        if (isset($_POST['aprobado_coordinador'])) {
            $aprobado = $_POST['aprobado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET aprobado_coordinador=$aprobado, rechazado_coordinador=0 WHERE numero_empleado='$numero_empleado' AND codigo_affaire='$codigo_affaire' AND area_funcional='$area_funcional' AND Estado_Aprobacion = 'Aprobado En Curso'";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        } elseif (isset($_POST['rechazado_coordinador'])) {
            $rechazado = $_POST['rechazado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET rechazado_coordinador=$rechazado, aprobado_coordinador=0 WHERE numero_empleado='$numero_empleado' AND codigo_affaire='$codigo_affaire' AND area_funcional='$area_funcional' AND Estado_Aprobacion = 'Aprobado En Curso'";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        }
    }
    // Actualización individual (por id)
    elseif (isset($_POST['id'])) {
        $id = intval($_POST['id']);
        if (isset($_POST['aprobado_coordinador'])) {
            $aprobado = $_POST['aprobado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET aprobado_coordinador=$aprobado, rechazado_coordinador=0 WHERE id=$id";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        } elseif (isset($_POST['rechazado_coordinador'])) {
            $rechazado = $_POST['rechazado_coordinador'] === '1' ? 1 : 0;
            $sql = "UPDATE horas_dia SET rechazado_coordinador=$rechazado, aprobado_coordinador=0 WHERE id=$id";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        } elseif (isset($_POST['comentario_coordinador'])) {
            $comentario = $conn->real_escape_string($_POST['comentario_coordinador']);
            $sql = "UPDATE horas_dia SET comentario_coordinador='$comentario' WHERE id=$id";
            $response['success'] = $conn->query($sql) ? true : false;
            if(!$response['success']) $response['error'] = $conn->error;
        }
    }
    echo json_encode($response);
    $conn->close();
    exit;
}

// Mostrar la tabla editable directamente
$proyecto_codigo = isset($_GET['proyecto']) ? $conn->real_escape_string($_GET['proyecto']) : '';
$area_funcional = isset($_GET['area']) ? $conn->real_escape_string($_GET['area']) : '';
// Agrupar por empleado y sumar horas/costo
$sql = "SELECT 
    hd.numero_empleado AS numero_de_empleado,
    UPPER(TRIM(hd.nombre_completo)) AS nombre_completo,
    vdma.horas_activas AS horas_asignadas_mes,
    vdma.costo_activo AS costo_asignado_mes,
    SUM(hd.tiempo_imputado_horas) AS total_horas,
    SUM(IFNULL(hd.tiempo_imputado_costo,0)) AS total_costo,
    MAX(hd.aprobado_coordinador) AS aprobado_coordinador,
    MAX(hd.rechazado_coordinador) AS rechazado_coordinador,
    GROUP_CONCAT(DISTINCT hd.comentario_coordinador SEPARATOR '; ') AS comentarios
FROM horas_dia hd
LEFT JOIN vista_detalle_mes_activo vdma ON vdma.matricula = hd.numero_empleado AND vdma.centro_costos = '$proyecto_codigo'
WHERE hd.codigo_affaire='$proyecto_codigo' AND hd.area_funcional='$area_funcional' AND hd.Estado_Aprobacion = 'Aprobado En Curso'
GROUP BY hd.numero_empleado, nombre_completo, vdma.horas_activas, vdma.costo_activo";
$result = $conn->query($sql);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/checkbox-blue.css">
<style>
/* Checkboxes deshabilitados en gris */
input[type="checkbox"].form-check-input:disabled {
    background-color: #e9ecef !important;
    border-color: #adb5bd !important;
    opacity: 1 !important;
    cursor: not-allowed;
    box-shadow: none;
}
</style>
<style>
/* Encabezados de la tabla GASTO GENERAL más oscuros */
#tabla-aprobacion thead th {
    background-color: #181818 !important; /* casi negro */
    color: #fff !important;
    font-weight: bold;
    border-color: #222 !important;
    z-index: 2;
}
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<div class="container-fluid mt-4 px-3">
        <!-- Modal Detalle -->
            <div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
                <div class="modal-dialog modal-xxl-custom">
</style>
<style>
/* Modal más ancho personalizado */
/* Modal ancho pero no tanto */
.modal-xxl-custom {
    max-width: 85vw;
    width: 85vw;
    min-width: 900px;
}
@media (max-width: 1200px) {
    .modal-xxl-custom {
        max-width: 100vw;
        width: 100vw;
        min-width: unset;
    }
}
</style>
                    <div class="modal-content">
                        <div class="modal-header p-2" style="background:#249c5b;color:#fff;align-items:center;">
                            <i class="bi bi-info-circle-fill fs-3 me-2"></i>
                            <h5 class="modal-title mb-0" id="modalDetalleLabel" style="font-weight:bold;"></h5>
                            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body pb-0">
                            <div id="detalle-contenido">Cargando...</div>
                        </div>
                        <div class="modal-footer justify-content-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span class="me-2">&larr;</span>Regresar</button>
                        </div>
                    </div>
                </div>
            </div>
    <h4 class="mb-3">Aprobación de Costos Cargados por Colaborador</h4>
    <div class="row mb-2">
        <div class="col-12">
            <div style="background:#f5f1ed;border-radius:7px;padding:18px 18px 10px 18px;border:1px solid #e0e0e0;max-width:500px;margin-bottom:10px;">
                <div style="font-weight:600;margin-bottom:10px;font-size:0.93em;">EJERCICIO DEL MES</div>
                <div class="row g-2">
                    <div class="col-6">
                        <div style="background:#eddcc8;border-radius:6px;padding:12px 0;text-align:center;">
                            <div style="font-size:1em;font-weight:bold;">$ <span id="costo-cargado-mes">0</span></div>
                            <div style="font-size:0.8em;letter-spacing:0.2px;">COSTO CARGADO ESTE MES</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div style="background:#eddcc8;border-radius:6px;padding:12px 0;text-align:center;">
                            <div style="font-size:1em;font-weight:bold;">$ <span id="costo-aprobado-mes">0</span></div>
                            <div style="font-size:0.8em;letter-spacing:0.2px;">COSTO APROBADO</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-2">
        <button id="btn-desmarcar-todos" class="btn btn-warning btn-sm" style="display:none;"><i class="bi bi-x-square"></i> Desmarcar todos los checks confirmados</button>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0" id="tabla-aprobacion">
            <thead>
                <tr>
                    <th style="background:#181818 !important;color:#fff !important;">Número de Empleado</th>
                    <th style="background:#181818 !important;color:#fff !important;">Nombre Colaborador</th>
                    <th style="background:#181818 !important;color:#fff !important;">Horas Asignadas Mes</th>
                    <th style="background:#181818 !important;color:#fff !important;">Costo Asignado Mes</th>
                    <th style="background:#181818 !important;color:#fff !important;">Horas</th>
                    <th style="background:#181818 !important;color:#fff !important;">Costo Imputado Mes</th>
                    <th style="background:#181818 !important;color:#fff !important;">Aprobado</th>
                    <th style="background:#181818 !important;color:#fff !important;">Rechazado</th>
                    <th style="background:#181818 !important;color:#fff !important;">Comentario</th>
                    <th style="background:#181818 !important;color:#fff !important;">Detalle</th>
                    <!-- <th>% CARGUE</th> -->
                </tr>
            </thead>
</script>
<script>
// Calcular y mostrar los totales de la tabla principal
function actualizarResumenCostos() {
    let totalCargado = 0;
    let totalAprobado = 0;
    document.querySelectorAll('#tabla-aprobacion tbody tr').forEach(function(row) {
        let costoCell = row.querySelector('td:nth-child(4)');
        let aprobadoChk = row.querySelector('.aprobado-coord');
        if (costoCell) {
            let costo = parseFloat(costoCell.textContent.replace(/\./g, '').replace(',', '.')) || 0;
            totalCargado += costo;
            if (aprobadoChk && aprobadoChk.checked) {
                totalAprobado += costo;
            }
        }
    });
    document.getElementById('costo-cargado-mes').textContent = totalCargado.toLocaleString('es-CO', {minimumFractionDigits:0});
    document.getElementById('costo-aprobado-mes').textContent = totalAprobado.toLocaleString('es-CO', {minimumFractionDigits:0});
}
// Inicializar al cargar
actualizarResumenCostos();
// Actualizar al cambiar checks de aprobado o rechazado y bloquear campos si corresponde
// Eliminado: manejo antiguo por id. El manejo masivo está implementado más abajo.
// Si hay cambios por AJAX, también puedes llamar a actualizarResumenCostos() después de fetch si lo deseas.
</script>
            <tbody>
            <?php
            // Obtener hh_teoricas del mes y año actual
            $mes_actual = date('m');
            $ano_actual = date('Y');
            $sql_hh_teoricas = "SELECT SUM(hh_teoricas) as hh_teoricas_mes FROM horas_habiles_calendario WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $ano_actual";
            $res_hh_teoricas = $conn->query($sql_hh_teoricas);
            $hh_teoricas_mes = 0;
            if ($res_hh_teoricas && $res_hh_teoricas->num_rows > 0) {
                $row_hh = $res_hh_teoricas->fetch_assoc();
                $hh_teoricas_mes = (float)($row_hh['hh_teoricas_mes'] ?? 0);
            }
            // Obtener horas imputadas globales del mes para cada colaborador
            $horas_mes_colaborador = [];
            $sql_horas_mes = "SELECT numero_empleado, SUM(tiempo_imputado_horas) AS horas_mes FROM horas_dia WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $ano_actual GROUP BY numero_empleado";
            $res_horas_mes = $conn->query($sql_horas_mes);
            if ($res_horas_mes && $res_horas_mes->num_rows > 0) {
                while ($row_hm = $res_horas_mes->fetch_assoc()) {
                    $horas_mes_colaborador[$row_hm['numero_empleado']] = (float)$row_hm['horas_mes'];
                }
            }
            if ($result && $result->num_rows > 0):
                while($row = $result->fetch_assoc()):
                    // Calcular % CARGUE para lógica de habilitación
                    $horas_global = isset($horas_mes_colaborador[$row['numero_de_empleado']]) ? $horas_mes_colaborador[$row['numero_de_empleado']] : 0;
                    $porcentaje_cargue = ($hh_teoricas_mes > 0) ? (($horas_global / $hh_teoricas_mes) * 100) : 0;
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['numero_de_empleado']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_completo']) ?></td>
                    <td><span style="color: #800080; font-weight: bold;">
                        <?= isset($row['horas_asignadas_mes']) ? number_format($row['horas_asignadas_mes'], 2, ',', '.') : '-' ?>
                    </span></td>
                    <td><span style="color: #800080; font-weight: bold;">
                        <?= isset($row['costo_asignado_mes']) ? number_format($row['costo_asignado_mes'], 0, '', '.') : '-' ?>
                    </span></td>
                    <td><span style="color: #17823d; font-weight: bold;">
                        <?= number_format($row['total_horas'], 2, ',', '.') ?>
                    </span></td>
                    <td><span style="color: #17823d; font-weight: bold;">
                        <?= number_format($row['total_costo'], 0, '', '.') ?>
                    </span></td>
                    <td class="text-center">
                        <?php
                        // Solo habilitar si % CARGUE es exactamente 100.0 (con tolerancia a decimales)
                        $is_cargue_100 = (abs($porcentaje_cargue - 100.0) < 0.01);
                        $disabled_apr = (!empty($row['aprobado_coordinador']) || !$is_cargue_100) ? 'disabled' : '';
                        ?>
                        <input type="checkbox" class="form-check-input aprobado-coord"
                            data-numero-empleado="<?= $row['numero_de_empleado'] ?>"
                            data-codigo-affaire="<?= htmlspecialchars($proyecto_codigo) ?>"
                            data-area-funcional="<?= htmlspecialchars($area_funcional) ?>"
                            <?= !empty($row['aprobado_coordinador']) ? 'checked' : '' ?> <?= $disabled_apr ?> >
                    </td>
                    <td class="text-center">
                        <?php
                        $disabled_rech = (!empty($row['rechazado_coordinador']) || !$is_cargue_100) ? 'disabled' : '';
                        ?>
                        <input type="checkbox" class="form-check-input rechazado-coord"
                            data-numero-empleado="<?= $row['numero_de_empleado'] ?>"
                            data-codigo-affaire="<?= htmlspecialchars($proyecto_codigo) ?>"
                            data-area-funcional="<?= htmlspecialchars($area_funcional) ?>"
                            <?= !empty($row['rechazado_coordinador']) ? 'checked' : '' ?> <?= $disabled_rech ?> >
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm comentario-coord" data-numero-empleado="<?= $row['numero_de_empleado'] ?>" value="<?= htmlspecialchars((string)($row['comentarios'] ?? '')) ?>" <?= (!empty($row['aprobado_coordinador']) || !empty($row['rechazado_coordinador']) || !$is_cargue_100) ? 'disabled' : '' ?> >
                    </td>
                    <td class="text-center">
                        <button class="btn btn-outline-primary btn-sm btn-detalle" 
                            data-numero-empleado="<?= htmlspecialchars($row['numero_de_empleado']) ?>"
                            data-codigo-affaire="<?= htmlspecialchars($proyecto_codigo) ?>"
                            data-area-funcional="<?= htmlspecialchars($area_funcional) ?>"
                            title="Ver Detalle">
                            <i class="bi bi-eye"></i> Ver
                        </button>
                    </td>
                    <!-- <td class="text-center">
                        <span style="color: #4C8AA3; font-weight: bold;">
                            <?= number_format($porcentaje_cargue, 1) ?>%
                        </span>
                    </td> -->
                </tr>
            <?php endwhile;
            else: ?>
                <tr><td colspan="9" class="text-center">No hay registros para editar.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function showStatus(elem, ok, errorMsg) {
    let icon = document.createElement('span');
    icon.style.marginLeft = '6px';
    if(ok) {
        icon.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
    } else {
        icon.innerHTML = '<i class="bi bi-x-circle-fill text-danger" title="' + (errorMsg ? errorMsg.replace(/'/g, "&apos;") : '') + '"></i>';
        if(errorMsg) {
            let msg = document.createElement('div');
            msg.className = 'alert alert-danger mt-2';
            msg.style.fontSize = '13px';
            msg.textContent = errorMsg;
            elem.parentNode.appendChild(msg);
            setTimeout(()=>{msg.remove();}, 6000);
        }
    }
    elem.parentNode.appendChild(icon);
    setTimeout(()=>{icon.remove();}, 3000);
}
document.querySelectorAll('.aprobado-coord').forEach(function(chk) {
    chk.addEventListener('change', function() {
        var id = this.getAttribute('data-id');
        var value = this.checked ? '1' : '0';
        if (this.checked) {
            if (!window.confirm('¿Está seguro que desea aprobar este registro?')) {
                this.checked = false;
                return;
            }
        }
        var rech = document.querySelector('.rechazado-coord[data-id="'+id+'"]');
        if (this.checked && rech) rech.checked = false;
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('id', id);
        formData.append('aprobado_coordinador', value);
        fetch('Aprobacion_lucca_Gasto_General.php', {method:'POST', body:formData})
            .then(r=>r.json())
            .then(data=>{ showStatus(this, data.success, data.error); })
            .catch(e=>{ showStatus(this, false, e.message); });
    });
});
document.querySelectorAll('.rechazado-coord').forEach(function(chk) {
    chk.addEventListener('change', function() {
        var id = this.getAttribute('data-id');
        var value = this.checked ? '1' : '0';
        if (this.checked) {
            if (!window.confirm('¿Está seguro que desea rechazar este registro?')) {
                this.checked = false;
                return;
            }
        }
        var apr = document.querySelector('.aprobado-coord[data-id="'+id+'"]');
        if (this.checked && apr) apr.checked = false;
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('id', id);
        formData.append('rechazado_coordinador', value);
        fetch('Aprobacion_lucca_Gasto_General.php', {method:'POST', body:formData})
            .then(r=>r.json())
            .then(data=>{ showStatus(this, data.success, data.error); })
            .catch(e=>{ showStatus(this, false, e.message); });
    });
});
document.querySelectorAll('.comentario-coord').forEach(function(input) {
    input.addEventListener('change', function() {
        var id = this.getAttribute('data-id');
        var value = this.value;
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('id', id);
        formData.append('comentario_coordinador', value);
        fetch('Aprobacion_lucca_Gasto_General.php', {method:'POST', body:formData})
            .then(r=>r.json())
            .then(data=>{ showStatus(this, data.success, data.error); })
            .catch(e=>{ showStatus(this, false, e.message); });
    });
});
</script>
<script>
// Modal y carga de detalle mejorado
document.querySelectorAll('.btn-detalle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var numero_empleado = this.getAttribute('data-numero-empleado');
        var codigo_affaire = this.getAttribute('data-codigo-affaire');
        var area_funcional = this.getAttribute('data-area-funcional');
        var modalEl = document.getElementById('modalDetalle');
        var modal = new bootstrap.Modal(modalEl);
        var contenido = document.getElementById('detalle-contenido');
        var titulo = document.getElementById('modalDetalleLabel');
        contenido.innerHTML = 'Cargando...';
        titulo.textContent = 'Detalle de Imputación';
        fetch('get_detalle_imputacion_Gasto_General.php?numero_empleado=' + encodeURIComponent(numero_empleado) + '&codigo_affaire=' + encodeURIComponent(codigo_affaire) + '&area_funcional=' + encodeURIComponent(area_funcional))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.detalles.length > 0) {
                    // Tomar nombre y apellido del primer registro
                    let nombre = (data.detalles[0].nom + ' ' + data.detalles[0].prenom).trim();
                    titulo.innerHTML = 'Detalle de Imputación - <span style="text-transform:uppercase;">' + nombre + '</span>';
                    let html = '<div class="table-responsive"><table class="table table-bordered table-sm align-middle">';
                    html += '<thead><tr>' +
                        '<th>N° Empleado</th>' +
                        '<th>Nombre Colaborador</th>' +
                        '<th>Proyecto</th>' +
                        '<th>Fecha</th>' +
                        '<th>Horas Imputadas</th>' +
                        '<th>Costo Imputado</th>' +
                        '<th>Comentario</th>' +
                        '</tr></thead><tbody>';
                    let totalHoras = 0;
                    let totalCostos = 0;
                    data.detalles.forEach(function(det) {
                        // Horas: formato "0,00"
                        let horas = parseFloat(det.horas.toString().replace('.', '').replace(',', '.')) || 0;
                        // Costo: formato "1.234.567"
                        let costo = parseFloat(det.costo.toString().replace(/\./g, '').replace(',', '.')) || 0;
                        totalHoras += horas;
                        totalCostos += costo;
                        html += '<tr>' +
                            '<td>' + det.numero_empleado + '</td>' +
                            '<td>' + (det.nom + ' ' + det.prenom).toUpperCase() + '</td>' +
                            '<td>' + det.proyecto + '</td>' +
                            '<td>' + det.fecha + '</td>' +
                            '<td><a style="color:#249c5b;font-weight:bold;" href="#">' + det.horas + '</a></td>' +
                            '<td><span style="color:#249c5b;font-weight:bold;">$ ' + det.costo + '</span></td>' +
                            '<td>' + (det.comentario || '-') + '</td>' +
                            '</tr>';
                    });
                    html += '<tr style="background:#e9f7ef;font-weight:bold;">' +
                        '<td colspan="4" class="text-end">TOTAL</td>' +
                        '<td style="color:#249c5b;">' + totalHoras.toLocaleString('es-CO', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>' +
                        '<td style="color:#249c5b;">$ ' + totalCostos.toLocaleString('es-CO', {minimumFractionDigits:0}) + '</td>' +
                        '<td></td>' +
                        '</tr>';
                    html += '</tbody></table></div>';
                    contenido.innerHTML = html;
                } else {
                    titulo.textContent = 'Detalle de Imputación';
                    contenido.innerHTML = '<div class="alert alert-warning">No se encontraron detalles para este registro.</div>';
                }
            })
            .catch(e => {
                titulo.textContent = 'Detalle de Imputación';
                contenido.innerHTML = '<div class="alert alert-danger">Error al cargar el detalle.</div>';
            });
        modal.show();
    });
});
</script>
<script>
// Botón para desmarcar todos los checks confirmados
document.getElementById('btn-desmarcar-todos').addEventListener('click', function() {
    if (!window.confirm('¿Está seguro que desea desmarcar todos los checks confirmados?')) return;
    // Habilitar todos los inputs antes de desmarcar
    document.querySelectorAll('.aprobado-coord, .rechazado-coord, .comentario-coord').forEach(function(inp) {
        inp.disabled = false;
    });
    // Desmarcar todos los aprobados
    document.querySelectorAll('.aprobado-coord:checked').forEach(function(chk) {
        chk.checked = false;
        var id = chk.getAttribute('data-id');
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('id', id);
        formData.append('aprobado_coordinador', '0');
        fetch('Aprobacion_lucca_Gasto_General.php', {method:'POST', body:formData})
            .then(r=>r.json())
            .then(data=>{ showStatus(chk, data.success, data.error); actualizarResumenCostos(); })
            .catch(e=>{ showStatus(chk, false, e.message); actualizarResumenCostos(); });
    });
    // Desmarcar todos los rechazados
    document.querySelectorAll('.rechazado-coord:checked').forEach(function(chk) {
        chk.checked = false;
        var id = chk.getAttribute('data-id');
        var formData = new FormData();
        formData.append('ajax', '1');
        formData.append('id', id);
        formData.append('rechazado_coordinador', '0');
        fetch('Aprobacion_lucca_Gasto_General.php', {method:'POST', body:formData})
            .then(r=>r.json())
            .then(data=>{ showStatus(chk, data.success, data.error); actualizarResumenCostos(); })
            .catch(e=>{ showStatus(chk, false, e.message); actualizarResumenCostos(); });
    });
    // Si no hay ninguno marcado, refrescar igual
    setTimeout(actualizarResumenCostos, 400);
});
</script>
<?php $conn->close(); ?>
