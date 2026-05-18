<?php
// --- CONEXIÓN Y SESIÓN ---
require_once 'include.php';
require_once 'config.php';
session_start();
// --- ENDPOINT PARA OBTENER CENTRO DE COSTOS POR NOMBRE DE PROYECTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'get_centro_costos') {
    $nombre_proyecto = trim($_POST['nombre_proyecto'] ?? '');
    $sql = "SELECT centro_costos FROM proyectos WHERE LOWER(TRIM(nombre_proyecto)) = LOWER(TRIM(?)) LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('s', $nombre_proyecto);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                echo json_encode(['success'=>true, 'centro_costos'=>$row['centro_costos']]);
                $res->free();
                $stmt->close();
                exit;
            }
            $res->free();
        }
        $stmt->close();
    }
    echo json_encode(['success'=>false]);
    exit;
}


// --- CRUD para avance_pdt_proyecto (tab1) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax'];
    // CRUD para avance_pdt_proyecto (tab1)
    if ($action === 'guardar') {
        $id = isset($_POST['registro_id']) && $_POST['registro_id'] !== '' ? intval($_POST['registro_id']) : null;
        $proyecto = $conn->real_escape_string($_POST['proyecto']);
        $nombre_proyecto = $conn->real_escape_string($_POST['nombre_proyecto']);
        $porc_ejec = intval($_POST['porcentaje_ejecutado']);
        $porc_prog = intval($_POST['porcentaje_programado']);
        if ($id) {
            $sql = "UPDATE avance_pdt_proyecto SET proyecto='$proyecto', nombre_proyecto='$nombre_proyecto', porcentaje_avance_fisico_ejecutado=$porc_ejec, porcentaje_avance_fisico_programado=$porc_prog WHERE id=$id";
            $conn->query($sql);
        } else {
            $sql = "INSERT INTO avance_pdt_proyecto (proyecto, nombre_proyecto, porcentaje_avance_fisico_ejecutado, porcentaje_avance_fisico_programado) VALUES ('$proyecto', '$nombre_proyecto', $porc_ejec, $porc_prog)";
            $conn->query($sql);
        }
        echo json_encode(['success'=>true]);
        exit;
    }
    if ($action === 'eliminar' && isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);
        $sql = "DELETE FROM avance_pdt_proyecto WHERE id=$id";
        $conn->query($sql);
        echo json_encode(['success'=>true]);
        exit;
    }
    if ($action === 'editar' && isset($_POST['edit_id'])) {
        $id = intval($_POST['edit_id']);
        $sql = "SELECT * FROM avance_pdt_proyecto WHERE id=$id LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            echo json_encode(['success'=>true,'row'=>$row]);
        } else {
            echo json_encode(['success'=>false]);
        }
        exit;
    }
    if ($action === 'listar') {
        $sql = "SELECT id, proyecto, nombre_proyecto, porcentaje_avance_fisico_ejecutado, porcentaje_avance_fisico_programado FROM avance_pdt_proyecto ORDER BY id DESC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        echo json_encode(['success'=>true,'rows'=>$rows]);
        exit;
    }
    // --- CRUD para avance_fisico_ejecutado_programado (tab2) ---
    if ($action === 'guardar_area_funcional') {
        $id = isset($_POST['registro_id']) && $_POST['registro_id'] !== '' ? intval($_POST['registro_id']) : null;
        $proyecto = $conn->real_escape_string($_POST['proyecto']);
        $nombre_proyecto = $conn->real_escape_string($_POST['nombre_proyecto']);
        $area_funcional = $conn->real_escape_string($_POST['area_funcional']);
        $porc_ejec = intval($_POST['porcentaje_ejecutado']);
        $porc_prog = intval($_POST['porcentaje_programado']);
        if ($id) {
            $sql = "UPDATE avance_fisico_ejecutado_programado SET PROYECTO='$proyecto', NOM_PROYECTO='$nombre_proyecto', AREA_FUNCIONAL='$area_funcional', PORCENTAJE_AVANCE_FISICO_EJECUTADO=$porc_ejec, PORCENTAJE_AVANCE_FISICO_PROGRAMADO=$porc_prog WHERE id=$id";
            $conn->query($sql);
        } else {
            $sql = "INSERT INTO avance_fisico_ejecutado_programado (PROYECTO, NOM_PROYECTO, AREA_FUNCIONAL, PORCENTAJE_AVANCE_FISICO_EJECUTADO, PORCENTAJE_AVANCE_FISICO_PROGRAMADO) VALUES ('$proyecto', '$nombre_proyecto', '$area_funcional', $porc_ejec, $porc_prog)";
            $conn->query($sql);
        }
        echo json_encode(['success'=>true]);
        exit;
    }
    if ($action === 'eliminar_area_funcional' && isset($_POST['delete_id'])) {
        $id = intval($_POST['delete_id']);
        $sql = "DELETE FROM avance_fisico_ejecutado_programado WHERE id=$id";
        $conn->query($sql);
        echo json_encode(['success'=>true]);
        exit;
    }
    if ($action === 'editar_area_funcional' && isset($_POST['edit_id'])) {
        $id = intval($_POST['edit_id']);
        $sql = "SELECT * FROM avance_fisico_ejecutado_programado WHERE id=$id LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            echo json_encode(['success'=>true,'row'=>$row]);
        } else {
            echo json_encode(['success'=>false]);
        }
        exit;
    }
    if ($action === 'listar_area_funcional') {
        $sql = "SELECT id, PROYECTO, NOM_PROYECTO, AREA_FUNCIONAL, PORCENTAJE_AVANCE_FISICO_EJECUTADO, PORCENTAJE_AVANCE_FISICO_PROGRAMADO FROM avance_fisico_ejecutado_programado ORDER BY id DESC";
        $res = $conn->query($sql);
        $rows = [];
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        echo json_encode(['success'=>true,'rows'=>$rows]);
        exit;
    }
    echo json_encode(['success'=>false]);
    exit;
}

