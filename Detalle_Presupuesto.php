<?php
// Detalle_Proyecto.php
// Muestra el detalle de un proyecto específico

session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}


// Capturar el menú sin imprimir su documento HTML completo.
ob_start();
include 'menu.php';
$menu_markup = ob_get_clean();

$menu_head_assets = '';
if (preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $menu_markup, $head_match)) {
    if (preg_match_all('/<link\b[^>]*>|<style\b[^>]*>.*?<\/style>/is', $head_match[1], $asset_matches)) {
        $menu_head_assets = implode("\n", $asset_matches[0]);
    }
}

$menu_body = $menu_markup;
if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $menu_markup, $body_match)) {
    $menu_body = $body_match[1];
}


require_once __DIR__ . '/vendor/autoload.php';
// Incluir archivos de configuración y conexión centralizada
require_once 'include.php';
require_once 'config.php';
// $conn debe estar definido en config.php/include.php
if (!isset($conn) || !$conn) {
    die("Error: No se pudo establecer la conexión a la base de datos.");
}

// Obtener información del usuario logueado
$usuario_actual = $_SESSION['usuario'];
$sql_usuario = "SELECT ROL, Área_Funcional, Nombre_Usuario FROM login_usuarios WHERE Usuario = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario_actual);
$stmt->execute();
$result_usuario = $stmt->get_result();
$usuario_info = $result_usuario->fetch_assoc();
$stmt->close();

if (!$usuario_info) {
    die("Error: No se pudo obtener la información del usuario.");
}

// Traer todas las filas

if ($usuario_info['ROL'] == 'COORD' || $usuario_info['ROL'] == 'MIX2') {
    $sql = "SELECT * FROM gastos_personal WHERE `ÁREA FUNCIONAL` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario_info['Área_Funcional']);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} elseif ($usuario_info['ROL'] == 'MIX') {
    if (strtoupper($usuario_actual) === 'JGELVEZ') {
        $areas_mix = ["Arquitectura y Urbanismo", "Estructuras"];
    } else {
        $areas_mix = ["BIM", "VÍAS", "Vías y Topografía"];
    }
    $areas_mix_str = "'" . implode("','", array_map([$conn, 'real_escape_string'], $areas_mix)) . "'";
    $sql = "SELECT * FROM gastos_personal WHERE `ÁREA FUNCIONAL` IN ($areas_mix_str)";
    $result = $conn->query($sql);
} else {
    $sql = "SELECT * FROM gastos_personal";
    $result = $conn->query($sql);
}

$meses = [
    'ene25','feb25','mar25','abr25','may25','jun25','jul25','ago25','sep25','oct25','nov25','dic25',
    'ene26','feb26','mar26','abr26','may26','jun26','jul26','ago26','sep26','oct26','nov26','dic26',
    'ene27','feb27','mar27','abr27','may27','jun27','jul27','ago27','sep27','oct27','nov27','dic27',
    'ene28','feb28','mar28','abr28','may28','jun28','jul28','ago28','sep28','oct28','nov28','dic28'
];



// Query para costos valorizados por área


$area_filter_valorizado = "";
if ($usuario_info['ROL'] == 'COORD' || $usuario_info['ROL'] == 'MIX2') {
    $area_filter_valorizado = " WHERE `ÁREA FUNCIONAL` = '" . $conn->real_escape_string($usuario_info['Área_Funcional']) . "'";
} elseif ($usuario_info['ROL'] == 'MIX') {
    $areas_mix = ["BIM", "VÍAS", "Vías y Topografía"];
    $areas_mix_str = "'" . implode("','", array_map([$conn, 'real_escape_string'], $areas_mix)) . "'";
    $area_filter_valorizado = " WHERE `ÁREA FUNCIONAL` IN ($areas_mix_str)";
}

$sql_costo_valorizado_areas = "SELECT 
    CECO_CONEXION, 
    `ÁREA FUNCIONAL`,
    SUM(ene_25 + feb_25 + mar_25 + abr_25 + may_25 + jun_25 + jul_25 + ago_25 + sep_25 + oct_25 + nov_25 + dic_25) AS costo_total
FROM costo_valorizado
$area_filter_valorizado
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


// Traer porcentajes de avance físico ejecutado y programado por área funcional para el proyecto desde la tabla correcta
// Query para avance físico


$area_filter_avance = "";
if ($usuario_info['ROL'] == 'COORD' || $usuario_info['ROL'] == 'MIX2') {
    $area_filter_avance = " WHERE AREA_FUNCIONAL = '" . $conn->real_escape_string($usuario_info['Área_Funcional']) . "'";
} elseif ($usuario_info['ROL'] == 'MIX') {
    $areas_mix = ["BIM", "VÍAS", "Vías y Topografía"];
    $areas_mix_str = "'" . implode("','", array_map([$conn, 'real_escape_string'], $areas_mix)) . "'";
    $area_filter_avance = " WHERE AREA_FUNCIONAL IN ($areas_mix_str)";
}

