<?php

// Incluir el menú principal
include 'menu.php';

// Incluir sesión y conexión centralizada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/include.php';
require_once __DIR__ . '/config.php';

// $conn ya está definido en config.php
if (!isset($conn) || !$conn) {
    die("Connection failed: No se pudo establecer la conexión a la base de datos.");
}
// Forzar charset utf8mb4 para evitar problemas de codificación en producción
$conn->set_charset('utf8mb4');

// Inicializar variables de usuario
$nombre_usuario = '';
$area_funcional_usuario = '';
$rol_usuario = '';

// === Áreas permitidas para usuarios especiales ===
// Para agregar otro usuario con áreas restringidas, solo añade una entrada al array:
// 'IDENTIFICADOR' => ['Área 1', 'Área 2', ...],
$usuarios_areas_especiales = [
  'JGELVEZ' => ['Arquitectura y Urbanismo', 'Estructuras'],
  // Ejemplo: 'OTROUSER' => ['Área X', 'Área Y'],
];

// Obtener identificador del usuario desde la sesión (usuario o matricula)
$identificador = null;
if (!empty($_SESSION['usuario'])) {
  $identificador = $_SESSION['usuario'];
  $id_field = 'Usuario';
} elseif (!empty($_SESSION['matricula'])) {
  $identificador = $_SESSION['matricula'];
  $id_field = 'Matricula';
}

if ($identificador !== null) {
  // Preparar y ejecutar consulta segura
  $sqlUser = "SELECT Nombre_Usuario, `Área_Funcional`, ROL FROM login_usuarios WHERE `" . $id_field . "` = ? LIMIT 1";
  if ($stmt = $conn->prepare($sqlUser)) {
    $stmt->bind_param('s', $identificador);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
      $rowUser = $res->fetch_assoc();
      $nombre_usuario = isset($rowUser['Nombre_Usuario']) ? $rowUser['Nombre_Usuario'] : '';
      // Manejar nombre de columna con o sin acento
      if (isset($rowUser['Área_Funcional'])) {
        $area_funcional_usuario = $rowUser['Área_Funcional'];
      } elseif (isset($rowUser['Area_Funcional'])) {
        $area_funcional_usuario = $rowUser['Area_Funcional'];
      }
      $rol_usuario = isset($rowUser['ROL']) ? $rowUser['ROL'] : '';
    }
    $stmt->close();
  }
}

// Obtener el área funcional del filtro si existe
$area_funcional = isset($_GET['area_funcional']) ? $_GET['area_funcional'] : '';

// Si el usuario es COORD, forzar el área funcional a la del usuario

$is_coordinator = false;
$is_mix2 = false;
// Nueva condición para MIX
$is_mix = false;
if (!empty($rol_usuario)) {
  if (stripos($rol_usuario, 'COORD') !== false) {
    $is_coordinator = true;
    if (!empty($area_funcional_usuario)) {
      $area_funcional = $area_funcional_usuario;
    }
  } elseif (strtoupper(trim($rol_usuario)) === 'MIX2') {
    $is_mix2 = true;
    if (!empty($area_funcional_usuario)) {
      $area_funcional = $area_funcional_usuario;
    }
  // Si el rol es MIX, activar bandera
  } elseif (strtoupper(trim($rol_usuario)) === 'MIX') {
    $is_mix = true;
  }
}


// Consultar datos para la gráfica, usando tiempo_imputado_costo como Costo Actual (AC)
$mes_inicio = '2026-01'; // Ocultamos 2025, solo mostramos 2026 en adelante

// 1. Capacidad Instalada y Gastos Personal (de la vista original)
$sql = "SELECT 
      mes,
      SUM(valor_capacidad_instalada) as valor_capacidad_instalada,
      SUM(valor_gastos_personal) as valor_gastos_personal
    FROM $vista_grafica_capacidad_instalada";
