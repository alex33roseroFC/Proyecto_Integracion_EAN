<?php
// Script para obtener el mes activo en proceso de aprobación
global $conn;
$mes_activo = null;
$sql_mes_activo = "SELECT anio, numero_mes, fecha FROM Mes_activo WHERE estado = 'APROBACION EN CURSO' ORDER BY anio DESC, numero_mes DESC LIMIT 1";
$res_mes = $conn->query($sql_mes_activo);
if ($res_mes && $res_mes->num_rows > 0) {
    $mes_activo = $res_mes->fetch_assoc();
}
?>
