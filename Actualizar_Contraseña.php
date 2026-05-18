<?php

require_once 'include.php';
require_once 'config.php';

$cookie_domain = (ENVIRONMENT === 'LOCAL') ? 'localhost' : $_SERVER['HTTP_HOST'];
$cookie_samesite = (ENVIRONMENT === 'LOCAL') ? 'Lax' : 'Strict';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $cookie_domain,
    'secure' => (ENVIRONMENT === 'PRODUCCION'),
    'httponly' => true,
    'samesite' => $cookie_samesite
]);
session_start();

if (!isset($conn) || !$conn) {
    die("Conexión fallida: No se pudo conectar a la base de datos.");
}

// Verificar que el usuario esté autenticado en el cambio de contraseña
if (!isset($_SESSION['usuario_cambiar_pwd'])) {
    header('Location: Cambiar_Contraseña.php');
    exit();
}

$usuario = $_SESSION['usuario_cambiar_pwd'];
$error = '';
$exito = '';
$contraseña_actual = '';

// Obtener la contraseña actual
$sql = "SELECT Password FROM login_usuarios WHERE Usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $contraseña_actual = $row['Password'];
}
$stmt->close();

// Procesar el cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_contrasena = $_POST['nueva_contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];
    
    // Validaciones
    if (empty($nueva_contrasena)) {
        $error = 'Por favor ingrese la nueva contraseña.';
    } elseif (empty($confirmar_contrasena)) {
        $error = 'Por favor confirme la nueva contraseña.';
    } elseif ($nueva_contrasena !== $confirmar_contrasena) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,}$/', $nueva_contrasena)) {
        $error = 'La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un carácter especial.';
    } else {
        // Usar el salt según entorno
        $salt = (ENVIRONMENT === 'LOCAL') ? 'GCA2026' : 'GCA_PROD_2026';
        $nueva_contrasena_cifrada = password_hash($nueva_contrasena . $salt, PASSWORD_DEFAULT);
        $sql_update = "UPDATE login_usuarios SET Password = ? WHERE Usuario = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ss", $nueva_contrasena_cifrada, $usuario);
        
        if ($stmt_update->execute()) {
            $_SESSION['mensaje_exito'] = 'Contraseña actualizada correctamente.';
            unset($_SESSION['usuario_cambiar_pwd']);
            header('Location: login.php');
            $stmt->close();
            exit();
        } else {
            $error = 'Error al actualizar la contraseña. Por favor, intente de nuevo.';
        }
        $stmt_update->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actualizar Contraseña</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', Arial, sans-serif;
                background: linear-gradient(135deg, #e0e7ef 0%, #f4f6fa 100%) !important;
            }
            .login-container {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: none;
            }
            .login-card {
                display: flex;
                width: 600px;
                min-height: 420px;
                box-shadow: 0 8px 32px 0 rgba(60,72,100,0.18);
                border-radius: 18px;
                overflow: hidden;
                background: #fff;
                margin: 10px;
                position: relative;
                transition: box-shadow 0.2s;
            }
            .login-card:hover {
                box-shadow: 0 12px 40px 0 rgba(60,72,100,0.22);
            }
            .login-left {
                background: linear-gradient(135deg, #5B8DB8 60%, #23406C 100%);
                width: 180px;
                min-width: 150px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
                color: #fff;
                position: relative;
            }
            .login-left svg {
                width: 54px;
                height: 54px;
                margin-bottom: 8px;
                filter: drop-shadow(0 2px 8px rgba(0,0,0,0.10));
            }
            .login-left .brand-title {
                font-size: 1.1rem;
                font-weight: 700;
                letter-spacing: 1.5px;
                text-align: center;
                margin-top: 4px;
                color: #fff;
                opacity: 0.95;
            }
            .login-right {
                width: 420px;
                padding: 40px 36px 36px 36px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                background: #fff;
            }
            .form-label, .form-header {
                font-weight: 600;
                color: #23406C;
                margin-bottom: 4px;
                font-size: 13px;
            }
            .form-control {
                border-radius: 8px;
                font-size: 15px;
                margin-bottom: 14px;
                width: 100%;
                padding: 7px 12px;
                border: 1.2px solid #c3c8d1;
                background-color: #f8fafc;
                transition: border 0.18s;
            }
            .form-control:focus {
                border-color: #5B8DB8;
                outline: none;
                background: #fff;
            }
            .btn-login, .bg-company {
                background: linear-gradient(90deg, #5B8DB8 60%, #23406C 100%);
                color: #fff;
                border: none;
                border-radius: 6px;
                font-weight: 600;
                font-size: 15px;
                letter-spacing: 0.5px;
                padding: 8px 0;
                box-shadow: 0 2px 12px 0 rgba(76, 138, 163, 0.10);
                transition: transform 0.18s, box-shadow 0.18s;
                width: 100%;
                margin-top: 6px;
                cursor: pointer;
                text-align: center;
                text-decoration: none;
                display: inline-block;
                user-select: none;
            }
            .btn-login:hover, .btn-login:focus, .bg-company:hover, .bg-company:focus {
                background: linear-gradient(90deg, #23406C 60%, #5B8DB8 100%);
                color: #fff;
                transform: scale(1.035);
                box-shadow: 0 6px 24px 0 rgba(76, 138, 163, 0.16);
                outline: 0;
            }
            .btn-login:active, .bg-company:active {
                transform: scale(0.98);
            }
            .btn-actions {
                display: flex;
                gap: 10px;
                margin-top: 10px;
                justify-content: space-between;
            }
            .alert {
                border-radius: 8px;
                margin-bottom: 14px;
                padding: 10px 14px;
                font-size: 14px;
            }
            @media (max-width: 800px) {
                .login-card {
                    flex-direction: column;
                    width: 98vw;
                    min-width: unset;
                }
                .login-left, .login-right {
                    width: 100%;
                    min-width: unset;
                }
                .login-right {
                    padding: 20px 8px 18px 8px;
                }
            }
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
                background: rgba(91, 141, 184, 0.38);
                border-radius: 50%;
                box-shadow: 0 8px 48px 0 rgba(91, 141, 184, 0.22), 0 0 32px 12px rgba(91, 141, 184, 0.13);
                filter: blur(2.5px) brightness(1.18);
                animation: bubbleUp var(--duration) linear infinite;
                animation-delay: var(--delay);
                z-index: 0;
                transition: background 0.3s;
            }
            @keyframes bubbleUp {
                0% {
                    transform: translateY(0) scale(1);
                    opacity: 0.7;
                }
                80% {
                    opacity: 0.5;
                }
                100% {
                    transform: translateY(-110vh) scale(1.1);
                    opacity: 0;
                }
            }
            .login-container, .login-card, .login-left, .login-right {
                position: relative;
                z-index: 1;
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
        <div class="bubble" style="--size:55px; --left:75%; --duration:17s; --delay:6s;"></div>
        <div class="bubble" style="--size:45px; --left:60%; --duration:13s; --delay:1.5s;"></div>
        <div class="bubble" style="--size:65px; --left:25%; --duration:19s; --delay:3.5s;"></div>
    </div>
    <div class="login-container">
        <div class="login-card">
            <div class="login-left">
                <!-- SVG Logo llamativo -->
                <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="32" cy="32" r="30" fill="#fff" fill-opacity="0.13"/>
                    <path d="M32 12L44 52H20L32 12Z" fill="#fff" fill-opacity="0.7"/>
                    <circle cx="32" cy="32" r="12" fill="#5B8DB8" fill-opacity="0.85"/>
                    <circle cx="32" cy="32" r="7" fill="#fff" fill-opacity="0.95"/>
                </svg>
                <div class="brand-title">APP HORAS</div>
            </div>
            <div class="login-right">
                <h5 class="text-center mb-2" style="color: #4c8aa3; font-weight: 600;">Actualizar Contraseña</h5>
                <p class="text-center text-muted mb-3" style="font-size: 0.93rem;">Actualice su contraseña de forma segura</p>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($exito): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Éxito:</strong> <?php echo $exito; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form action="Actualizar_Contraseña.php" method="POST" autocomplete="off">
                    <label class="form-label">Usuario</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario); ?>" disabled>
                    <hr class="my-3">
                    <label for="nueva_contrasena" class="form-label">Nueva Contraseña</label>
                    <div class="input-group mb-2">
                        <input type="password" id="nueva_contrasena" name="nueva_contrasena" class="form-control" placeholder="Ingrese la nueva contraseña" required>
                        <button class="btn btn-outline-secondary btn-toggle" type="button" id="toggleNueva">
                            <i id="iconoNueva" class="fa-regular fa-eye"></i> <span id="textoNueva">Mostrar</span>
                        </button>
                    </div>
                    <label for="confirmar_contrasena" class="form-label">Confirmar Contraseña</label>
                    <div class="input-group mb-3">
                        <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" class="form-control" placeholder="Confirme la nueva contraseña" required>
                        <button class="btn btn-outline-secondary btn-toggle" type="button" id="toggleConfirmar">
                            <i id="iconoConfirmar" class="fa-regular fa-eye"></i> <span id="textoConfirmar">Mostrar</span>
                        </button>
                    </div>
                    <small id="validacion" class="d-block mb-2"></small>
                    <div class="btn-actions">
                        <button type="submit" class="btn btn-login" style="width:48%;min-width:90px;">Actualizar Contraseña</button>
                        <a href="limpiar_sesion.php" class="btn btn-login" style="width:48%;min-width:90px;background:#f8fafc;background-color:#f8fafc;color:#23406C;border:1.2px solid #c3c8d1;box-shadow:none;">Volver al Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Footer removido por solicitud -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle para mostrar/ocultar nueva contraseña
        document.getElementById('toggleNueva').addEventListener('click', function(e) {
            e.preventDefault();
            const input = document.getElementById('nueva_contrasena');
            const icono = document.getElementById('iconoNueva');
            const texto = document.getElementById('textoNueva');
            if (input.type === 'password') {
                input.type = 'text';
                icono.classList.remove('fa-eye');
                icono.classList.add('fa-eye-slash');
                texto.textContent = 'Ocultar';
            } else {
                input.type = 'password';
                icono.classList.remove('fa-eye-slash');
                icono.classList.add('fa-eye');
                texto.textContent = 'Mostrar';
            }
        });
        // Toggle para mostrar/ocultar confirmación de contraseña
        document.getElementById('toggleConfirmar').addEventListener('click', function(e) {
            e.preventDefault();
            const input = document.getElementById('confirmar_contrasena');
            const icono = document.getElementById('iconoConfirmar');
            const texto = document.getElementById('textoConfirmar');
            if (input.type === 'password') {
                input.type = 'text';
                icono.classList.remove('fa-eye');
                icono.classList.add('fa-eye-slash');
                texto.textContent = 'Ocultar';
            } else {
                input.type = 'password';
                icono.classList.remove('fa-eye-slash');
                icono.classList.add('fa-eye');
                texto.textContent = 'Mostrar';
            }
        });
        // Validación en tiempo real
        const nuevaInput = document.getElementById('nueva_contrasena');
        const confirmarInput = document.getElementById('confirmar_contrasena');
        const validacionSpan = document.getElementById('validacion');
        function validarContraseñas() {
            if (nuevaInput.value && confirmarInput.value) {
                if (nuevaInput.value === confirmarInput.value) {
                    validacionSpan.innerHTML = '<span style="color: #28a745;">✓ Las contraseñas coinciden</span>';
                } else {
                    validacionSpan.innerHTML = '<span style="color: #dc3545;">✗ Las contraseñas no coinciden</span>';
                }
            } else {
                validacionSpan.innerHTML = '';
            }
        }
        nuevaInput.addEventListener('input', validarContraseñas);
        confirmarInput.addEventListener('input', validarContraseñas);
    </script>
</body>
</html>