$sql_avance = "SELECT 
    AREA_FUNCIONAL, 
    PORCENTAJE_AVANCE_FISICO_EJECUTADO, 
    PORCENTAJE_AVANCE_FISICO_PROGRAMADO 
FROM avance_fisico_ejecutado_programado
$area_filter_avance";
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Detalle de Presupuestos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="css/tabla-alta-reducida.css" rel="stylesheet">
<?php echo $menu_head_assets; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.form-select').select2({
                width: '100%',
                allowClear: true,
                placeholder: function() {
                    return $(this).data('placeholder') || 'Seleccionar...';
                },
                language: {
                    noResults: function() {
                        return 'No se encontraron resultados';
                    }
                }
            });

            $('#filtroProyecto, #filtroVersion, #filtroCategoria, #filtroNombreCategoria, #filtroAreaFuncional').on('change', function() {
                aplicarFiltros();
            });

            actualizarResumenes();
            recalcularTotalesFiltrados();
        });

        function formatearMoneda(valor) {
            return '$ ' + Math.round(valor).toLocaleString('es-CO');
        }

        function formatearHoras(valor) {
            return valor.toLocaleString('es-CO', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            });
        }

        function obtenerColumnasMes(tabla) {
            const mesesCols = [];
            const mesesRegex = /^(ene|feb|mar|abr|may|jun|jul|ago|sep|oct|nov|dic)/i;
            const ths = tabla.querySelectorAll('thead th');

            ths.forEach((th, idx) => {
                if (mesesRegex.test(th.textContent.trim())) {
                    mesesCols.push(idx);
                }
            });

            return mesesCols;
        }

        function sumarTablaVisible(tabla) {
            const visibles = Array.from(tabla.querySelectorAll('tbody tr')).filter(fila => fila.style.display !== 'none');
            const mesesCols = obtenerColumnasMes(tabla);
            let suma = 0;

            visibles.forEach(fila => {
                mesesCols.forEach(col => {
                    const celda = fila.cells[col];
                    if (!celda) return;

                    const val = celda.textContent
                        .replace(/\$/g, '')
                        .replace(/\./g, '')
                        .replace(/,/g, '.')
                        .replace(/[^\d.-]/g, '');

                    if (val && !isNaN(val)) {
                        suma += parseFloat(val);
                    }
                });
            });

            return suma;
        }

        function actualizarResumenes() {
            const tablaHoras = document.getElementById('tablaHoras');
            const tablaCostos = document.getElementById('tablaCostos');
            const totalHoras = tablaHoras ? sumarTablaVisible(tablaHoras) : 0;
            const totalPresupuesto = tablaCostos ? sumarTablaVisible(tablaCostos) : 0;

            const resumenHoras = document.getElementById('resumenHorasValor');
            const resumenPresupuesto = document.getElementById('resumenPresupuestoValor');

            if (resumenHoras) {
                resumenHoras.textContent = formatearHoras(totalHoras);
            }

            if (resumenPresupuesto) {
                resumenPresupuesto.textContent = formatearMoneda(totalPresupuesto);
            }
        }

        function aplicarFiltros() {
            const filtros = {
                proyecto: $('#filtroProyecto').val()?.toLowerCase() || '',
                version: $('#filtroVersion').val()?.toLowerCase() || '',
                categoria: $('#filtroCategoria').val()?.toLowerCase() || '',
                nombreCategoria: $('#filtroNombreCategoria').val()?.toLowerCase() || '',
                areaFuncional: $('#filtroAreaFuncional').val()?.toLowerCase() || ''
            };

            const tablas = document.querySelectorAll('.tabla-filtro');
            tablas.forEach(tabla => {
                const filas = tabla.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                for (let fila of filas) {
                    if (fila.classList.contains('fila-total')) continue;
                    const celdas = {
                        proyecto: fila.cells[1]?.textContent.toLowerCase() || '',
                        version: fila.cells[2]?.textContent.toLowerCase() || '',
                        categoria: fila.cells[4]?.textContent.toLowerCase() || '',
                        nombreCategoria: fila.cells[6]?.textContent.toLowerCase() || '',
                        areaFuncional: fila.cells[7]?.textContent.toLowerCase() || ''
                    };
                    const mostrarFila = Object.keys(filtros).every(key => {
                        return !filtros[key] || celdas[key].includes(filtros[key]);
                    });
                    fila.style.display = mostrarFila ? '' : 'none';
                }
            });
            recalcularTotalesFiltrados();
            actualizarResumenes();
        }

        function recalcularTotalesFiltrados() {
            document.querySelectorAll('.tabla-filtro').forEach(tabla => {
                const tfoot = tabla.querySelector('tfoot');
                if (!tfoot) return;

                const filas = Array.from(tabla.querySelectorAll('tbody tr:not(.fila-total)'));
                if (filas.length === 0) return;

                const visibles = filas.filter(fila => fila.style.display !== 'none');
                if (visibles.length === 0) {
                    tfoot.querySelectorAll('td').forEach(td => {
                        td.textContent = '';
                    });
                    const etiquetaTotal = tfoot.querySelector('.total-label');
                    if (etiquetaTotal) {
                        etiquetaTotal.textContent = 'TOTAL FILTRADO';
                    }
                    return;
                }

                const numCols = tfoot.querySelectorAll('td').length;
                const esCosto = tabla.id === 'tablaCostos';
                const mesesCols = obtenerColumnasMes(tabla);
                const etiquetaTotal = tfoot.querySelector('.total-label');

                if (etiquetaTotal) {
                    etiquetaTotal.textContent = 'TOTAL FILTRADO';
                }

                for (let col = 0; col < numCols; col++) {
                    const td = tfoot.querySelectorAll('td')[col];
                    if (!td) continue;

                     if (td.classList.contains('total-label') || td.classList.contains('total-spacer')) {
                        continue;
                    }

                    if (mesesCols.includes(col)) {
                        let suma = 0;
                        let esNumero = false;
                        let colVisible = false;

                        visibles.forEach(fila => {
                            const celda = fila.cells[col];
                            if (!celda || celda.offsetParent === null) return;

                            colVisible = true;
                            const val = celda.textContent
                                .replace(/\$/g, '')
                                .replace(/\./g, '')
                                .replace(/,/g, '.')
                                .replace(/[^\d.-]/g, '');

                            if (val && !isNaN(val)) {
                                suma += parseFloat(val);
                                esNumero = true;
                            }
                        });

                        if (esNumero && colVisible) {
                            if (esCosto) {
                                td.textContent = '$ ' + Math.round(suma).toLocaleString('es-CO');
                            } else {
                                td.textContent = suma % 1 === 0
                                    ? suma.toLocaleString('es-CO') + ',0'
                                    : suma.toLocaleString('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                            }
                        } else {
                            td.textContent = '';
                        }
                    } else {
                        td.textContent = '';
                    }
                }
            });
        }

        function limpiarFiltro() {
            $('#filtroProyecto, #filtroVersion, #filtroCategoria, #filtroNombreCategoria, #filtroAreaFuncional').val(null).trigger('change');
        }
    </script>
    <style>
        .filtros-container {
            background: #fff;
            margin-bottom: 2rem;
        }

        .filtros-titulo {
            color: #17823d;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 1.5rem;
        }

        .filtros-container .form-label,
        .filtros-container label,
        .filtros-container .select2-selection__placeholder,
        .filtros-container .select2-selection__rendered,
        .filtros-container .select2-selection__single {
            color: #17823d !important;
            font-weight: 600;
        }

        .filtros-container .col-md-2 > label,
        .filtros-container .col-md-3 > label,
        .filtros-container .col-md-4 > label {
            color: #17823d !important;
            font-weight: 600;
        }

        .form-label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }

        .select2-container--default .select2-selection--single {
            height: 38px !important;
            border: 1px solid #ddd !important;
            border-radius: 4px !important;
            background-color: #fff !important;
            padding: 5px 10px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px !important;
            padding-left: 0 !important;
            color: #444 !important;
            font-size: 0.9rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }

        .select2-dropdown {
            border-color: #ddd !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #4C8AA3 !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-select {
            border: 1px solid #ddd;
            border-radius: 4px;
            height: 38px;
            padding: 5px 10px;
            font-size: 0.9rem;
            background-color: #fff;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 12px;
            padding-right: 2rem;
        }

        .modern-select:hover {
            border-color: #cbd5e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .modern-select:focus {
            border-color: #4C8AA3;
            box-shadow: 0 0 0 3px rgba(76,138,163,0.15);
            outline: none;
        }

        .select2-container--default .select2-selection--single {
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            height: 42px !important;
            padding: 0.375rem 0.75rem;
            background-color: #fff !important;
            transition: all 0.2s ease;
        }

        .select2-container--default .select2-selection--single:hover {
            border-color: #cbd5e0 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 24px !important;
            color: #2d3748 !important;
            font-size: 0.9rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            width: 30px !important;
        }

        .select2-dropdown {
            border-color: #e2e8f0 !important;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05) !important;
            border-radius: 8px !important;
        }

        .select2-search__field {
            border-radius: 4px !important;
            padding: 0.5rem !important;
        }

        .select2-results__option {
            padding: 0.5rem 1rem !important;
            font-size: 0.9rem !important;
        }

        .form-label {
            color: #4a5568;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .select-container {
            position: relative;
        }

        .detalle-table th, .detalle-table td, .tabla-filtro th, .tabla-filtro td {
            border-right: 1px solid #e0e0e0;
            vertical-align: middle;
            font-size: 0.82rem;
            font-family: 'Segoe UI', 'Roboto', 'Arial', sans-serif;
            padding: 8px 6px;
            white-space: nowrap;
        }

        .detalle-table th {
            background: #4C8AA3;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 0.97rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .detalle-table tfoot tr {
            position: sticky;
            bottom: 0;
            z-index: 11;
        }

        .detalle-table tfoot td {
            background: #f2f7f4;
            font-weight: bold;
            border-top: 2px solid #b7d7c5;
        }

        .detalle-table tfoot td.total-label {
            background: #dff2e8;
            color: #17823d;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .detalle-table tfoot td.total-spacer {
            background: #edf5f0;
        }

        .table-shell {
            margin: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            background: #fff;
        }

        .table-scroll {
            overflow-x: auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            max-width: 100%;
            max-height: 420px;
        }

        .table-scroll::-webkit-scrollbar {
            height: 6px;
            width: 8px;
        }

        .table-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .table-scroll::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .detalle-table {
            margin-bottom: 0;
            width: max-content;
            min-width: 100%;
        }

        .detalle-table th:nth-child(1), .detalle-table td:nth-child(1) { min-width: 120px; }
        .detalle-table th:nth-child(2), .detalle-table td:nth-child(2) { min-width: 250px; }
        .detalle-table th:nth-child(3), .detalle-table td:nth-child(3) { min-width: 100px; }
        .detalle-table th:nth-child(4), .detalle-table td:nth-child(4) { min-width: 120px; }

        .detalle-table td {
            color: #222;
            font-weight: 400;
            padding-top: 0.18rem;
            padding-bottom: 0.18rem;
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

        .main-content    {
            background: transparent !important;
            padding-left: 150px !important;
            padding-right: 80px !important;
            margin-top: 28px;
        }

        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .resumen-card {
            border-radius: 18px;
            padding: 1rem 1.25rem 1rem 1.25rem;
            box-shadow: 0 10px 24px rgba(23, 130, 61, 0.08);
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            min-height: 120px;
        }

        .resumen-card-presupuesto {
            background: linear-gradient(135deg, #f7fafc 0%, #e6f6f2 100%);
            border-color: #b6e2d3;
        }
        .resumen-card-horas {
            background: linear-gradient(135deg, #f8f7fc 0%, #e6eaf6 100%);
            border-color: #b6c6e2;
        }

        .resumen-icon {
            font-size: 2.2rem;
            margin-bottom: 0.2rem;
            color: #4dc18f;
            background: #e6f6f2;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(76,193,143,0.08);
        }
        .resumen-card-horas .resumen-icon {
            color: #5c7edc;
            background: #e6eaf6;
            box-shadow: 0 2px 8px rgba(92,126,220,0.08);
        }

        .resumen-label {
            color: #5f6f66;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.35rem;
        }

        .resumen-valor {
            color: #17823d;
            font-size: 1.7rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .resumen-ayuda {
            color: #6d7f74;
            font-size: 0.85rem;
            margin-top: 0.4rem;
        }

        .table-section-title {
            padding: 1rem 1rem 0.5rem;
        }

        @media (max-width: 992px) {
            .main-content {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }

            .resumen-grid {
                grid-template-columns: 1fr;
            }
        }

         /* Fondo de burbujas animadas, poco notorias */
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
        background: rgba(91, 141, 184, 0.13);
        border-radius: 50%;
        box-shadow: 0 8px 48px 0 rgba(91, 141, 184, 0.10), 0 0 32px 12px rgba(91, 141, 184, 0.07);
        filter: blur(2.5px) brightness(1.08);
        animation: bubbleUp var(--duration) linear infinite;
        animation-delay: var(--delay);
        z-index: 0;
        transition: background 0.3s;
    }
    @keyframes bubbleUp {
        0% {
            transform: translateY(0) scale(1);
            opacity: 0.5;
        }
        80% {
            opacity: 0.25;
        }
        100% {
            transform: translateY(-110vh) scale(1.1);
            opacity: 0;
        }
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
    </div>
<?php echo $menu_body; ?>
<div class="main-content">

    <?php
    // Mantenemos el cálculo de totales ya que se usan más adelante
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
    SUM(ene_25 + feb_25 + mar_25 + abr_25 + may_25 + jun_25 + jul_25 + ago_25 + sep_25 + oct_25 + nov_25 + dic_25) AS costo_total
FROM costo_valorizado
GROUP BY CECO_CONEXION";
$costos_actuales = [];
$result_costo = $conn->query($sql_costo_valorizado);
if ($result_costo && $result_costo->num_rows > 0) {
    while($row_costo = $result_costo->fetch_assoc()) {
        $costos_actuales[$row_costo['CECO_CONEXION']] = $row_costo['costo_total'];
    }
}
    ?>

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
$areas_actual = [];
$all_areas = array_unique(array_merge($areas, $areas_actual));
sort($all_areas);
$valores = array_map(function($a) use ($totales_area) {
    return (int)round((isset($totales_area[$a]) ? $totales_area[$a] : 0)/1000000,0);
}, $all_areas);
$valores_actual = array_map(function($a) use ($costos_actuales_areas) {
    return 0;
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
if (count($all_areas) > 0 && (array_sum($valores) > 0 || array_sum($valores_actual) > 0)) {
    ?>

    <style>
    .container {
        max-width: 95% !important;
        margin: 20px auto;
        padding: 0 15px;
    }
    .bg-light {
        background-color: #f0fdf4 !important;
        border: 1px solid #dcfce7;
    }
    /* Mejoras de responsividad */
    @media (max-width: 1400px) {
        .container {
            max-width: 98% !important;
            padding: 0 10px;
        }
    }
    @media (max-width: 992px) {
        .container {
            max-width: 100% !important;
            padding: 0 5px;
        }
    }
    .rounded-3 {
        border-radius: 0.5rem !important;
    }
    .gap-4 {
        gap: 1.5rem !important;
    }
    .gap-2 {
        gap: 0.5rem !important;
    }
    .table-responsive {
        margin: 0;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .table {
        margin-bottom: 0;
        font-size: 0.875rem;
        width: 100%;
        background: white;
    }
    .table th {
        background-color: #5C9BB3 !important;
        color: white;
        font-weight: 500;
        padding: 0.75rem 1rem;
        border-bottom: none;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .table td {
        padding: 0.625rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #edf2f7;
        color: #2d3748;
    }
    .table tbody tr:hover {
        background-color: #f8fafc;
    }
    .table td[style*="color:#17823d"] {
        color: #17823d !important;
        font-weight: 500;
    }
    /* Estilo para valores monetarios */
    .table td:has(span.currency) {
        text-align: right;
    }
    .currency {
        font-family: 'Segoe UI', system-ui, sans-serif;
        font-weight: 500;
    }
    /* Alinear porcentajes a la derecha */
    .table td:last-child {
        text-align: right;
    }
    /* Ajustes para el encabezado */
    .d-flex.justify-content-between {
        max-width: 85%;
        margin: 0 auto 2rem;
        padding: 0 30px;
    }
    h2.mb-0 {
        color: #2d3748;
        font-size: 1.75rem;
        font-weight: 600;
    }
    </style>

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
    <div class="row mb-4">
        <div class="col-md-12">

                    <div class="resumen-grid">
                        <div class="resumen-card resumen-card-presupuesto">
                            <div class="resumen-icon"><i class="bi bi-cash-stack"></i></div>
                            <div class="resumen-label">Suma Presupuesto</div>
                            <div class="resumen-valor" id="resumenPresupuestoValor"><?php echo '$ ' . number_format((int) round($total_costo), 0, '', '.'); ?></div>
                            <div class="resumen-ayuda">Valor acumulado del presupuesto cargado en costo.</div>
                        </div>
                        <div class="resumen-card resumen-card-horas">
                            <div class="resumen-icon"><i class="bi bi-clock-history"></i></div>
                            <div class="resumen-label">Suma Horas</div>
                            <div class="resumen-valor" id="resumenHorasValor"><?php echo number_format($total_horas, 1, ',', '.'); ?></div>
                            <div class="resumen-ayuda">Total de horas visibles en el detalle presupuestal.</div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="filtros-container bg-white p-4 rounded-4 shadow-sm border">
                        <h6 class="filtros-titulo mb-4">FILTROS DE BÚSQUEDA</h6>
                        <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-5">
                                <div class="col-md-3">
                                    <div class="form-header">Área Funcional</div>
                                    <div class="select-container">
                                        <select id="filtroAreaFuncional" class="form-select modern-select" <?php echo (in_array($usuario_info['ROL'], ['SUPER', 'MIX'])) ? '' : 'disabled'; ?>>
                                            <?php
                                            $areasFuncionales = array();
                                            foreach ($filas as $fila) {
                                                if (isset($fila['ÁREA FUNCIONAL']) && !in_array($fila['ÁREA FUNCIONAL'], $areasFuncionales)) {
                                                    $areasFuncionales[] = $fila['ÁREA FUNCIONAL'];
                                                }
                                            }
                                            sort($areasFuncionales);
                                            $areaUsuario = isset($usuario_info['Área_Funcional']) ? $usuario_info['Área_Funcional'] : '';
                                            foreach ($areasFuncionales as $areaFuncional) {
                                                $selected = ($areaFuncional === $areaUsuario) ? 'selected' : '';
                                                echo "<option value='" . htmlspecialchars($areaFuncional, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "' $selected>" . htmlspecialchars($areaFuncional, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-header">Nombre de Proyecto</div>
                                    <div class="select-container">
                                        <select id="filtroProyecto" class="form-select modern-select" data-placeholder="Buscar proyecto...">
                                            <option value=""></option>
                                            <?php
                                            // Construir lista de proyectos a partir de las filas que sí aparecen en la tabla
                                            $proyectos_en_tabla = [];
                                            foreach ($filas as $fila) {
                                                // Preferir nombre_proyecto si está disponible, sino intentar con PROYECTO (centro_costos)
                                                $nombre = '';
                                                if (!empty($fila['nombre_proyecto'])) {
                                                    $nombre = trim($fila['nombre_proyecto']);
                                                } elseif (!empty($fila['PROYECTO'])) {
                                                    // Intentar resolver nombre desde la tabla proyectos (solo si es necesario)
                                                    $cc = trim($fila['PROYECTO']);
                                                    $stmt_p = $conn->prepare("SELECT nombre_proyecto FROM proyectos WHERE centro_costos = ? LIMIT 1");
                                                    if ($stmt_p) {
                                                        $stmt_p->bind_param('s', $cc);
                                                        $stmt_p->execute();
                                                        $res_p = $stmt_p->get_result();
                                                        if ($r = $res_p->fetch_assoc()) {
                                                            $nombre = trim($r['nombre_proyecto']);
                                                        }
                                                        $stmt_p->close();
                                                    }
                                                }
                                                if ($nombre !== '') {
                                                    $proyectos_en_tabla[] = $nombre;
                                                }
                                            }
                                            // Quitar duplicados y ordenar
                                            $proyectos_en_tabla = array_unique($proyectos_en_tabla);
                                            sort($proyectos_en_tabla);
                                            foreach ($proyectos_en_tabla as $p) {
                                                $opt = htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                                echo "<option value='" . $opt . "'>" . $opt . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-header">Categoría</div>
                                    <div class="select-container">
                                        <select id="filtroCategoria" class="form-select modern-select">
                                            <option value="">Todas</option>
                                            <?php
                                            $categorias = array();
                                            foreach ($filas as $fila) {
                                                if (isset($fila['CATEGORIA']) && !in_array($fila['CATEGORIA'], $categorias)) {
                                                    $categorias[] = $fila['CATEGORIA'];
                                                }
                                            }
                                            sort($categorias);
                                            foreach ($categorias as $categoria) {
                                                echo "<option value='" . htmlspecialchars($categoria, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>" . 
                                                     htmlspecialchars($categoria, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-header">Versión</div>
                                    <div class="select-container">
                                        <select id="filtroVersion" class="form-select modern-select">
                                            <option value="">Todas</option>
                                            <?php
                                            $versiones = array();
                                            foreach ($filas as $fila) {
                                                if (isset($fila['VERSION']) && !in_array($fila['VERSION'], $versiones)) {
                                                    $versiones[] = $fila['VERSION'];
                                                }
                                            }
                                            sort($versiones);
                                            foreach ($versiones as $version) {
                                                echo "<option value='" . htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>" . 
                                                     htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-header">Nombre Categoría</div>
                                    <div class="select-container">
                                        <select id="filtroNombreCategoria" class="form-select modern-select">
                                            <option value="">Todas</option>
                                            <?php
                                            $nombresCategoria = array();
                                            foreach ($filas as $fila) {
                                                if (isset($fila['NOMBRE CATEGORIA']) && !in_array($fila['NOMBRE CATEGORIA'], $nombresCategoria)) {
                                                    $nombresCategoria[] = $fila['NOMBRE CATEGORIA'];
                                                }
                                            }
                                            sort($nombresCategoria);
                                            foreach ($nombresCategoria as $nombreCat) {
                                                echo "<option value='" . htmlspecialchars($nombreCat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "'>" . 
                                                     htmlspecialchars($nombreCat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="table-shell" id="tablasDetalle" style="background: #e0f7e9; border-radius: 18px; box-shadow: 0 4px 18px rgba(60,60,60,0.10); padding: 1.7rem 1.3rem 1.3rem 1.3rem;">
                        <!-- Título de la tabla con ícono -->
                        <div class="d-flex align-items-center mb-2 table-section-title" style="gap:10px;">
                            <i class="bi bi-clipboard-data" style="font-size:1.6rem;color:#17823d;"></i>
                            <h5 class="mb-0" style="color:#17823d;font-weight:700;letter-spacing:0.5px;">DETALLE PRESUPUESTO CARGADO EN HH</h5>
                        </div>
                        <?php
                        // --- Tabla de detalle por horas (igual que antes) ---
                        $cols_mostrar = [
                            'PROYECTO','NOMBRE PROYECTO','VERSION','FECHA VERSION','CATEGORIA','TARIFA COAN 2','NOMBRE CATEGORIA','ÁREA FUNCIONAL'
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
                            'PROYECTO','NOMBRE PROYECTO','VERSION','FECHA VERSION','CATEGORIA','TARIFA COAN 2','NOMBRE CATEGORIA','ÁREA FUNCIONAL'
                        ], $meses_mostrar);
                        // Cambiar color de encabezado a verde pastel y letra blanca
                        $thead = "<tr>";
                        foreach ($cols_mostrar_final as $colname) {
                            $thead .= "<th style='background-color:#4dc18f !important; color:#fff !important; font-weight:bold; white-space:nowrap; overflow:auto; word-break:break-all;'>" . htmlspecialchars($colname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</th>";
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
                                $td_style = "white-space:nowrap; overflow:auto; word-break:break-all;";
                                if ($colname == 'NOMBRE PROYECTO') {
                                    $nombre_proyecto = '';
                                    if (isset($cols['nombre_proyecto'])) {
                                        $nombre_proyecto = $cols['nombre_proyecto'];
                                    } elseif (isset($cols['PROYECTO'])) {
                                        // Si no tenemos el nombre, hacemos una consulta directa
                                        $stmt = $conn->prepare("SELECT nombre_proyecto FROM proyectos WHERE centro_costos = ?");
                                        $proyecto_id = $cols['PROYECTO'];
                                        $stmt->bind_param("s", $proyecto_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($row = $result->fetch_assoc()) {
                                            $nombre_proyecto = $row['nombre_proyecto'];
                                        }
                                        $stmt->close();
                                    }
                                    $row_html[] = "<td style='$td_style'>" . htmlspecialchars($nombre_proyecto ?: 'N/A', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                } else if (in_array($colname, $meses)) {
                                    $c = isset($cols[$colname]) ? $cols[$colname] : '';
                                    $c_trim = trim((string)$c);
                                    $c_num = str_replace(",", ".", $c_trim);
                                    if (is_numeric($c_num) && (float)$c_num != 0) {
                                        $row_html[] = "<td style='color:#17823d; font-weight:bold; background-color:#d6e3dd; $td_style'>" . number_format((float)$c_num, 1, ',', '.') . "</td>";
                                        // $totales_col[$colname] += (float)$c_num; // El total ya se suma antes, no aquí
                                        $total_horas += (float)$c_num;
                                        $suma_meses += (float)$c_num;
                                    } else {
                                        $row_html[] = "<td style='$td_style'>" . (is_numeric($c_num) ? number_format((float)$c_num, 1, ',', '.') : htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</td>";
                                    }
                                } else if ($colname == 'TARIFA COAN 2') {
                                    $c = isset($cols[$colname]) ? $cols[$colname] : '';
                                    $c_trim = trim((string)$c);
                                    $c_num = str_replace(",", ".", $c_trim);
                                    $row_html[] = "<td style='$td_style'>" . (is_numeric($c_num) ? '$ ' . number_format((int)$c_num, 0, '', '.') : htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</td>";
                                } else {
                                    $c = isset($cols[$colname]) ? $cols[$colname] : '';
                                    $c_trim = trim((string)$c);
                                    $row_html[] = "<td style='$td_style'>" . htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                }
                            }
                            $row_html[] = "</tr>";
                            $tbody .= implode('', $row_html);
                            $tarifa = isset($cols['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$cols['TARIFA COAN 2'])) : 0;
                            $plata_fila = $suma_meses * (is_numeric($tarifa) ? (float)$tarifa : 0);
                            $total_plata += $plata_fila;
                        }
                        // Mostrar tabla sin totales
                        // --- Agregar fila de totales por columna (horas) ---
                        $tfoot = "<tr class='fila-total' style='background-color:#e2e2e2; font-weight:bold;'>";
                        foreach ($cols_mostrar_final as $index => $colname) {
                            if ($index === 0) {
                                $tfoot .= "<td class='total-label'>TOTAL FILTRADO</td>";
                            } elseif ($index < 8) {
                                $tfoot .= "<td class='total-spacer'></td>";
                            } elseif (in_array($colname, $meses_mostrar)) {
                                $total_mes = isset($totales_col[$colname]) ? $totales_col[$colname] : 0;
                                $tfoot .= "<td style='color:#17823d;'>" . number_format($total_mes, 1, ',', '.') . "</td>";
                            } else {
                                $tfoot .= "<td></td>";
                            }
                        }
                        $tfoot .= "</tr>";
                        echo '<div class="table-scroll"><table class="table table-striped detalle-table align-middle mb-0 tabla-filtro" id="tablaHoras" data-tipo="horas"><thead>' . $thead . '</thead><tbody>' . $tbody . '</tbody><tfoot>' . $tfoot . '</tfoot></table></div>';
                        }
                        ?>

                    </div>

                    <!-- Tabla de detalle por costo -->
                    <div class="table-shell mt-5" style="background: #e6f4fa; border-radius: 18px; box-shadow: 0 4px 18px rgba(60,60,60,0.10); padding: 1.7rem 1.3rem 1.3rem 1.3rem;">
                        <div class="d-flex align-items-center mb-2 table-section-title" style="gap:10px;">
                            <i class="bi bi-cash-coin" style="font-size:1.6rem;color:#17823d;"></i>
                            <h5 class="mb-0" style="color:#17823d;font-weight:700;letter-spacing:0.5px;">DETALLE PRESUPUESTO CARGADO EN COSTO</h5>
                        </div>
                        <div class="table-scroll">
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
                            'PROYECTO', 'NOMBRE PROYECTO', 'VERSION','FECHA VERSION','CATEGORIA','TARIFA COAN 2','NOMBRE CATEGORIA','ÁREA FUNCIONAL'
                        ], $meses_mostrar_costo);
                        $thead_costo = "<tr>";
                        foreach ($cols_mostrar_final_costo as $colname) {
                            $thead_costo .= "<th style='background-color:#4dc18f !important; color:#fff !important; font-weight:bold; white-space:nowrap; overflow:auto; word-break:break-all;'>" . htmlspecialchars($colname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</th>";
                        }
                        $thead_costo .= "</tr>";
                        $tbody_costo = "";
                        $total_plata_costo = 0;
                        if (!empty($filas)) {
                            foreach ($filas as $cols) {
                                $tarifa = isset($cols['TARIFA COAN 2']) ? str_replace(",", ".", trim((string)$cols['TARIFA COAN 2'])) : 0;
                                $row_html = ["<tr>"];
                                foreach ($cols_mostrar_final_costo as $colname) {
                                    $td_style = "white-space:nowrap; overflow:auto; word-break:break-all;";
                                    
                                    // Manejo especial para la columna NOMBRE PROYECTO
                                    if ($colname == 'NOMBRE PROYECTO') {
                                        $nombre_proyecto = '';
                                        if (isset($cols['PROYECTO'])) {
                                            $stmt = $conn->prepare("SELECT nombre_proyecto FROM proyectos WHERE centro_costos = ?");
                                            $proyecto_id = $cols['PROYECTO'];
                                            $stmt->bind_param("s", $proyecto_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            if ($row = $result->fetch_assoc()) {
                                                $nombre_proyecto = $row['nombre_proyecto'];
                                            }
                                            $stmt->close();
                                        }
                                        $row_html[] = "<td style='$td_style'>" . htmlspecialchars($nombre_proyecto ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                        continue;
                                    }
                                    
                                    $c = isset($cols[$colname]) ? $cols[$colname] : '';
                                    $c_trim = trim((string)$c);
                                    if (in_array($colname, $meses)) {
                                        $c_num = str_replace(",", ".", $c_trim);
                                        $costo = (is_numeric($c_num) && is_numeric($tarifa)) ? ((float)$c_num * (float)$tarifa) : 0;
                                        $row_html[] = "<td style='color:#17823d; font-weight:bold; background-color:#d6e3dd; $td_style'>$ " . number_format((int)$costo, 0, '', '.') . "</td>";
                                        $total_plata_costo += $costo;
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
                            // Mostrar tabla de costos sin totales
                            // --- Agregar fila de totales por columna (costos) ---
                            $tfoot_costo = "<tr class='fila-total' style='background-color:#e2e2e2; font-weight:bold;'>";
                            foreach ($cols_mostrar_final_costo as $index => $colname) {
                                if ($index === 0) {
                                    $tfoot_costo .= "<td class='total-label'>TOTAL FILTRADO</td>";
                                } elseif ($index < 8) {
                                    $tfoot_costo .= "<td class='total-spacer'></td>";
                                } elseif (in_array($colname, $meses_mostrar_costo)) {
                                    $total_mes = isset($totales_col_costo[$colname]) ? $totales_col_costo[$colname] : 0;
                                    $tfoot_costo .= "<td style='color:#17823d;'>$ " . number_format($total_mes, 0, '', '.') . "</td>";
                                } else {
                                    $tfoot_costo .= "<td></td>";
                                }
                            }
                            $tfoot_costo .= "</tr>";
                            echo '<table class="table table-striped detalle-table align-middle mb-0 tabla-filtro" id="tablaCostos" data-tipo="costo"><thead>' . $thead_costo . '</thead><tbody>' . $tbody_costo . '</tbody><tfoot>' . $tfoot_costo . '</tfoot></table>';

                        }
                        ?>
                        </div>
                    </div>

                </div>
            </div>
    </div>

</div>
</body>
</html>
<?php $conn->close(); ?>
