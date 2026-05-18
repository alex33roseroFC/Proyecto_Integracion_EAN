<?php
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}
require_once 'include.php';
require_once 'config.php';

// Obtener parámetros del proyecto
$proyecto_codigo = isset($_GET['proyecto']) ? $conn->real_escape_string($_GET['proyecto']) : '';
$area_funcional = isset($_GET['area']) ? $conn->real_escape_string($_GET['area']) : '';
$nombre_proyecto = isset($_GET['nombre']) ? $conn->real_escape_string($_GET['nombre']) : '';
$tipo_modal = isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : '';
$es_ausencia = strtolower($tipo_modal) === 'ausencia';

// Procesar edición de horas_dia si viene POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_app_reporte_inputhh'])) {
    $id = intval($_POST['id']);
    $nom = $conn->real_escape_string($_POST['nom']);
    $prenom = $conn->real_escape_string($_POST['prenom']);
    $tiempo_imputado_horas = floatval($_POST['tiempo_imputado_horas']);
    $tiempo_imputado_costo = floatval($_POST['tiempo_imputado_costo']);
    $sql_update = "UPDATE horas_dia SET nom='$nom', prenom='$prenom', tiempo_imputado_horas=$tiempo_imputado_horas, tiempo_imputado_costo=$tiempo_imputado_costo WHERE id=$id";
    $conn->query($sql_update);
}

// Consultar información del presupuesto del proyecto
$sql = "SELECT 
    gp.PROYECTO,
    gp.`ÁREA FUNCIONAL`,
    p.nombre_proyecto,
    p.nature_imputation,
    SUM(
        gp.`ene25`+gp.`feb25`+gp.`mar25`+gp.`abr25`+gp.`may25`+gp.`jun25`+gp.`jul25`+gp.`ago25`+gp.`sep25`+gp.`oct25`+gp.`nov25`+gp.`dic25`+
        gp.`ene26`+gp.`feb26`+gp.`mar26`+gp.`abr26`+gp.`may26`+gp.`jun26`+gp.`jul26`+gp.`ago26`+gp.`sep26`+gp.`oct26`+gp.`nov26`+gp.`dic26`+
        gp.`ene27`+gp.`feb27`+gp.`mar27`+gp.`abr27`+gp.`may27`+gp.`jun27`+gp.`jul27`+gp.`ago27`+gp.`sep27`+gp.`oct27`+gp.`nov27`+gp.`dic27`+
        gp.`ene28`+gp.`feb28`+gp.`mar28`+gp.`abr28`+gp.`may28`+gp.`jun28`+gp.`jul28`+gp.`ago28`+gp.`sep28`+gp.`oct28`+gp.`nov28`+gp.`dic28`
    ) AS total_horas,
    (SELECT COALESCE(SUM(cv.`acum_año_anterior`+cv.`ene_25`+cv.`feb_25`+cv.`mar_25`+cv.`abr_25`+cv.`may_25`+cv.`jun_25`+cv.`jul_25`+cv.`ago_25`+cv.`sep_25`+cv.`oct_25`+cv.`nov_25`+cv.`dic_25`), 0)
     FROM costo_valorizado cv
     WHERE cv.CECO_CONEXION = gp.PROYECTO
         AND cv.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`) AS total_valorizado_2025,
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
    WHERE gp2.PROYECTO = gp.PROYECTO AND gp2.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`) AS total_costo
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
WHERE gp.PROYECTO = '$proyecto_codigo'
    AND gp.`ÁREA FUNCIONAL` = '$area_funcional'
GROUP BY gp.PROYECTO, gp.`ÁREA FUNCIONAL`, p.nombre_proyecto, p.nature_imputation";

$result = $conn->query($sql);
$proyecto_data = null;

if ($result && $result->num_rows > 0) {
    $proyecto_data = $result->fetch_assoc();
} else {
    // Si no hay datos en gastos_personal, buscar en horas_dia
    $sql_alt = "SELECT 
        ar.codigo_affaire AS PROYECTO,
        ar.area_funcional AS `ÁREA FUNCIONAL`,
        p.nombre_proyecto,
        p.nature_imputation,
        0 AS total_horas,
        (SELECT COALESCE(SUM(cv.`acum_año_anterior`+cv.`ene_25`+cv.`feb_25`+cv.`mar_25`+cv.`abr_25`+cv.`may_25`+cv.`jun_25`+cv.`jul_25`+cv.`ago_25`+cv.`sep_25`+cv.`oct_25`+cv.`nov_25`+cv.`dic_25`),0)
         FROM costo_valorizado cv
         WHERE cv.CECO_CONEXION = ar.codigo_affaire
             AND cv.`ÁREA FUNCIONAL` = ar.area_funcional) AS total_valorizado_2025,
        COALESCE(SUM(ar.tiempo_imputado_costo),0) AS tiempo_imputado_costo,
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
    WHERE ar.codigo_affaire = '$proyecto_codigo'
        AND ar.area_funcional = '$area_funcional'
        AND ar.Estado_Aprobacion = 'Aprobado En Curso'
    GROUP BY ar.codigo_affaire, ar.area_funcional, p.nombre_proyecto, p.nature_imputation";
    
    $result_alt = $conn->query($sql_alt);
    if ($result_alt && $result_alt->num_rows > 0) {
        $proyecto_data = $result_alt->fetch_assoc();
    }
}

// Si no hay datos, mostrar mensaje
if (!$proyecto_data) {
    echo '<div class="alert alert-warning">No se encontraron datos para este proyecto.</div>';
    exit;
}

// Calcular valores derivados
$bac = (float)$proyecto_data['total_costo'];
$ac = (float)$proyecto_data['total_valorizado_2025'] + (float)($proyecto_data['costo_real_aprobado'] ?? 0);
$tiempo_imputado = (float)$proyecto_data['tiempo_imputado_costo'];
$costo_aprobado = (float)$proyecto_data['costo_aprobado'];

// Determinar si es FRAIS GENERAUX DIVERS
$nature_upper = strtoupper(trim($proyecto_data['nature_imputation'] ?? ''));
$nature_upper = preg_replace('/\s+/', ' ', $nature_upper);
$es_gasto_general = ($nature_upper === 'FRAIS GENERAUX DIVERS');
$es_ausencia = $es_ausencia || ($nature_upper === 'ABSENCE');

$etc = $bac - $ac;
$nuevo_costo_actual = $ac + $costo_aprobado;
$porc_aprobado_mes = ($tiempo_imputado > 0) ? ($costo_aprobado / $tiempo_imputado) * 100 : 0;

// Si es gasto general, AC y ETC son 0
if ($es_gasto_general) {
    $ac = 0;
    $etc = 0;
}

