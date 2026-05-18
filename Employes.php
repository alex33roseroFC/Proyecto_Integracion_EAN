<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || strtoupper(trim($_SESSION['rol'])) !== 'SUPER') {
    header('Location: Index2.php');
    exit();
}

require_once 'include.php';
require_once 'config.php';

// --- INICIALIZACIÓN DE VARIABLES ---
$edit_state = false;
$id = 0;
$matricula = $nom = $prenom = $fecha_ingreso = $fechas_retiro = $fechas_retiro_2 = $tipo_identificacion = $genero = $notas = $razon_social = $rol = $area_funcional = $area = $esp = $cargo_ingresa = $sede = $ciudad_base = $no_prorroga = $coordinador_area = $salario = $tipo_contrato = $dias_vacaciones_disp = $tipo_salario = $arl_riesgo = $auxilios = $cat_coan = $tarifa_coan = $nombre_categoria = $hh_promedio_mes = $horas_diarias = '';

// --- LÓGICA DEL FORMULARIO ---

// GUARDAR / INSERTAR
if (isset($_POST['save'])) {
    // Recoger todos los datos del POST
    $matricula = $_POST['matricula']; $nom = $_POST['nom']; $prenom = $_POST['prenom']; $fecha_ingreso = $_POST['fecha_ingreso']; $fechas_retiro = $_POST['fechas_retiro']; $fechas_retiro_2 = $_POST['fechas_retiro_2']; $tipo_identificacion = $_POST['tipo_identificacion']; $genero = $_POST['genero']; $notas = $_POST['notas']; $razon_social = $_POST['razon_social']; $rol = $_POST['rol']; $area_funcional = $_POST['area_funcional']; $area = $_POST['area']; $esp = $_POST['esp']; $cargo_ingresa = $_POST['cargo_ingresa']; $sede = $_POST['sede']; $ciudad_base = $_POST['ciudad_base']; $no_prorroga = $_POST['no_prorroga']; $coordinador_area = $_POST['coordinador_area']; $salario = $_POST['salario']; $tipo_contrato = $_POST['tipo_contrato']; $dias_vacaciones_disp = $_POST['dias_vacaciones_disp']; $tipo_salario = $_POST['tipo_salario']; $arl_riesgo = $_POST['arl_riesgo']; $auxilios = $_POST['auxilios']; $cat_coan = $_POST['cat_coan']; $tarifa_coan = $_POST['tarifa_coan']; $nombre_categoria = $_POST['nombre_categoria']; $hh_promedio_mes = $_POST['hh_promedio_mes']; $horas_diarias = $_POST['horas_diarias'];

    $query = "INSERT INTO empleados (matricula, nom, prenom, fecha_ingreso, fechas_retiro, fechas_retiro_2, tipo_identificacion, genero, notas, razon_social, rol, area_funcional, area, esp, cargo_ingresa, sede, ciudad_base, no_prorroga, coordinador_area, salario, tipo_contrato, dias_vacaciones_disp, tipo_salario, arl_riesgo, auxilios, cat_coan, tarifa_coan, nombre_categoria, hh_promedio_mes, horas_diarias) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('issssssssssssssssssdsisssssdii', $matricula, $nom, $prenom, $fecha_ingreso, $fechas_retiro, $fechas_retiro_2, $tipo_identificacion, $genero, $notas, $razon_social, $rol, $area_funcional, $area, $esp, $cargo_ingresa, $sede, $ciudad_base, $no_prorroga, $coordinador_area, $salario, $tipo_contrato, $dias_vacaciones_disp, $tipo_salario, $arl_riesgo, $auxilios, $cat_coan, $tarifa_coan, $nombre_categoria, $hh_promedio_mes, $horas_diarias);
    $stmt->execute();
    // Obtener el id insertado si es un nuevo registro
    $new_id = $conn->insert_id ? $conn->insert_id : $matricula;
    header('Location: empleados.php?edit=' . $new_id . '&success=2');
    exit();
}

