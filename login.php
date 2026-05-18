<?php

require_once 'include.php';
require_once 'config.php';

// Detectar entorno y ajustar dominio de cookie
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

// $conn ya está definido en include.php según el entorno
if (!isset($conn) || !$conn) {
  die("Conexión fallida: No se pudo conectar a la base de datos.");
}

$error = '';
$mensaje_exito = '';

// Verificar si hay mensaje de éxito de cambio de contraseña
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];
    $sql = "SELECT * FROM login_usuarios WHERE Usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
      $row = $result->fetch_assoc();
      // Usar el mismo salt que en cifrar_passwords.php
      $salt = (ENVIRONMENT === 'LOCAL') ? 'GCA2026' : 'GCA_PROD_2026';
      $hash = $row['Password'];
      // Detectar si el password almacenado es un hash (bcrypt, argon2, etc)
      $es_hash = (strpos($hash, '$2y$') === 0 || strpos($hash, '$argon2') === 0);
      if ($es_hash) {
        $contrasena_valida = password_verify($contrasena . $salt, $hash);
      } else {
        $contrasena_valida = ($contrasena === $hash);
      }
      if ($contrasena_valida) {
        $_SESSION['usuario'] = $row['Usuario'];
        $_SESSION['matricula'] = isset($row['Matricula']) ? $row['Matricula'] : null;
        $_SESSION['loggedin'] = true;
        $_SESSION['rol'] = $row['ROL'];
        // Redirección inmediata según ROL
        if (isset($row['ROL']) && strtoupper(trim($row['ROL'])) === 'COORD') {
          header('Location: Balance.php');
        } elseif (isset($row['ROL']) && strtoupper(trim($row['ROL'])) === 'SUPER') {
          header('Location: puente1.php');
        } elseif (isset($row['ROL']) && strtoupper(trim($row['ROL'])) === 'DIR') {
          header('Location: Proyectos_Cargados.php');
        } elseif (isset($row['ROL']) && strtoupper(trim($row['ROL'])) === 'MIX2') {
          header('Location: puente2.php');
        } elseif (isset($row['ROL']) && strtoupper(trim($row['ROL'])) === 'MIX') {
          header('Location: Balance.php');
        } elseif (isset($row['ROL']) && stripos($row['ROL'], 'MIX') !== false) {
          header('Location: mix.php');
        } elseif (isset($row['ROL']) && stripos($row['ROL'], 'ADMIN') !== false) {
          header('Location: puente.php');
        } elseif (isset($row['ROL']) && stripos($row['ROL'], 'Coordinador') !== false) {
          header('Location: Coordinador.php');
        } elseif (isset($row['ROL']) && strtoupper(trim($row['ROL'])) === 'USER') {
          header('Location: Colaborador.php');
        } else {
          header('Location: Index2.php');
        }
        $stmt->close();
        $conn->close();
        exit();
      } else {
        $error = 'Usuario o contraseña incorrectos.';
        $stmt->close();
        $conn->close();
      }
    } else {
      $error = 'Usuario o contraseña incorrectos.';
      $stmt->close();
      $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <title>Login</title>
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- <link rel="icon" type="image/x-icon" href="Incono.ico"> -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="css/login-styles.css">
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
    .form-label {
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
    .btn-login {
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
    .btn-login:hover, .btn-login:focus {
      background: linear-gradient(90deg, #23406C 60%, #5B8DB8 100%);
      color: #fff;
      transform: scale(1.035);
      box-shadow: 0 6px 24px 0 rgba(76, 138, 163, 0.16);
      outline: 0;
    }
    .btn-login:active {
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
        <!-- Logo container eliminado -->
        <div style="height:8px;"></div>
        <?php if ($mensaje_exito): ?>
          <div class="alert alert-success alert-dismissible fade show text-center" role="alert">
            <strong>¡Éxito!</strong>
            <p style="margin-top: 8px; margin-bottom: 0;">
              <?php echo $mensaje_exito; ?>
            </p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger text-center" role="alert">
            <?php echo $error; ?>
          </div>
        <?php endif; ?>
        <form action="login.php" method="POST" autocomplete="off">
          <label for="usuario" class="form-label">
            <i class="bi bi-person-circle" style="font-size: 1.1em; margin-right: 5px; color:#5B8DB8;"></i>
            Usuario
          </label>
          <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Usuario" required autofocus>
          <label for="contrasena" class="form-label">
            <i class="bi bi-lock-fill" style="font-size: 1.1em; margin-right: 5px; color:#5B8DB8;"></i>
            Contraseña
          </label>
          <input type="password" id="contrasena" name="contrasena" class="form-control" placeholder="Contraseña" required>
          <div class="btn-actions">
            <button type="submit" class="btn btn-login" style="width:48%;min-width:90px;">Ingresar</button>
            <a href="Cambiar_Contraseña.php" class="btn btn-login" style="width:48%;min-width:90px;background:#f8fafc;background-color:#f8fafc;color:#23406C;border:1.2px solid #c3c8d1;box-shadow:none;">Cambiar contraseña</a>
          </div>
        </form>
      </div>
    </div>
  </div>
  <footer class="text-center mt-3" style="width:100%;position:fixed;bottom:12px;left:0;z-index:999;">
    <div class="small text-muted" style="font-size:0.875rem;color:#6c757d;"></div>
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Script de compatibilidad para navegadores antiguos
    (function() {
      'use strict';
      
      // Detectar y forzar estilos si es necesario
      if (window.addEventListener) {
        window.addEventListener('load', function() {
          // Verificar que los elementos tengan los estilos correctos
          var loginCard = document.querySelector('.login-card');
          var loginContainer = document.querySelector('.login-container');
          
          if (loginCard && window.getComputedStyle) {
            var cardDisplay = window.getComputedStyle(loginCard).display;
            if (cardDisplay === 'block' || cardDisplay === 'inline') {
              // Forzar flexbox si no se está aplicando
              loginCard.style.display = 'flex';
            }
          }
          
          if (loginContainer && window.getComputedStyle) {
            var containerDisplay = window.getComputedStyle(loginContainer).display;
            if (containerDisplay === 'block' || containerDisplay === 'inline') {
              loginContainer.style.display = 'flex';
            }
          }
          
          // Asegurar que los inputs y botones se muestren correctamente
          var formControls = document.querySelectorAll('.form-control');
          for (var i = 0; i < formControls.length; i++) {
            formControls[i].style.width = '100%';
          }
          
          // Asegurar que los botones tengan el cursor pointer
          var buttons = document.querySelectorAll('.btn-login');
          for (var j = 0; j < buttons.length; j++) {
            buttons[j].style.cursor = 'pointer';
          }
        });
      }
    })();
  </script>
</body>
</html>