$usuario_logueado = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
$area_funcional = '';
$nombre_usuario = '';
$rol_usuario = '';
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

// Mostrar nombre de usuario y rol en la parte superior
    echo '<div style="background:#f8f9fa;padding:10px 20px;margin-bottom:15px;border-bottom:1px solid #dee2e6; display:flex; align-items:center; justify-content:space-between;">';
    echo '<div style="display:flex;align-items:center;gap:22px;">';
    echo '<img src="logofza2.PNG" alt="Logo" style="height:60px;max-width:180px;object-fit:contain;">';
    echo '<span style="font-family:Segoe UI, Arial, sans-serif; font-size:1rem; color:#17823d;"><strong>Usuario:</strong> ' . htmlspecialchars($nombre_usuario ?: $usuario_logueado) . '</span>';
echo '</div>';
echo '<a href="puente1.php" class="btn btn-regresar" style="display:flex;align-items:center;gap:6px;font-weight:500;border:1.5px solid #b5c6d6;background:#fff;color:#4C8AA3;padding:7px 18px;border-radius:8px;font-size:1.08rem;text-decoration:none;transition:box-shadow 0.2s;">'
    .'<span style="font-size:1.2em;line-height:1;">&larr;</span>'
    .' <span style="color:#4C8AA3;">Regresar</span>'
    .'</a>';
echo '</div>';
?>

<style>
.simple-tabs {
    display: flex;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 0;
    background: #fff;
    border-radius: 0.5rem 0.5rem 0 0;
    padding-left: 10px;
}
/* Mejorar el aspecto del select2 y el formulario */
.select2-container--default .select2-selection--single {
    background: #f8fafc;
    border: 1.5px solid #b5c6d6;
    border-radius: 8px;
    height: 42px;
    padding: 6px 12px;
    font-size: 1rem;
    color: #2a3b4d;
    box-shadow: 0 1px 3px rgba(60,72,88,0.07);
    transition: border-color 0.2s;
}
.select2-container--default .select2-selection--single:focus,
.select2-container--default .select2-selection--single:hover {
    border-color: #007bff;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #2a3b4d;
    line-height: 28px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
    right: 10px;
}
.select2-dropdown {
    border-radius: 8px;
    border: 1.5px solid #b5c6d6;
    box-shadow: 0 4px 16px rgba(60,72,88,0.10);
}
.select2-results__option--highlighted {
    background: #007bff !important;
    color: #fff !important;
}

