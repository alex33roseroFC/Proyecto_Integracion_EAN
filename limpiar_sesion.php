<?php
session_start();
// Limpiar la sesión de cambio de contraseña
unset($_SESSION['usuario_cambiar_pwd']);
// Redireccionar a login
header('Location: login.php');
exit();