$condiciones = array();
if (!empty($area_funcional)) {
  $condiciones[] = "area_funcional = '" . $conn->real_escape_string($area_funcional) . "'";
} else {
  // Solo mostrar áreas permitidas según el usuario
  if ($is_mix && array_key_exists(strtoupper($identificador), $usuarios_areas_especiales)) {
    $areas_permitidas = $usuarios_areas_especiales[strtoupper($identificador)];
  } elseif ($is_mix) {
    $areas_permitidas = ["BIM", "VÍAS", "Vías y Topografía"];
  } else {
    $areas_permitidas = [
      'Vías y Topografía',
      'Geotecnia y Pavimentos',
      'Eléctrica',
      'Hidráulica y Medio Ambiente',
      'Arquitectura y Urbanismo',
      'Mecánica',
      'Estructuras',
      'Dirección de Proyectos',
      'Tecnología',
      'Área_Prueba'
    ];
  }
  $areas_sql = array_map(function($a) use ($conn) { return "'" . $conn->real_escape_string($a) . "'"; }, $areas_permitidas);
  $condiciones[] = "area_funcional IN (" . implode(",", $areas_sql) . ")";
}
$condiciones[] = "CONVERT(mes USING utf8mb4) COLLATE utf8mb4_unicode_ci >= '$mes_inicio'";
if (count($condiciones) > 0) {
    $sql .= " WHERE " . implode(' AND ', $condiciones);
}
$sql .= " GROUP BY mes ORDER BY mes";
$result = $conn->query($sql);

// 2. Costo Actual (AC) desde horas_dia agrupado por mes y área funcional
$sql_ac = "SELECT DATE_FORMAT(fecha_mes, '%Y-%m') as mes, SUM(tiempo_imputado_costo) as costo_actual FROM $vista_horas_mes_detalle_capacidad_instalada ";
$condiciones_ac = array();
if (!empty($area_funcional)) {
  $condiciones_ac[] = "area_funcional = '" . $conn->real_escape_string($area_funcional) . "'";
} else {
  // Solo mostrar áreas permitidas según el usuario
  if ($is_mix && array_key_exists(strtoupper($identificador), $usuarios_areas_especiales)) {
    $areas_permitidas = $usuarios_areas_especiales[strtoupper($identificador)];
  } elseif ($is_mix) {
    $areas_permitidas = ["BIM", "VÍAS", "Vías y Topografía"];
  } else {
    $areas_permitidas = [
      'Vías y Topografía',
      'Geotecnia y Pavimentos',
      'Eléctrica',
      'Hidráulica y Medio Ambiente',
      'Arquitectura y Urbanismo',
      'Mecánica',
      'Estructuras',
      'Dirección de Proyectos',
      'Tecnología',
      'Área_Prueba'
    ];
  }
  $areas_sql = array_map(function($a) use ($conn) { return "'" . $conn->real_escape_string($a) . "'"; }, $areas_permitidas);
  $condiciones_ac[] = "area_funcional IN (" . implode(",", $areas_sql) . ")";
}
$condiciones_ac[] = "DATE_FORMAT(fecha_mes, '%Y-%m') >= '$mes_inicio'";
if (count($condiciones_ac) > 0) {
    $sql_ac .= " WHERE " . implode(' AND ', $condiciones_ac);
}
$sql_ac .= " GROUP BY mes ORDER BY mes";
$result_ac = $conn->query($sql_ac);

define('DEBUG_MODE', isset($_GET['debug']) && $_GET['debug'] == '1');
$debug_output = '';

// Preparar datos para la gráfica
$meses = array();
$valores = array();
$gastos_personal = array();
$valores_real_ejecutado = array();
$costos_mensuales_morados = array();

// Definir los meses de 2026 en formato de la gráfica y los campos de la vista
$meses_2026 = [
  '2026-01' => 'Ene_2026_Costo',
  '2026-02' => 'Feb_2026_Costo',
  '2026-03' => 'Mar_2026_Costo',
  '2026-04' => 'Abr_2026_Costo',
  '2026-05' => 'May_2026_Costo',
  '2026-06' => 'Jun_2026_Costo',
  '2026-07' => 'Jul_2026_Costo',
  '2026-08' => 'Ago_2026_Costo',
  '2026-09' => 'Sep_2026_Costo',
  '2026-10' => 'Oct_2026_Costo',
  '2026-11' => 'Nov_2026_Costo',
  '2026-12' => 'Dic_2026_Costo',
];