// ACTUALIZAR / UPDATE
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $matricula = $_POST['matricula']; $nom = $_POST['nom']; $prenom = $_POST['prenom']; $fecha_ingreso = $_POST['fecha_ingreso']; $fechas_retiro = $_POST['fechas_retiro']; $fechas_retiro_2 = $_POST['fechas_retiro_2']; $tipo_identificacion = $_POST['tipo_identificacion']; $genero = $_POST['genero']; $notas = $_POST['notas']; $razon_social = $_POST['razon_social']; $rol = $_POST['rol']; $area_funcional = $_POST['area_funcional']; $area = $_POST['area']; $esp = $_POST['esp']; $cargo_ingresa = $_POST['cargo_ingresa']; $sede = $_POST['sede']; $ciudad_base = $_POST['ciudad_base']; $no_prorroga = $_POST['no_prorroga']; $coordinador_area = $_POST['coordinador_area']; $salario = $_POST['salario']; $tipo_contrato = $_POST['tipo_contrato']; $dias_vacaciones_disp = $_POST['dias_vacaciones_disp']; $tipo_salario = $_POST['tipo_salario']; $arl_riesgo = $_POST['arl_riesgo']; $auxilios = $_POST['auxilios']; $cat_coan = $_POST['cat_coan']; $tarifa_coan = $_POST['tarifa_coan']; $nombre_categoria = $_POST['nombre_categoria']; $hh_promedio_mes = $_POST['hh_promedio_mes']; $horas_diarias = $_POST['horas_diarias'];

    $query = "UPDATE empleados SET matricula=?, nom=?, prenom=?, fecha_ingreso=?, fechas_retiro=?, fechas_retiro_2=?, tipo_identificacion=?, genero=?, notas=?, razon_social=?, rol=?, area_funcional=?, area=?, esp=?, cargo_ingresa=?, sede=?, ciudad_base=?, no_prorroga=?, coordinador_area=?, salario=?, tipo_contrato=?, dias_vacaciones_disp=?, tipo_salario=?, arl_riesgo=?, auxilios=?, cat_coan=?, tarifa_coan=?, nombre_categoria=?, hh_promedio_mes=?, horas_diarias=? WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('issssssssssssssssssdsisssssdiii', $matricula, $nom, $prenom, $fecha_ingreso, $fechas_retiro, $fechas_retiro_2, $tipo_identificacion, $genero, $notas, $razon_social, $rol, $area_funcional, $area, $esp, $cargo_ingresa, $sede, $ciudad_base, $no_prorroga, $coordinador_area, $salario, $tipo_contrato, $dias_vacaciones_disp, $tipo_salario, $arl_riesgo, $auxilios, $cat_coan, $tarifa_coan, $nombre_categoria, $hh_promedio_mes, $horas_diarias, $id);
    $stmt->execute();
    header('Location: empleados.php?edit=' . $id . '&success=1');
    exit();
}

// ELIMINAR / DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM empleados WHERE id=$id");
    header('location: empleados.php');
    exit();
}

// CARGAR DATOS PARA EDICIÓN
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $edit_state = true;
    $rec = $conn->query("SELECT * FROM empleados WHERE id=$id");
    $record = $rec->fetch_assoc();
    // Cargar todos los campos en las variables
    $matricula = $record['matricula']; $nom = $record['nom']; $prenom = $record['prenom']; $fecha_ingreso = $record['fecha_ingreso']; $fechas_retiro = $record['fechas_retiro']; $fechas_retiro_2 = $record['fechas_retiro_2']; $tipo_identificacion = $record['tipo_identificacion']; $genero = $record['genero']; $notas = $record['notas']; $razon_social = $record['razon_social']; $rol = $record['rol']; $area_funcional = $record['area_funcional']; $area = $record['area']; $esp = $record['esp']; $cargo_ingresa = $record['cargo_ingresa']; $sede = $record['sede']; $ciudad_base = $record['ciudad_base']; $no_prorroga = $record['no_prorroga']; $coordinador_area = $record['coordinador_area']; $salario = $record['salario']; $tipo_contrato = $record['tipo_contrato']; $dias_vacaciones_disp = $record['dias_vacaciones_disp']; $tipo_salario = $record['tipo_salario']; $arl_riesgo = $record['arl_riesgo']; $auxilios = $record['auxilios']; $cat_coan = $record['cat_coan']; $tarifa_coan = $record['tarifa_coan']; $nombre_categoria = $record['nombre_categoria']; $hh_promedio_mes = $record['hh_promedio_mes']; $horas_diarias = $record['horas_diarias'];
}

// Incluir header solo después de la lógica de procesamiento y redirecciones
require_once 'includes/header.php';


// Mostrar mensaje de éxito si corresponde
$success_type = isset($_GET['success']) ? $_GET['success'] : '';
?>

<?php
// Obtener lista de coordinadores desde la tabla login_usuarios (columna Nombre_Usuario)
$coordinadores = array();
if (isset($conn)) {
    $q = "SELECT DISTINCT Nombre_Usuario FROM login_usuarios ORDER BY Nombre_Usuario";
    if ($r = $conn->query($q)) {
        while ($row = $r->fetch_assoc()) {
            if (isset($row['Nombre_Usuario'])) $coordinadores[] = $row['Nombre_Usuario'];
        }
        $r->free();
    }
}
?>




<?php if ($success_type == '1'): ?>
<div class="alert alert-success alert-dismissible fade show mt-3" role="alert" style="max-width:600px;margin:auto;">
    <strong>¡Registro actualizado correctamente!</strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php elseif ($success_type == '2'): ?>
<div class="alert alert-success alert-dismissible fade show mt-3" role="alert" style="max-width:600px;margin:auto;">
    <strong>¡Empleado registrado correctamente!</strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<div class="page-header"><h2>Gestión de Empleados</h2></div>

