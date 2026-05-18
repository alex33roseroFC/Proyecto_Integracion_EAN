<?php
// Incluye la configuración centralizada para compatibilidad de entorno
require_once 'include.php';
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Verificar si el usuario es SUPER o MIX2
if (!isset($_SESSION['rol']) || !in_array(strtoupper(trim($_SESSION['rol'])), ['SUPER', 'MIX2'])) {
    header('Location: Index2.php');
    exit();
}

$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Puente 1</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
    </style>
</head>
<body class="container py-5">
    <div class="card mb-3 p-3 d-flex flex-row justify-content-between align-items-center" style="border-radius:18px; box-shadow:0 2px 12px 0 rgba(60,72,100,.08);">
        <img src="logofza2.PNG" alt="Logo Forza" style="height:48px; margin-left:8px;">
    <a href="logout.php" class="btn btn-danger btn-sm" style="font-weight:600; padding:6px 16px; font-size:0.95rem;"><i class="bi bi-box-arrow-right" style="margin-right:6px;"></i>Cerrar sesión</a>
    </div>
    <div class="card p-4">
        <div class="card-body">
            <h4 class="card-title text-center mb-4" style="color:#4C8AA3; font-weight: 600;">Seleccione un módulo</h4>
            <div class="grid-container">
                <a href="ModuloDirector.php" class="btn btn-custom"><i class="bi bi-person-badge" style="margin-right:8px;"></i>Multi - Módulo Director</a>
                <a href="Balance.php" class="btn btn-custom"><i class="bi bi-people" style="margin-right:8px;"></i>Multi - Módulo Coordinador</a>
                <?php if (strtoupper(trim($_SESSION['rol'])) === 'SUPER') { ?>
                    <a href="empleados.php" class="btn btn-custom"><i class="bi bi-person-lines-fill" style="margin-right:8px;"></i>Base Empleados</a>
                    <a href="proyectos.php" class="btn btn-custom"><i class="bi bi-folder2-open" style="margin-right:8px;"></i>Base Proyectos</a>
                    <a href="Avance_pdt.php" class="btn btn-custom"><i class="bi bi-bar-chart-line" style="margin-right:8px;"></i>Avance PDT</a>
                <?php } ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>