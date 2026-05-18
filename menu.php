<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_logueado = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
$area_funcional = '';
$nombre_usuario = '';
$rol_usuario = '';
require_once __DIR__ . '/include.php';
require_once __DIR__ . '/config.php';
if (!empty($usuario_logueado)) {
    $sql_user = "SELECT Área_Funcional, Nombre_Usuario, ROL FROM login_usuarios WHERE Usuario = '" . $conn->real_escape_string($usuario_logueado) . "' LIMIT 1";
    $res_user = $conn->query($sql_user);
    if ($res_user && $res_user->num_rows > 0) {
        $urow = $res_user->fetch_assoc();
        $area_funcional = isset($urow['Área_Funcional']) ? $urow['Área_Funcional'] : '';
        $nombre_usuario = isset($urow['Nombre_Usuario']) ? $urow['Nombre_Usuario'] : '';
        $rol_usuario = isset($urow['ROL']) ? $urow['ROL'] : '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú</title>
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        /* Modern sidebar header */
        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 24px 20px 18px 20px;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
        }
        .sidebar-header .logo-circle {
            width: 44px;
            height: 44px;
            background: #6c47a3;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(108,71,163,0.08);
        }
        .sidebar-header .app-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #6c47a3;
            letter-spacing: 0.5px;
        }
        /* Modern nav styles */
        .sidebar-nav ul {
            padding: 18px 0 0 0;
        }
        .sidebar {
            position: fixed;
            top: 28px;
            left: 0;
            width: 240px;
            height: calc(100dvh - 28px);
            background: linear-gradient(135deg, #5B8DB8 60%, #23406C 100%);
            color: #23406C;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            box-shadow: 2px 0 16px rgba(60,72,100,0.08);
            transition: width 0.2s, top 0.2s, height 0.2s;
            border-top: none;
            overflow-y: auto;
        }
        }
        .sidebar-link span {
            margin-left: 0;
            font-size: 1.08rem;
            font-weight: 500;
        }
        .sidebar-link.active, .sidebar-link:hover {
            background: linear-gradient(100deg, #eaf1fb 60%, #b6d0ea 100%) !important;
            color: #23406C !important;
            box-shadow: 0 2px 12px 0 rgba(91,141,184,0.10);
            border-radius: 16px;
            outline: 2.5px solid #5B8DB8;
            outline-offset: 0px;
            font-weight: 700;
            transition: background 0.18s, color 0.18s, box-shadow 0.18s, outline 0.18s;
        }
        .sidebar-link svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            stroke: #6c47a3;
            transition: stroke 0.18s;
        }
        .sidebar-link.active svg, .sidebar-link:hover svg {
            stroke: #fff;
        }
        .sidebar-link .sidebar-badge {
            margin-left: auto;
        }
        .sidebar-section {
            font-size: 0.93rem;
            color: #4C8AA3;
            opacity: 0.8;
            font-weight: 600;
            padding: 18px 24px 6px 24px;
            letter-spacing: 0.5px;
        }
        .main-content {
            margin-left: 240px;
            width: calc(100% - 240px);
            min-height: 100vh;
            background: linear-gradient(135deg, #e0e7ef 0%, #f4f6fa 100%);
            transition: margin-left 0.08s, width 0.08s;
            will-change: margin-left, width;
            box-shadow: none;
            border-top-left-radius: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar.collapsed ~ .main-content {
            margin-left: 72px;
            width: calc(100% - 72px);
        }
        @media (max-width: 900px) {
            .sidebar {
                left: -240px;
                transition: left 0.2s;
            }
            .sidebar.open {
                left: 0;
                z-index: 1200;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .sidebar.collapsed {
                width: 240px;
            }
        }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.25);
            z-index: 1199;
        }
        .sidebar.open ~ .sidebar-backdrop {
            display: block;
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f7f7f7;
            padding-top: 64px;
        }
        /* Eliminada definición duplicada de .sidebar para evitar conflictos de altura y posición */
        .sidebar.collapsed {
            width: 72px;
        }
        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 16px 12px 16px;
            font-size: 1.3rem;
            font-weight: bold;
            color: #6c47a3;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
        }
        /* El header nunca se contrae */
        .sidebar.collapsed .sidebar-header {
            padding: 18px 16px 12px 16px;
            font-size: 1.3rem;
        }
        .sidebar-header .logo {
            width: 36px;
            height: 36px;
            background: #6c47a3;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .sidebar-toggle {
            margin-left: auto;
            background: #fff;
            border: 2px solid #6c47a3;
            border-radius: 8px;
            padding: 5px 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sidebar-toggle:hover {
            background: #f3eaff;
        }
        .sidebar-nav {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 12px 0 0 0;
        }
        .sidebar-nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .sidebar-nav li {
            margin-bottom: 2px;
        }
        .sidebar-link {
                        transition: background 0.18s, color 0.18s, box-shadow 0.18s, outline 0.18s;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0;
            padding: 0 0 0 18px;
            color: #fff !important;
            text-decoration: none;
            font-size: 0.97rem;
            border-radius: 8px 0 0 8px;
            transition: background 0.18s, color 0.18s, padding 0.2s, font-size 0.2s;
            position: relative;
            height: 48px;
            width: 100%;
        }
        .sidebar-link span {
            display: inline-block;
            margin-left: 14px;
            font-size: 0.97rem;
            transition: opacity 0.2s, width 0.2s;
            white-space: nowrap;
            width: auto;
            opacity: 1;
        }
        .sidebar-link.active, .sidebar-link:hover {
            background: #6c47a3;
            color: #fff;
        }
        .sidebar-link svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
            stroke: #fff !important;
            transition: stroke 0.18s;
            margin-left: 0;
        }
        .sidebar-link.active svg, .sidebar-link:hover svg {
            stroke: #fff;
        }
        .sidebar-badge {
            background: #00bcd4;
            color: #fff;
            font-size: 0.75rem;
            border-radius: 8px;
            padding: 2px 8px;
            margin-left: 8px;
            font-weight: 500;
        }
        .sidebar-badge.purple {
            background: #6c47a3;
        }
        .sidebar-badge.red {
            background: #e53935;
        }
        .sidebar-section {
            font-size: 0.93rem;
            color: #222;
            opacity: 0.7;
            font-weight: 600;
            padding: 18px 24px 6px 24px;
            letter-spacing: 0.5px;
        }
        /* Colapsar texto de opciones al contraer */
        .sidebar.collapsed .sidebar-link span,
        .sidebar.collapsed .sidebar-section {
            width: 0;
            opacity: 0;
            overflow: hidden;
            margin: 0;
            padding: 0;
            display: inline-block;
        }
        .sidebar.collapsed .sidebar-link {
            justify-content: center;
        }
        .sidebar.collapsed .sidebar-header .logo {
            margin: 0;
        }
        /* Menú transparente cuando hay modal abierto */
        .sidebar.menu-transparent {
            opacity: 0.12;
            transition: opacity 0.2s;
        }
        /* NAVBAR transparente cuando hay modal abierto */
        .navbar-forza.menu-transparent {
            opacity: 0.12;
            transition: opacity 0.2s;
        }
        .modal {
            z-index: 1200 !important;
        }
        .modal-backdrop {
            z-index: 1190 !important;
        }
    </style>