// Mostrar advertencia si el costo cargado del mes supera el saldo por ejecutar
$mostrar_advertencia_costo_supera_saldo = false;
if (!$es_gasto_general && !$es_ausencia && $bac != 0 && $tiempo_imputado > $etc) {
    $mostrar_advertencia_costo_supera_saldo = true;
}
?>

<div class="container-fluid p-0">
    <!-- Mensaje si BAC es 0 (no mostrar para gastos generales) - PRIORIDAD 1 -->
    <?php if ($bac == 0 && !$es_gasto_general && !$es_ausencia): ?>
        <div class="alert alert-danger mb-3" role="alert" style="font-weight:600; font-size:15px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            ¡Este proyecto no tiene presupuesto, por tanto estas HH no se podrán aprobar!
        </div>
    <?php endif; ?>
    
    <!-- Mensaje dinámico si el saldo por ejecutar es negativo (no mostrar para gastos generales ni si BAC es 0) - PRIORIDAD 2 -->
    <?php if (!$es_gasto_general && !$es_ausencia && $bac != 0): ?>
    <div id="alerta-saldo-negativo" class="alert alert-warning mb-3" role="alert" style="font-weight:600; font-size:15px; <?= $mostrar_advertencia_costo_supera_saldo ? '' : 'display:none;' ?>">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        ¡El costo cargado supera el saldo por ejecutar, por tanto estas HH no se podrán aprobar!
    </div>
    <?php endif; ?>
    
    <!-- Tarjeta de resumen del presupuesto -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); <?= $es_ausencia ? 'display:none;' : '' ?>">
        <div class="card-body p-4">
            <!-- Estilo tipo resumen ejecutivo compacto y responsive -->
            <style>
                .resumen-ejecutivo {
                    border: 1px solid #bbb;
                    border-radius: 6px;
                    padding: 12px;
                    margin-bottom: 0;
                    background: #fff;
                    font-size: 13px;
                    height: 100%;
                }
                .resumen-ejecutivo-titulo {
                    font-size: 13px;
                    color: #444;
                    font-weight: 600;
                    margin-bottom: 8px;
                    letter-spacing: 0.3px;
                    text-transform: uppercase;
                }
                .resumen-ejecutivo-cifras {
                    display: flex;
                    flex-direction: row;
                    gap: 6px;
                    justify-content: space-between;
                }
                .resumen-ejecutivo-cifra {
                    background: rgba(139, 195, 139, 0.85);
                    border-radius: 4px;
                    padding: 10px 4px;
                    text-align: center;
                    flex: 1;
                    min-width: 0;
                    min-height: 60px;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                }
                .resumen-ejecutivo-cifra-morado {
                    background: #EDDAC6;
                    border-radius: 4px;
                    padding: 10px 4px;
                    text-align: center;
                    flex: 1;
                    min-width: 0;
                    min-height: 60px;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                }
                .resumen-mensual {
                    border: 1px solid #bbb;
                    border-radius: 6px;
                    padding: 12px;
                    margin-bottom: 0;
                    background: #fff;
                    font-size: 13px;
                    height: 100%;
                }
                .resumen-mensual-titulo {
                    font-size: 13px;
                    color: #444;
                    font-weight: 600;
                    margin-bottom: 8px;
                    letter-spacing: 0.3px;
                    text-transform: uppercase;
                }
                .resumen-ejecutivo-cifra-valor {
                    font-size: 0.95rem;
                    font-weight: 700;
                    color: #222;
                    margin-bottom: 2px;
                    line-height: 1.1;
                }
                .resumen-ejecutivo-cifra-label {
                    font-size: 9px;
                    color: #555;
                    font-weight: 400;
                    line-height: 1.2;
                }
                /* Nuevo saldo (caja única) */
                .nuevo-saldo {
                    border: 1px solid #bbb;
                    border-radius: 6px;
                    padding: 12px;
                    margin-bottom: 0;
                    background: #fff;
                    font-size: 13px;
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                }
                .nuevo-saldo-titulo {
                    font-size: 13px;
                    color: #444;
                    font-weight: 600;
                    text-transform: uppercase;
                    margin-bottom: 10px;
                    letter-spacing: 0.3px;
                }
                .nuevo-saldo-caja {
                    background: #d8e6e1;
                    border-radius: 4px;
                    padding: 10px 15px;
                    text-align: center;
                    width: 100%;
                    max-width: 250px;
                    min-height: 60px;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                }
                .nuevo-saldo-valor {
                    font-size: 0.95rem;
                    font-weight: 700;
                    color: #223;
                    margin: 0 0 2px 0;
                    line-height: 1.1;
                }
                
                /* Responsive para tablets y móviles */
                @media (max-width: 992px) {
                    .resumen-ejecutivo-cifras {
                        gap: 4px;
                    }
                    .resumen-ejecutivo-cifra,
                    .resumen-ejecutivo-cifra-morado {
                        padding: 5px 3px;
                    }
                    .resumen-ejecutivo-cifra-valor,
                    .nuevo-saldo-valor {
                        font-size: 0.85rem;
                    }
                    .resumen-ejecutivo-cifra-label {
                        font-size: 8px;
                    }
                }
                
                @media (max-width: 768px) {
                    .resumen-ejecutivo,
                    .resumen-mensual,
                    .nuevo-saldo {
                        padding: 10px;
                        margin-bottom: 15px;
                    }
                    .resumen-ejecutivo-cifra-valor,
                    .nuevo-saldo-valor {
                        font-size: 0.8rem;
                    }
                    .resumen-ejecutivo-cifra-label {
                        font-size: 7.5px;
                    }
                    .resumen-ejecutivo-titulo,
                    .resumen-mensual-titulo,
                    .nuevo-saldo-titulo {
                        font-size: 12px;
                    }
                }
                
                @media (max-width: 576px) {
                    .resumen-ejecutivo-cifras {
                        gap: 3px;
                    }
                    .resumen-ejecutivo-cifra,
                    .resumen-ejecutivo-cifra-morado {
                        padding: 4px 2px;
                    }
                    .resumen-ejecutivo-cifra-valor,
                    .nuevo-saldo-valor {
                        font-size: 0.75rem;
                    }
                    .resumen-ejecutivo-cifra-label {
                        font-size: 7px;
                    }
                    .nuevo-saldo-caja {
                        max-width: 100%;
                    }
                }
            </style>

            <div class="row g-3 mb-3">
                <?php if (!$es_gasto_general): ?>
                <div class="col-12 col-lg-4">
                    <div class="resumen-ejecutivo">
                        <div class="resumen-ejecutivo-titulo">Saldos al corte anterior</div>
                        <div class="resumen-ejecutivo-cifras">
                            <div class="resumen-ejecutivo-cifra">
                                <div class="resumen-ejecutivo-cifra-valor">$ <?= number_format($bac, 0, '', '.') ?></div>
                                <div class="resumen-ejecutivo-cifra-label">PTO A TERMINACIÓN (BAC)</div>
                            </div>
                            <div class="resumen-ejecutivo-cifra">
                                <div class="resumen-ejecutivo-cifra-valor">$ <?= number_format($ac, 0, '', '.') ?></div>
                                <div class="resumen-ejecutivo-cifra-label">COSTO ACTUAL</div>
                            </div>
                            <div class="resumen-ejecutivo-cifra">
                                <div class="resumen-ejecutivo-cifra-valor">$ <?= number_format($etc, 0, '', '.') ?></div>
                                <div class="resumen-ejecutivo-cifra-label">SALDO POR EJECUTAR</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-12 <?= $es_gasto_general ? 'col-lg-12' : 'col-lg-4' ?>">
                    <div class="resumen-mensual">
                        <div class="resumen-mensual-titulo">Ejercicio del mes</div>
                        <div class="resumen-ejecutivo-cifras">
                            <div class="resumen-ejecutivo-cifra-morado">
                                <div class="resumen-ejecutivo-cifra-valor" id="tiempo-imputado-valor">$ <?= number_format($tiempo_imputado, 0, '', '.') ?></div>
                                <div class="resumen-ejecutivo-cifra-label">COSTO CARGADO ESTE MES</div>
                            </div>
                            <div class="resumen-ejecutivo-cifra-morado">
                                <div class="resumen-ejecutivo-cifra-valor" id="costo-aprobado-valor">$ <?= number_format($costo_aprobado, 0, '', '.') ?></div>
                                <div class="resumen-ejecutivo-cifra-label">COSTO APROBADO</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!$es_gasto_general): ?>
                <div class="col-12 col-lg-4">
                    <div class="nuevo-saldo">
                                            <div class="nuevo-saldo-titulo">Nuevo saldo</div>
                                            <div class="d-flex flex-row gap-2 w-100 justify-content-center align-items-stretch">
                                                <div class="nuevo-saldo-caja mb-0" style="min-width:150px;">
                                                    <div class="nuevo-saldo-valor" id="nuevo-costo-actual-valor">$ <?= number_format($nuevo_costo_actual, 0, '', '.') ?></div>
                                                    <div class="resumen-ejecutivo-cifra-label">NUEVO COSTO ACTUAL</div>
                                                </div>
                                                <div class="nuevo-saldo-caja mb-0" style="min-width:150px;">
                                                    <div class="nuevo-saldo-valor" id="diferencia-bac-nuevo-valor">$ <?= number_format($bac - $nuevo_costo_actual, 0, '', '.') ?></div>
                                                    <div class="resumen-ejecutivo-cifra-label">NUEVO SALDO POR EJECUTAR</div>
                                                </div>
                                            </div>
                                            <script>
                                                // Sincroniza el estado de los checkboxes de aprobado/rechazado al cargar la tabla
                                                function sincronizarCheckboxesAprobacion() {
                                                    document.querySelectorAll('tbody tr').forEach(function(row) {
                                                        var aprobado = row.querySelector('[data-campo="aprobado_coordinador"]');
                                                        var rechazado = row.querySelector('[data-campo="rechazado_coordinador"]');
                                                        var comentario = row.querySelector('[data-campo="comentario_coordinador"]');
                                                        var porcentaje = parseFloat(row.getAttribute('data-porcentaje') || 0);
                                                        if (aprobado && rechazado) {
                                                            if (<?= $es_ausencia ? 'true' : 'false' ?>) {
                                                                aprobado.disabled = false;
                                                                rechazado.disabled = false;
                                                                if (comentario) comentario.disabled = false;
                                                                return;
                                                            }
                                                            // Si el % de cargue es menor a 100%, ambos deben permanecer deshabilitados
                                                            if (porcentaje < 100) {
                                                                aprobado.disabled = true;
                                                                rechazado.disabled = true;
                                                                if (comentario) comentario.disabled = true;
                                                                return;
                                                            }
                                                            // Permitir cambiar libremente entre aprobar y rechazar si el porcentaje es 100%
                                                            aprobado.disabled = false;
                                                            rechazado.disabled = false;
                                                            if (comentario) comentario.disabled = false;
                                                        }
                                                    });
                                                }

                                            // Actualizar dinámicamente el saldo por ejecutar cuando cambie el costo aprobado
                                            (function() {
                                                function obtenerCostoFila(row) {
                                                    var costoInput = row.querySelector('[data-campo="tiempo_imputado_costo"]');
                                                    if (costoInput) {
                                                        var vInput = parseFloat(costoInput.value) || 0;
                                                        if (!isNaN(vInput)) return vInput;
                                                    }

                                                    var costoSpan = row.querySelector('.costo-formateado');
                                                    if (costoSpan) {
                                                        var limpio = (costoSpan.textContent || '').replace(/\./g, '').replace(/,/g, '.').replace(/[^\d.-]/g, '');
                                                        var vSpan = parseFloat(limpio) || 0;
                                                        if (!isNaN(vSpan)) return vSpan;
                                                    }

                                                    return 0;
                                                }

                                                function actualizarSaldoPorEjecutar() {
                                                    var bac = <?= $bac ?>;
                                                    var ac = <?= $ac ?>;
                                                    var aprobado = 0;
                                                    var elemNuevo = document.getElementById('nuevo-costo-actual-valor');
                                                    var elemSaldo = document.getElementById('diferencia-bac-nuevo-valor');
                                                    // Sumar todos los aprobados de la tabla
                                                    document.querySelectorAll('tbody tr').forEach(function(row) {
                                                        var aprobadoCheck = row.querySelector('[data-campo="aprobado_coordinador"]');
                                                        if (aprobadoCheck && aprobadoCheck.checked) {
                                                            var costo = obtenerCostoFila(row);
                                                            aprobado += costo;
                                                        }
                                                    });
                                                    var nuevoCostoActual = ac + aprobado;
                                                    var saldoPorEjecutar = bac - nuevoCostoActual;
                                                    if (elemNuevo) {
                                                        elemNuevo.textContent = '$ ' + Math.round(nuevoCostoActual).toLocaleString('es-CL').replace(/,/g, '.');
                                                    }
                                                    if (elemSaldo) {
                                                        elemSaldo.textContent = '$ ' + Math.round(saldoPorEjecutar).toLocaleString('es-CL').replace(/,/g, '.');
                                                    }
                                                }
                                                // Actualizar al cargar y en cada cambio
                                                document.addEventListener('DOMContentLoaded', actualizarSaldoPorEjecutar);
                                                document.querySelectorAll('[data-campo="aprobado_coordinador"], [data-campo="tiempo_imputado_costo"]').forEach(function(el) {
                                                    el.addEventListener('change', actualizarSaldoPorEjecutar);
                                                    el.addEventListener('input', actualizarSaldoPorEjecutar);
                                                });
                                            })();
                                            </script>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>


        <!-- Tabla de edición de horas_dia -->
        <div class="container-fluid <?= $es_ausencia ? 'mt-0' : 'mt-4' ?> px-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Aprobación de Costos Cargados por Colaborador</h4>
            </div>
            <div class="mb-3">
                <button type="button" class="btn btn-warning btn-sm" id="btn-reset-aprobaciones" style="display:none;">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Desmarcar todas las aprobaciones/rechazos
                </button>
                <span class="text-muted ms-2" style="font-size:12px; display:none;">(Solo para uso especial. Refresque la página si no ve cambios inmediatos.)</span>
            </div>
            <?php
            // Ya se incluyó la conexión $conn desde include.php/config.php
            $codigo_affaire = $proyecto_data['PROYECTO'];
            $area_funcional = $proyecto_data['ÁREA FUNCIONAL'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_app_reporte_inputhh'])) {
                $id = intval($_POST['id']);
                $nom = $conn->real_escape_string($_POST['nom']);
                $prenom = $conn->real_escape_string($_POST['prenom']);
                $tiempo_imputado_horas = floatval($_POST['tiempo_imputado_horas']);
                $tiempo_imputado_costo = floatval($_POST['tiempo_imputado_costo']);
                $sql_update = "UPDATE horas_dia SET nom='$nom', prenom='$prenom', tiempo_imputado_horas=$tiempo_imputado_horas, tiempo_imputado_costo=$tiempo_imputado_costo WHERE id=$id";
                $conn->query($sql_update);
            }
            // Agrupar por empleado y sumar horas/costo
            $sql = "SELECT hd.numero_empleado, hd.nom, hd.prenom, hd.codigo_affaire, 
                SUM(hd.tiempo_imputado_horas) AS total_horas, 
                SUM(hd.tiempo_imputado_costo) AS total_costo, 
                MAX(hd.aprobado_coordinador) AS aprobado_coordinador, 
                MAX(hd.rechazado_coordinador) AS rechazado_coordinador, 
                GROUP_CONCAT(DISTINCT hd.comentario_coordinador SEPARATOR '; ') AS comentarios,
                vdma.horas_activas,
                vdma.costo_activo
            FROM horas_dia hd
            LEFT JOIN vista_detalle_mes_activo vdma ON vdma.matricula = hd.numero_empleado AND vdma.centro_costos = '".$conn->real_escape_string($codigo_affaire)."'
            WHERE hd.codigo_affaire='".$conn->real_escape_string($codigo_affaire)."' 
                AND hd.area_funcional='".$conn->real_escape_string($area_funcional)."' 
                AND hd.Estado_Aprobacion = 'Aprobado En Curso'
            GROUP BY hd.numero_empleado, hd.nom, hd.prenom, hd.codigo_affaire, vdma.horas_activas, vdma.costo_activo";
            $result = $conn->query($sql);
            ?>
            
            <style>
                .table-responsive-custom {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }
                
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                
                .table-imputaciones {
                    width: 100%;
                    border-collapse: collapse;
                    background: #fff;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    border-radius: 8px;
                    overflow: hidden;
                    font-size: 14px;
                }
                
                .table-imputaciones thead th {
                    background: linear-gradient(180deg, #f8f9fa, #e9ecef);
                    color: #333;
                    font-weight: 600;
                    padding: 12px 8px;
                    text-align: center;
                    border-bottom: 2px solid #dee2e6;
                    white-space: nowrap;
                }
                .table-imputaciones tbody td {
                    text-align: center;
                    border-right: 1px solid #e0e0e0;
                }
                .table-imputaciones thead th {
                    border-right: 1px solid #e0e0e0;
                }
                .table-imputaciones thead th:last-child,
                .table-imputaciones tbody td:last-child {
                    border-right: none;
                }
                .table-imputaciones tbody td input,
                .table-imputaciones tbody td span {
                    text-align: center;
                    display: inline-block;
                    width: 100%;
                }
                
                .table-imputaciones tbody td {
                    padding: 10px 8px;
                    border-bottom: 1px solid #f1f3f5;
                    vertical-align: middle;
                }
                
                .table-imputaciones tbody tr:hover {
                    background-color: #f8f9fa;
                }
                
                .table-imputaciones .form-control-sm {
                    padding: 4px 8px;
                    font-size: 13px;
                    border-radius: 4px;
                }
                
                .table-imputaciones .form-check-input[data-campo="aprobado_coordinador"] {
                    cursor: pointer;
                    width: 18px;
                    height: 18px;
                    accent-color: #17823d !important;
                }
                .table-imputaciones .form-check-input[data-campo="rechazado_coordinador"] {
                    cursor: pointer;
                    width: 18px;
                    height: 18px;
                    accent-color: #DF685C !important;
                }
                
                .table-imputaciones .form-check-input:disabled {
                    cursor: not-allowed;
                    opacity: 0.6;
                    accent-color: #bcbcbc;
                    background-color: #ededed;
                }
                
                .btn-ver-detalle {
                    white-space: nowrap;
                    padding: 5px 10px;
                    font-size: 13px;
                    transition: all 0.3s ease;
                }
                
                .btn-ver-detalle:hover {
                    transform: scale(1.05);
                    box-shadow: 0 2px 8px rgba(0,123,255,0.3);
                }
                
                @media (max-width: 768px) {
                    .table-imputaciones {
                        font-size: 12px;
                    }
                    
                    .table-imputaciones thead th {
                        padding: 8px 4px;
                        font-size: 11px;
                    }
                    
                    .table-imputaciones tbody td {
                        padding: 6px 4px;
                    }
                    
                    .table-imputaciones .form-control-sm {
                        font-size: 11px;
                        padding: 3px 6px;
                    }
                }
            </style>
            
            <div class="table-responsive-custom">
            <table class="table-imputaciones">
                <thead>
                    <tr>
                        <th>Nombre Colaborador</th>
                        <th<?= $es_ausencia ? ' style="display:none;"' : '' ?>>Horas Asignadas Mes</th>
                        <th<?= $es_ausencia ? ' style="display:none;"' : '' ?>>Costo Asignado Mes</th>
                        <th>Horas Cargadas</th>
                        <th<?= $es_ausencia ? ' style="display:none;"' : '' ?>>Costo Cargado Mes</th>
                        <th>Aprobado</th>
                        <th>Rechazado</th>
                        <th>Comentario</th>
                        <th>Detalle</th>
                        <!-- <th>% CARGUE</th> -->
                    </tr>
                </thead>
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
                if ($result && $result instanceof mysqli_result && $result->num_rows > 0):
                    while($row = $result->fetch_assoc()):
                        $horas_global = isset($horas_mes_colaborador[$row['numero_empleado']]) ? $horas_mes_colaborador[$row['numero_empleado']] : 0;
                        $porcentaje_cargue = ($hh_teoricas_mes > 0) ? (($horas_global / $hh_teoricas_mes) * 100) : 0;
                ?>
                        <tr data-porcentaje="<?= round($porcentaje_cargue,1) ?>">
                            <td>
                                <span class="nombre-colaborador">
                                    <?= htmlspecialchars(trim($row['nom'] . ' ' . $row['prenom'])) ?>
                                </span>
                            </td>
                            <td<?= $es_ausencia ? ' style="display:none;"' : '' ?>>
                                <span style="color: #800080; font-weight: bold;">
                                    <?= isset($row['horas_activas']) ? number_format($row['horas_activas'], 2, ',', '.') : '-' ?>
                                </span>
                            </td>
                            <td<?= $es_ausencia ? ' style="display:none;"' : '' ?>>
                                <span style="color: #800080; font-weight: bold;">
                                    <?= isset($row['costo_activo']) ? number_format($row['costo_activo'], 0, '', '.') : '-' ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: #17823d; font-weight: bold;">
                                    <?= number_format($row['total_horas'], 2, ',', '.') ?>
                                </span>
                            </td>
                            <td<?= $es_ausencia ? ' style="display:none;"' : '' ?>>
                                <span class="costo-formateado" style="color: #17823d; font-weight: bold;">
                                    <?= number_format($row['total_costo'], 0, '', '.') ?>
                                </span>
                            </td>
                            <td class="text-center align-middle">
                                <input type="checkbox" class="form-check-input editable-input checkbox-aprobado"
                                    data-numero-empleado="<?= htmlspecialchars($row['numero_empleado']) ?>"
                                    data-codigo-affaire="<?= htmlspecialchars($row['codigo_affaire']) ?>"
                                    data-porcentaje="<?= round($porcentaje_cargue,1) ?>"
                                    data-campo="aprobado_coordinador"
                                    <?= !empty($row['aprobado_coordinador']) ? 'checked' : '' ?>
                                    <?= (!$es_ausencia && floatval($porcentaje_cargue) < 100.0 ? 'disabled' : '') ?>
                                    onclick="event.stopPropagation();" >
                            </td>
                            <td class="text-center align-middle">
                                <input type="checkbox" class="form-check-input editable-input"
                                    data-numero-empleado="<?= htmlspecialchars($row['numero_empleado']) ?>"
                                    data-codigo-affaire="<?= htmlspecialchars($row['codigo_affaire']) ?>"
                                    data-porcentaje="<?= round($porcentaje_cargue,1) ?>"
                                    data-campo="rechazado_coordinador"
                                    <?= !empty($row['rechazado_coordinador']) ? 'checked' : '' ?>
                                    <?= (!$es_ausencia && floatval($porcentaje_cargue) < 100.0 ? 'disabled' : '') ?>
                                    onclick="event.stopPropagation();">
                            </td>
                            <td>
                                <input type="text" value="<?= htmlspecialchars((string)($row['comentarios'] ?? '')) ?>" class="form-control form-control-sm editable-input" data-numero-empleado="<?= htmlspecialchars($row['numero_empleado']) ?>" data-codigo-affaire="<?= htmlspecialchars($row['codigo_affaire']) ?>" data-campo="comentario_coordinador" autocomplete="off" <?= (!$es_ausencia && floatval($porcentaje_cargue) < 100.0 ? 'disabled' : '') ?> >
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-primary btn-ver-detalle" 
                                   data-numero-empleado="<?= htmlspecialchars($row['numero_empleado']) ?>"
                                   data-codigo-affaire="<?= htmlspecialchars($row['codigo_affaire']) ?>"
                                   data-nom="<?= htmlspecialchars($row['nom']) ?>"
                                   data-prenom="<?= htmlspecialchars($row['prenom']) ?>"
                                   title="Ver detalles de imputación">
                                   <i class="bi bi-eye-fill me-1"></i>Ver
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
                    <tr><td colspan="<?= $es_ausencia ? '6' : '9' ?>" class="text-center">No hay registros para editar.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
            <?php $conn->close(); ?>

        </div>


    <!-- Cargar Bootstrap y estilos ANTES del script -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/checkbox-blue.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <script>
        // Botón para resetear aprobaciones/rechazos (compatible con modales y recarga dinámica)
        // Botón para resetear aprobaciones/rechazos (compatible con modales y recarga dinámica)
        (function() {
            function resetAprobacionesHandler(e) {
                if (e.target && e.target.id === 'btn-reset-aprobaciones') {
                    if (!confirm('¿Está seguro que desea desmarcar todas las aprobaciones y rechazos? Esta acción es reversible solo manualmente.')) return;
                    var checkboxes = document.querySelectorAll('[data-campo="aprobado_coordinador"], [data-campo="rechazado_coordinador"]');
                    checkboxes.forEach(function(checkbox) {
                        if (checkbox.checked || checkbox.disabled) {
                            checkbox.checked = false;
                            checkbox.disabled = false;
                            // Guardar en servidor
                            var campo = checkbox.getAttribute('data-campo');
                            var id = checkbox.getAttribute('data-id');
                            fetch('update_app_reporte_inputhh.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'id=' + encodeURIComponent(id) + '&campo=' + encodeURIComponent(campo) + '&valor=0'
                            });
                            // Habilitar el campo de comentario correspondiente
                            var comentarioInput = document.querySelector('[data-id="' + id + '"][data-campo="comentario_coordinador"]');
                            if (comentarioInput) {
                                comentarioInput.disabled = false;
                            }
                        }
                    });
                    // Re-evaluar condiciones de habilitación/deshabilitación
                    if (typeof sincronizarCheckboxesAprobacion === 'function') {
                        sincronizarCheckboxesAprobacion();
                    }
                    if (typeof recalcularTotales === 'function') {
                        recalcularTotales();
                    }
                    alert('Se han desmarcado todas las aprobaciones y rechazos.');
                }
            }
            // Usar event delegation para asegurar funcionamiento en modales y recarga dinámica
            document.addEventListener('click', resetAprobacionesHandler, true);
        })();
        // Usar una función autoejecutable para que funcione cuando se carga dinámicamente
        (function() {
            // Esperar un momento para asegurar que el DOM esté completamente cargado
            setTimeout(function() {
                // Verificar que Bootstrap esté cargado
                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap no está cargado!');
                    return;
                } else {
                    console.log('Bootstrap cargado correctamente');
                }
                
                const ac = <?= $ac ?>;
                const esGastoGeneral = <?= $es_gasto_general ? 'true' : 'false' ?>;
                const esAusencia = <?= $es_ausencia ? 'true' : 'false' ?>;

                   // Sincronizar checkboxes al cargar la tabla
                   sincronizarCheckboxesAprobacion();
            
            // Función para recalcular totales desde la tabla
            function recalcularTotales() {
                let tiempoTotal = 0;
                let aprobadoTotal = 0;
                const bac = <?= $bac ?>;

                function obtenerCostoFila(row) {
                    const costoInput = row.querySelector('[data-campo="tiempo_imputado_costo"]');
                    if (costoInput) {
                        const vInput = parseFloat(costoInput.value) || 0;
                        if (!isNaN(vInput)) return vInput;
                    }

                    const costoSpan = row.querySelector('.costo-formateado');
                    if (costoSpan) {
                        const limpio = (costoSpan.textContent || '').replace(/\./g, '').replace(/,/g, '.').replace(/[^\d.-]/g, '');
                        const vSpan = parseFloat(limpio) || 0;
                        if (!isNaN(vSpan)) return vSpan;
                    }

                    return 0;
                }
                
                // Recorrer todas las filas de la tabla
                document.querySelectorAll('tbody tr').forEach(function(row, index) {
                    const aprobadoCheck = row.querySelector('[data-campo="aprobado_coordinador"]');
                    const costo = obtenerCostoFila(row);
                    tiempoTotal += costo;
                    if (aprobadoCheck && aprobadoCheck.checked) {
                        aprobadoTotal += costo;
                    }
                });
                // Calcular el nuevo saldo por ejecutar
                const nuevoCostoActual = ac + aprobadoTotal;
                const nuevoSaldoPorEjecutar = bac - nuevoCostoActual;
                // Actualizar los valores en las etiquetas
                const elemTiempo = document.getElementById('tiempo-imputado-valor');
                const elemAprobado = document.getElementById('costo-aprobado-valor');
                const elemNuevo = document.getElementById('nuevo-costo-actual-valor');
                const elemSaldo = document.getElementById('diferencia-bac-nuevo-valor');
                const alertaSaldo = document.getElementById('alerta-saldo-negativo');
                if (elemTiempo) {
                    elemTiempo.textContent = '$ ' + Math.round(tiempoTotal).toLocaleString('es-CL').replace(/,/g, '.');
                }
                if (elemAprobado) {
                    elemAprobado.textContent = '$ ' + Math.round(aprobadoTotal).toLocaleString('es-CL').replace(/,/g, '.');
                }
                if (elemNuevo) {
                    elemNuevo.textContent = '$ ' + Math.round(nuevoCostoActual).toLocaleString('es-CL').replace(/,/g, '.');
                }
                if (elemSaldo) {
                    elemSaldo.textContent = '$ ' + Math.round(nuevoSaldoPorEjecutar).toLocaleString('es-CL').replace(/,/g, '.');
                }
                // Mostrar/ocultar alerta y deshabilitar checkboxes si el saldo es negativo
                const saldoNegativo = nuevoSaldoPorEjecutar < 0;
                const bacCero = bac == 0;
                // No mostrar alerta ni deshabilitar checkboxes si es gasto general
                if (!esGastoGeneral && !esAusencia) {
                    if (alertaSaldo) {
                        alertaSaldo.style.display = saldoNegativo ? 'block' : 'none';
                    }
                    // Si el usuario intenta aprobar más del saldo, desmarcar todo y mostrar advertencia
                    if (saldoNegativo) {
                        // Buscar el último checkbox marcado (el que está checked y disparó el evento) y desmarcarlo y actualizar en backend
                        // Poner todos los aprobado_coordinador y rechazado_coordinador en 0 en backend y desmarcar visualmente
                        document.querySelectorAll('tbody tr').forEach(function(row) {
                            var aprobado = row.querySelector('[data-campo="aprobado_coordinador"]');
                            var rechazado = row.querySelector('[data-campo="rechazado_coordinador"]');
                            var id = aprobado ? aprobado.getAttribute('data-id') : null;
                            if (aprobado) {
                                aprobado.checked = false;
                                aprobado.disabled = false;
                                fetch('update_app_reporte_inputhh.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'id=' + encodeURIComponent(id) + '&campo=aprobado_coordinador&valor=0'
                                });
                            }
                            // No desmarcar ni deshabilitar el checkbox de rechazado cuando el saldo es negativo
                        });
                        // Habilitar comentarios
                        document.querySelectorAll('[data-campo="comentario_coordinador"]').forEach(function(input) {
                            input.disabled = false;
                        });
                        // Refrescar los totales visuales a 0
                        if (elemAprobado) elemAprobado.textContent = '$ 0';
                        if (elemNuevo) elemNuevo.textContent = '$ ' + Math.round(ac).toLocaleString('es-CL').replace(/,/g, '.');
                        if (elemSaldo) elemSaldo.textContent = '$ ' + Math.round(bac - ac).toLocaleString('es-CL').replace(/,/g, '.');
                        // Mostrar alerta
                        if (alertaSaldo) {
                            alertaSaldo.style.display = 'block';
                        }
                        // Scroll a la alerta
                        if (alertaSaldo) {
                            alertaSaldo.scrollIntoView({behavior: 'smooth', block: 'center'});
                        }
                        // Detener aquí para que el usuario replantee la selección
                        return { tiempoTotal: 0, aprobadoTotal: 0 };
                    }
                    // Deshabilitar o habilitar checkboxes según las condiciones
                    // Solo deshabilitar el checkbox de aprobado, dejar rechazado habilitado
                    document.querySelectorAll('.checkbox-aprobado').forEach(function(checkbox) {
                        var row = checkbox.closest('tr');
                        var porcentaje = parseFloat((row && row.getAttribute('data-porcentaje')) || checkbox.getAttribute('data-porcentaje') || 0);
                        if (bacCero || saldoNegativo || porcentaje < 100) {
                            checkbox.disabled = true;
                        } else {
                            checkbox.disabled = false;
                        }
                    });
                    document.querySelectorAll('[data-campo="rechazado_coordinador"]').forEach(function(checkbox) {
                        var row = checkbox.closest('tr');
                        var porcentaje = parseFloat((row && row.getAttribute('data-porcentaje')) || 0);
                        if (bacCero || saldoNegativo || porcentaje < 100) {
                            checkbox.disabled = true;
                        } else {
                            checkbox.disabled = false;
                        }
                    });
                }
                // Notificar a la ventana padre
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'actualizarTotales',
                        tiempo_imputado: tiempoTotal,
                        costo_aprobado: aprobadoTotal
                    }, '*');
                }
                return { tiempoTotal, aprobadoTotal };
            }
            
            // Recalcular totales AL CARGAR la página
            recalcularTotales();
            
            // Listener específico para checkboxes de aprobado (actualización masiva por agrupación)
            document.querySelectorAll('[data-campo="aprobado_coordinador"]').forEach(function(checkbox) {
                checkbox.addEventListener('change', function(e) {
                    e.stopPropagation();
                    if (this.disabled) {
                        this.checked = !this.checked;
                        return;
                    }
                    var row = this.closest('tr');
                    var rechazado = row ? row.querySelector('[data-campo="rechazado_coordinador"]') : null;
                    if (this.checked) {
                        if (!confirm('¿Está seguro que desea APROBAR las horas de este colaborador?')) {
                            this.checked = false;
                            return;
                        }
                        if (rechazado && rechazado.checked) {
                            rechazado.checked = false;
                        }
                    }
                    var numeroEmpleado = this.getAttribute('data-numero-empleado');
                    var codigoAffaire = this.getAttribute('data-codigo-affaire');
                    var valor = this.checked ? 1 : 0;
                    sincronizarCheckboxesAprobacion();
                    recalcularTotales();
                    fetch('update_app_reporte_inputhh.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'numero_empleado=' + encodeURIComponent(numeroEmpleado) + '&codigo_affaire=' + encodeURIComponent(codigoAffaire) + '&campo=aprobado_coordinador&valor=' + encodeURIComponent(valor) + '&accion=masivo'
                    });
                });
            });

            // Listener específico para checkboxes de rechazado (actualización masiva por agrupación)
            document.querySelectorAll('[data-campo="rechazado_coordinador"]').forEach(function(checkbox) {
                checkbox.addEventListener('change', function(e) {
                    if (this.disabled) {
                        this.checked = !this.checked;
                        return;
                    }
                    var row = this.closest('tr');
                    var aprobado = row ? row.querySelector('[data-campo="aprobado_coordinador"]') : null;
                    if (this.checked) {
                        if (!confirm('¿Está seguro que desea RECHAZAR las horas de este colaborador?')) {
                            this.checked = false;
                            return;
                        }
                        if (aprobado && aprobado.checked) {
                            aprobado.checked = false;
                        }
                    }
                    var numeroEmpleado = this.getAttribute('data-numero-empleado');
                    var codigoAffaire = this.getAttribute('data-codigo-affaire');
                    var valor = this.checked ? 1 : 0;
                    sincronizarCheckboxesAprobacion();
                    recalcularTotales();
                    fetch('update_app_reporte_inputhh.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'numero_empleado=' + encodeURIComponent(numeroEmpleado) + '&codigo_affaire=' + encodeURIComponent(codigoAffaire) + '&campo=rechazado_coordinador&valor=' + encodeURIComponent(valor) + '&accion=masivo'
                    });
                });
            });
            
            // Listener para cambios en tiempo_imputado_costo (actualización en tiempo real)
            document.querySelectorAll('[data-campo="tiempo_imputado_costo"]').forEach(function(input) {
                input.addEventListener('input', function() {
                    recalcularTotales();
                });
                
                input.addEventListener('change', function(e) {
                    e.stopPropagation();
                    
                    var id = this.getAttribute('data-id');
                    var valor = this.value;
                    
                    recalcularTotales();
                    
                    var statusSpan = document.getElementById('status-' + id);
                    if (statusSpan) {
                        statusSpan.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
                    }
                    
                    fetch('update_app_reporte_inputhh.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(id) + '&campo=tiempo_imputado_costo&valor=' + encodeURIComponent(valor)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (statusSpan) {
                                statusSpan.innerHTML = '<span class="text-success">✔</span>';
                                setTimeout(() => { statusSpan.innerHTML = ''; }, 1500);
                            }
                        } else {
                            if (statusSpan) {
                                statusSpan.innerHTML = '<span class="text-danger" title="' + (data.error || 'Error') + '">✖</span>';
                                setTimeout(() => { statusSpan.innerHTML = ''; }, 1500);
                            }
                        }
                    })
                    .catch((error) => {
                        if (statusSpan) {
                            statusSpan.innerHTML = '<span class="text-danger">✖</span>';
                            setTimeout(() => { statusSpan.innerHTML = ''; }, 1500);
                        }
                    });
                });
            });
            
            // Listener genérico para otros campos editables
            document.querySelectorAll('.editable-input:not([data-campo="aprobado_coordinador"]):not([data-campo="rechazado_coordinador"]):not([data-campo="tiempo_imputado_costo"])').forEach(function(input) {
                input.addEventListener('change', function(e) {
                    e.stopPropagation();
                    
                    var id = this.getAttribute('data-id');
                    var campo = this.getAttribute('data-campo');
                    var valor = this.value;
                    
                    var statusSpan = document.getElementById('status-' + id);
                    if (statusSpan) {
                        statusSpan.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span>';
                    }
                    
                    fetch('update_app_reporte_inputhh.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + encodeURIComponent(id) + '&campo=' + encodeURIComponent(campo) + '&valor=' + encodeURIComponent(valor)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (statusSpan) {
                                statusSpan.innerHTML = '<span class="text-success">✔</span>';
                                setTimeout(() => { statusSpan.innerHTML = ''; }, 1500);
                            }
                        } else {
                            if (statusSpan) {
                                statusSpan.innerHTML = '<span class="text-danger" title="' + (data.error || 'Error') + '">✖</span>';
                                setTimeout(() => { statusSpan.innerHTML = ''; }, 1500);
                            }
                        }
                    })
                    .catch((error) => {
                        if (statusSpan) {
                            statusSpan.innerHTML = '<span class="text-danger">✖</span>';
                            setTimeout(() => { statusSpan.innerHTML = ''; }, 1500);
                        }
                    });
                });
            });
            
            // Verificar cuántos botones se encontraron
            const botonesDetalle = document.querySelectorAll('.btn-ver-detalle');
            console.log('Botones encontrados:', botonesDetalle.length);
            
            // Guardar el contenido original del modal padre
            let contenidoOriginal = null;
            let tituloOriginal = null;
            
            // Listener para el botón de ver detalles (abre modal secundario sin cerrar el principal)
            botonesDetalle.forEach(function(btn, index) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const numeroEmpleado = this.getAttribute('data-numero-empleado');
                    const codigoAffaire = this.getAttribute('data-codigo-affaire');
                    const nom = this.getAttribute('data-nom');
                    const prenom = this.getAttribute('data-prenom');
                    // Cambiar título del modal secundario
                    document.getElementById('modalDetalleImputacionLabel').innerHTML = '<i class="bi bi-info-circle-fill me-2"></i>Detalle de Imputación - ' + nom + ' ' + prenom;
                    // Mostrar spinner
                    document.getElementById('modalDetalleImputacionBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div><p class="mt-3 text-muted">Cargando detalles...</p></div>';
                    // Mostrar modal secundario encima (sin cerrar el principal)
                    var modalDetalle = new bootstrap.Modal(document.getElementById('modalDetalleImputacion'), {backdrop: 'static', focus: true});
                    modalDetalle.show();
                    // Hacer fetch AJAX para cargar el detalle
                    fetch('get_detalle_imputacion.php?numero_empleado=' + encodeURIComponent(numeroEmpleado) + '&codigo_affaire=' + encodeURIComponent(codigoAffaire))
                        .then(response => response.json())
                        .then(data => {
                            let html = '';
                            if (data.success && data.detalles && data.detalles.length > 0) {
                                html += `<div class="table-responsive" style="padding: 18px 18px 0 18px;">
                                    <table class="table table-bordered table-sm align-middle mb-0" style="border-radius: 12px; overflow: hidden; background: #fff;">
                                        <thead>
                                            <tr style="background: #e9f7ef;">
                                                <th class="text-center">N° Empleado</th>
                                                <th class="text-center">Nombre Colaborador</th>
                                                <th class="text-center">Proyecto</th>
                                                <th class="text-center">Fecha</th>
                                                <th class="text-center">Horas Imputadas</th>
                                                ${esAusencia ? '' : '<th class="text-center">Costo Imputado</th>'}
                                                <th class="text-center">Comentario</th>
                                            </tr>
                                        </thead>
                                        <tbody>`;
                                let totalHoras = 0;
                                let totalCosto = 0;
                                data.detalles.forEach(function(detalle) {
                                    let horas = parseFloat(detalle.horas.replace(/\./g, '').replace(',', '.')) || 0;
                                    let costo = parseFloat(detalle.costo.replace(/\./g, '').replace(',', '.')) || 0;
                                    totalHoras += horas;
                                    totalCosto += costo;
                                    let nombreCompleto = (detalle.nom || '') + ' ' + (detalle.prenom || '');
                                    nombreCompleto = nombreCompleto.trim() || '-';
                                    html += `<tr>
                                        <td class="text-center fw-semibold">${detalle.numero_empleado || '-'}</td>
                                        <td><span class="fw-bold">${nombreCompleto}</span></td>
                                        <td class="text-muted">${detalle.proyecto || '-'}</td>
                                        <td>${detalle.fecha || '-'}</td>
                                        <td class="text-end"><span style="color: #007bff; font-weight: 600;">${detalle.horas || '0'}</span></td>
                                        ${esAusencia ? '' : `<td class="text-end"><span style="color: #28a745; font-weight: 600;">$ ${detalle.costo || '0'}</span></td>`}
                                        <td><small>${detalle.comentario || '-'}</small></td>
                                    </tr>`;
                                });
                                html += `</tbody>
                                    <tfoot>
                                        <tr style="background: #f6f6f6; font-size: 1.08em;">
                                            <td colspan="4" class="text-end fw-bold" style="white-space: nowrap;">TOTALES:</td>
                                            <td class="text-end fw-bold" style="color: #007bff; white-space: nowrap;">${totalHoras.toLocaleString('es-CL', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                                            ${esAusencia ? '' : `<td class="text-end fw-bold" style="color: #28a745; white-space: nowrap;">$ ${Math.round(totalCosto).toLocaleString('es-CL')}</td>`}
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                                </div>`;
                            } else {
                                html += `<div class="alert alert-info" role="alert"><i class="bi bi-info-circle me-2"></i>No hay detalles para mostrar.</div>`;
                            }
                            document.getElementById('modalDetalleImputacionBody').innerHTML = html;
                        })
                        .catch(error => {
                            document.getElementById('modalDetalleImputacionBody').innerHTML = `<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-2"></i>Error al cargar los detalles: ${error.message}<br>Por favor, intente nuevamente.</div>`;
                        });
                });
            });
            }, 100); // Esperar 100ms para que el DOM esté listo
        })(); // Cerrar función autoejecutable
        </script>
        <script>
        // Recargar la página principal al cerrar el modal de detalle
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById('modalDetalleImputacion');
            if (modal) {
                modal.addEventListener('hidden.bs.modal', function () {
                    if (window.parent && window.parent !== window) {
                        window.parent.location.reload();
                    }
                });
            }
        });
        </script>


