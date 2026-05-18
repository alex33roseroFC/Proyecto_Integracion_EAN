<?php
// Opción de descarga del archivo Excel original
if (isset($_GET['descargar'])) {
    $archivo = "Documentos/GP-FOR-06_V6_Presupuesto - Mejorado.xlsm";
    if (file_exists($archivo)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.ms-excel.sheet.macroEnabled.12');
        header('Content-Disposition: attachment; filename="'.basename($archivo).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($archivo));
        readfile($archivo);
        exit;
    } else {
        echo "El archivo no existe.";
        exit;
    }
}
// -------------------- ACTIVAR SESIÓN --------------------
//session_start();


session_start();
//echo '<pre>dashboard.php: '; print_r($_SESSION); echo '</pre>';
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}


$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
$matricula = isset($_SESSION['matricula']) ? strval($_SESSION['matricula']) : '';
$Nombre_User = isset($_SESSION['Nombre_Usuario']) ? strval($_SESSION['Nombre_Usuario']) : '';

// Encabezado de bienvenida y logout

// Obtener datos del usuario desde la base de datos
$area_funcional = '';
$nombre_usuario = '';

require_once 'include.php';
require_once 'config.php';

if (!isset($conn) || !$conn) {
    die("Error conexión: No se pudo conectar a la base de datos.");
}

if (!empty($usuario)) {
    $sql_user = "SELECT Área_Funcional, Nombre_Usuario FROM login_usuarios WHERE Usuario = ? LIMIT 1";
    $stmt_user = $conn->prepare($sql_user);
    if ($stmt_user) {
        $stmt_user->bind_param("s", $usuario);
        $stmt_user->execute();
        $stmt_user->store_result();
        $stmt_user->bind_result($area_funcional_db, $nombre_usuario_db);
        if ($stmt_user->fetch()) {
            $area_funcional = $area_funcional_db;
            $nombre_usuario = $nombre_usuario_db;
        }
        $stmt_user->close();
    }
}

// Top bar con datos del usuario

echo "<div class='container py-4'>"
    ."<div class='d-flex justify-content-between align-items-center mb-4'>"
    ."<div class='d-flex align-items-center' style='gap:30px;margin-left:0;'>"
    ."<a href='Proyectos_Cargados.php' class='btn btn-outline-secondary' style='margin-left:0;'>"
    ."<svg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='currentColor' class='bi bi-arrow-left' viewBox='0 0 16 16' style='margin-right:6px;vertical-align:middle;'>"
    ."<path fill-rule='evenodd' d='M15 8a.5.5 0 0 1-.5.5H2.707l3.147 3.146a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L2.707 7.5H14.5A.5.5 0 0 1 15 8z'/></svg>"
    ."Regresar</a>"
    ."</div>"
    ."<div class='d-flex align-items-center' style='gap:24px;'>"
    ."<span style='color:#4C8AA3;font-weight:600;font-size:16px;'>Nombre:</span><span style='font-size:16px;color:#4C8AA3;'>" .htmlspecialchars($nombre_usuario)."</span>"
    ."<span style='color:#888;font-size:16px;'>Área Funcional: " . htmlspecialchars($area_funcional) . "</span>"
    ."</div>"
    ."</div>"
    ."</div>";

 
// ...existing code...
 

require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$mensaje = "";
$tabla_html = "";
$datos_guardar = [];
$permitir_subida = true; // Variable para controlar si se permite subir documentos
 
