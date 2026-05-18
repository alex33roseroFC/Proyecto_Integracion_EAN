<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Base URL del sitio (ajustar según el host local)
$base_url = 'http://localhost/App_Presupuestal_Horas/';

// Base path (UNC) para ubicación en red — ruta del proyecto
$base_path = '\\APP1BOGGCA\\xampp\\htdocs\\Proyecto_GCA';

// Validación de sesión y rol comentada para acceso libre
/*
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || 
   (strtoupper(trim($_SESSION['rol'])) !== 'TH' && 
  strpos(strtoupper($_SESSION['rol']), 'ADMIN') === false && 
  strtoupper(trim($_SESSION['rol'])) !== 'SUPER')){
  header("Location: " . $base_url . 'login.php');
  exit;
}
*/

// Incluir el archivo de conexión a la base de datos
require_once "db_connection.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Horas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
      <div class="container-fluid">
  <!-- marca removida por solicitud del usuario -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- navbar items removed: Coordinador link hidden -->
            <ul class="navbar-nav"></ul>
        </div>
      </div>
    </nav>
  <div class="container">
    <!-- Encabezado de página: imagen en la esquina superior izquierda -->
    <div class="d-flex align-items-center mb-3 header-top">
  <img src="encabezado.png" alt="Encabezado" style="display:none; max-height:80px; margin-right:12px;" />
      <div class="page-title-placeholder" style="margin-left:12px; flex:1; display:flex; align-items:center;"></div>
    </div>