<script>
// Move the form page header into the global header placeholder so it sits inline with the site image.
document.addEventListener('DOMContentLoaded', function(){
    try {
        var slot = document.querySelector('.page-title-placeholder');
        var ph = document.querySelector('.page-header');
        if (slot && ph) {
            // make header's h2 inherit styles and center vertically
            ph.style.margin = '0';
            ph.style.display = 'flex';
            ph.style.alignItems = 'center';
            // center the title horizontally within the placeholder
            slot.style.justifyContent = 'center';
            // ensure the H2 text is centered
            const h2 = ph.querySelector('h2');
            if (h2) { h2.style.margin = '0'; h2.style.textAlign = 'center'; }
            slot.appendChild(ph);
        }
    } catch(e){ console && console.warn && console.warn('Move header failed', e); }
});
</script>

<form method="post" action="empleados.php" class="data-form">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <fieldset class="form-section border rounded-3 p-4 mb-4 bg-white shadow-sm">
        <legend class="fs-5 mb-3 text-primary"><span style="display:inline-flex;align-items:center;gap:8px;"><span style="font-size:1.1em;">👤</span><span>Información Personal</span></span></legend>
        <div class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Matrícula</label>
                <input aria-label="Matrícula" type="number" name="matricula" value="<?php echo $matricula; ?>" class="form-control form-control-sm rounded-pill" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Nombre</label>
                <input aria-label="Nombre" type="text" name="nom" value="<?php echo $nom; ?>" class="form-control form-control-sm rounded-pill" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Apellido</label>
                <input aria-label="Apellido" type="text" name="prenom" value="<?php echo $prenom; ?>" class="form-control form-control-sm rounded-pill" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Tipo Identificación</label>
                <select aria-label="Tipo Identificación" name="tipo_identificacion" class="form-select form-select-sm rounded-pill">
                    <option value="" <?php if (trim($tipo_identificacion) === '') echo 'selected'; ?>>-- Seleccione --</option>
                    <option value="Tarjeta de Identidad" <?php if (trim($tipo_identificacion) === 'Tarjeta de Identidad') echo 'selected'; ?>>Tarjeta de Identidad</option>
                    <option value="Cédula de Ciudadanía" <?php if (trim($tipo_identificacion) === 'Cédula de Ciudadanía') echo 'selected'; ?>>Cédula de Ciudadanía</option>
                    <option value="Cédula de Extranjería" <?php if (trim($tipo_identificacion) === 'Cédula de Extranjería') echo 'selected'; ?>>Cédula de Extranjería</option>
                    <option value="Pasaporte" <?php if (trim($tipo_identificacion) === 'Pasaporte') echo 'selected'; ?>>Pasaporte</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Género</label>
                <input aria-label="Género" type="text" name="genero" value="<?php echo $genero; ?>" class="form-control form-control-sm rounded-pill">
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section border rounded-3 p-4 mb-4 bg-white shadow-sm">
        <legend class="fs-5 mb-3 text-primary"><span style="display:inline-flex;align-items:center;gap:8px;"><span style="font-size:1.1em;">📅</span><span>Fechas Clave</span></span></legend>
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Fecha Ingreso</label>
                <input aria-label="Fecha Ingreso" type="date" name="fecha_ingreso" value="<?php echo $fecha_ingreso; ?>" class="form-control form-control-sm rounded-pill" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Fecha Retiro</label>
                <input aria-label="Fecha Retiro" type="date" name="fechas_retiro" value="<?php echo $fechas_retiro; ?>" class="form-control form-control-sm rounded-pill">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Fecha Retiro 2</label>
                <input aria-label="Fecha Retiro 2" type="date" name="fechas_retiro_2" value="<?php echo $fechas_retiro_2; ?>" class="form-control form-control-sm rounded-pill">
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend><span style="display:inline-flex;align-items:center;gap:8px;"><span style="font-size:1.1em;">📅</span><span>Fechas Clave</span></span></legend>
        <div class="form-fields-grid">
            <div class="form-group"><div class="form-header">Fecha Ingreso</div><input aria-label="Fecha Ingreso" type="date" name="fecha_ingreso" value="<?php echo $fecha_ingreso; ?>" required></div>
            <div class="form-group"><div class="form-header">Fecha Retiro</div><input aria-label="Fecha Retiro" type="date" name="fechas_retiro" value="<?php echo $fechas_retiro; ?>"></div>
            <div class="form-group"><div class="form-header">Fecha Retiro 2</div><input aria-label="Fecha Retiro 2" type="date" name="fechas_retiro_2" value="<?php echo $fechas_retiro_2; ?>"></div>
        </div>
    </fieldset>


    <fieldset class="form-section border rounded-3 p-4 mb-4 bg-white shadow-sm">
        <legend class="fs-5 mb-3 text-primary"><span style="display:inline-flex;align-items:center;gap:8px;"><span style="font-size:1.1em;">🏢</span><span>Datos Organizacionales</span></span></legend>
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Razón Social</label>
                <select aria-label="Razón Social" name="razon_social" class="form-select form-select-sm rounded-pill">
                    <option value="" <?php if (trim($razon_social) === '') echo 'selected'; ?>>-- Seleccione --</option>
                    <option value="Gomez Cajiao" <?php if (trim($razon_social) === 'Gomez Cajiao') echo 'selected'; ?>>Gomez Cajiao</option>
                    <option value="Setec Andina" <?php if (trim($razon_social) === 'Setec Andina') echo 'selected'; ?>>Setec Andina</option>
                    <option value="Setec Colombia" <?php if (trim($razon_social) === 'Setec Colombia') echo 'selected'; ?>>Setec Colombia</option>
                    <option value="Consorcios" <?php if (trim($razon_social) === 'Consorcios') echo 'selected'; ?>>Consorcios</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Rol</label>
                <input aria-label="Rol" type="text" name="rol" value="<?php echo $rol; ?>" class="form-control form-control-sm rounded-pill">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Área Funcional</label>
                <select aria-label="Área Funcional" name="area_funcional" class="form-select form-select-sm rounded-pill">
                    <option value="" <?php if (trim($area_funcional) === '') echo 'selected'; ?>>-- Seleccione --</option>
                    <option value="Área_Prueba" <?php if (trim($area_funcional) === 'Área_Prueba') echo 'selected'; ?>>Área_Prueba</option>
                    <option value="Mecánica - Eléctrica" <?php if (trim($area_funcional) === 'Mecánica - Eléctrica') echo 'selected'; ?>>Mecánica - Eléctrica</option>
                    <option value="Instrumentación &amp; Control" <?php if (trim($area_funcional) === 'Instrumentación & Control') echo 'selected'; ?>>Instrumentación &amp; Control</option>
                    <option value="Arquitectura y Urbanismo" <?php if (trim($area_funcional) === 'Arquitectura y Urbanismo') echo 'selected'; ?>>Arquitectura y Urbanismo</option>
                    <option value="BIM" <?php if (trim($area_funcional) === 'BIM') echo 'selected'; ?>>BIM</option>
                    <option value="Comercial" <?php if (trim($area_funcional) === 'Comercial') echo 'selected'; ?>>Comercial</option>
                    <option value="Control de Gestión" <?php if (trim($area_funcional) === 'Control de Gestión') echo 'selected'; ?>>Control de Gestión</option>
                    <option value="Control documental" <?php if (trim($area_funcional) === 'Control documental') echo 'selected'; ?>>Control documental</option>
                    <option value="Dirección de Construcción" <?php if (trim($area_funcional) === 'Dirección de Construcción') echo 'selected'; ?>>Dirección de Construcción</option>
                    <option value="Dirección Ejecutiva" <?php if (trim($area_funcional) === 'Dirección Ejecutiva') echo 'selected'; ?>>Dirección Ejecutiva</option>
                    <option value="Dirección Ingeniería" <?php if (trim($area_funcional) === 'Dirección Ingeniería') echo 'selected'; ?>>Dirección Ingeniería</option>
                    <option value="Estructuras" <?php if (trim($area_funcional) === 'Estructuras') echo 'selected'; ?>>Estructuras</option>
                    <option value="Finanzas" <?php if (trim($area_funcional) === 'Finanzas') echo 'selected'; ?>>Finanzas</option>
                    <option value="Geotecnia y Pavimentos" <?php if (trim($area_funcional) === 'Geotecnia y Pavimentos') echo 'selected'; ?>>Geotecnia y Pavimentos</option>
                    <option value="Gestión Integral" <?php if (trim($area_funcional) === 'Gestión Integral') echo 'selected'; ?>>Gestión Integral</option>
                    <option value="Hidráulica y Medio Ambiente" <?php if (trim($area_funcional) === 'Hidráulica y Medio Ambiente') echo 'selected'; ?>>Hidráulica y Medio Ambiente</option>
                    <option value="Legal" <?php if (trim($area_funcional) === 'Legal') echo 'selected'; ?>>Legal</option>
                    <option value="Mecánica" <?php if (trim($area_funcional) === 'Mecánica') echo 'selected'; ?>>Mecánica</option>
                    <option value="Planificación y control" <?php if (trim($area_funcional) === 'Planificación y control') echo 'selected'; ?>>Planificación y control</option>
                    <option value="Proyectos de Ingeniería" <?php if (trim($area_funcional) === 'Proyectos de Ingeniería') echo 'selected'; ?>>Proyectos de Ingeniería</option>
                    <option value="Servicios Compartidos y TH" <?php if (trim($area_funcional) === 'Servicios Compartidos y TH') echo 'selected'; ?>>Servicios Compartidos y TH</option>
                    <option value="Tecnología" <?php if (trim($area_funcional) === 'Tecnología') echo 'selected'; ?>>Tecnología</option>
                    <option value="Vías y Topografía" <?php if (trim($area_funcional) === 'Vías y Topografía') echo 'selected'; ?>>Vías y Topografía</option>
                    <option value="Vías y Topografía" <?php if (trim($area_funcional) === 'Vías y Topografía') echo 'selected'; ?>>Vías</option>
                    <option value="Vías y Topografía" <?php if (trim($area_funcional) === 'Vías y Topografía') echo 'selected'; ?>>Topografía</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Área</label>
                <select aria-label="Área" name="area" class="form-select form-select-sm rounded-pill">
                    <option value="" <?php if (trim($area) === '') echo 'selected'; ?>>-- Seleccione --</option>
                    <option value="COMERCIAL" <?php if (trim($area) === 'COMERCIAL') echo 'selected'; ?>>COMERCIAL</option>
                    <option value="CONTROL DE GESTION" <?php if (trim($area) === 'CONTROL DE GESTION') echo 'selected'; ?>>CONTROL DE GESTION</option>
                    <option value="DIRECCION EJECUTIVA" <?php if (trim($area) === 'DIRECCION EJECUTIVA') echo 'selected'; ?>>DIRECCION EJECUTIVA</option>
                    <option value="FINANZAS" <?php if (trim($area) === 'FINANZAS') echo 'selected'; ?>>FINANZAS</option>
                    <option value="GESTION INTEGRAL" <?php if (trim($area) === 'GESTION INTEGRAL') echo 'selected'; ?>>GESTION INTEGRAL</option>
                    <option value="GESTIÓN HUMANA" <?php if (trim($area) === 'GESTIÓN HUMANA') echo 'selected'; ?>>GESTIÓN HUMANA</option>
                    <option value="LEGAL" <?php if (trim($area) === 'LEGAL') echo 'selected'; ?>>LEGAL</option>
                    <option value="DIRECCIÓN DE CONSTRUCCIÓN" <?php if (trim($area) === 'DIRECCIÓN DE CONSTRUCCIÓN') echo 'selected'; ?>>DIRECCIÓN DE CONSTRUCCIÓN</option>
                    <option value="DIRECCIÓN DE INGENIERÍA" <?php if (trim($area) === 'DIRECCIÓN DE INGENIERÍA') echo 'selected'; ?>>DIRECCIÓN DE INGENIERÍA</option>
                    <option value="TECNOLOGIA" <?php if (trim($area) === 'TECNOLOGIA') echo 'selected'; ?>>TECNOLOGIA</option>
                    <option value="CONTROL DOCUMENTAL" <?php if (trim($area) === 'CONTROL DOCUMENTAL') echo 'selected'; ?>>CONTROL DOCUMENTAL</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Especialidad</label>
                <select aria-label="Especialidad" name="esp" class="form-select form-select-sm rounded-pill">
                    <option value="" <?php if (trim($esp) === '') echo 'selected'; ?>>-- Seleccione --</option>
                    <option value="Gomez Cajiao" <?php if (trim($esp) === 'Gomez Cajiao') echo 'selected'; ?>>Gomez Cajiao</option>
                    <option value="Setec Andina" <?php if (trim($esp) === 'Setec Andina') echo 'selected'; ?>>Setec Andina</option>
                    <option value="Setec Colombia" <?php if (trim($esp) === 'Setec Colombia') echo 'selected'; ?>>Setec Colombia</option>
                    <option value="Consorcios" <?php if (trim($esp) === 'Consorcios') echo 'selected'; ?>>Consorcios</option>
                </select>
            </div>
        </div>
        <div class="row g-3 align-items-end mt-2">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Cargo Ingresa</label>
                <input aria-label="Cargo Ingresa" type="text" name="cargo_ingresa" value="<?php echo $cargo_ingresa; ?>" class="form-control form-control-sm rounded-pill">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Sede</label>
                <input aria-label="Sede" type="text" name="sede" value="<?php echo $sede; ?>" class="form-control form-control-sm rounded-pill">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Ciudad Base</label>
                <input aria-label="Ciudad Base" type="text" name="ciudad_base" value="<?php echo $ciudad_base; ?>" class="form-control form-control-sm rounded-pill">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Coordinador Área</label>
                <?php if (!empty($coordinadores) && is_array($coordinadores)): ?>
                    <select aria-label="Coordinador Área" name="coordinador_area" class="form-select form-select-sm rounded-pill">
                        <option value="" <?php if (trim($coordinador_area) === '') echo 'selected'; ?>>-- Seleccione --</option>
                        <?php foreach ($coordinadores as $c): ?>
                            <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php if (trim($coordinador_area) === $c) echo 'selected'; ?>><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input aria-label="Coordinador Área" type="text" name="coordinador_area" value="<?php echo htmlspecialchars($coordinador_area, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm rounded-pill">
                <?php endif; ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend><span style="display:inline-flex;align-items:center;gap:8px;"><span style="font-size:1.1em;">🤝</span><span>Condiciones Contractuales</span></span></legend>
        <div class="form-fields-grid">
            <div class="form-group"><div class="form-header">Tipo Contrato</div>
                <select aria-label="Tipo Contrato" name="tipo_contrato" class="form-control">
                    <option value="" <?php if (trim($tipo_contrato) === '') echo 'selected'; ?>>-- Seleccione --</option>
                    <option value="Contrato a término indefinido" <?php if (trim($tipo_contrato) === 'Contrato a término indefinido') echo 'selected'; ?>>Contrato a término indefinido</option>
                    <option value="Contrato a término fijo" <?php if (trim($tipo_contrato) === 'Contrato a término fijo') echo 'selected'; ?>>Contrato a término fijo</option>
                    <option value="Contrato por obra o labor determinada" <?php if (trim($tipo_contrato) === 'Contrato por obra o labor determinada') echo 'selected'; ?>>Contrato por obra o labor determinada</option>
                    <option value="Contrato ocasional, accidental o transitorio" <?php if (trim($tipo_contrato) === 'Contrato ocasional, accidental o transitorio') echo 'selected'; ?>>Contrato ocasional, accidental o transitorio</option>
                    <option value="Contrato de aprendizaje" <?php if (trim($tipo_contrato) === 'Contrato de aprendizaje') echo 'selected'; ?>>Contrato de aprendizaje</option>
                    <option value="Contrato a tiempo parcial" <?php if (trim($tipo_contrato) === 'Contrato a tiempo parcial') echo 'selected'; ?>>Contrato a tiempo parcial</option>
                </select>
            </div>
            <div class="form-group"><div class="form-header">Salario</div><input aria-label="Salario" type="number" step="0.01" name="salario" value="<?php echo $salario; ?>"></div>
            <div class="form-group"><div class="form-header">Tipo Salario</div><input aria-label="Tipo Salario" type="text" name="tipo_salario" value="<?php echo $tipo_salario; ?>"></div>
            <div class="form-group"><div class="form-header">No. Prórroga</div><input aria-label="No. Prórroga" type="text" name="no_prorroga" value="<?php echo $no_prorroga; ?>"></div>
            <div class="form-group"><div class="form-header">Días Vacaciones Disp.</div><input aria-label="Días Vacaciones Disp." type="number" name="dias_vacaciones_disp" value="<?php echo $dias_vacaciones_disp; ?>"></div>
            <div class="form-group"><div class="form-header">Auxilios</div><input aria-label="Auxilios" type="text" name="auxilios" value="<?php echo $auxilios; ?>"></div>
            <div class="form-group"><div class="form-header">ARL Riesgo</div><input aria-label="ARL Riesgo" type="text" name="arl_riesgo" value="<?php echo $arl_riesgo; ?>"></div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend><span style="display:inline-flex;align-items:center;gap:8px;"><span style="font-size:1.1em;">💰</span><span>Cotización y Facturación</span></span></legend>
        <div class="form-fields-grid">
            <div class="form-group"><div class="form-header">Cat. Coan</div>
                <select aria-label="Cat. Coan" name="cat_coan" class="form-control">
                    <option value="" <?php if (trim($cat_coan) === '') echo 'selected'; ?>>-- Seleccione --</option>
                    <option value="9302D" <?php if (trim($cat_coan) === '9302D') echo 'selected'; ?>>9302D</option>
                    <option value="9303D" <?php if (trim($cat_coan) === '9303D') echo 'selected'; ?>>9303D</option>
                    <option value="9304D" <?php if (trim($cat_coan) === '9304D') echo 'selected'; ?>>9304D</option>
                    <option value="9303I" <?php if (trim($cat_coan) === '9303I') echo 'selected'; ?>>9303I</option>
                    <option value="9313I" <?php if (trim($cat_coan) === '9313I') echo 'selected'; ?>>9313I</option>
                    <option value="9314I" <?php if (trim($cat_coan) === '9314I') echo 'selected'; ?>>9314I</option>
                    <option value="9315I" <?php if (trim($cat_coan) === '9315I') echo 'selected'; ?>>9315I</option>
                    <option value="9316I" <?php if (trim($cat_coan) === '9316I') echo 'selected'; ?>>9316I</option>
                    <option value="9317I" <?php if (trim($cat_coan) === '9317I') echo 'selected'; ?>>9317I</option>
                    <option value="9331I" <?php if (trim($cat_coan) === '9331I') echo 'selected'; ?>>9331I</option>
                    <option value="9323I" <?php if (trim($cat_coan) === '9323I') echo 'selected'; ?>>9323I</option>
                    <option value="9319I" <?php if (trim($cat_coan) === '9319I') echo 'selected'; ?>>9319I</option>
                    <option value="9330I" <?php if (trim($cat_coan) === '9330I') echo 'selected'; ?>>9330I</option>
                    <option value="9321I" <?php if (trim($cat_coan) === '9321I') echo 'selected'; ?>>9321I</option>
                    <option value="9322I" <?php if (trim($cat_coan) === '9322I') echo 'selected'; ?>>9322I</option>
                    <option value="9347I" <?php if (trim($cat_coan) === '9347I') echo 'selected'; ?>>9347I</option>
                    <option value="9338I" <?php if (trim($cat_coan) === '9338I') echo 'selected'; ?>>9338I</option>
                    <option value="9303C" <?php if (trim($cat_coan) === '9303C') echo 'selected'; ?>>9303C</option>
                    <option value="9313C" <?php if (trim($cat_coan) === '9313C') echo 'selected'; ?>>9313C</option>
                    <option value="9314C" <?php if (trim($cat_coan) === '9314C') echo 'selected'; ?>>9314C</option>
                    <option value="9315C" <?php if (trim($cat_coan) === '9315C') echo 'selected'; ?>>9315C</option>
                    <option value="9316C" <?php if (trim($cat_coan) === '9316C') echo 'selected'; ?>>9316C</option>
                    <option value="9317C" <?php if (trim($cat_coan) === '9317C') echo 'selected'; ?>>9317C</option>
                    <option value="9331C" <?php if (trim($cat_coan) === '9331C') echo 'selected'; ?>>9331C</option>
                    <option value="9333C" <?php if (trim($cat_coan) === '9333C') echo 'selected'; ?>>9333C</option>
                    <option value="9323C" <?php if (trim($cat_coan) === '9323C') echo 'selected'; ?>>9323C</option>
                    <option value="9319C" <?php if (trim($cat_coan) === '9319C') echo 'selected'; ?>>9319C</option>
                    <option value="9330C" <?php if (trim($cat_coan) === '9330C') echo 'selected'; ?>>9330C</option>
                    <option value="9321C" <?php if (trim($cat_coan) === '9321C') echo 'selected'; ?>>9321C</option>
                    <option value="9322C" <?php if (trim($cat_coan) === '9322C') echo 'selected'; ?>>9322C</option>
                    <option value="9347C" <?php if (trim($cat_coan) === '9347C') echo 'selected'; ?>>9347C</option>
                    <option value="9338C" <?php if (trim($cat_coan) === '9338C') echo 'selected'; ?>>9338C</option>
                    <option value="9399A" <?php if (trim($cat_coan) === '9399A') echo 'selected'; ?>>9399A</option>
                    <option value="9314A" <?php if (trim($cat_coan) === '9314A') echo 'selected'; ?>>9314A</option>
                    <option value="9315A" <?php if (trim($cat_coan) === '9315A') echo 'selected'; ?>>9315A</option>
                    <option value="9316A" <?php if (trim($cat_coan) === '9316A') echo 'selected'; ?>>9316A</option>
                    <option value="9317A" <?php if (trim($cat_coan) === '9317A') echo 'selected'; ?>>9317A</option>
                    <option value="9331A" <?php if (trim($cat_coan) === '9331A') echo 'selected'; ?>>9331A</option>
                    <option value="9323A" <?php if (trim($cat_coan) === '9323A') echo 'selected'; ?>>9323A</option>
                    <option value="9319A" <?php if (trim($cat_coan) === '9319A') echo 'selected'; ?>>9319A</option>
                    <option value="9330A" <?php if (trim($cat_coan) === '9330A') echo 'selected'; ?>>9330A</option>
                    <option value="9321A" <?php if (trim($cat_coan) === '9321A') echo 'selected'; ?>>9321A</option>
                    <option value="9322A" <?php if (trim($cat_coan) === '9322A') echo 'selected'; ?>>9322A</option>
                    <option value="9347A" <?php if (trim($cat_coan) === '9347A') echo 'selected'; ?>>9347A</option>
                    <option value="9391A" <?php if (trim($cat_coan) === '9391A') echo 'selected'; ?>>9391A</option>
                </select>
            </div>
            <div class="form-group"><div class="form-header">Tarifa Coan</div>
                <!-- visible formatted read-only display -->
                <input aria-label="Tarifa Coan" type="text" class="form-control tarifa-coan-display" readonly value="<?php echo ($tarifa_coan !== '' && $tarifa_coan !== null) ? number_format((float)$tarifa_coan, 2, ',', '.') : ''; ?>">
                <!-- hidden numeric field submitted to server -->
                <input type="hidden" name="tarifa_coan" id="tarifa_coan_hidden" value="<?php echo ($tarifa_coan !== '' && $tarifa_coan !== null) ? number_format((float)$tarifa_coan, 2, '.', '') : ''; ?>">
            </div>
            <div class="form-group"><div class="form-header">Nombre Categoría</div>
                <!-- visible read-only display -->
                <input aria-label="Nombre Categoría" type="text" class="form-control nombre-categoria-display" readonly value="<?php echo htmlspecialchars($nombre_categoria, ENT_QUOTES, 'UTF-8'); ?>">
                <!-- hidden field submitted to server -->
                <input type="hidden" name="nombre_categoria" id="nombre_categoria_hidden" value="<?php echo htmlspecialchars($nombre_categoria, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group"><div class="form-header">HH Promedio/Mes</div><input aria-label="HH Promedio/Mes" type="number" name="hh_promedio_mes" value="<?php echo $hh_promedio_mes; ?>"></div>
            <div class="form-group"><div class="form-header">Horas Diarias</div><input aria-label="Horas Diarias" type="number" name="horas_diarias" value="<?php echo $horas_diarias; ?>"></div>
        </div>
    </fieldset>

    <fieldset class="form-section">
        <legend>Notas Adicionales</legend>
        <div class="form-group"><div class="form-header">Notas</div><textarea aria-label="Notas" name="notas" style="width: 100%;"><?php echo $notas; ?></textarea></div>
    </fieldset>

    <div class="form-group">
        <?php if ($edit_state == false): ?>
            <button type="submit" name="save" class="btn btn-primary">Guardar Empleado</button>
        <?php else: ?>
            <button type="submit" name="update" class="btn btn-primary">Actualizar Empleado</button>
        <?php endif; ?>
        <a href="Listado_Empleados.php" class="btn btn-secondary">Listado Empleados</a>
    </div>
