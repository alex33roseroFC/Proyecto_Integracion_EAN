<?php
// obtener_presupuesto_compartido.php

header('Content-Type: text/html; charset=utf-8');
// Incluye la configuración centralizada para compatibilidad de entorno
require_once __DIR__ . '/include.php'; // crea $conn
if (!$conn || $conn->connect_error) {
    die("Error conexión: " . ($conn ? $conn->connect_error : 'No se pudo establecer la conexión.'));
}
$conn->set_charset('utf8mb4');
$centro_costo = isset($_GET['centro_costo']) ? $conn->real_escape_string($_GET['centro_costo']) : '';
$sql = "SELECT id, Centro_Costo, Area_Funcional, Monto_Prestado, Area_Funcional_Seleccionada FROM compartir_presupuesto WHERE Centro_Costo = '" . $centro_costo . "' ORDER BY id DESC";
$res = $conn->query($sql);
$total_monto = 0;
echo '<table class="table table-bordered table-hover table-sm align-middle" style="border-radius:0.7rem;overflow:hidden;">';
echo '<thead class="table-primary" style="background-color: #A6C2C9 !important;"><tr>';
echo '<th style="background-color: #A6C2C9 !important; color: #fff;">Área Funcional</th>';
echo '<th style="background-color: #A6C2C9 !important; color: #fff;">Área Funcional Destino</th>';
echo '<th style="background-color: #A6C2C9 !important; color: #fff;">Monto Cedido</th>';
echo '<th style="min-width:90px; background-color: #A6C2C9 !important; color: #fff;">Acción</th>';
echo '</tr></thead><tbody>';
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $total_monto += (float)$row['Monto_Prestado'];
        echo '<tr data-id="' . (int)$row['id'] . '" >' ;
        echo '<td class="td-area_funcional">' . htmlspecialchars($row['Area_Funcional']) . '</td>';
        echo '<td class="td-area_funcional_sel">' . htmlspecialchars($row['Area_Funcional_Seleccionada']) . '</td>';
        echo '<td class="td-monto_prestado">$ ' . number_format((float)$row['Monto_Prestado'], 0, '', '.') . '</td>';
        echo '<td class="text-center">';
        echo '<button class="btn btn-outline-primary btn-sm btn-editar me-1" title="Editar"><i class="bi bi-pencil-square" style="font-size:1.2rem;"></i></button>';
        echo '<button class="btn btn-outline-danger btn-sm btn-eliminar" title="Eliminar"><i class="bi bi-trash-fill" style="font-size:1.2rem;"></i></button>';
        echo '</td>';
        echo '</tr>';
    }
    // Fila de totales
    echo '<tr style="background-color: #A6C2C9; font-weight: 700;">';
    echo '<td colspan="2" style="text-align: right; padding: 12px; color: #000;">TOTAL:</td>';
    echo '<td style="color: #000;">$ ' . number_format($total_monto, 0, '', '.') . '</td>';
    echo '<td></td>';
    echo '</tr>';
} else {
    echo '<tr><td colspan="4" class="text-center">Sin registros</td></tr>';
}
echo '</tbody></table>';
$conn->close();
?>