.form-control, .select2-container--default .select2-selection--single {
    margin-bottom: 12px;
    border-radius: 8px;
    border: 1.5px solid #d3e0e9;
    background: #fff;
    box-shadow: 0 2px 8px 0 rgba(60,72,88,0.07);
    transition: border-color 0.2s, box-shadow 0.2s;
    font-size: 1.08rem;
    padding: 10px 14px;
    width: 320px;
    min-width: 180px;
    max-width: 95%;
    box-sizing: border-box;
}
.form-control:focus {
    border-color: #17823d;
    box-shadow: 0 0 0 2px rgba(23,130,61,0.12);
    outline: none;
}
.form-group {
    margin-bottom: 18px;
}
form {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px 0 rgba(60,72,88,0.10);
    padding: 28px 32px 18px 32px;
    margin-bottom: 24px;
    border: 1.5px solid #e0e0e0;
}
label {
    font-weight: 500;
    color: #17823d;
    margin-bottom: 6px;
    display: block;
    font-size: 1.05rem;
}
.btn-success {
    background: #17823D;
    border: none;
    color: #fff;
    font-weight: 600;
    border-radius: 6px;
    padding: 7px 22px;
    box-shadow: 0 2px 8px 0 rgba(60,72,88,0.07);
    transition: background 0.2s;
}
.btn-success:hover {
    background: #146e32;
}
.btn-secondary {
    border-radius: 6px;
    padding: 7px 22px;
    font-weight: 500;
    background: #6F6F6F;
    color: #fff;
    border: none;
}
.row.g-2 > [class^='col-'] {
    padding-right: 12px;
}
.row.g-2 {
    margin-bottom: 0.5rem;
}
.simple-tabs button {
    background: #6F6F6F; /* gris oscuro para inactivo */
    border: none;
    color: #fff;
    font-weight: 600;
    padding: 14px 24px 10px 24px;
    margin-right: 2px;
    border-radius: 0.5rem 0.5rem 0 0;
    font-family: 'Segoe UI', Arial, sans-serif !important;
    transition: background 0.2s, color 0.2s;
    font-size: 1.15rem;
}
.simple-tabs button.active {
    background: #17823d; /* verde */
    color: #fff; /* texto blanco */
    border-bottom: 2px solid #17823d;
    font-weight: bold;
}
.simple-tabs button:hover {
    color: #0056b3;
    background: #f1f3f6;
}
.simple-tab-content {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-top: none;
    padding: 24px 28px 18px 28px;
    border-radius: 0 0 0.5rem 0.5rem;
    min-height: 120px;
}

/* --- Tabla profesional tipo dashboard --- */

.tabla-dashboard {
    border-radius: 0.5rem 0.5rem 0 0;
    overflow: hidden;
    box-shadow: 0 2px 8px 0 rgba(60,72,88,0.07);
    font-size: 15px;
    border-collapse: separate;
    border-spacing: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
}
.tabla-dashboard thead tr {
    background: #5a8ca4;
}
.tabla-dashboard thead th {
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    border-bottom: 2px solid #4a7a8c;
    border-top: none;
    letter-spacing: 0.5px;
    padding: 10px 8px;
    text-align: left;
    border-right: 1px solid #e0e0e0;
}
.tabla-dashboard thead th:last-child {
    border-right: none;
}
.tabla-dashboard tbody td {
    border-right: 1px solid #f3f3f3;
}
.tabla-dashboard tbody td:last-child {
    border-right: none;
}
}

.tabla-dashboard thead {
    border-bottom: 1px solid #b5c6d6;
}
.tabla-dashboard tbody tr {
    background: #f8fafc;
    border-bottom: 1px solid #e0e0e0;
    transition: background 0.2s;
}
.tabla-dashboard tbody tr:nth-child(even) {
    background: #f3f6fa;
}
.tabla-dashboard tbody td {
    color: #333;
    padding: 9px 8px;
    vertical-align: middle;
    font-size: 15px;
    font-weight: 500;
}
.tabla-dashboard tbody tr:hover {
    background: #eaf2fb;
}
.tabla-dashboard .col-green {
    background: #eaf7ea;
    color: #17823d;
    font-weight: 600;
}
.tabla-dashboard .col-orange {
    background: #fff4e5;
    color: #e67e22;
    font-weight: 600;
}
.tabla-dashboard .col-blue {
    background: #e3f0fa;
    color: #5a8ca4;
    font-weight: 600;
}
.tabla-dashboard .col-gray {
    background: #f8fafc;
    color: #6f6f6f;
}
.tabla-dashboard .btn {
    font-size: 13px;
    padding: 3px 12px;
}
</style>