// -------------------- SUBIDA EXCEL --------------------
if (isset($_POST["subir"])) {
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $targetFile = $targetDir . basename($_FILES["excel"]["name"]);
 
    if (move_uploaded_file($_FILES["excel"]["tmp_name"], $targetFile)) {
    // Procesar Excel: hoja DATA, rango A1:BK600
        try {
            $spreadsheet = IOFactory::load($targetFile);
            $sheet = $spreadsheet->getSheetByName('DATA');
            if ($sheet) {
                $range = 'A1:BK600';
                $data = $sheet->rangeToArray($range, null, true, false);
                if (count($data) > 1) {
                    // Validación de proyecto comentada - permitir subida sin validar proyecto
                    $proyecto_existe = true; // Asumir que el proyecto siempre existe
                    if ($proyecto_existe) {
                    // Buscar integración en la primera fila de datos
                    $integracion = null;
                    // Buscar los índices de las columnas USUARIO y MATRICULA USUARIO
                    $idx_usuario = null;
                    $idx_matricula = null;
                    foreach ($data[0] as $idx => $colname) {
                        if (trim(mb_strtoupper($colname)) === 'USUARIO') $idx_usuario = $idx;
                        if (trim(mb_strtoupper($colname)) === 'MATRICULA USUARIO') $idx_matricula = $idx;
                    }
                    for ($i = 1; $i < count($data); $i++) {
                        // Sobrescribir usuario y matrícula en cada fila
                        if ($idx_usuario !== null) $data[$i][$idx_usuario] = $usuario;
                        if ($idx_matricula !== null) $data[$i][$idx_matricula] = $matricula;
                        // Solo tomar la integración de la primera fila válida
                        if ($integracion === null && isset($data[$i][3])) {
                            $integracion = $data[$i][3];
                        }
                    }
                    if ($integracion !== null) {
                        $sql_check = "SELECT COUNT(*) as total FROM gastos_personal WHERE `INTEGRACION` = ?";
                        $stmt_check = $conn->prepare($sql_check);
                        $stmt_check->bind_param("s", $integracion);
                        $stmt_check->execute();
                        $result_check = $stmt_check->get_result();
                        $row_check = $result_check->fetch_assoc();
                        if ($row_check && $row_check['total'] > 0) {
                            // Modal Bootstrap
                            $permitir_subida = false; // Bloquear subida
                            $mensaje = '<div class="modal fade" id="modalIntegracion" tabindex="-1" aria-labelledby="modalIntegracionLabel" aria-hidden="true">'
                                .'<div class="modal-dialog modal-dialog-centered">'
                                .'<div class="modal-content">'
                                .'<div class="modal-header bg-danger text-white">'
                                .'<h5 class="modal-title" id="modalIntegracionLabel">Ya existe una versión de presupuesto</h5>'
                                .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                                .'</div>'
                                .'<div class="modal-body">'
                                .'Esta versión del presupuesto ya está registrada. Para continuar, elimina la versión anterior o solicita ayuda para actualizarla.'
                                .'<div class="text-muted small mt-2">Si necesitas soporte, contacta al administrador.</div>'
                                .'</div>'
                                .'<div class="modal-footer">'
                                .'<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>'
                                .'</div>'
                                .'</div>'
                                .'</div>'
                                .'</div>';
                            $mensaje .= "<script>window.onload = function(){var myModal = new bootstrap.Modal(document.getElementById('modalIntegracion'));myModal.show();document.getElementById('modalIntegracion').addEventListener('hidden.bs.modal', function(){window.location.href='Index2.php';});}</script>";
                            // No return, dejar que la página siga renderizando
                        }
                    }
                    $mensaje = "<div class='alert alert-success'>Archivo subido correctamente: " . basename($_FILES["excel"]["name"]) . "</div>";
                    $tabla_html_arr = [];
                    $tabla_html_arr[] = "<div style='max-height:400px; overflow:auto; font-size:12px;'>";
                    $tabla_html_arr[] = "<table class='table table-bordered table-sm mt-3 text-center' style='font-size:12px; table-layout:auto; width:100%;'>";
                    $tabla_html_arr[] = "<thead><tr style='background-color:#4C8AA3; color:white;'>";
                    // Índices de columnas a mostrar
                    $cols_mostrar = [0,1,2,4,5,6,7,8,9,10];
                    // NO agregar USUARIO y MATRICULA USUARIO a cols_mostrar para la vista previa
                    // Calcular totales de meses para decidir cuáles mostrar
                    $totales_col = array_fill(0, count($data[0]), 0);
                    for ($i = 1; $i < count($data); $i++) {
                        for ($k = 15; $k <= 62; $k++) {
                            if (isset($data[$i][$k])) {
                                $mes_val = str_replace(",", ".", trim((string)$data[$i][$k]));
                                if (is_numeric($mes_val)) {
                                    $totales_col[$k] += (float)$mes_val;
                                }
                            }
                        }
                    }
                    $meses_mostrar = [];
                    for ($k = 15; $k <= 62; $k++) {
                        if (isset($totales_col[$k]) && $totales_col[$k] != 0) {
                            $meses_mostrar[] = $k;
                        }
                    }
                    // Si todos los meses son 0, mostrar al menos el primer mes
                    if (empty($meses_mostrar)) {
                        $meses_mostrar[] = 15;
                    }
                    $cols_mostrar = array_merge($cols_mostrar, $meses_mostrar);
                    foreach ($cols_mostrar as $idx) {
                        $colname = isset($data[0][$idx]) ? $data[0][$idx] : '';
                        if (trim(mb_strtoupper($colname)) === 'USUARIO' || trim(mb_strtoupper($colname)) === 'MATRICULA USUARIO') continue;
                        $tabla_html_arr[] = "<th style='background-color:#4C8AA3; color:white; font-weight:bold; white-space:nowrap; overflow:auto; word-break:break-all;'>" . htmlspecialchars($colname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</th>";
                    }
                    $tabla_html_arr[] = "</tr></thead><tbody>";
                    $datos_guardar = [];
                    $tabla_html_arr[] = "<tbody>";
                    $total_horas = 0;
                    $total_calculado = 0;
                    $total_plata = 0;
                    // $totales_col ya calculado arriba
                    for ($i = 1; $i < count($data); $i++) {
                        $cols = $data[$i];
                        $col_h = isset($cols[7]) ? trim((string)$cols[7]) : '';
                        $col_h_num = str_replace(",", ".", $col_h);
                        if ($col_h === '' || $col_h === '0' || $col_h === '0.0' || $col_h === '0,0' || (is_numeric($col_h_num) && (float)$col_h_num == 0.0)) {
                            continue;
                        }
                        foreach ([2, 4, 5] as $fecha_idx) {
                            if (isset($cols[$fecha_idx]) && $cols[$fecha_idx] !== null && $cols[$fecha_idx] !== '') {
                                $valor = $cols[$fecha_idx];
                                if (is_numeric($valor)) {
                                    $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($valor);
                                    $cols[$fecha_idx] = date('Y-m-d', $timestamp);
                                } else {
                                    $fecha = str_replace("/", "-", trim($valor));
                                    $cols[$fecha_idx] = date('Y-m-d', strtotime($fecha));
                                }
                            }
                        }
                        // Sumar horas y calcular plata por fila
                        $suma_meses = 0;
                        foreach ($meses_mostrar as $k) {
                            if (isset($cols[$k])) {
                                $mes_val = str_replace(",", ".", trim((string)$cols[$k]));
                                if (is_numeric($mes_val)) {
                                    $total_horas += (float)$mes_val;
                                    $suma_meses += (float)$mes_val;
                                }
                            }
                        }
                        $tarifa = isset($cols[8]) ? str_replace(",", ".", trim((string)$cols[8])) : 0;
                        $plata_fila = $suma_meses * (is_numeric($tarifa) ? (float)$tarifa : 0);
                        $total_plata += $plata_fila;
                        $row_html = ["<tr>"];
                        foreach ($cols_mostrar as $j) {
                            $colname = isset($data[0][$j]) ? $data[0][$j] : '';
                            if (trim(mb_strtoupper($colname)) === 'USUARIO' || trim(mb_strtoupper($colname)) === 'MATRICULA USUARIO') continue;
                            $c = isset($cols[$j]) ? $cols[$j] : '';
                            $c_trim = trim((string)$c);
                            $td_style = "white-space:nowrap; overflow:auto; word-break:break-all;";
                            // Si es la columna PROYECTO, mostrar siempre como texto (con ceros a la izquierda)
                            if (trim(mb_strtoupper($colname)) === 'PROYECTO') {
                                $row_html[] = "<td style='$td_style'>" . htmlspecialchars($c, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                                continue;
                            }
                            // Pintar celdas de meses seleccionados en verde si >0
                            if (in_array($j, $meses_mostrar)) {
                                $c_num = str_replace(",", ".", $c_trim);
                                if (is_numeric($c_num) && (float)$c_num != 0) {
                                    $row_html[] = "<td style='color:#17823d; font-weight:bold; background-color:#d6e3dd; $td_style'>" . (int)$c_num . "</td>";
                                } else {
                                    $row_html[] = "<td style='$td_style'>" . (is_numeric($c_num) ? (int)$c_num : htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</td>";
                                }
                            }
                            // Tarifa COAN (columna 8) en formato moneda entero
                            else if ($j == 8) {
                                $c_num = str_replace(",", ".", $c_trim);
                                $row_html[] = "<td style='$td_style'>" . (is_numeric($c_num) ? '$ ' . number_format((int)$c_num, 0, '', '.') : htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</td>";
                            }
                            else {
                                $row_html[] = "<td style='$td_style'>" . htmlspecialchars($c_trim, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
                            }
                        }
                        $row_html[] = "</tr>";
                        $tabla_html_arr[] = implode('', $row_html);
                        // Suponiendo que la columna TOTAL_CALCULADO es la última (índice 62)
                        if (isset($cols[62]) && is_numeric(str_replace(",", ".", $cols[62]))) {
                            $total_calculado += (float)str_replace(",", ".", $cols[62]);
                        }
                        // Forzar PROYECTO a string para conservar ceros a la izquierda
                        if (isset($idx_usuario) && isset($cols[0])) {
                            $cols[0] = (string)$cols[0];
                        }
                        $datos_guardar[] = $cols;
                    }
                    // Fila de totales por columna de mes (solo para los meses)
                    $row_total = ["<tr style='background-color:#e9ecef; font-weight:bold;'>"];
                    foreach ($cols_mostrar as $j) {
                        if (in_array($j, $meses_mostrar)) {
                            $row_total[] = "<td style='white-space:nowrap; overflow:auto; word-break:break-all;'>" . (int)$totales_col[$j] . "</td>";
                        } else {
                            $row_total[] = "<td style='white-space:nowrap; overflow:auto; word-break:break-all;'></td>";
                        }
                    }
                    $row_total[] = "</tr>";
                    $tabla_html_arr[] = implode('', $row_total);
                    $tabla_html_arr[] = "</tbody></table></div>";
                    $tabla_html_arr[] = "<div class='alert alert-info mt-3' style='font-size:13px;'><strong>🔎 Total Horas (meses):</strong> " . (int)$total_horas . "</div>";
                    $tabla_html_arr[] = "<div class='alert alert-success mt-2' style='font-size:13px;'><strong>💰 Total Plata:</strong> $ " . number_format((int)$total_plata, 0, '', '.') . "</div>";
                    $tabla_html = implode('', $tabla_html_arr);
                    $_SESSION["datos_guardar"] = $datos_guardar;
                    }
                } else {
                    $mensaje .= "<div class='alert alert-warning'>No se encontraron datos en el rango A1:BK600 de la hoja DATA.</div>";
                }
            } else {
                $mensaje .= "<div class='alert alert-warning'>No se encontró la hoja llamada DATA en el archivo.</div>";
            }
        } catch (Exception $e) {
            $mensaje .= "<div class='alert alert-danger'>Error al procesar el Excel: " . $e->getMessage() . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger'>Error al subir el archivo.</div>";
    }
}
 
// -------------------- PROCESAR DATOS MANUALES --------------------
if (isset($_POST["procesar"])) {
    $contenido = trim($_POST["datos"]);
    $lineas = explode("\n", $contenido);
 
    if (count($lineas) > 1) {
        // Encabezados
        $tabla_html = "<div style='max-height:400px; overflow:auto; font-size:12px;'>";
        $tabla_html .= "<table class='table table-bordered table-sm mt-3 text-center' style='font-size:12px;'>";
        $tabla_html .= "<thead><tr style='background-color:#4C8AA3; color:white;'>";
 
        $campos = explode("\t", $lineas[0]);
        foreach ($campos as $c) {
            $tabla_html .= "<th style='background-color:#4C8AA3; color:white; font-weight:bold;'>" . htmlspecialchars($c) . "</th>";
        }
        $tabla_html .= "</tr></thead><tbody>";
 
        $total_horas = 0; // acumulador tipo Power BI
 
        // Filas
        for ($i = 1; $i < count($lineas); $i++) {
            $cols = explode("\t", $lineas[$i]);
            $tabla_html .= "<tr>";
            foreach ($cols as $j => $c) {
                $c_trim = trim($c);
 
                if ($j >= 15) {
                    // convertir coma en punto para PHP
                    $c_num = str_replace(",", ".", $c_trim);
                    if (is_numeric($c_num)) {
                        if ((float)$c_num != 0) {
                            $tabla_html .= "<td style='color:#17823d; font-weight:bold; background-color:#d6e3dd'>" . htmlspecialchars($c_trim) . "</td>";
                        } else {
                            $tabla_html .= "<td>" . htmlspecialchars($c_trim) . "</td>";
                        }
                        $total_horas += (float)$c_num;
                    } else {
                        $tabla_html .= "<td>" . htmlspecialchars($c_trim) . "</td>";
                    }
                } else {
                    $tabla_html .= "<td>" . htmlspecialchars($c_trim) . "</td>";
                }
            }
            $tabla_html .= "</tr>";
            $datos_guardar[] = $cols;
        }
        $tabla_html .= "</tbody></table></div>";
 
        // Etiqueta tipo Power BI
        $tabla_html .= "<div class='alert alert-info mt-3' style='font-size:13px;'>
            <strong>🔎 Total Horas (meses):</strong> " . number_format($total_horas, 2) . "
        </div>";
 
        $_SESSION["datos_guardar"] = $datos_guardar;
    }
}
 
 
 
// -------------------- GUARDAR EN BD --------------------
if (isset($_POST["guardar"])) {
    if (isset($_SESSION["datos_guardar"])) {
    $datos_guardar = $_SESSION["datos_guardar"];
    // Obtener usuario y matrícula de la sesión
    $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
    $matricula = isset($_SESSION['matricula']) ? strval($_SESSION['matricula']) : '';
    
        // VALIDACIÓN IMPORTANTE: Validar si el Proyecto existe en la tabla proyectos.centro_costos
        $proyecto = null;
        foreach ($datos_guardar as $row) {
            if (isset($row[0])) { // PROYECTO es índice 0
                $proyecto = trim($row[0]);
                break;
            }
        }
        
        $proyecto_existe = false;
        if ($proyecto !== null && $proyecto !== '') {
            $sql_proyecto = "SELECT COUNT(*) as total FROM proyectos WHERE `centro_costos` = ?";
            $stmt_proyecto = $conn->prepare($sql_proyecto);
            if ($stmt_proyecto) {
                $stmt_proyecto->bind_param("s", $proyecto);
                $stmt_proyecto->execute();
                $result_proyecto = $stmt_proyecto->get_result();
                $row_proyecto = $result_proyecto->fetch_assoc();
                $proyecto_existe = ($row_proyecto && $row_proyecto['total'] > 0);
                $stmt_proyecto->close();
            }
        }
        
        if (!$proyecto_existe) {
            // Mostrar mensaje modal si el proyecto no existe
            $mensaje = '<div class="modal fade" id="modalProyecto" tabindex="-1" aria-labelledby="modalProyectoLabel" aria-hidden="true">'
                .'<div class="modal-dialog modal-dialog-centered">'
                .'<div class="modal-content">'
                .'<div class="modal-header bg-danger text-white">'
                .'<h5 class="modal-title" id="modalProyectoLabel">No encontramos el proyecto</h5>'
                .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                .'</div>'
                .'<div class="modal-body">'
                .'No pudimos validar el proyecto <b>' . htmlspecialchars($proyecto) . '</b> en la base de datos. Para continuar, verifica el código o solicita su creación.'
                .'<div class="text-muted small mt-2">Si necesitas soporte, contacta al administrador.</div>'
                .'</div>'
                .'<div class="modal-footer">'
                .'<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>'
                .'</div>'
                .'</div>'
                .'</div>'
                .'</div>';
            $mensaje .= "<script>window.onload = function(){var myModal = new bootstrap.Modal(document.getElementById('modalProyecto'));myModal.show();document.getElementById('modalProyecto').addEventListener('hidden.bs.modal', function(){window.location.href='Index2.php';});}</script>";
        } else {
            // Validar si ya existe la integración
            $integracion = null;
        foreach ($datos_guardar as $row) {
            if (isset($row[3])) { // INTEGRACION es índice 3
                $integracion = $row[3];
                break;
            }
        }
        if ($integracion !== null) {
            $sql_check = "SELECT COUNT(*) as total FROM gastos_personal WHERE `INTEGRACION` = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("s", $integracion);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            $row_check = $result_check->fetch_assoc();
            if ($row_check && $row_check['total'] > 0) {
                // Mostrar mensaje modal si ya existe
                $mensaje = '<div class="modal fade" id="modalIntegracion" tabindex="-1" aria-labelledby="modalIntegracionLabel" aria-hidden="true">'
                    .'<div class="modal-dialog modal-dialog-centered">'
                    .'<div class="modal-content">'
                    .'<div class="modal-header bg-warning text-dark">'
                    .'<h5 class="modal-title" id="modalIntegracionLabel">Versión ya registrada</h5>'
                    .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                    .'</div>'
                    .'<div class="modal-body">'
                    .'<div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert">'
                    .'<div><strong>Ya existe una versión de presupuesto.</strong><br>'
                    .'Para continuar, elimina la versión anterior o solicita ayuda para actualizarla.</div>'
                    .'</div>'
                    .'<div class="text-muted small mt-2">Si necesitas soporte, contacta al administrador.</div>'
                    .'</div>'
                    .'<div class="modal-footer">'
                    .'<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>'
                    .'<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>'
                    .'</div>'
                    .'</div>'
                    .'</div>'
                    .'</div>';
                $mensaje .= "<script>window.onload = function(){var myModal = new bootstrap.Modal(document.getElementById('modalIntegracion'));myModal.show();document.getElementById('modalIntegracion').addEventListener('hidden.bs.modal', function(){window.location.href='Index2.php';});}</script>";
            } else {
                // Guardar normalmente
                foreach ($datos_guardar as $row) {
                    $sql = "INSERT INTO gastos_personal (
                        `PROYECTO`, `VERSION`, `FECHA VERSION`, `INTEGRACION`, `FECHA INICIO PROYECTO`, `FECHA FIN PROYECTO`,
                        `CATEGORIA`, `NOMBRE CATEGORIA`, `TARIFA COAN 2`, `ÁREA`, `ÁREA FUNCIONAL`,
                        `COORDINADOR ÁREA`, `COORDINADOR ÁREA NUM`, `USUARIO`, `MATRICULA USUARIO`,
                        `ene25`, `feb25`, `mar25`, `abr25`, `may25`, `jun25`, `jul25`, `ago25`, `sep25`, `oct25`, `nov25`, `dic25`,
                        `ene26`, `feb26`, `mar26`, `abr26`, `may26`, `jun26`, `jul26`, `ago26`, `sep26`, `oct26`, `nov26`, `dic26`,
                        `ene27`, `feb27`, `mar27`, `abr27`, `may27`, `jun27`, `jul27`, `ago27`, `sep27`, `oct27`, `nov27`, `dic27`,
                        `ene28`, `feb28`, `mar28`, `abr28`, `may28`, `jun28`, `jul28`, `ago28`, `sep28`, `oct28`, `nov28`, `dic28`
                    ) VALUES (" . str_repeat("?,", 62) . "?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        // Buscar los índices de las columnas USUARIO y MATRICULA USUARIO
                        $idx_usuario = null;
                        $idx_matricula = null;
                        if (isset($row) && is_array($row)) {
                            foreach ($row as $idx => $colname) {
                                if (is_string($colname) && trim(mb_strtoupper($colname)) === 'USUARIO') $idx_usuario = $idx;
                                if (is_string($colname) && trim(mb_strtoupper($colname)) === 'MATRICULA USUARIO') $idx_matricula = $idx;
                            }
                        }
                        // Sobrescribir usuario y matrícula en cada fila antes de guardar
                        if ($idx_usuario !== null) $row[$idx_usuario] = $usuario;
                        if ($idx_matricula !== null) $row[$idx_matricula] = $matricula;
                        $row = array_pad($row, 63, null);
                        $types = str_repeat("s", 63);
                        $stmt->bind_param($types, ...$row);
                        // Normalizar fechas (asumiendo que FECHA VERSION = índice 2, FECHA INICIO PROYECTO = 4, FECHA FIN PROYECTO = 5)
                        foreach ([2, 4, 5] as $idx) {
                            if (!empty($row[$idx])) {
                                $fecha = str_replace("/", "-", trim($row[$idx]));
                                $row[$idx] = date("Y-m-d", strtotime($fecha));
                            } else {
                                $row[$idx] = null;
                            }
                        }
                        if (!$stmt->execute()) {
                            echo "<div class='alert alert-danger'>Error en fila: " . $stmt->error . "</div>";
                        }
                    } else {
                        echo "<div class='alert alert-danger'>Error prepare: " . $conn->error . "</div>";
                    }
                }
                // Modal Bootstrap éxito
                $mensaje = '<div class="modal fade" id="modalGuardado" tabindex="-1" aria-labelledby="modalGuardadoLabel" aria-hidden="true">'
                    .'<div class="modal-dialog">'
                    .'<div class="modal-content">'
                    .'<div class="modal-header bg-success text-white">'
                    .'<h5 class="modal-title" id="modalGuardadoLabel">Datos guardados</h5>'
                    .'<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                    .'</div>'
                    .'<div class="modal-body">'
                    .'✅ Los datos fueron guardados correctamente en la base de datos.'
                    .'</div>'
                    .'<div class="modal-footer">'
                    .'<button type="button" class="btn btn-success" data-bs-dismiss="modal">Cerrar</button>'
                    .'</div>'
                    .'</div>'
                    .'</div>'
                    .'</div>';
                $mensaje .= "<script>window.onload = function(){var myModal = new bootstrap.Modal(document.getElementById('modalGuardado'));myModal.show();}</script>";
                unset($_SESSION["datos_guardar"]);
            }
        } else {
            $mensaje = "<div class='alert alert-warning'>⚠️ No hay integración para validar. Primero pega y procesa.</div>";
        }
        }
    } else {
        $mensaje = "<div class='alert alert-warning'>⚠️ No hay datos para guardar. Primero pega y procesa.</div>";
    }
}
 
?>
 
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Subir Presupuesto (BAC)</title>
   
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background: #f4f6fa !important;
        }
        .card, .custom-table {
            box-shadow: 0 2px 12px 0 rgba(60,72,100,.08), 0 1.5px 4px 0 rgba(60,72,100,.04);
            border-radius: 18px !important;
        }
        .btn-primary, .btn-success, .btn-danger, .btn-outline-primary, .btn-guardar-bd {
            box-shadow: 0 2px 8px 0 rgba(60,72,100,.10);
            border-radius: 10px !important;
            font-weight: 600;
        }
        .btn-modulo-director {
            background: linear-gradient(90deg, #4C8AA3 0%, #17823D 100%);
            color: #fff !important;
            border: none;
            box-shadow: 0 2px 8px 0 rgba(60,72,100,.12);
            border-radius: 10px !important;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: background 0.2s;
        }
        .btn-modulo-director:hover {
            background: linear-gradient(90deg, #17823D 0%, #4C8AA3 100%);
            color: #fff;
        }
        .form-label {
            color: #4C8AA3;
            font-weight: 600;
        }
        .custom-table thead th {
            box-shadow: 0 2px 8px 0 rgba(60,72,100,.04);
        }
        
        /* Estilos profesionales y amigables para alertas */
        .alert {
            border: none;
            border-radius: 14px;
            padding: 16px 18px;
            font-weight: 500;
            font-size: 15px;
            line-height: 1.5;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
            animation: slideInDown 0.35s ease-out;
            transition: all 0.3s ease;
            position: relative;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #e8f5e9 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #ffebee 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #fffde7 100%);
            color: #856404;
            border-left: 5px solid #ffc107;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #e1f5fe 100%);
            color: #0c5460;
            border-left: 5px solid #17a2b8;
        }
        
        .alert strong {
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        
        .alert::before {
            content: "";
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .alert-success::before {
            content: "✓";
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .alert-danger::before {
            content: "!";
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        .alert-warning::before {
            content: "!";
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .alert-info::before {
            content: "ℹ";
            background-color: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }
    </style>
    
</head>
<body class="container py-5" style="position:relative;">
    <div style="position:fixed; top:0; left:0; height:100vh; width:18px; background:#b3cdd1; z-index:1000;"></div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold" style="color:#4C8AA3; letter-spacing:0.5px; display:flex; align-items:center; gap:8px;">
            <i class="bi bi-filetype-xls" style="font-size:2rem; color:#495057; background:#e9ecef; border-radius:6px; padding:2px 4px 2px 4px;"></i>
            SUBIR DOCUMENTO EXCEL DE PRESUPUESTO (BAC)
        </h4>
        <div class="d-flex gap-2">
            <!-- <a href="ModuloDirector.php" class="btn btn-modulo-director">Módulo Director</a> -->
        </div>
    </div>
    <?php if (!empty($mensaje) && strpos($mensaje, 'modal fade') !== false) { echo $mensaje; } ?>

    <!-- Subir Excel -->

    <!-- Barra de progreso con Bootstrap -->
    <div id="progressBarContainer" style="display:none; margin-bottom:20px;">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span style="color:#4C8AA3; font-weight:600; font-size:14px;">Cargando archivo...</span>
            <span id="progressPercent" style="color:#4C8AA3; font-weight:700; font-size:16px;">0%</span>
        </div>
        <div class="progress" style="height:30px; border-radius:6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" 
                 style="width:0%; background:linear-gradient(90deg, #4C8AA3 0%, #17823D 100%); font-weight:600; color:white; font-size:13px;"
                 aria-valuenow="0" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                <span id="progressBarText" style="position:absolute; width:100%; text-align:center; line-height:30px;">0%</span>
            </div>
        </div>
    </div>

    <form id="formExcel" action="" method="post" enctype="multipart/form-data" class="mb-4">
        <div class="card shadow-sm border-0 p-4" style="background:linear-gradient(120deg,#f8fafc 60%,#e9ecef 100%); border-radius:22px;">
            <div class="mb-4 text-center">
                <i class="bi bi-filetype-xls" style="font-size:2.5rem; color:#4C8AA3; background:#e9ecef; border-radius:8px; padding:6px 10px 6px 10px;"></i>
                <h5 class="fw-bold mt-2 mb-0" style="color:#4C8AA3; letter-spacing:0.5px;">Gestión de Presupuesto Excel</h5>
                <div style="color:#888; font-size:14px;">Sube o descarga la plantilla de presupuesto BAC</div>
            </div>
            <div class="mb-4">
                <label for="excelInput" class="form-label">Selecciona archivo Excel</label>
                <input type="file" name="excel" id="excelInput" class="form-control" required <?php echo !$permitir_subida ? 'disabled' : ''; ?> >
            </div>
            <div class="d-flex flex-column flex-md-row gap-2 justify-content-center">
                <button type="submit" name="subir" class="btn btn-primary d-flex align-items-center gap-2 px-4 py-2" style="background:#495057; border:none; font-size:1.1rem;" <?php echo !$permitir_subida ? 'disabled' : ''; ?>>
                    <i class="bi bi-cloud-arrow-up-fill" style="font-size:1.3rem;"></i>
                    Subir documento
                </button>
                <a href="?descargar=1" class="btn btn-outline-success d-flex align-items-center gap-2 px-4 py-2" style="border-color:#4C8AA3; color:#4C8AA3; font-size:1.1rem;">
                    <i class="bi bi-file-earmark-arrow-down-fill" style="font-size:1.3rem;"></i>
                    Descargar plantilla de Presupuesto
                </a>
            </div>
        </div>
    </form>

    <script>
    // Mostrar barra de progreso al enviar el formulario con progreso realista
    document.getElementById('formExcel').addEventListener('submit', function(e) {
        var progressBarContainer = document.getElementById('progressBarContainer');
        var progressBar = document.getElementById('progressBar');
        var progressPercent = document.getElementById('progressPercent');
        var progressBarText = document.getElementById('progressBarText');
        
        progressBarContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressPercent.textContent = '0%';
        progressBarText.textContent = '0%';
        progressBar.setAttribute('aria-valuenow', '0');

        // Progreso realista simulado
        var width = 0;
        var startTime = Date.now();
        var duration = Math.random() * 3000 + 4000; // 4-7 segundos de carga esperada
        
        var interval = setInterval(function() {
            var elapsed = Date.now() - startTime;
            var progress = (elapsed / duration) * 100;
            
            // Usar función easing para progreso más natural
            if (progress < 30) {
                // Primer 30% rápido
                width = progress * 1.2;
            } else if (progress < 70) {
                // Medio más lento
                width = 30 + (progress - 30) * 0.8;
            } else {
                // Último 30% muy lento
                width = 70 + (progress - 70) * 0.5;
            }
            
            if (width > 95) {
                width = 95; // Detenerse en 95% hasta que se complete
                clearInterval(interval);
            }
            
            var roundedWidth = Math.round(width);
            progressBar.style.width = roundedWidth + '%';
            progressPercent.textContent = roundedWidth + '%';
            progressBarText.textContent = roundedWidth + '%';
            progressBar.setAttribute('aria-valuenow', roundedWidth);
        }, 100);
        
        // Cuando se complete la carga (página se recarga), mostrar 100%
        var completeProgress = function() {
            progressBar.style.width = '100%';
            progressPercent.textContent = '100%';
            progressBarText.textContent = '100%';
            progressBar.setAttribute('aria-valuenow', '100');
            progressBar.classList.remove('progress-bar-animated');
        };
        
        window.addEventListener('beforeunload', completeProgress);
    });
    </script>

    <!-- Tarjetas resumen tipo dashboard -->
    <?php
    // Extraer totales para las tarjetas si existen
    $totalPlata = 0;
    $totalHoras = 0;
    $totalFilas = 0;
    if (preg_match('/<strong>💰 Total Plata:<\/strong> \$ ([\d\.]+)/', $tabla_html, $m)) {
        $totalPlata = $m[1];
    }
    if (preg_match('/<strong>🔎 Total Horas \(meses\):<\/strong> ([\d\.]+)/', $tabla_html, $m)) {
        $totalHoras = $m[1];
    }
    if (isset($datos_guardar) && is_array($datos_guardar)) {
        $totalFilas = count($datos_guardar);
    }
    ?>
    <?php if (!empty($tabla_html)) : ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body text-center">
                    <div class="fs-5 text-muted mb-1">Total Plata</div>
                    <div class="fs-2 fw-bold text-success">$<?= number_format((int)$totalPlata, 0, '', '.') ?></div>
                    <div class="small text-muted">Suma de todas las filas</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body text-center">
                    <div class="fs-5 text-muted mb-1">Total Horas</div>
                    <div class="fs-2 fw-bold text-primary"><?= (int)$totalHoras ?></div>
                    <div class="small text-muted">Suma de horas de todos los meses</div>
                </div>
            </div>
        </div>
        <!-- Tarjeta de Total Filas eliminada -->
    </div>
    <?php endif; ?>

    <!-- Vista previa -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body">
            <div class="table-responsive">
            <style>
                .custom-table thead th {
                    background: #f8fafd;
                    color: #495057;
                    font-weight: 600;
                    border-bottom: 2px solid #e9ecef;
                    border-right: 1px solid #e0e0e0;
                }
                .custom-table tbody td {
                    border-right: 1px solid #e0e0e0;
                }
                .custom-table tbody tr:nth-child(even) {
                    background: #f4f7fb;
                }
                .custom-table tbody tr:nth-child(odd) {
                    background: #fff;
                }
                .custom-badge-green {background:#e6f4ea;color:#17823d;font-weight:600;border-radius:12px;padding:2px 10px;display:inline-block;}
                .custom-badge-yellow {background:#fff7e0;color:#b88a00;font-weight:600;border-radius:12px;padding:2px 10px;display:inline-block;}
                .custom-badge-red {background:#ffeaea;color:#d32f2f;font-weight:600;border-radius:12px;padding:2px 10px;display:inline-block;}
                .btn-guardar-bd {
                    background: #17823D;
                    color: #fff;
                    font-weight: 600;
                    border: none;
                    border-radius: 6px;
                    padding: 10px 24px;
                    transition: background 0.2s;
                }
                .btn-guardar-bd:hover { background: #126c2c; }
            </style>
            <?php
            // Renderizar la tabla tal como se genera originalmente, sin clases ni badges personalizados
            if (!empty($tabla_html)) {
                echo $tabla_html;
            }
            ?>
            </div>
            <!-- Botón guardar en la BD solo si hay datos -->
            <?php if (!empty($tabla_html)) : ?>
                <form method="post" class="mt-3 text-end">
                    <button type="submit" name="guardar" class="btn btn-success">Guardar en la BD</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <!-- Modal Integración AJAX -->
    <div class="modal fade" id="modalIntegracionAjax" tabindex="-1" aria-labelledby="modalIntegracionAjaxLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="modalIntegracionAjaxLabel">Versión ya registrada</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalIntegracionAjaxBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
            (function(){
                var integracionValida = false;
                var proyectoValido = false;
                var fileInput = document.getElementById('excelInput');
                var formExcel = document.getElementById('formExcel');
                fileInput.addEventListener('change', function(e) {
                    integracionValida = false;
                    proyectoValido = false;
                    if (!fileInput.files.length) return;
                    var file = fileInput.files[0];
                    var reader = new FileReader();
                    reader.onload = function(e2) {
                        var data = new Uint8Array(e2.target.result);
                        var workbook = XLSX.read(data, {type: 'array'});
                        var sheet = workbook.Sheets['DATA'];
                        if (!sheet) { integracionValida = true; proyectoValido = true; return; }
                        var json = XLSX.utils.sheet_to_json(sheet, {header:1, range:0});
                        if (json.length < 2) { integracionValida = true; proyectoValido = true; return; }
                        
                        // VALIDACIÓN DEL PROYECTO
                        var proyecto = (json[1][0] || '').toString().trim();
                        if (!proyecto) { 
                            proyectoValido = true; 
                            integracionValida = true; 
                            return; 
                        }
                        
                        // AJAX para validar proyecto
                        var xhrProyecto = new XMLHttpRequest();
                        xhrProyecto.open('POST', 'validar_proyecto.php', false); // síncrono
                        xhrProyecto.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhrProyecto.onload = function() {
                            try {
                                var respProyecto = JSON.parse(xhrProyecto.responseText);
                                if (!respProyecto.exists) {
                                    proyectoValido = false;
                                    var modalBody = document.createElement('div');
                                    modalBody.innerHTML = '<div class="modal fade" id="modalProyectoAjax" tabindex="-1" aria-labelledby="modalProyectoAjaxLabel" aria-hidden="true">' +
                                        '<div class="modal-dialog modal-dialog-centered">' +
                                        '<div class="modal-content">' +
                                        '<div class="modal-header bg-danger text-white">' +
                                        '<h5 class="modal-title" id="modalProyectoAjaxLabel">Proyecto no disponible</h5>' +
                                        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
                                        '</div>' +
                                        '<div class="modal-body">' +
                                        '<div class="alert alert-danger d-flex align-items-start gap-2 mb-0" role="alert">' +
                                        '<div><strong>No pudimos validar el proyecto.</strong><br>Proyecto: <b>' + proyecto + '</b>. Verifica el código o solicita su creación.</div>' +
                                        '</div>' +
                                        '<div class="text-muted small mt-2">Si necesitas soporte, contacta al administrador.</div>' +
                                        '</div>' +
                                        '<div class="modal-footer">' +
                                        '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>' +
                                        '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>' +
                                        '</div>' +
                                        '</div>' +
                                        '</div>' +
                                        '</div>';
                                    
                                    // Remover el modal anterior si existe
                                    var oldModal = document.getElementById('modalProyectoAjax');
                                    if (oldModal) oldModal.remove();
                                    document.body.appendChild(modalBody.firstElementChild);
                                    
                                    var myModal = new bootstrap.Modal(document.getElementById('modalProyectoAjax'));
                                    myModal.show();
                                    fileInput.value = '';
                                    return;
                                } else {
                                    proyectoValido = true;
                                }
                            } catch (err) { proyectoValido = true; }
                        };
                        xhrProyecto.send('proyecto=' + encodeURIComponent(proyecto));
                        
                        // VALIDACIÓN DE INTEGRACIÓN
                        if (!proyectoValido) return; // Si el proyecto no es válido, no continuar
                        
                        var integracion = (json[1][3] || '').toString().trim();
                        if (!integracion) { integracionValida = true; return; }
                        // AJAX para validar integración
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', 'validar_integracion.php', false); // síncrono
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.exists) {
                                    integracionValida = false;
                                    var modalBody = document.getElementById('modalIntegracionAjaxBody');
                                    modalBody.innerHTML = '<div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert">' +
                                        '<div><strong>Este proyecto ya cuenta con la versión de presupuesto registrada. Verifica la información antes de continuar.</strong><br></div>' +
                                        '</div>' +
                                        '<div class="text-muted small mt-2">Si necesitas soporte, contacta al administrador.</div>';
                                    
                                    var myModal = new bootstrap.Modal(document.getElementById('modalIntegracionAjax'));
                                    myModal.show();
                                    fileInput.value = '';
                                } else {
                                    integracionValida = true;
                                }
                            } catch (err) { integracionValida = true; }
                        };
                        xhr.send('integracion=' + encodeURIComponent(integracion));
                    };
                    reader.readAsArrayBuffer(file);
                });
                formExcel.addEventListener('submit', function(e) {
                    if (!integracionValida || !proyectoValido) {
                        e.preventDefault();
                    }
                });
            })();
    </script>
</body>
</html>

