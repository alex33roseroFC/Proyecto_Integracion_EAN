<?php
// Diagnostic: list columns of `proyectos` table to help debug "Unknown column 'p.costo_asignado'" errors.
// Usage: place this file on your server and open it in a browser (or run `php -f check_proyectos_columns.php`).
// It uses the same DB constants from includes/db_connection.php

require_once __DIR__ . '/include.php';
require_once __DIR__ . '/config.php';

// $conn ya está definido en config.php
if (!isset($conn) || !$conn) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: No se pudo establecer la conexión a la base de datos.\n";
    exit(1);
}

header('Content-Type: text/plain; charset=utf-8');

echo "DESCRIBE proyectos:\n\n";

$res = $conn->query("DESCRIBE proyectos");
if (!$res) {
    echo "ERROR running DESCRIBE proyectos: " . $conn->error . "\n";
    exit(1);
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

foreach ($rows as $row) {
    echo sprintf("%-30s %-20s %-10s %-10s %-10s %s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default'] === null ? 'NULL' : $row['Default'], $row['Extra']);
}

echo "\nIf you see a column named 'costo_asignado' here, reply with that — otherwise paste the output so I can update the query.";

$conn->close();
