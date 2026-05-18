<?php
// --- INICIO: Manejo de sesión y errores seguro ---

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/header.php';
// Incluye la configuración centralizada para compatibilidad de entorno
require_once 'include.php'; // crea $conn

function h($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// --- LÓGICA PREVIA ---

// Cargar empleados para el dropdown
$empleados_result = $conn->query("SELECT id, nom, prenom FROM empleados ORDER BY nom ASC");

$cliente = $linea_negocio = $monto_contrato = $nature_imputation = $probabilidad = $notas = $Area = '';
$nombre_proyecto = $descripcion = $fecha_inicio = $fecha_fin_estimada = '';
$centro_costos = $id_director = null;
$id = 0;
$edit_state = false;

// --- LÓGICA PARA PROCESAR EL FORMULARIO ---

// GUARDAR / INSERTAR
if (isset($_POST['action']) && $_POST['action'] === 'save') {
    $nombre_proyecto = $_POST['nombre_proyecto'];
    $descripcion = $_POST['descripcion'];
    $centro_costos = $_POST['centro_costos'];
    $id_director = $_POST['id_director'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin_estimada = $_POST['fecha_fin_estimada'];
    $cliente = $_POST['cliente'] ?? '';
    $linea_negocio = $_POST['linea_negocio'] ?? '';
    $monto_contrato = $_POST['monto_contrato'] ?? '';
    $nature_imputation = $_POST['nature_imputation'] ?? '';
    $probabilidad = $_POST['probabilidad'] ?? '';
    $notas = $_POST['notas'] ?? '';
    $Area = $_POST['Area'] ?? '';
    // Si viene un id (edición) tratar como UPDATE para evitar duplicados si el usuario pulsa "Guardar"
    $post_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($post_id === 0 && isset($_SESSION['editing_proyecto_id'])) {
        $post_id = intval($_SESSION['editing_proyecto_id']);
    }
    if ($post_id > 0) {
        $query = "UPDATE proyectos SET nombre_proyecto=?, descripcion=?, centro_costos=?, id_director=?, fecha_inicio=?, fecha_fin_estimada=?, cliente=?, linea_negocio=?, monto_contrato=?, nature_imputation=?, probabilidad=?, notas=?, Area=? WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssiisssssssssi', $nombre_proyecto, $descripcion, $centro_costos, $id_director, $fecha_inicio, $fecha_fin_estimada, $cliente, $linea_negocio, $monto_contrato, $nature_imputation, $probabilidad, $notas, $Area, $post_id);
        $stmt->execute();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Registro actualizado correctamente.'];
    // Limpiar id de edición en sesión
    unset($_SESSION['editing_proyecto_id']);
    } else {
        $query = "INSERT INTO proyectos (nombre_proyecto, descripcion, centro_costos, id_director, fecha_inicio, fecha_fin_estimada, cliente, linea_negocio, monto_contrato, nature_imputation, probabilidad, notas, Area) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssiisssssssss', $nombre_proyecto, $descripcion, $centro_costos, $id_director, $fecha_inicio, $fecha_fin_estimada, $cliente, $linea_negocio, $monto_contrato, $nature_imputation, $probabilidad, $notas, $Area);
        $stmt->execute();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Registro creado correctamente.'];
    unset($_SESSION['editing_proyecto_id']);
    }
    
    header('location: proyectos.php');
    exit();
}

// ACTUALIZAR / UPDATE
if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = $_POST['id'];
    $nombre_proyecto = $_POST['nombre_proyecto'];
    $descripcion = $_POST['descripcion'];
    $centro_costos = $_POST['centro_costos'];
    $id_director = $_POST['id_director'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin_estimada = $_POST['fecha_fin_estimada'];
    $cliente = $_POST['cliente'] ?? '';
    $linea_negocio = $_POST['linea_negocio'] ?? '';
    $monto_contrato = $_POST['monto_contrato'] ?? '';
    $nature_imputation = $_POST['nature_imputation'] ?? '';
    $probabilidad = $_POST['probabilidad'] ?? '';
    $notas = $_POST['notas'] ?? '';
    $Area = $_POST['Area'] ?? '';

    $query = "UPDATE proyectos SET nombre_proyecto=?, descripcion=?, centro_costos=?, id_director=?, fecha_inicio=?, fecha_fin_estimada=?, cliente=?, linea_negocio=?, monto_contrato=?, nature_imputation=?, probabilidad=?, notas=?, Area=? WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssiisssssssssi', $nombre_proyecto, $descripcion, $centro_costos, $id_director, $fecha_inicio, $fecha_fin_estimada, $cliente, $linea_negocio, $monto_contrato, $nature_imputation, $probabilidad, $notas, $Area, $id);
    $stmt->execute();

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Registro actualizado correctamente.'];
    unset($_SESSION['editing_proyecto_id']);
    header('location: proyectos.php');
    exit();
}

// ELIMINAR / DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM proyectos WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Registro eliminado correctamente.'];
    unset($_SESSION['editing_proyecto_id']);
    header('location: proyectos.php');
    exit();
}

// --- LÓGICA PARA LLENAR EL FORMULARIO (EDICIÓN) ---
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $edit_state = true;
    $query = "SELECT * FROM proyectos WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();

    $nombre_proyecto = $record['nombre_proyecto'];
    $descripcion = $record['descripcion'];
    $centro_costos = $record['centro_costos'];
    $id_director = $record['id_director'];
    $fecha_inicio = $record['fecha_inicio'];
    $fecha_fin_estimada = $record['fecha_fin_estimada'];
    $cliente = $record['cliente'] ?? '';
    $linea_negocio = $record['linea_negocio'] ?? '';
    $monto_contrato = $record['monto_contrato'] ?? '';
    $nature_imputation = $record['nature_imputation'] ?? '';
    $probabilidad = $record['probabilidad'] ?? '';
    $notas = $record['notas'] ?? '';
    $Area = $record['Area'] ?? '';
    // Guardar en sesión el id que está en edición para proteger contra pérdida del hidden id
    $_SESSION['editing_proyecto_id'] = intval($id);
}