// Mapear resultados de capacidad instalada y gastos personal
$data_grafica = array();
if ($result && $result->num_rows > 0) {
  $temp_gastos = array();
  while($row = $result->fetch_assoc()) {
    $data_grafica[$row['mes']] = [
      'valor_capacidad_instalada' => $row['valor_capacidad_instalada'],
      'valor_gastos_personal' => $row['valor_gastos_personal']
    ];
  }
}

// Mapear resultados de AC
$data_ac = array();
if ($result_ac && $result_ac->num_rows > 0) {
  while($row = $result_ac->fetch_assoc()) {
    $data_ac[$row['mes']] = $row['costo_actual'];
  }
}

// Unificar meses (solo los que existan en 2026 y desde marzo en adelante)
// Unificar meses (solo los que existan en 2026)
$all_meses = array_unique(array_merge(array_keys($data_grafica), array_keys($data_ac)));
sort($all_meses);
foreach ($all_meses as $mes) {
  if (substr($mes, 0, 4) !== '2026') continue; // Solo 2026
  $meses[] = $mes;
  $valores[] = isset($data_grafica[$mes]['valor_capacidad_instalada']) ? floatval($data_grafica[$mes]['valor_capacidad_instalada']) : 0;
  $gastos_personal[] = isset($data_grafica[$mes]['valor_gastos_personal']) ? floatval($data_grafica[$mes]['valor_gastos_personal']) : 0;
  // Ocultar valor de AC para marzo 2026
  if ($mes === '2026-03') {
    $valores_real_ejecutado[] = null;
  } else {
    $valores_real_ejecutado[] = isset($data_ac[$mes]) ? floatval($data_ac[$mes]) : 0;
  }
}
// Ya no ocultar el valor de costo mensual por área para enero y febrero 2026

// === NUEVO: Calcular barras por nature_imputation ===
$costos_affaire = array_fill(0, count($meses), null);
$costos_gastos_generales = array_fill(0, count($meses), null);
$costos_absence = array_fill(0, count($meses), null);

$campos = array();
foreach ($meses_2026 as $campo) { $campos[] = $campo; }
$campos_sql = implode(", ", $campos);

$sql_costo = "SELECT v.centro_costos, v.area_funcional, v.nombre_proyecto, $campos_sql, p.nature_imputation FROM $vista_costo_mensual_por_area_proyecto_cc v ";
$sql_costo .= "LEFT JOIN proyectos p ON v.centro_costos = p.centro_costos ";
if (!empty($area_funcional)) {
  $sql_costo .= "WHERE v.area_funcional = '" . $conn->real_escape_string($area_funcional) . "' ";
}

$res_costo = $conn->query($sql_costo);

if ($res_costo) {
  while ($row = $res_costo->fetch_assoc()) {
    $nature = isset($row['nature_imputation']) ? strtoupper(trim($row['nature_imputation'])) : '';
    $nature = preg_replace('/\s+/', ' ', $nature);
    foreach ($meses as $i => $mes) {
      if (isset($meses_2026[$mes])) {
        $campo = $meses_2026[$mes];
        $valor = floatval($row[$campo] ?? 0);
        // Ya no ocultar enero y febrero
        if ($nature === 'AFFAIRE') {
          $costos_affaire[$i] = ($costos_affaire[$i] ?? 0) + $valor;
        } elseif ($nature === 'FRAIS GENERAUX DIVERS') {
          $costos_gastos_generales[$i] = ($costos_gastos_generales[$i] ?? 0) + $valor;
        } elseif ($nature === 'ABSENCE') {
          $costos_absence[$i] = ($costos_absence[$i] ?? 0) + $valor;
        }
      }
    }
  }
  $res_costo->free();
}
if (DEBUG_MODE) {
  $debug_output .= '<div style="background:#ffe;border:1px solid #cc0;padding:10px;margin:10px 0;">';
  $debug_output .= '<b>Consulta SQL Capacidad Instalada:</b><br><pre>' . htmlspecialchars($sql) . '</pre>';
  $debug_output .= '<b>Consulta SQL AC:</b><br><pre>' . htmlspecialchars($sql_ac) . '</pre>';
  $debug_output .= '<b>Meses:</b> ' . htmlspecialchars(json_encode($meses)) . '<br>';
  $debug_output .= '<b>Capacidad instalada:</b> ' . htmlspecialchars(json_encode($valores)) . '<br>';
  $debug_output .= '<b>Gastos personal:</b> ' . htmlspecialchars(json_encode($gastos_personal)) . '<br>';
  $debug_output .= '<b>Costo actual (AC):</b> ' . htmlspecialchars(json_encode($valores_real_ejecutado)) . '<br>';
  $debug_output .= '</div>';
}