</form>

<?php
require_once 'includes/footer.php';
?>

<script>
// Auto-fill tarifa_coan (readonly) based on selected cat_coan
(function(){
    const tarifaMap = {
        '9302D': 331700,'9303D':206600,'9304D':97700,
        '9303I':141800,'9313I':122900,'9314I':130600,'9315I':117000,'9316I':115600,'9317I':98500,'9331I':76800,'9323I':55200,'9319I':13500,'9330I':75900,'9321I':63400,'9322I':54500,'9347I':50200,'9338I':36500,
        '9303C':120200,'9313C':74700,'9314C':88200,'9315C':90800,'9316C':67300,'9317C':54100,'9331C':50200,'9333C':45400,'9323C':31400,'9319C':20500,'9330C':46900,'9321C':35700,'9322C':39100,'9347C':36900,'9338C':28200,
        '9399A':88300,'9314A':57000,'9315A':46700,'9316A':32500,'9317A':40400,'9331A':36900,'9323A':22800,'9319A':13400,'9330A':47300,'9321A':26300,'9322A':21500,'9347A':15700,'9391A':15700
    };

    const nombreMap = {
        '9302D':'Dirección Ejecutiva',
        '9303D':'Director Línea de Negocio, Operaciones, Administrativo o Comercial',
        '9304D':'Gerente de Área (Legal, IT, Financiero)',
        '9303I':'Director Proyecto',
        '9313I':'Coordinador de Área',
        '9314I':'Referente Técnico o Coordinador de Proyecto',
        '9315I':'Especialista Principal',
        '9316I':'Especialista Asistente',
        '9317I':'Profesional Especializado',
        '9331I':'Profesional de Diseño',
        '9323I':'Profesional Graduado',
        '9319I':'Estudiante Universitario o Practicante',
        '9330I':'Técnico o Tecnólogo Experto',
        '9321I':'Modelador Experto',
        '9322I':'Modelador o Delineante',
        '9347I':'Técnico Graduado',
        '9338I':'Auxiliar o Conductor',
        '9303C':'Director Proyecto',
        '9313C':'Coordinador de Área',
        '9314C':'Especialista Técnico Experto',
        '9315C':'Especialista Experto Áreas de Soporte',
        '9316C':'Ingeniero Residente',
        '9317C':'Residente Áreas de Soporte',
        '9331C':'Ingeniero Residente Auxiliar',
        '9333C':'Residente Auxiliar Áreas de Soporte',
        '9323C':'Profesional Graduado',
        '9319C':'Estudiante Universitario o Practicante',
        '9330C':'Topógrafo o Inspector Experto',
        '9321C':'Inspector Técnico',
        '9322C':'Inspector Áreas de Soporte',
        '9347C':'Técnico Graduado',
        '9338C':'Auxiliar o Conductor',
        '9399A':'Asesor',
        '9314A':'Jefe de Área',
        '9315A':'Coordinador Administrativo',
        '9316A':'Especialista',
        '9317A':'Profesional de Apoyo',
        '9331A':'Profesional Administrativo',
        '9323A':'Analista Profesional',
        '9319A':'Estudiante Universitario o Practicante',
        '9330A':'Tecnólogo Especializado',
        '9321A':'Analista Especializado',
        '9322A':'Analista Comercial o Administrativo',
        '9347A':'Analista Junior',
        '9391A':'Auxiliar Administrativo'
    };

    function fmt(n){
        try { return new Intl.NumberFormat('es-CO',{minimumFractionDigits:2, maximumFractionDigits:2}).format(Number(n)); }
        catch(e){ return (Number(n) || 0).toFixed(2); }
    }

    function setTarifaFor(cat){
        const hid = document.getElementById('tarifa_coan_hidden');
        const disp = document.querySelector('.tarifa-coan-display');
        if (!hid || !disp) return;
        const v = tarifaMap[cat] !== undefined ? tarifaMap[cat] : '';
        if (v === ''){ hid.value = ''; disp.value = ''; }
        else { hid.value = Number(v).toFixed(2); disp.value = fmt(v); }
    }

    function setNombreFor(cat){
        const hid = document.getElementById('nombre_categoria_hidden');
        const disp = document.querySelector('.nombre-categoria-display');
        if (!hid || !disp) return;
        const v = nombreMap[cat] !== undefined ? nombreMap[cat] : '';
        if (v === ''){ hid.value = ''; disp.value = ''; }
        else { hid.value = v; disp.value = v; }
    }

    document.addEventListener('DOMContentLoaded', function(){
        const sel = document.querySelector('[name="cat_coan"]');
        if (!sel) return;
        // initial fill
        setTarifaFor(sel.value);
        setNombreFor(sel.value);
        // update on change
        sel.addEventListener('change', function(){ setTarifaFor(this.value); setNombreFor(this.value); });
    });
})();
</script>