<div class="simple-tabs">
    <button class="active" id="tab1-btn" onclick="showTab(1)">PDT POR PROYECTO</button>
    <button id="tab2-btn" onclick="showTab(2)">PDT POR ÁREA FUNCIONAL</button>
</div>
<div class="simple-tab-content" id="tab1-content">
    <h4 style="color:#4C8AA3;font-weight:600;margin-top:10px;">PDT POR PROYECTO</h4>

    <!-- Formulario para agregar o editar registro -->
    <form method="post" style="margin-bottom:16px;" id="formRegistro">
        <input type="hidden" name="registro_id" id="registro_id" value="">
        <div class="row g-2">
            <div class="col-md-3">
                <?php
                // Obtener lista de proyectos para el select
                $proyectos_result = $conn->query("SELECT nombre_proyecto FROM proyectos ORDER BY nombre_proyecto ASC");
                ?>
                <select class="form-control" name="nombre_proyecto" id="nombre_proyecto" required>
                    <option value="">Buscar nombre de proyecto...</option>
                    <?php if ($proyectos_result && $proyectos_result->num_rows > 0): ?>
                        <?php while ($p = $proyectos_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($p['nombre_proyecto']); ?>"><?php echo htmlspecialchars($p['nombre_proyecto']); ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="proyecto" id="proyecto" placeholder="Proyecto" required>
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control" name="porcentaje_ejecutado" id="porcentaje_ejecutado" placeholder="% Avance Físico Ejecutado" min="0" max="100" required>
            </div>
            <div class="col-md-3">
                <input type="number" class="form-control" name="porcentaje_programado" id="porcentaje_programado" placeholder="% Avance Físico Programado" min="0" max="100" required>
            </div>
            <div class="col-md-12 mt-2">
                                <div style="display: flex; gap: 12px; align-items: center; margin-top: 8px;">
                                    <button type="submit" class="btn btn-success" name="guardar_registro">Guardar</button>
                                    <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()">Limpiar</button>
                                </div>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="tabla-dashboard table align-middle" id="avance_pdt_proyecto" style="min-width:900px;">
            <thead>
                <tr>
                    <th>PROYECTO</th>
                    <th>NOMBRE PROYECTO</th>
                    <th>% AVANCE EJECUTADO</th>
                    <th>% AVANCE PROGRAMADO</th>
                    <th>ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT id, proyecto, nombre_proyecto, porcentaje_avance_fisico_ejecutado, porcentaje_avance_fisico_programado FROM avance_pdt_proyecto ORDER BY id DESC";
                $res = $conn->query($sql);
                if ($res && $res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        $color_ejec = $row['porcentaje_avance_fisico_ejecutado'] >= 70 ? '#1abc9c' : ($row['porcentaje_avance_fisico_ejecutado'] >= 40 ? '#f5a623' : '#e74c3c');
                        $color_prog = $row['porcentaje_avance_fisico_programado'] >= 70 ? '#1abc9c' : ($row['porcentaje_avance_fisico_programado'] >= 40 ? '#f5a623' : '#e74c3c');
                        echo '<tr>';
                        echo '<td style="font-weight:600;">' . htmlspecialchars($row['proyecto']) . '</td>';
                        echo '<td style="color:#4a6fa1;">' . htmlspecialchars($row['nombre_proyecto']) . '</td>';
                        echo '<td style="font-weight:600;color:' . $color_ejec . ';">' . htmlspecialchars($row['porcentaje_avance_fisico_ejecutado']) . '%</td>';
                        echo '<td style="font-weight:600;color:' . $color_prog . ';">' . htmlspecialchars($row['porcentaje_avance_fisico_programado']) . '%</td>';
                        echo '<td>';
                        echo '<button class="btn btn-primary btn-sm me-1" onclick="editarRegistro(' . $row['id'] . ')">Editar</button>';
                        echo '<button class="btn btn-danger btn-sm" onclick="eliminarRegistro(' . $row['id'] . ')">Eliminar</button>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="5" style="color:#aaa;text-align:center;">No hay registros.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<script>

// Cargar Select2 para el select de proyectos
const select2CDN = document.createElement('link');
select2CDN.rel = 'stylesheet';
select2CDN.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
document.head.appendChild(select2CDN);
const select2Script = document.createElement('script');
select2Script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
select2Script.onload = function() {
    $('#nombre_proyecto').select2({
        placeholder: 'Buscar nombre de proyecto...',
        allowClear: true,
        width: '100%'
    });
    $('#nombre_proyecto').select2({
        placeholder: 'Buscar nombre de proyecto...',
        allowClear: true,
        width: '100%'
    });
};
document.body.appendChild(select2Script);

// Cuando cambia el nombre del proyecto, buscar el centro de costos y ponerlo en el campo 'proyecto'
document.getElementById('nombre_proyecto').addEventListener('change', function() {
    const nombreProyecto = this.value;
    if (!nombreProyecto) {
        document.getElementById('proyecto').value = '';
        return;
    }
    const formData = new FormData();
    formData.append('ajax', 'get_centro_costos');
    formData.append('nombre_proyecto', nombreProyecto);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('proyecto').value = data.centro_costos;
        } else {
            document.getElementById('proyecto').value = '';
        }
    });
});