// === NUEVO: Calcular barras por nature_imputation ===
$costos_affaire = array_fill(0, count($meses), null);
$costos_gastos_generales = array_fill(0, count($meses), null);
$costos_absence = array_fill(0, count($meses), null);

$campos = array();
foreach ($meses_2026 as $campo) { $campos[] = $campo; }
$campos_sql = implode(", ", $campos);

$sql_costo = "SELECT v.centro_costos, v.area_funcional, v.nombre_proyecto, $campos_sql, p.nature_imputation FROM $vista_costo_mensual_por_area_proyecto_cc v ";
$sql_costo .= "LEFT JOIN proyectos p ON v.centro_costos = p.centro_costos ";
if (!empty($area_funcional)) {
  $sql_costo .= "WHERE v.area_funcional = '" . $conn->real_escape_string($area_funcional) . "' ";
}

$res_costo = $conn->query($sql_costo);

if ($res_costo) {
  while ($row = $res_costo->fetch_assoc()) {
    $nature = isset($row['nature_imputation']) ? strtoupper(trim($row['nature_imputation'])) : '';
    $nature = preg_replace('/\s+/', ' ', $nature);
    foreach ($meses as $i => $mes) {
      if (isset($meses_2026[$mes])) {
        $campo = $meses_2026[$mes];
        $valor = floatval($row[$campo] ?? 0);
        if ($mes === '2026-01' || $mes === '2026-02') continue; // Ocultar enero y febrero
        if ($nature === 'AFFAIRE') {
          $costos_affaire[$i] = ($costos_affaire[$i] ?? 0) + $valor;
        } elseif ($nature === 'FRAIS GENERAUX DIVERS') {
          $costos_gastos_generales[$i] = ($costos_gastos_generales[$i] ?? 0) + $valor;
        } elseif ($nature === 'ABSENCE') {
          $costos_absence[$i] = ($costos_absence[$i] ?? 0) + $valor;
        }
      }
    }
  }
  $res_costo->free();
}
$conn->close();

$max_lineas = 0;
$max_barras = 0;
for ($i = 0; $i < count($meses); $i++) {
  $max_lineas = max(
    $max_lineas,
    floatval($valores[$i] ?? 0),
    floatval($gastos_personal[$i] ?? 0)
  );

  $detalle_apilado = floatval($costos_affaire[$i] ?? 0)
    + floatval($costos_gastos_generales[$i] ?? 0)
    + floatval($costos_absence[$i] ?? 0);

  $max_barras = max(
    $max_barras,
    floatval($valores_real_ejecutado[$i] ?? 0),
    $detalle_apilado
  );
}

$max_grafica = max($max_lineas, $max_barras);
$max_grafica = $max_grafica > 0 ? ceil($max_grafica * 1.1) : 100;

// Incluir Bootstrap y mostrar el formulario de filtro y el canvas con estilos
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">';
echo '<style>';
echo '  /* Ajuste responsivo para que el contenido se mueva con el menú */';
echo '  .main-content {';
echo '    transition: padding-left 0.2s;';
echo '    padding-left: 240px; /* ancho del menú expandido */';
echo '  }';
echo '  @media (max-width: 900px) {';
echo '    .main-content {';
echo '      padding-left: 0 !important;';
echo '    }';
echo '  }';
echo '  .sidebar.collapsed ~ .main-content {';
echo '    padding-left: 72px !important; /* ancho del menú contraído */';
echo '  }';
echo '</style>';
echo '<div class="main-content container mb-3" style="margin-top: 40px; background:transparent; box-shadow:none;">';
if (DEBUG_MODE && !empty($debug_output)) { echo $debug_output; }
// Título principal de la página (más grande, color #17823d, con icono de gráfico)
echo '<h1 class="text-center mb-3" style="font-weight:700; color:#17823d; font-size:2rem; letter-spacing:1px; display:flex; align-items:center; justify-content:center; gap:12px;">
  <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#43a047" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><ellipse cx="12" cy="7" rx="8" ry="3"/><path d="M4 7v3c0 1.1 3.6 2 8 2s8-.9 8-2V7"/><path d="M4 14v3c0 1.1 3.6 2 8 2s8-.9 8-2v-3"/><path d="M15 10l2 2 4-4"/></svg>
  <span style="color:#43a047;">Capacidad Instalada</span>