?>

<div class="page-header">
    <h2>Gestión de Proyectos</h2>
</div>

<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo h($_SESSION['flash']['type']); ?> alert-dismissible fade show" role="alert">
        <?php echo h($_SESSION['flash']['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<form method="post" action="proyectos.php" class="data-form primary-form">
    <input type="hidden" name="id" value="<?php echo h($id); ?>">
    
    <fieldset class="form-section">
        <legend>Información del Proyecto</legend>
        <div class="project-form-grid">
            <div class="project-field project-field-wide">
                <label class="field-label" for="nombre_proyecto">Nombre del Proyecto</label>
                <input id="nombre_proyecto" aria-label="Nombre del Proyecto" type="text" name="nombre_proyecto" value="<?php echo h($nombre_proyecto); ?>" required>
            </div>
            <div class="project-field">
                <label class="field-label" for="centro_costos">Centro de Costos</label>
                <input id="centro_costos" aria-label="Centro de Costos" type="number" name="centro_costos" value="<?php echo h($centro_costos); ?>" required>
            </div>
            <div class="project-field">
                <label class="field-label" for="id_director">Director de Proyecto</label>
                <select id="id_director" aria-label="Director de Proyecto" name="id_director" required>
                    <option value="">-- Seleccione un director --</option>
                    <?php while ($empleado = $empleados_result->fetch_assoc()): ?>
                        <option value="<?php echo $empleado['id']; ?>" <?php if ($id_director == $empleado['id']) echo 'selected'; ?>>
                            <?php echo h(($empleado['prenom'] ?? '') . ' ' . ($empleado['nom'] ?? '')); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="project-field">
                <label class="field-label" for="cliente">Cliente</label>
                <input id="cliente" aria-label="Cliente" type="text" name="cliente" value="<?php echo h($cliente); ?>">
            </div>
            <div class="project-field">
                <label class="field-label" for="linea_negocio">Línea de Negocio</label>
                <input id="linea_negocio" aria-label="Línea de Negocio" type="text" name="linea_negocio" value="<?php echo h($linea_negocio); ?>">
            </div>
            <div class="project-field">
                <label class="field-label" for="monto_contrato">Monto Contrato</label>
                <input id="monto_contrato" aria-label="Monto Contrato" type="number" step="0.01" name="monto_contrato" value="<?php echo h($monto_contrato); ?>">
            </div>
            <div class="project-field">
                <label class="field-label" for="fecha_inicio">Fecha de Inicio</label>
                <input id="fecha_inicio" aria-label="Fecha de Inicio" type="date" name="fecha_inicio" value="<?php echo h($fecha_inicio); ?>" required>
            </div>
            <div class="project-field">
                <label class="field-label" for="fecha_fin_estimada">Fecha Fin Estimada</label>
                <input id="fecha_fin_estimada" aria-label="Fecha Fin Estimada" type="date" name="fecha_fin_estimada" value="<?php echo h($fecha_fin_estimada); ?>" required>
            </div>
            <div class="project-field">
                <label class="field-label" for="nature_imputation">Nature Imputation</label>
                <input id="nature_imputation" aria-label="Nature Imputation" type="text" name="nature_imputation" value="<?php echo h($nature_imputation); ?>">
            </div>
            <div class="project-field">
                <label class="field-label" for="probabilidad">Probabilidad</label>
                <input id="probabilidad" aria-label="Probabilidad" type="text" name="probabilidad" value="<?php echo h($probabilidad); ?>">
            </div>
            <div class="project-field">
                <label class="field-label" for="Area">Area</label>
                <input id="Area" aria-label="Area" type="text" name="Area" value="<?php echo h($Area); ?>">
            </div>
            <div class="project-field project-field-full">
                <label class="field-label" for="descripcion">Descripción</label>
                <textarea id="descripcion" aria-label="Descripción" name="descripcion"><?php echo h($descripcion); ?></textarea>
            </div>
            <div class="project-field project-field-full">
                <label class="field-label" for="notas">Notas</label>
                <textarea id="notas" aria-label="Notas" name="notas"><?php echo h($notas); ?></textarea>
            </div>
        </div>
    </fieldset>

    <div class="form-actions">
            <?php if ($edit_state == false): ?>
                <button id="btn-save" type="submit" name="action" value="save" class="btn btn-primary">Guardar Proyecto</button>
            <?php else: ?>
                <button type="submit" name="action" value="update" class="btn btn-primary">Actualizar Proyecto</button>
            <?php endif ?>
    </div>
</form>

<hr>

<h3>Listado de Proyectos</h3>
<div class="table-wrapper">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nombre del Proyecto</th>
                <th>Descripción</th>
                <th>Centro de Costos</th>
                <th>ID Director</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Cliente</th>
                <th>Línea de Negocio</th>
                <th>Monto Contrato</th>
                <th>Nature Imputation</th>
                <th>Probabilidad</th>
                <th>Notas</th>
                <th>Area</th>
                <th>Director de Proyecto</th>
                <th colspan="2">Acción</th>
            </tr>
            <tr class="filters-row">
                <th><input type="text" class="col-filter" placeholder="Buscar Nombre..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Descripción..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Centro..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar ID Director..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Fecha Inicio..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Fecha Fin..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Cliente..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Línea..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Monto..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Nature..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Probabilidad..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Notas..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Area..."></th>
                <th><input type="text" class="col-filter" placeholder="Buscar Director..."></th>
                <th colspan="2"></th>
            </tr>
        </thead>
        <tbody>
        <?php 
        // Usamos un JOIN para obtener el nombre del director del proyecto
        $query = "SELECT p.*, e.nom, e.prenom FROM proyectos p LEFT JOIN empleados e ON p.id_director = e.id ORDER BY p.id DESC";
        $results = $conn->query($query);
        while ($row = $results->fetch_assoc()) { 
            // Director name
            $director_name = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));
        ?>
            <tr>
                <td><?php echo h($row['nombre_proyecto'] ?? ''); ?></td>
                <td><?php echo h($row['descripcion'] ?? ''); ?></td>
                <td><?php echo h($row['centro_costos'] ?? ''); ?></td>
                <td><?php echo h($row['id_director'] ?? ''); ?></td>
                <td><?php echo h($row['fecha_inicio'] ?? ''); ?></td>
                <td><?php echo h($row['fecha_fin_estimada'] ?? ''); ?></td>
                <td><?php echo h($row['cliente'] ?? ''); ?></td>
                <td><?php echo h($row['linea_negocio'] ?? ''); ?></td>
                <td><?php echo h($row['monto_contrato'] ?? ''); ?></td>
                <td><?php echo h($row['nature_imputation'] ?? ''); ?></td>
                <td><?php echo h($row['probabilidad'] ?? ''); ?></td>
                <td><?php echo h($row['notas'] ?? ''); ?></td>
                <td><?php echo h($row['Area'] ?? ''); ?></td>
                <td><?php echo h($director_name); ?></td>
                <td>
                    <a href="proyectos.php?edit=<?php echo $row['id']; ?>" class="btn btn-info">Editar</a>
                </td>
                <td>
                    <a href="proyectos.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar este registro?');">Eliminar</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<?php
require_once 'includes/footer.php';
?>

<style>
    .table-wrapper {
        margin-top: 24px;
        overflow-x: auto;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }
    .data-table {
        width: 100%;
        min-width: 1180px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }
    .data-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8fafc;
        color: #0f172a;
        font-weight: 700;
        text-align: left;
        border-bottom: 1px solid #dbe3ee;
    }
    .data-table th,
    .data-table td {
        padding: 10px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #edf2f7;
        white-space: normal;
    }
    .data-table tbody tr:nth-child(even) {
        background: #fcfdff;
    }
    .data-table tbody tr:hover {
        background: #f4f8fc;
    }
    .data-table tbody td {
        color: #334155;
        line-height: 1.35;
    }
    .data-table a.btn {
        padding: 5px 10px;
        font-size: 12px;
        border-radius: 7px;
        white-space: nowrap;
    }
    .data-table td:nth-child(2),
    .data-table td:nth-child(12) {
        min-width: 180px;
        max-width: 240px;
    }
    .data-table td:nth-child(1),
    .data-table td:nth-child(7),
    .data-table td:nth-child(8),
    .data-table td:nth-child(13),
    .data-table td:nth-child(14) {
        min-width: 120px;
    }
    .filters-row input.col-filter {
        width: 100%;
        box-sizing: border-box;
        padding: 7px 9px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background: #fff;
        font-size: 12px;
    }
    .filters-row th {
        background: #f1f5f9;
        padding-top: 8px;
        padding-bottom: 8px;
    }
    .form-section {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }
    .form-section legend {
        font-size: 28px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
    }
    .project-form-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        align-items: start;
    }
    .project-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .project-field-wide {
        grid-column: span 2;
    }
    .project-field-full {
        grid-column: 1 / -1;
    }
    .field-label {
        color: #1e293b;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.2;
    }
    .project-field input,
    .project-field select,
    .project-field textarea {
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        box-sizing: border-box;
        background: #fff;
        color: #0f172a;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .project-field textarea {
        min-height: 110px;
        resize: vertical;
    }
    .project-field input:focus,
    .project-field select:focus,
    .project-field textarea:focus,
    .filters-row input.col-filter:focus {
        outline: none;
        border-color: #4c8aa3;
        box-shadow: 0 0 0 3px rgba(76, 138, 163, 0.15);
    }
    .form-actions {
        margin-top: 18px;
        display: flex;
        justify-content: flex-end;
    }
    @media (max-width: 992px) {
        .project-form-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .project-field-wide {
            grid-column: span 2;
        }
        .data-table {
            min-width: 1200px;
        }
        .table-wrapper {
            border-radius: 12px;
        }
    }
    @media (max-width: 640px) {
        .form-section {
            padding: 16px;
        }
        .project-form-grid {
            grid-template-columns: 1fr;
            gap: 14px;
        }
        .project-field-wide,
        .project-field-full {
            grid-column: auto;
        }
        .form-actions {
            justify-content: stretch;
        }
        .form-actions .btn {
            width: 100%;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filters = document.querySelectorAll('.col-filter');
    const table = document.querySelector('.data-table');
    const tbody = table.querySelector('tbody');

    function normalize(text) {
        return text.trim().toLowerCase();
    }

    function applyFilters() {
        const values = Array.from(filters).map(f => normalize(f.value));
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const dataCols = values.length; // number of data columns to check

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let visible = true;

            for (let i = 0; i < dataCols; i++) {
                const filterVal = values[i];
                if (!filterVal) continue;
                const cell = cells[i];
                if (!cell) { visible = false; break; }
                const cellText = normalize(cell.textContent || cell.innerText || '');
                if (cellText.indexOf(filterVal) === -1) {
                    visible = false;
                    break;
                }
            }

            row.style.display = visible ? '' : 'none';
        });
    }

    // Debounce helper
    function debounce(fn, delay) {
        let timer = null;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    const applyFiltersDebounced = debounce(applyFilters, 250);
    filters.forEach(f => {
        f.addEventListener('input', applyFiltersDebounced);
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var idField = document.querySelector('input[name="id"]');
    var btnSave = document.getElementById('btn-save');
    if (idField && btnSave) {
        var idVal = idField.value ? parseInt(idField.value) : 0;
        if (idVal > 0) {
            btnSave.style.display = 'none';
        }
    }
});
</script>

<script>
// Prevenir envíos dobles: deshabilitar botones submit al enviar el formulario
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form.data-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        var submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submits.forEach(function(s) { s.disabled = true; });
        // opcional: mostrar un indicador
    });
});
</script>