// --- AJAX CRUD JS ---
function limpiarFormulario() {
    document.getElementById('formRegistro').reset();
    document.getElementById('registro_id').value = '';
}

// Enviar formulario por AJAX
document.getElementById('formRegistro').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('ajax', 'guardar');
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            limpiarFormulario();
            cargarTabla();
        } else {
            alert('Error al guardar');
        }
    })
    .catch(() => alert('Error de red'));
});

// Cargar tabla por AJAX
function cargarTabla() {
    const formData = new FormData();
    formData.append('ajax', 'listar');
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#avance_pdt_proyecto tbody');
            if (data.rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="color:#aaa;text-align:center;">No hay registros.</td></tr>';
            } else {
                tbody.innerHTML = data.rows.map(row => {
                    const color_ejec = row.porcentaje_avance_fisico_ejecutado >= 70 ? '#1abc9c' : (row.porcentaje_avance_fisico_ejecutado >= 40 ? '#f5a623' : '#e74c3c');
                    const color_prog = row.porcentaje_avance_fisico_programado >= 70 ? '#1abc9c' : (row.porcentaje_avance_fisico_programado >= 40 ? '#f5a623' : '#e74c3c');
                    return `<tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="font-weight:600;color:#222;font-size:15px;">${row.proyecto}</td>
                        <td style="color:#4a6fa1;font-size:15px;">${row.nombre_proyecto}</td>
                        <td style="font-weight:600;color:${color_ejec};font-size:15px;">${row.porcentaje_avance_fisico_ejecutado}%</td>
                        <td style="font-weight:600;color:${color_prog};font-size:15px;">${row.porcentaje_avance_fisico_programado}%</td>
                        <td>
                            <button class='btn btn-primary btn-sm' onclick='editarRegistro(${row.id})'>Editar</button>
                            <button class='btn btn-danger btn-sm' onclick='eliminarRegistro(${row.id})'>Eliminar</button>
                        </td>
                    </tr>`;
                }).join('');
            }
        }
    });
}

// Eliminar registro
function eliminarRegistro(id) {
    if (!confirm('¿Seguro que deseas eliminar este registro?')) return;
    const formData = new FormData();
    formData.append('ajax', 'eliminar');
    formData.append('delete_id', id);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) cargarTabla();
        else alert('Error al eliminar');
    });
}

// Editar registro
function editarRegistro(id) {
    const formData = new FormData();
    formData.append('ajax', 'editar');
    formData.append('edit_id', id);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.row) {
            document.getElementById('registro_id').value = data.row.id;
            document.getElementById('proyecto').value = data.row.proyecto;
            document.getElementById('nombre_proyecto').value = data.row.nombre_proyecto;
            document.getElementById('porcentaje_ejecutado').value = data.row.porcentaje_avance_fisico_ejecutado;
            document.getElementById('porcentaje_programado').value = data.row.porcentaje_avance_fisico_programado;
        }
    });
}

// Inicializar tabla al cargar
window.addEventListener('DOMContentLoaded', cargarTabla);
</script>