</h1>';

// Mostrar información del usuario logueado si está disponible
if (!empty($nombre_usuario) || !empty($area_funcional_usuario) || !empty($rol_usuario)) {
  $safe_nombre = htmlspecialchars($nombre_usuario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $safe_area = htmlspecialchars($area_funcional_usuario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $safe_rol = htmlspecialchars($rol_usuario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

  // Preparar fecha en español (ej. 21 de octubre de 2025)
  if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/Bogota');
  }
  $meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $fecha_formateada = date('j') . ' de ' . $meses_es[intval(date('n')) - 1] . ' de ' . date('Y');
  $safe_fecha = htmlspecialchars($fecha_formateada, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

  // Contenedor con botón a la izquierda y texto de usuario alineado a la derecha (estilo similar a la imagen)
  echo '<div class="d-flex justify-content-between align-items-center mb-3">';
  // Botón para regresar a Balance.php (oculto por CSS)
    echo '<style>.btn-volver-balance{display:none!important;}</style>';
    echo '<div class="">';
    echo '  <a href="Balance.php" class="btn btn-outline-secondary btn-sm btn-volver-balance" style="border-radius:10px; padding:6px 12px; display:inline-flex; align-items:center; gap:8px;" aria-label="Volver al Balance">';
    echo '    <svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">';
    echo '      <circle cx="12" cy="12" r="9" fill="#ffffff" stroke="#dee2e6" stroke-width="1"/>'; 
    echo '      <path d="M14 8l-4 4 4 4" stroke="#6c757d" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'; 
    echo '    </svg>';
    echo '    <span>Volver al Balance</span>';
    echo '  </a>';
    echo '</div>';
  // Texto del usuario alineado a la derecha (incluye fecha)
  echo '<div class="text-end" style="font-size:0.95rem; display:none;">';
  echo '  Usuario: <strong>' . $safe_nombre . '</strong> | Área: <strong>' . $safe_area . '</strong> | Rol: <strong style="color:#17823d;">' . $safe_rol . '</strong>';
  echo '  <span style="color:#6c757d; margin-left:12px;">| Fecha: <strong>' . $safe_fecha . '</strong></span>';
  echo '</div>';
  echo '</div>';
}

// Formulario de filtro: mostrar solo si NO es coordinador NI MIX2
if (!$is_coordinator && !$is_mix2) {
  echo '<div class="card shadow-sm p-3 mb-3">';
  echo '<form method="GET" class="row g-3 align-items-center">';
  echo '<div class="col-auto">';
  echo '<div class="form-header">Filtrar por Área Funcional:</div>';
  echo '</div>';
  echo '<div class="col-auto">';
  echo '<select aria-label="Filtrar por Área Funcional" name="area_funcional" id="area_funcional" class="form-select" onchange="this.form.submit()">';
  echo '<option value="">Todas las áreas</option>';
  if ($is_mix && array_key_exists(strtoupper($identificador), $usuarios_areas_especiales)) {
    $areas = $usuarios_areas_especiales[strtoupper($identificador)];
  } elseif ($is_mix) {
    $areas = ['BIM', 'VÍAS', 'Vías y Topografía'];
  } else {
    $areas = [
      'Vías y Topografía',
      'Geotecnia y Pavimentos',
      'Eléctrica',
      'Hidráulica y Medio Ambiente',
      'Arquitectura y Urbanismo',
      'Mecánica',
      'Estructuras',
      'Dirección de Proyectos',
      'Tecnología',
      'Área_Prueba'
    ];
  }
  foreach ($areas as $area) {
    $selected = (isset($_GET['area_funcional']) && $_GET['area_funcional'] === $area) ? 'selected' : '';
    echo "<option value=\"$area\" $selected>$area</option>";
  }
  echo '</select>';
  echo '</div>';
  echo '</form>';
  echo '</div>';
}

echo '<div class="card shadow-sm p-4 mb-4" style="background:#f8fafb; border-radius:18px; width:100%; max-width:1200px; margin:0 auto;">';
echo '<canvas id="graficaCapacidad" style="width:100%; aspect-ratio: 5/1; display:block; background:transparent; border-radius:12px;"></canvas>';
echo '</div>';
echo '</div>';
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>';
echo '<script>';
echo 'const ctx = document.getElementById("graficaCapacidad").getContext("2d");';
echo 'if (typeof ChartDataLabels !== "undefined") { Chart.register(ChartDataLabels); }';
echo 'const chart = new Chart(ctx, {';
echo '    type: "line",';
echo '    data: {';
echo '        labels: ' . json_encode($meses) . ',';
echo '        datasets: [';
echo '          {';
echo '            label: "CAPACIDAD INSTALADA",';
echo '            data: ' . json_encode($valores) . ',';
echo '            yAxisID: "yLine",';
echo '            order: 1,';
echo '            backgroundColor: "rgba(86, 156, 214, 0.38)",'; // Azul pastel intenso
echo '            borderColor: "#569CD6",';
echo '            borderWidth: 2,';
echo '            pointRadius: function(ctx) { return ctx.raw === 0 ? 0 : 3; },';
echo '            pointHoverRadius: function(ctx) { return ctx.raw === 0 ? 0 : 6; },';
echo '            pointStyle: "circle",';
echo '            tension: 0.4,';
echo '            cubicInterpolationMode: "monotone"';
echo '          },';
echo '          {';
echo '            label: "PRESUPUESTO A TERMINACIÓN (BAC)",';
echo '            data: ' . json_encode($gastos_personal) . ',';
echo '            yAxisID: "yLine",';
echo '            order: 1,';
echo '            backgroundColor: "rgba(34, 139, 84, 0.28)",'; // Verde intenso pastel oscuro
echo '            borderColor: "#228B54",';
echo '            borderWidth: 2,';
echo '            borderDash: [8, 4],';
echo '            pointRadius: function(ctx) { return ctx.raw === 0 ? 0 : 3; },';
echo '            pointHoverRadius: function(ctx) { return ctx.raw === 0 ? 0 : 6; },';
echo '            pointStyle: "circle",';
echo '            tension: 0.4,';
echo '            cubicInterpolationMode: "monotone"';
echo '          },';
echo '          {';
echo '            label: "COSTO ACTUAL (AC)",';
echo '            data: ' . json_encode($valores_real_ejecutado) . ',';
echo '            type: "bar",';
echo '            yAxisID: "yBars",';
echo '            stack: "ac_total",';
echo '            order: 3,';
echo '            backgroundColor: "rgba(34, 139, 84, 0.38)",'; // Verde pastel oscuro
echo '            borderColor: "#228B54",';
echo '            borderWidth: 1,';
echo '            datalabels: { display: false }';
echo '          },';
echo '          {';
echo '            label: "ASIGNADO A PROYECTO",';
echo '            data: ' . json_encode($costos_affaire) . ',';
echo '            type: "bar",';
echo '            yAxisID: "yBars",';
echo '            stack: "detalle_imputacion",';
echo '            order: 2,';
echo '            backgroundColor: "rgba(120, 70, 180, 0.84)",'; // Morado pastel oscuro
echo '            borderColor: "#7846B4",';
echo '            borderWidth: 1,';
echo '            datalabels: { display: false }';
echo '          },';
echo '          {';
echo '            label: "AREAS ADMINISTRATIVAS",';
echo '            data: ' . json_encode($costos_gastos_generales) . ',';
echo '            type: "bar",';
echo '            yAxisID: "yBars",';
echo '            stack: "detalle_imputacion",';
echo '            order: 2,';
echo '            backgroundColor: "rgba(54, 162, 235, 0.84)",'; // Celeste pastel intenso
echo '            borderColor: "#36A2EB",';
echo '            borderWidth: 1,';
echo '            datalabels: { display: false }';
echo '          },';
echo '          {';
echo '            label: "AUSENCIAS",';
echo '            data: ' . json_encode($costos_absence) . ',';
echo '            type: "bar",';
echo '            yAxisID: "yBars",';
echo '            stack: "detalle_imputacion",';
echo '            order: 2,';
echo '            backgroundColor: "rgba(255, 159, 64, 0.84)",'; // Naranja pastel intenso
echo '            borderColor: "#FF9F40",';
echo '            borderWidth: 1,';
echo '            datalabels: { display: false }';
echo '          }';
echo '        ]';
echo '    },';
echo '    options: {';
echo '        plugins: {';
echo '            datalabels: { display: false },';
echo '            tooltip: {';
echo '                callbacks: {';
echo '                    label: function(context) {';
echo '                        var label = context.dataset.label || "";';
echo '                        var value = context.parsed && (context.parsed.y !== undefined ? context.parsed.y : context.parsed);';
echo '                        if (value === null || value === undefined || value === 0) return "";';
echo '                        try {';
echo '                            var formatted = new Intl.NumberFormat("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 0 }).format(value);';
echo '                            return label + ": " + formatted;';
echo '                        } catch (e) {';
echo '                            return label + ": " + value;';
echo '                        }';
echo '                    }';
echo '                }';
echo '            }';
echo '        },';
echo '        hover: { mode: "nearest", intersect: true },';
echo '        scales: {';
echo '            x: {';
echo '                stacked: true,';
echo '                ticks: {';
echo '                    font: { size: 12 },';
echo '                    maxRotation: 30,';
echo '                    minRotation: 30,';
echo '                    autoSkip: false,';
echo '                    maxTicksLimit: 100';
echo '                },';
echo '            },';
echo '            yLine: {';
echo '                beginAtZero: true,';
echo '                position: "left",';
echo '                stacked: false,';
echo '                max: ' . json_encode($max_grafica) . ',';
echo '                ticks: {';
echo '                    callback: function(value) {';
echo '                        return new Intl.NumberFormat("es-CO").format(value);';
echo '                    }';
echo '                }';
echo '            },';
echo '            yBars: {';
echo '                beginAtZero: true,';
echo '                position: "right",';
echo '                stacked: true,';
echo '                display: false,';
echo '                grid: { drawOnChartArea: false },';
echo '                max: ' . json_encode($max_grafica);
echo '            }';
echo '        },';
echo '        datasets: {';
echo '            bar: { barPercentage: 0.62, categoryPercentage: 0.72 },';
echo '            line: { stacked: false }';
echo '        }';
echo '    }';
echo '});';
echo '  // Redibujar la gráfica cuando el menú lateral cambie de tamaño\n';
echo '  function fixCanvasResolution() {\n';
echo '    var canvas = document.getElementById("graficaCapacidad");\n';
echo '    var dpr = window.devicePixelRatio || 1;\n';
echo '    var parent = canvas.parentElement;\n';
echo '    var width = parent.offsetWidth;\n';
echo '    // Mantener proporción 5:1 (ancho:alto)\n';
echo '    var height = Math.round(width / 5);\n';
echo '    canvas.width = width * dpr;\n';
echo '    canvas.height = height * dpr;\n';
echo '    canvas.style.width = width + "px";\n';
echo '    canvas.style.height = height + "px";\n';
echo '  }\n';
echo '  function triggerResize() {\n';
echo '    function doResize() {\n';
echo '      fixCanvasResolution();\n';
echo '      chart.resize();\n';
echo '    }\n';
echo '    if (typeof window.ResizeObserver !== "undefined") {\n';
echo '      new ResizeObserver(doResize).observe(document.querySelector(".main-content"));\n';
echo '    } else {\n';
echo '      window.addEventListener("resize", doResize);\n';
echo '    }\n';
echo '    var sidebarToggle = document.getElementById("sidebarToggle");\n';
echo '    if (sidebarToggle) {\n';
echo '      sidebarToggle.addEventListener("click", function() {\n';
echo '        setTimeout(doResize, 300);\n';
echo '      });\n';
echo '    }\n';
echo '  }\n';
echo '  triggerResize();\n';
echo '</script>';
?>
