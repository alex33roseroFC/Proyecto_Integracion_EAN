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
// Redirigir a Proyectos_Cargados.php antes de cualquier salida
header('Location: Proyectos_Cargados.php');
exit();





$usuario_logueado = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
$sql = "SELECT 
    PROYECTO,
    MIN(`FECHA INICIO PROYECTO`) AS fecha_inicio,
    MAX(`FECHA FIN PROYECTO`) AS fecha_fin,
    SUM(
        `ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
        `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
        `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
        `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`
    ) AS total_horas,
    SUM((
        `ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
        `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
        `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
        `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`
    ) * `TARIFA COAN 2`) AS total_costo
FROM gastos_personal 
WHERE USUARIO = '" . $conn->real_escape_string($usuario_logueado) . "' 
GROUP BY PROYECTO
ORDER BY fecha_inicio ASC;";


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


$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen Ejecutivo de Proyectos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .resumen-table th, .resumen-table td {
            border-right: 1px solid #e0e0e0;
            vertical-align: middle;
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
<body class="container mt-5">
    <h2 class="mb-4"> </h2>
    <div class="card shadow-sm rounded-4 mb-4" style="border:none;">
        <div class="card-body">
            <?php
            // ...existing code...