<!-- Modal secundario para detalle de imputación (ajustado y más ancho) -->
<style>
    /* Ajuste para modal grande y responsivo */
    #modalDetalleImputacion .modal-dialog {
        max-width: 95vw;
        width: 100%;
        margin: 0 auto;
    }
    #modalDetalleImputacion .modal-content {
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    #modalDetalleImputacion .modal-body {
        overflow-y: auto;
        max-height: 65vh;
        min-height: 300px;
    }
    @media (max-width: 768px) {
        #modalDetalleImputacion .modal-dialog {
            max-width: 99vw;
        }
        #modalDetalleImputacion .modal-body {
            max-height: 55vh;
        }
    }
</style>
<div class="modal fade" id="modalDetalleImputacion" tabindex="-1" aria-labelledby="modalDetalleImputacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow rounded-4 border-0">
            <div class="modal-header bg-success bg-gradient text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalDetalleImputacionLabel">
                    <i class="bi bi-info-circle-fill me-2"></i>Detalle de Imputación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4 bg-light rounded-bottom-4" id="modalDetalleImputacionBody" style="padding: 32px 24px;">
                <!-- Aquí se cargará el detalle -->
            </div>
            <div class="modal-footer bg-light rounded-bottom-4 border-0 d-flex justify-content-end" style="padding: 0 1.5rem 1.5rem 1.5rem;">
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="min-width: 120px; margin-right: 32px; margin-bottom: 16px; margin-top: 32px;">
                    &larr; Regresar
                </button>
            </div>
        </div>
    </div>
</div>