</head>
<body>
    <!-- NAVBAR SUPERIOR -->
    <header class="navbar-forza">
        <div class="navbar-left">
            <button class="navbar-menu-btn" id="sidebarToggle" title="Expandir/Contraer menú" style="background:linear-gradient(135deg, #5B8DB8 60%, #23406C 100%);border:none;border-radius:12px;padding:7px 14px;box-shadow:0 2px 8px rgba(60,72,100,0.10);display:flex;align-items:center;justify-content:center;transition:box-shadow 0.18s;">
                <i class="bi bi-list" style="font-size:1.7em;color:#fff;"></i>
            </button>
        </div>
        <div class="navbar-center">
               <!-- Search input removed -->
        </div>
        <div class="navbar-right" style="display:flex;align-items:center;gap:14px;">
            <div style="display:flex;align-items:center;gap:7px;">
                <!-- Ícono de usuario eliminado junto con la palabra Bienvenido -->
            </div>
            <div style="height:22px;width:1.5px;background:#b2e5c2;opacity:0.7;margin:0 7px;"></div>
            <div id="datetime-colombia" style="display:flex;align-items:center;gap:7px;">
                <span style="font-size:0.97em;color:#23406C;font-weight:600;margin-right:10px;">
                    <?php echo htmlspecialchars($nombre_usuario); ?>
                </span>
                <div style="height:24px;width:2.2px;background:#5B8DB8;opacity:0.85;margin:0 10px;border-radius:1.5px;"></div>
                <span id="fecha-colombia" style="font-size:0.93em;color:#444;font-weight:500;"></span>
                <span id="hora-colombia" style="font-size:0.97em;color:#23406C;font-weight:600;"></span>
            </div>
        </div>
    </header>
    <style>
        .navbar-forza {
            width: 100%;
            height: 64px;
                background: linear-gradient(135deg, #e0e7ef 0%, #b6d0ea 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 16px rgba(60,72,100,0.08);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1100;
            padding: 0 24px 0 0;
        }
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-left: 18px;
        }
        .logo-navbar {
            display: flex;
            align-items: center;
            height: 100px;
            padding: 0 12px 0 0;
        }
        .logo-navbar img {
            width: auto;
            height: 44px;
            max-width: 160px;
            object-fit: contain;
            display: block;
        }
        .navbar-menu-btn {
            background: #fff;
            border: 2px solid #6c47a3;
            border-radius: 8px;
            padding: 6px 10px;
            cursor: pointer;
            margin-left: 8px;
            transition: background 0.2s;
        }
        .navbar-menu-btn:hover {
            background: #f3eaff;
        }
        .navbar-center {
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
        }
        .navbar-search {
            width: 340px;
            max-width: 100%;
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            font-size: 1rem;
            background: #f7f7f7;
            outline: none;
            transition: border 0.2s;
        }
        .navbar-search:focus {
            .logo-navbar img {
                width: auto;
                height: 180px;
                max-width: 400px;
                object-fit: contain;
                display: block;
            }
        }
        .navbar-user img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        body {
            padding-top: 64px;
        }
        .main-content {
            margin-left: 240px;
            width: calc(100% - 240px);
            min-height: 100vh;
            background: #f7f7f7;
            transition: margin-left 0.2s, width 0.2s;
            box-shadow: none;
            border-top-left-radius: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar.collapsed ~ .main-content {
            margin-left: 72px;
            width: calc(100% - 72px);
        }
        @media (max-width: 900px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            .sidebar.open ~ .main-content {
                filter: blur(2px);
                pointer-events: none;
            }
        }
    </style>
    <aside class="sidebar" id="sidebar" style="display:flex;flex-direction:column;">
        <div class="sidebar-header"></div>
        <nav class="sidebar-nav" style="flex:1 1 auto;">
            <ul>
                <?php if ($rol_usuario === 'USER'): ?>
                    <li>
                        <a href="Colaborador.php#asignacion" class="sidebar-link" id="menu-asignacion-link">
                            <i class="bi bi-person-lines-fill" style="font-size:20px;"></i>
                            <span>Asignación</span>
                        </a>
                    </li>
                    <li>
                        <a href="Colaborador.php#aprobacion" class="sidebar-link" id="menu-aprobacion-link">
                            <i class="bi bi-clipboard-check-fill" style="font-size:20px;"></i>
                            <span>Aprobación</span>
                        </a>
                    </li>
                    <li>
                        <a href="cargue_horas.php" class="sidebar-link">
                            <i class="bi bi-cloud-upload-fill" style="font-size:20px;"></i>
                            <span>Cargue horas</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="javascript:history.back()" class="sidebar-link">
                            <i class="bi bi-arrow-left-circle-fill" style="font-size:20px;"></i>
                            <span>Regresar</span>
                        </a>
                    </li>
                    <li>
                        <a href="Balance.php" class="sidebar-link">
                            <i class="bi bi-graph-up-arrow" style="font-size:20px;"></i>
                            <span>Balance</span>
                        </a>
                    </li>
                    <li>
                        <a href="Detalle_Presupuesto.php" class="sidebar-link">
                            <i class="bi bi-file-earmark-bar-graph" style="font-size:20px;"></i>
                            <span>Detalle Presupuesto</span>
                        </a>
                    </li>
                    <li>
                        <a href="Capacidad_Instalada.php" class="sidebar-link">
                            <i class="bi bi-cpu" style="font-size:20px;"></i>
                            <span>Capacidad Instalada</span>
                        </a>
                    </li>
                    <li>
                        <a href="cargue_horas.php" class="sidebar-link">
                            <i class="bi bi-cloud-upload-fill" style="font-size:20px;"></i>
                            <span>Cargue horas</span>
                        </a>
                    </li>
                    <li>
                        <a href="Coordinador.php" class="sidebar-link">
                            <i class="bi bi-person-badge" style="font-size:20px;"></i>
                            <span>Asignación</span>
                        </a>
                    </li>
                    <li>
                        <a href="Aprobacion_Lucca.php" class="sidebar-link">
                            <i class="bi bi-patch-check-fill" style="font-size:20px;"></i>
                            <span>Aprobación Lucca</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div style="padding: 10px 10px 16px 10px; margin-top:auto;">
            <div class="sidebar-user-card" style="background:linear-gradient(135deg, #5B8DB8 60%, #23406C 100%);border-radius:14px;padding:16px 10px 14px 10px;margin-bottom:12px;box-shadow:0 2px 12px rgba(60,72,100,0.10);text-align:center;border:1.2px solid #5B8DB8;max-width:190px;margin-left:auto;margin-right:auto;">
                <hr style="border:none;border-top:1.5px solid #fff;margin:0 0 10px 0;opacity:0.5;">
                <div style="display:flex;justify-content:center;align-items:center;margin-bottom:7px;">
                    <div style="background:#fff;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px 0 rgba(60,72,100,0.10);">
                        <svg xmlns='http://www.w3.org/2000/svg' width='28' height='28' fill='#4C8AA3' viewBox='0 0 16 16'>
                            <path d='M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'/>
                        </svg>
                    </div>
                </div>
                <div style="font-weight:700;font-size:1.01rem;color:#fff;line-height:1.1;text-transform:capitalize;white-space:normal;word-break:break-word;">
                    <?php echo htmlspecialchars($nombre_usuario ?: $usuario_logueado ?: 'Usuario'); ?>
                </div>
                <div style="font-size:0.87rem;color:#fff;margin-top:2px;text-transform:capitalize;opacity:0.95;">
                    <?php echo htmlspecialchars($area_funcional); ?>
                </div>
            </div>
            <a href="logout.php" class="sidebar-logout-btn" style="border:1.2px solid #f8b4b4;background:#fff;color:#e57373;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.97rem;border-radius:8px;padding:7px 0;box-shadow:0 1px 4px rgba(248,180,180,0.07);text-decoration:none;transition:background 0.18s;gap:7px;max-width:170px;margin-left:auto;margin-right:auto;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#e57373" class="bi bi-box-arrow-right" viewBox="0 0 16 16" style="vertical-align:middle;">
                    <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                    <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                </svg>
                <span style="vertical-align:middle;">Cerrar Sesión</span>
            </a>
            <div class="sidebar-user-collapsed" style="display:none;flex-direction:column;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="background:#f6fff8;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-person-fill" style="font-size:18px;color:#7b8794;"></i>
                </div>
                <a href="logout.php" style="background:#fff;color:#e57373;display:flex;align-items:center;justify-content:center;border-radius:8px;width:38px;height:38px;box-shadow:0 1px 4px rgba(248,180,180,0.07);text-decoration:none;transition:background 0.18s;">
                    <i class="bi bi-box-arrow-right" style="font-size:20px;color:#e57373;"></i>
                </a>
            </div>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    
    <script>
    // Si estamos en Colaborador.php, activar el tab correspondiente al hacer clic en el menú sin recargar
    document.addEventListener('DOMContentLoaded', function() {
        var isColaborador = window.location.pathname.includes('Colaborador.php');
        var asignacionLink = document.getElementById('menu-asignacion-link');
        var aprobacionLink = document.getElementById('menu-aprobacion-link');
        if (isColaborador && asignacionLink && aprobacionLink) {
            asignacionLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (window.location.hash !== '#asignacion') {
                    window.location.hash = '#asignacion';
                }
            });
            aprobacionLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (window.location.hash !== '#aprobacion') {
                    window.location.hash = '#aprobacion';
                }
            });
        }
    });
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');
        function toggleSidebarMobile() {
            if (!sidebar) {
                return;
            }
            if (window.innerWidth <= 900) {
                sidebar.classList.toggle('open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        }
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleSidebarMobile);
        }
        if (sidebarBackdrop && sidebar) {
            sidebarBackdrop.addEventListener('click', () => {
                sidebar.classList.remove('open');
            });
        }
        window.addEventListener('resize', () => {
            if (window.innerWidth > 900 && sidebar) {
                sidebar.classList.remove('open');
            }
        });
    </script>
    <style>
        /* Oculta la tarjeta de usuario y el botón de cerrar sesión cuando el menú está colapsado */
        .sidebar.collapsed .sidebar-user-card,
        .sidebar.collapsed .sidebar-logout-btn {
            display: none !important;
        }
        .sidebar.collapsed .sidebar-user-collapsed {
            display: flex !important;
        }
        .sidebar-user-collapsed {
            display: none;
        }
    </style>
    <script>
    function updateColombiaTime() {
        // Colombia timezone offset (UTC-5)
        const now = new Date();
        // Convert to UTC-5
        const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        const colombia = new Date(utc - (5 * 60 * 60 * 1000));
        // Formato dd/mm/yyyy
        const dia = colombia.getDate().toString().padStart(2, '0');
        const mes = (colombia.getMonth() + 1).toString().padStart(2, '0');
        const anio = colombia.getFullYear();
        const fecha = `${dia}/${mes}/${anio}`;
        let hora = colombia.toLocaleTimeString('es-CO', { hour: '2-digit', minute:'2-digit', hour12: true });
        document.getElementById('fecha-colombia').textContent = fecha;
        document.getElementById('hora-colombia').textContent = hora;
    }
    setInterval(updateColombiaTime, 1000);
    updateColombiaTime();
</script>
</body>
</html>