<div class="simple-tab-content" id="tab2-content" style="display:none;">
    <h4 style="color:#4C8AA3;font-weight:600;">PDT POR ÁREA FUNCIONAL</h4>
    <!-- Formulario para agregar o editar registro de área funcional -->
    <form method="post" style="margin-bottom:16px;" id="formRegistroAreaFuncional">
        <input type="hidden" name="registro_id" id="registro_id_area_funcional" value="">
        <div class="row g-2">
            <div class="col-md-3">
                <?php
                // Obtener lista de proyectos para el select (igual que en tab1)
                $proyectos_result2 = $conn->query("SELECT nombre_proyecto FROM proyectos ORDER BY nombre_proyecto ASC");
                ?>
                <select class="form-control" name="nombre_proyecto" id="nombre_proyecto_area_funcional" required>
                    <option value="">Buscar nombre de proyecto...</option>
                    <?php if ($proyectos_result2 && $proyectos_result2->num_rows > 0): ?>
                        <?php while ($p2 = $proyectos_result2->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($p2['nombre_proyecto']); ?>"><?php echo htmlspecialchars($p2['nombre_proyecto']); ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="proyecto" id="proyecto_area_funcional" placeholder="Proyecto" required readonly>
            </div>
            <div class="col-md-3">
                <select class="form-control" name="area_funcional" id="area_funcional_area_funcional" required>
                    <option value="">Seleccionar área funcional...</option>
                    <option value="Dirección de Proyectos">Dirección de Proyectos</option>
                    <option value="Estructuras">Estructuras</option>
                    <option value="Vías y Topografía">Vías y Topografía</option>
                    <option value="Geotecnia y Pavimentos">Geotecnia y Pavimentos</option>
                    <option value="Hidráulica y Medio Ambiente">Hidráulica y Medio Ambiente</option>
                    <option value="Arquitectura y Urbanismo">Arquitectura y Urbanismo</option>
                    <option value="Mecánica">Mecánica</option>
                    <option value="Eléctrica">Eléctrica</option>
                    <option value="BIM">BIM</option>
                    <option value="Vías">Vías</option>
                    <option value="Topografía">Topografía</option>
                    <option value="Tecnología">Tecnología</option>
                    <option value="Área_Prueba">Área_Prueba</option>
                </select>
            </div>
            <div class="col-md-1">
                <input type="number" class="form-control" name="porcentaje_ejecutado" id="porcentaje_ejecutado_area_funcional" placeholder="% Ejecutado" min="0" max="100" required>
            </div>
            <div class="col-md-1">
                <input type="number" class="form-control" name="porcentaje_programado" id="porcentaje_programado_area_funcional" placeholder="% Programado" min="0" max="100" required>
            </div>
            <div class="col-md-2 mt-2">
                <div style="display: flex; gap: 12px; align-items: center;">
                  <button type="submit" class="btn btn-success" name="guardar_registro">Guardar</button>
                  <button type="button" class="btn btn-secondary" onclick="limpiarFormularioAreaFuncional()">Limpiar</button>
                </div>
            </div>
        </div>
    </form>
    <div class="table-responsive">
        <table class="tabla-dashboard table align-middle" id="avance_area_funcional" style="min-width:900px;">
            <thead>
                <tr>
                    <th>PROYECTO</th>
                    <th>NOMBRE PROYECTO</th>
                    <th>ÁREA FUNCIONAL</th>
                    <th>% AVANCE EJECUTADO</th>
                    <th>% AVANCE PROGRAMADO</th>
                    <th>ACCIONES</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script>
// --- CRUD JS para avance_fisico_ejecutado_programado (área funcional) ---
function limpiarFormularioAreaFuncional() {
    document.getElementById('formRegistroAreaFuncional').reset();
    document.getElementById('registro_id_area_funcional').value = '';
}
document.getElementById('formRegistroAreaFuncional').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('ajax', 'guardar_area_funcional');
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            limpiarFormularioAreaFuncional();
            cargarTablaAreaFuncional();
        } else {
            alert('Error al guardar');
        }
    })
    .catch(() => alert('Error de red'));
});
function cargarTablaAreaFuncional() {
    const formData = new FormData();
    formData.append('ajax', 'listar_area_funcional');
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const tbody = document.querySelector('#avance_area_funcional tbody');
            if (data.rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="color:#aaa;text-align:center;">No hay registros.</td></tr>';
            } else {
                tbody.innerHTML = data.rows.map(row => {
                    const color_ejec = row.PORCENTAJE_AVANCE_FISICO_EJECUTADO >= 70 ? '#1abc9c' : (row.PORCENTAJE_AVANCE_FISICO_EJECUTADO >= 40 ? '#f5a623' : '#e74c3c');
                    const color_prog = row.PORCENTAJE_AVANCE_FISICO_PROGRAMADO >= 70 ? '#1abc9c' : (row.PORCENTAJE_AVANCE_FISICO_PROGRAMADO >= 40 ? '#f5a623' : '#e74c3c');
                    return `<tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="font-weight:600;color:#222;font-size:15px;">${row.PROYECTO}</td>
                        <td style="color:#4a6fa1;font-size:15px;">${row.NOM_PROYECTO}</td>
                        <td style="color:#222;font-size:15px;">${row.AREA_FUNCIONAL}</td>
                        <td style="font-weight:600;color:${color_ejec};font-size:15px;">${row.PORCENTAJE_AVANCE_FISICO_EJECUTADO}%</td>
                        <td style="font-weight:600;color:${color_prog};font-size:15px;">${row.PORCENTAJE_AVANCE_FISICO_PROGRAMADO}%</td>
                        <td>
                            <button class='btn btn-primary btn-sm' onclick='editarRegistroAreaFuncional(${row.id})'>Editar</button>
                            <button class='btn btn-danger btn-sm' onclick='eliminarRegistroAreaFuncional(${row.id})'>Eliminar</button>
                        </td>
                    </tr>`;
                }).join('');
            }
        }
    });
}
function eliminarRegistroAreaFuncional(id) {
    if (!confirm('¿Seguro que deseas eliminar este registro?')) return;
    const formData = new FormData();
    formData.append('ajax', 'eliminar_area_funcional');
    formData.append('delete_id', id);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) cargarTablaAreaFuncional();
        else alert('Error al eliminar');
    });
}
function editarRegistroAreaFuncional(id) {
    const formData = new FormData();
    formData.append('ajax', 'editar_area_funcional');
    formData.append('edit_id', id);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.row) {
            document.getElementById('registro_id_area_funcional').value = data.row.id;
            document.getElementById('proyecto_area_funcional').value = data.row.PROYECTO;
            document.getElementById('nombre_proyecto_area_funcional').value = data.row.NOM_PROYECTO;
            document.getElementById('area_funcional_area_funcional').value = data.row.AREA_FUNCIONAL;
            document.getElementById('porcentaje_ejecutado_area_funcional').value = data.row.PORCENTAJE_AVANCE_FISICO_EJECUTADO;
            document.getElementById('porcentaje_programado_area_funcional').value = data.row.PORCENTAJE_AVANCE_FISICO_PROGRAMADO;
        }
    });
}
// Inicializar tabla al cargar
window.addEventListener('DOMContentLoaded', cargarTablaAreaFuncional);
</script>
<script>
function showTab(tab) {
    document.getElementById('tab1-content').style.display = tab === 1 ? '' : 'none';
    document.getElementById('tab2-content').style.display = tab === 2 ? '' : 'none';
    document.getElementById('tab1-btn').classList.toggle('active', tab === 1);
    document.getElementById('tab2-btn').classList.toggle('active', tab === 2);
}
// Cargar Select2 para el select de proyectos en tab2
const select2Script2 = document.createElement('script');
select2Script2.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
select2Script2.onload = function() {
    $('#nombre_proyecto_area_funcional').select2({
        placeholder: 'Buscar nombre de proyecto...',
        allowClear: true,
        width: '100%'
    });
};
document.body.appendChild(select2Script2);
// Cuando cambia el nombre del proyecto en área funcional, buscar el centro de costos y ponerlo en el campo correspondiente
document.getElementById('nombre_proyecto_area_funcional').addEventListener('change', function() {
    const nombreProyecto = this.value;
    if (!nombreProyecto) {
        document.getElementById('proyecto_area_funcional').value = '';
        return;
    }
    const formData = new FormData();
    formData.append('ajax', 'get_centro_costos');
    formData.append('nombre_proyecto', nombreProyecto);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('proyecto_area_funcional').value = data.centro_costos;
        } else {
            document.getElementById('proyecto_area_funcional').value = '';
        }
    });
});
</script>
