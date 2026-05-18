
<?php
// Incluye la configuración centralizada para compatibilidad de entorno
require_once 'include.php';
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Verificar si el usuario es ADMIN
if (!isset($_SESSION['rol']) || strpos(strtoupper($_SESSION['rol']), 'ADMIN') === false) {
    header('Location: Index2.php');
    exit();
}

$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Puente de Módulos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: #f4f6fa !important;
        }
        .card {
            box-shadow: 0 2px 12px 0 rgba(60,72,100,.08), 0 1.5px 4px 0 rgba(60,72,100,.04);
            border-radius: 18px !important;
            border: none;
        }
        .btn-custom {
            background-color: #4C8AA3;
            color: #fff;
            font-weight: 700;
            box-shadow: 0 2px 8px 0 rgba(60,72,100,.12);
            border-radius: 10px;
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #3E6E82;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px 0 rgba(60,72,100,.15);
        }
        .btn-logout {
            background-color: #dc3545;
            color: #fff;
        }
        .btn-logout:hover {
            background-color: #c82333;
            color: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .header h3 {
            color: #4C8AA3;
            font-weight: 700;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
    </style>
</head>
<body class="container py-5">

    <div class="header">
        <h3>Bienvenido, <?= htmlspecialchars($usuario) ?>!</h3>
        <a href="logout.php" class="btn btn-logout">Cerrar sesión</a>
    </div>

    <div class="card p-4">
        <div class="card-body">
            <h4 class="card-title text-center mb-4" style="color:#4C8AA3; font-weight: 600;">Seleccione un módulo</h4>
            <div class="grid-container">
                <a href="empleados.php" class="btn btn-custom">Base Empleados</a>
                <a href="proyectos.php" class="btn btn-custom">Base Proyectos</a>
                    <a href="Avance_pdt.php" class="btn btn-custom">Avance PDT</a>
                <a href="ModuloDirector.php" class="btn btn-custom">Multi Módulo Director</a>
                <a href="Index2.php" class="btn btn-custom">Multi Módulo Proyectos</a>
                <a href="Coordinador.php" class="btn btn-custom">Módulo Coordinador</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
