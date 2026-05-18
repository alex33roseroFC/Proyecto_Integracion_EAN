<?php
// update_centro_costos.php

session_start();
// Incluye la configuración centralizada para compatibilidad de entorno
require_once 'include.php'; // crea $conn

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "No autorizado"]);
    exit;
}

// Only allow POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
    exit;
}

$pk = isset($_POST['pk']) ? trim($_POST['pk']) : '';
$centro_costos = isset($_POST['centro_costos']) ? trim($_POST['centro_costos']) : '';

if ($pk === '' || $centro_costos === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Datos incompletos"]);
    exit;
}

// Detectar el nombre de la columna PK
$primary_key_column = '';
$sql_columns = "SELECT * FROM asignación LIMIT 1";
if ($result = $conn->query($sql_columns)) {
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        if ($field->flags & MYSQLI_PRI_KEY_FLAG) {
            $primary_key_column = $field->name;
            break;
        }
    }
    $result->free();
}
if ($primary_key_column === '') {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "No se detectó PK"]);
    exit;
}

$sql = "UPDATE asignación SET centro_costos = ? WHERE `$primary_key_column` = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('ss', $centro_costos, $pk);
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $conn->error]);
}
