<?php
// modal_Detalle_Empleado_Director.php
// Muestra el detalle de las imputaciones de un empleado específico para aprobación del director


header('Content-Type: text/html; charset=utf-8');
// Incluye la configuración centralizada para compatibilidad de entorno
require_once __DIR__ . '/include.php'; // crea $conn

// Obtener parámetros
$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
$apellido = isset($_GET['apellido']) ? trim($_GET['apellido']) : '';
$codigo_affaire = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
$area_funcional = isset($_GET['area']) ? trim($_GET['area']) : '';

if (empty($nombre) || empty($apellido) || empty($codigo_affaire)) {
    echo '<div class="alert alert-danger m-3">Parámetros incompletos.</div>';
    exit;
}

// Primero obtener el numero_empleado desde app_reporte_inputhh

$sql_empleado = "SELECT numero_empleado FROM horas_dia WHERE Nom = ? AND Prenom = ? AND codigo_affaire = ? LIMIT 1";
$stmt_emp = $conn->prepare($sql_empleado);
if (!$stmt_emp) {
    echo '<div class="alert alert-danger m-3">Error al preparar consulta de empleado.</div>';
    exit;
}

$stmt_emp->bind_param('sss', $nombre, $apellido, $codigo_affaire);
$stmt_emp->execute();
$result_emp = $stmt_emp->get_result();

if ($result_emp->num_rows === 0) {
    echo '<div class="alert alert-warning m-3">No se encontró el número de empleado.</div>';
    $stmt_emp->close();
    exit;
}

$row_emp = $result_emp->fetch_assoc();
$numero_de_empleado = $row_emp['numero_empleado'];
$stmt_emp->close();

// Ahora consultar la tabla horas_dia

$sql = "SELECT 
    numero_empleado,
    Nom AS nom,
    Prenom AS prenom,
    area_funcional,
    codigo_affaire,
    nombre_affaire AS nombre_proyect_forza,
    fecha,
    tiempo_imputado_horas,
    tiempo_imputado_costo,
    comentario
FROM horas_dia
WHERE codigo_affaire = ? AND numero_empleado = ? AND Estado_Aprobacion = 'Aprobado En Curso'
ORDER BY fecha DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo '<div class="alert alert-danger m-3">Error en la consulta de detalle.</div>';
    exit;
}

$stmt->bind_param('ss', $codigo_affaire, $numero_de_empleado);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning m-3">No se encontraron registros de detalle para este empleado.</div>';
    $stmt->close();
    exit;
}
?>

<div class="container-fluid p-3">
    <div class="row">
        <div class="col-12">
            <h6 class="mb-3" style="color: #4C8AA3;">Detalle de Imputaciones por Día</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm align-middle mb-0">
                    <thead class="table-light text-center">
                        <tr>
                            <th>Nº Empleado</th>
                            <th>Nombre Colaborador</th>
                            <th>Proyecto</th>
                            <th>Fecha</th>
                            <th>Horas Imputadas</th>
                            <th>Costo Imputado</th>
                            <th>Comentario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_horas = 0;
                        $total_costo = 0;
                        while ($row = $result->fetch_assoc()): 
                            $horas = is_numeric($row['tiempo_imputado_horas']) ? (float)$row['tiempo_imputado_horas'] : 0;
                            $costo = is_numeric($row['tiempo_imputado_costo']) ? (float)$row['tiempo_imputado_costo'] : 0;
                            $total_horas += $horas;
                            $total_costo += $costo;
                            
                            // Concatenar nombre y apellido
                            $nombre_completo = trim(($row['nom'] ?? '') . ' ' . ($row['prenom'] ?? ''));
                        ?>
                        <tr>
                            <td class="text-center"><?= htmlspecialchars($row['numero_empleado'] ?? '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($nombre_completo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['nombre_proyect_forza'] ?? '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            <td class="text-center">
                                <?php 
                                $fecha = $row['fecha'] ?? '';
                                if (!empty($fecha)) {
                                    echo date('d/m/Y', strtotime($fecha));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td class="text-center text-primary fw-semibold"><?= number_format($horas, 2, ',', '.') ?></td>
                            <td class="text-end text-success fw-semibold">$ <?= number_format($costo, 0, '', '.') ?></td>
                            <td><?= htmlspecialchars($row['comentario'] ?? '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <tr class="table-secondary fw-bold">
                            <td colspan="4" class="text-end">TOTALES:</td>
                            <td class="text-center text-primary"><?= number_format($total_horas, 2, ',', '.') ?></td>
                            <td class="text-end text-success">$ <?= number_format($total_costo, 0, '', '.') ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
?>
