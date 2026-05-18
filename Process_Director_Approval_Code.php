
<?php
// aprobacion_director.php
// Muestra detalle de imputaciones para aprobación del director (ahora usando horas_dia)

header('Content-Type: text/html; charset=utf-8');

require_once 'include.php';
require_once 'config.php';

// Procesar solicitud AJAX para guardar cambios individuales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_director') {
    // Log para debug
    error_log('POST recibido: ' . print_r($_POST, true));
    
    $codigo_affaire = isset($_POST['codigo_affaire']) ? trim($_POST['codigo_affaire']) : '';
    $nombre = isset($_POST['Nom']) ? trim($_POST['Nom']) : '';
    $apellido = isset($_POST['Prenom']) ? trim($_POST['Prenom']) : '';
    $area_funcional = isset($_POST['area_funcional']) ? trim($_POST['area_funcional']) : '';
    $aprobado_director = isset($_POST['aprobado_director']) ? (int)$_POST['aprobado_director'] : 0;
    $rechazado_director = isset($_POST['rechazado_director']) ? (int)$_POST['rechazado_director'] : 0;
    $comentario_director = isset($_POST['comentario_director']) ? trim($_POST['comentario_director']) : '';
    
    error_log("Datos: codigo=$codigo_affaire, nombre=$nombre, apellido=$apellido, aprobado=$aprobado_director, rechazado=$rechazado_director, comentario=$comentario_director");
    
    if ($codigo_affaire === '' || $nombre === '' || $apellido === '') {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos', 'datos' => $_POST]);
        exit;
    }
    
    $sql_update = "UPDATE horas_dia 
             SET aprobado_director = ?, rechazado_director = ?, comentario_director = ?
             WHERE codigo_affaire = ? AND Nom = ? AND Prenom = ? AND Estado_Aprobacion = 'Aprobado En Curso'";
    if ($area_funcional !== '') {
      $sql_update .= " AND area_funcional = ?";
      $stmt_update = $conn->prepare($sql_update);
      $stmt_update->bind_param('iisssss', $aprobado_director, $rechazado_director, $comentario_director, $codigo_affaire, $nombre, $apellido, $area_funcional);
    } else {
      $stmt_update = $conn->prepare($sql_update);
      $stmt_update->bind_param('iissss', $aprobado_director, $rechazado_director, $comentario_director, $codigo_affaire, $nombre, $apellido);
    }
    
    if ($stmt_update->execute()) {
        $affected = $stmt_update->affected_rows;
        error_log("Filas afectadas: $affected");
        echo json_encode(['success' => true, 'message' => 'Cambios guardados correctamente', 'affected_rows' => $affected]);
    } else {
        error_log("Error SQL: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $conn->error]);
    }
    $stmt_update->close();
    exit;
}

// Procesar solicitud AJAX para aprobar todos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'aprobar_todos_director') {
  $codigo_affaire = isset($_POST['codigo_affaire']) ? trim($_POST['codigo_affaire']) : '';
  $area_funcional = isset($_POST['area_funcional']) ? trim($_POST['area_funcional']) : '';
  $comentario_general = isset($_POST['comentario_general']) ? trim($_POST['comentario_general']) : '';
  
  if ($codigo_affaire === '') {
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro del proyecto (codigo_affaire).']);
    exit;
  }
  $sql = "UPDATE horas_dia SET aprobado_director = 1, rechazado_director = 0, comentario_director = ? WHERE codigo_affaire = ? AND Estado_Aprobacion = 'Aprobado En Curso'";
  $types = 'ss';
  $params = [$comentario_general, $codigo_affaire];
  if ($area_funcional !== '') {
    $sql .= " AND area_funcional = ?";
    $types .= 's';
    $params[] = $area_funcional;
  }
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error preparando la consulta.']);
    exit;
  }
  $stmt->bind_param($types, ...$params);
  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Todos los registros aprobados correctamente.']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Error al aprobar todos: ' . $conn->error]);
  }
  $stmt->close();
  exit;
}

// Procesar solicitud AJAX para rechazar todos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'rechazar_todos_director') {
  $codigo_affaire = isset($_POST['codigo_affaire']) ? trim($_POST['codigo_affaire']) : '';
  $area_funcional = isset($_POST['area_funcional']) ? trim($_POST['area_funcional']) : '';
  $comentario_general = isset($_POST['comentario_general']) ? trim($_POST['comentario_general']) : '';
  
  if ($codigo_affaire === '') {
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro del proyecto (codigo_affaire).']);
    exit;
  }
  $sql = "UPDATE horas_dia SET aprobado_director = 0, rechazado_director = 1, comentario_director = ? WHERE codigo_affaire = ? AND Estado_Aprobacion = 'Aprobado En Curso'";
  $types = 'ss';
  $params = [$comentario_general, $codigo_affaire];
  if ($area_funcional !== '') {
    $sql .= " AND area_funcional = ?";
    $types .= 's';
    $params[] = $area_funcional;
  }
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error preparando la consulta.']);
    exit;
  }
  $stmt->bind_param($types, ...$params);
  if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Todos los registros rechazados correctamente.']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Error al rechazar todos: ' . $conn->error]);
  }
  $stmt->close();
  exit;
}

// Leer parámetros
$p = isset($_GET['p']) ? trim($_GET['p']) : '';
$af = isset($_GET['af']) ? trim($_GET['af']) : '';

if ($p === '') {
    http_response_code(400);
    echo '<div class="alert alert-danger m-2">Falta el parámetro del proyecto (codigo_affaire).</div>';
    exit;
}

// Construir SQL con filtro obligatorio por codigo_affaire y opcional por área funcional
// ...existing code...
$sql = "SELECT codigo_affaire, nombre_affaire, Nom, Prenom, 
SUM(tiempo_imputado_horas) AS tiempo_imputado_horas, 
SUM(tiempo_imputado_costo) AS tiempo_imputado_costo, 
aprobado_coordinador, comentario_coordinador, area_funcional, aprobado_director, rechazado_director, comentario_director
FROM horas_dia
WHERE codigo_affaire = ? AND Estado_Aprobacion = 'Aprobado En Curso'";
$params = [$p];
$types = 's';

if ($af !== '') {
    $sql .= " AND area_funcional = ?";
    $params[] = $af;
    $types .= 's';
}

$sql .= " GROUP BY codigo_affaire, nombre_affaire, Nom, Prenom, aprobado_coordinador, comentario_coordinador, area_funcional, aprobado_director, rechazado_director, comentario_director";
$sql .= " ORDER BY Nom, Prenom";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo '<div class="alert alert-danger m-2">Error preparando la consulta.</div>';
  exit;
}

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
  http_response_code(500);
  echo '<div class="alert alert-danger m-2">Error ejecutando la consulta.</div>';
  $stmt->close();
  exit;
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning m-2">Sin registros para los filtros seleccionados.</div>';
    $stmt->close();
    exit;
}

// Renderizar tabla
?>
<!-- SweetAlert2 para diálogos profesionales -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="d-flex justify-content-end mb-2" style="gap:0.5rem;" id="directorCtx" data-p="<?= htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-af="<?= htmlspecialchars($af, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <button type="button" class="btn btn-success btn-sm" id="btnAprobarTodos"
    onclick="(function(){
      try{
        var ctx=document.getElementById('directorCtx');
        var codigoAffaire=(ctx&&ctx.dataset&&ctx.dataset.p)||new URLSearchParams(window.location.search).get('p')||'';
        var areaFuncional=(ctx&&ctx.dataset&&ctx.dataset.af)||new URLSearchParams(window.location.search).get('af')||'';
        var comentarioGeneral=(document.getElementById('comentariosGenerales')&&document.getElementById('comentariosGenerales').value.trim())||'';
        if(!codigoAffaire){alert('Falta el parámetro del proyecto.');return;}
        if(!confirm('¿Está seguro de aprobar todos los registros visualizados?')) return;
        fetch('aprobacion_director.php',{
          method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:'accion=aprobar_todos_director&codigo_affaire='+encodeURIComponent(codigoAffaire)+'&area_funcional='+encodeURIComponent(areaFuncional)+'&comentario_general='+encodeURIComponent(comentarioGeneral)
        }).then(function(r){return r.json()}).then(function(data){
          if(data.success){
            document.querySelectorAll('.chk-aprobado-director').forEach(function(chk){chk.checked=true;});
            document.querySelectorAll('.chk-rechazado-director').forEach(function(chk){chk.checked=false;});
            // Actualizar comentarios en cada fila con el comentario general
            document.querySelectorAll('.txt-comentario-director').forEach(function(inp){inp.value=comentarioGeneral;});
            document.querySelectorAll('tr[data-row-id]').forEach(function(row){row.style.backgroundColor='#d4edda';setTimeout(function(){row.style.backgroundColor='';},1000);});
          }else{alert('Error: '+(data.message||'Error desconocido'));}
        }).catch(function(){alert('Error de conexión al aprobar todos');});
      }catch(e){console.error(e);alert('Error inesperado');}
    })();"><i class="bi bi-check-circle"></i> Aprobar Todos</button>
  <button type="button" class="btn btn-danger btn-sm" id="btnRechazarTodos"
    onclick="(function(){
      try{
        if(!confirm('¿Está seguro de rechazar todos los registros visualizados?')) return;
        document.querySelectorAll('.chk-rechazado-director').forEach(function(chk){chk.checked=true;chk.dispatchEvent(new Event('change'));});
        document.querySelectorAll('.chk-aprobado-director').forEach(function(chk){chk.checked=false;chk.dispatchEvent(new Event('change'));});
      }catch(e){console.error(e);}
    })();"><i class="bi bi-x-circle"></i> Rechazar Todos</button>
</div>

<!-- Cuadro de texto para comentarios generales -->
<div class="mb-3">
  <label for="comentariosGenerales" class="form-label fw-semibold">Comentarios Generales:</label>
  <textarea class="form-control" id="comentariosGenerales" name="comentariosGenerales" rows="3" placeholder="Escriba aquí sus comentarios generales sobre este proyecto..."
            oninput="var val=this.value;document.querySelectorAll('.txt-comentario-director').forEach(function(c){c.value=val;});"></textarea>
  <small class="text-muted">Este comentario se copiará a todos los registros. Luego marque aprobar/rechazar para guardar.</small>
</div>

<div class="table-responsive w-100" style="overflow-x:auto;">
  <table class="table table-bordered table-hover align-middle mb-0 w-100" id="tablaDirector" style="background:#fff;min-width:900px;">
    <thead class="table-light align-middle text-center" style="font-size:1.05em;">
      <tr>
        <th style="min-width:110px;">Código Affaire</th>
        <th style="min-width:180px;">Nombre Affaire</th>
        <th style="min-width:110px;">Nombre</th>
        <th style="min-width:110px;">Apellido</th>
        <th style="min-width:90px;">Horas Imputadas</th>
        <th style="min-width:120px;">Costo Imputado</th>
        <th style="min-width:70px;">Aprob. Coord.</th>
        <th style="min-width:140px;">Comentario Coord.</th>
        <th style="min-width:120px;">Área Funcional</th>
        <!-- <th style="min-width:70px;">Aprob. Director</th> -->
        <!-- <th style="min-width:70px;">Rech. Director</th> -->
        <!-- <th style="min-width:180px;">Comentario Director</th> -->
        <th style="min-width:70px;">Detalle</th>
      </tr>
    </thead>
    <tbody style="font-size:0.98em;">
      <?php 
      $rowIndex = 0;
      while ($row = $result->fetch_assoc()):
        $rowIndex++;
        $horas = is_numeric($row['tiempo_imputado_horas']) ? (float)$row['tiempo_imputado_horas'] : null;
        $costo = is_numeric($row['tiempo_imputado_costo']) ? (float)$row['tiempo_imputado_costo'] : null;
        $aprobado = isset($row['aprobado_coordinador']) ? $row['aprobado_coordinador'] : '';
        $coment = isset($row['comentario_coordinador']) ? $row['comentario_coordinador'] : '';
        // Interpretar aprobado_coordinador como booleano para checkbox
        $apStr = strtolower(trim((string)$aprobado));
        if (is_numeric($aprobado)) {
          $isChecked = ((int)$aprobado) === 1;
        } else {
          $isChecked = in_array($apStr, ['si','sí','yes','y','true','aprobado','approved']);
        }
        // Director fields
        $aprobadoDirector = isset($row['aprobado_director']) ? $row['aprobado_director'] : '';
        $rechazadoDirector = isset($row['rechazado_director']) ? $row['rechazado_director'] : '';
        $comentarioDirector = isset($row['comentario_director']) ? $row['comentario_director'] : '';
        // Interpretar aprobado_director y rechazado_director como booleanos para checkbox
        $aprobadoDirectorChecked = (is_numeric($aprobadoDirector) ? ((int)$aprobadoDirector) === 1 : in_array(strtolower(trim((string)$aprobadoDirector)), ['si','sí','yes','y','true','aprobado','approved']));
        $rechazadoDirectorChecked = (is_numeric($rechazadoDirector) ? ((int)$rechazadoDirector) === 1 : in_array(strtolower(trim((string)$rechazadoDirector)), ['si','sí','yes','y','true','rechazado','rejected']));
      ?>
      <tr data-codigo-affaire="<?= htmlspecialchars($row['codigo_affaire'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" 
          data-nombre="<?= htmlspecialchars($row['Nom'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" 
          data-apellido="<?= htmlspecialchars($row['Prenom'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          data-area="<?= htmlspecialchars($row['area_funcional'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          data-row-id="<?= $rowIndex ?>">
        <td><?= htmlspecialchars($row['codigo_affaire'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($row['nombre_affaire'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($row['Nom'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($row['Prenom'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td class="text-success fw-semibold"><?= is_null($horas) ? '-' : number_format($horas, 2, ',', '.') ?></td>
        <td class="text-success fw-semibold"><?= is_null($costo) ? '-' : '$ ' . number_format($costo, 0, ',', '.') ?></td>
        <td class="text-center"><input type="checkbox" class="form-check-input" disabled <?= $isChecked ? 'checked' : '' ?> title="<?= htmlspecialchars((string)$aprobado, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></td>
        <td><?= htmlspecialchars($coment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($row['area_funcional'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
        <!-- <td class="text-center">
      <input type="checkbox" class="form-check-input chk-aprobado-director" 
             <?= $aprobadoDirectorChecked ? 'checked' : '' ?> 
             data-row-id="<?= $rowIndex ?>"
             onchange="(function(chk){var row=chk.closest('tr');var txt=row.querySelector('.txt-comentario-director');var gen=document.getElementById('comentariosGenerales');if(txt&&gen&&(txt.value.trim()==='')){txt.value=gen.value;}if(chk.checked){var r=row.querySelector('.chk-rechazado-director');if(r)r.checked=false;}var data={accion:'guardar_director',codigo_affaire:row.dataset.codigoAffaire,Nom:row.dataset.nombre,Prenom:row.dataset.apellido,area_funcional:row.dataset.area,aprobado_director:row.querySelector('.chk-aprobado-director').checked?1:0,rechazado_director:row.querySelector('.chk-rechazado-director').checked?1:0,comentario_director:(txt?txt.value.trim():'')};fetch('aprobacion_director.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:Object.keys(data).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(data[k])).join('&')}).then(r=>r.json()).then(d=>{if(d.success){row.style.backgroundColor='#d4edda';setTimeout(()=>row.style.backgroundColor='',1000);}else{alert('Error: '+d.message);}}).catch(()=>alert('Error de conexión'));})(this);"
             title="<?= htmlspecialchars((string)$aprobadoDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </td> -->
    <!-- <td class="text-center">
      <input type="checkbox" class="form-check-input chk-rechazado-director" 
             <?= $rechazadoDirectorChecked ? 'checked' : '' ?> 
             data-row-id="<?= $rowIndex ?>"
             onchange="(function(chk){var row=chk.closest('tr');var txt=row.querySelector('.txt-comentario-director');var gen=document.getElementById('comentariosGenerales');if(txt&&gen&&(txt.value.trim()==='')){txt.value=gen.value;}if(chk.checked){var a=row.querySelector('.chk-aprobado-director');if(a)a.checked=false;}var data={accion:'guardar_director',codigo_affaire:row.dataset.codigoAffaire,Nom:row.dataset.nombre,Prenom:row.dataset.apellido,area_funcional:row.dataset.area,aprobado_director:row.querySelector('.chk-aprobado-director').checked?1:0,rechazado_director:row.querySelector('.chk-rechazado-director').checked?1:0,comentario_director:(txt?txt.value.trim():'')};fetch('aprobacion_director.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:Object.keys(data).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(data[k])).join('&')}).then(r=>r.json()).then(d=>{if(d.success){row.style.backgroundColor='#d4edda';setTimeout(()=>row.style.backgroundColor='',1000);}else{alert('Error: '+d.message);}}).catch(()=>alert('Error de conexión'));})(this);"
             title="<?= htmlspecialchars((string)$rechazadoDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </td> -->
    <!-- <td>
      <input type="text" class="form-control form-control-sm txt-comentario-director" 
             value="<?= htmlspecialchars($comentarioDirector, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
             data-row-id="<?= $rowIndex ?>"
             placeholder="Ingrese comentario">
    </td> -->
    <td class="text-center">
      <button type="button" class="btn btn-sm btn-outline-primary btn-view-detalle-empleado" 
              data-nombre="<?= htmlspecialchars($row['Nom'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              data-apellido="<?= htmlspecialchars($row['Prenom'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              data-codigo="<?= htmlspecialchars($row['codigo_affaire'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              data-area="<?= htmlspecialchars($row['area_funcional'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              onclick="abrirModalDetalleEmpleado(this)"
              title="Ver Detalle">
        <i class="bi bi-eye"></i>
      </button>
    </td>
  </tr>
  <?php endwhile; ?>
</tbody>
<script>
// Usar delegación de eventos para que funcione en modales
document.addEventListener('change', function(e) {
  // Si el cambio es en un checkbox de aprobado director
  if (e.target.classList.contains('chk-aprobado-director')) {
    const rowId = e.target.dataset.rowId;
    const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
    
    if (e.target.checked && row) {
      const chkRechazado = row.querySelector('.chk-rechazado-director');
      if (chkRechazado) chkRechazado.checked = false;
    }
    
    guardarCambioDirector(rowId);
  }
  
  // Si el cambio es en un checkbox de rechazado director
  if (e.target.classList.contains('chk-rechazado-director')) {
    const rowId = e.target.dataset.rowId;
    const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
    
    if (e.target.checked && row) {
      const chkAprobado = row.querySelector('.chk-aprobado-director');
      if (chkAprobado) chkAprobado.checked = false;
    }
    
    guardarCambioDirector(rowId);
  }
});

// Función para guardar cambios individuales
function guardarCambioDirector(rowId) {
  const row = document.querySelector(`tr[data-row-id="${rowId}"]`);
  if (!row) return;

  const codigoAffaire = row.dataset.codigoAffaire;
  const nombre = row.dataset.nombre;
  const apellido = row.dataset.apellido;
  const areaFuncional = row.dataset.area;

  const aprobado = row.querySelector('.chk-aprobado-director').checked ? 1 : 0;
  const rechazado = row.querySelector('.chk-rechazado-director').checked ? 1 : 0;
  const txtComentario = row.querySelector('.txt-comentario-director');
  const comentario = txtComentario ? txtComentario.value.trim() : '';

  fetch('aprobacion_director.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `accion=guardar_director&codigo_affaire=${encodeURIComponent(codigoAffaire)}&Nom=${encodeURIComponent(nombre)}&Prenom=${encodeURIComponent(apellido)}&area_funcional=${encodeURIComponent(areaFuncional)}&aprobado_director=${aprobado}&rechazado_director=${rechazado}&comentario_director=${encodeURIComponent(comentario)}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      row.style.backgroundColor = '#d4edda';
      setTimeout(() => { row.style.backgroundColor = ''; }, 1000);
    } else {
      alert('Error al guardar: ' + (data.message || 'Error desconocido'));
    }
  })
  .catch(() => {
    alert('Error de conexión al guardar los cambios');
  });
}

// Listeners para comentarios (guardar al perder foco o Enter) - con delegación
document.addEventListener('blur', function(e) {
  if (e.target.classList.contains('txt-comentario-director')) {
    guardarCambioDirector(e.target.dataset.rowId);
  }
}, true);

document.addEventListener('keypress', function(e) {
  if (e.target.classList.contains('txt-comentario-director') && e.key === 'Enter') {
    e.preventDefault();
    e.target.blur();
  }
});

// Botón Aprobar Todos (masivo) - con delegación
document.addEventListener('click', function(e) {
  if (e.target.id === 'btnAprobarTodos' || e.target.closest('#btnAprobarTodos')) {
    const ctx = document.getElementById('directorCtx');
    const codigoAffaire = (ctx && ctx.dataset && ctx.dataset.p) ? ctx.dataset.p : (new URLSearchParams(window.location.search).get('p') || '');
    const areaFuncional = (ctx && ctx.dataset && ctx.dataset.af) ? ctx.dataset.af : (new URLSearchParams(window.location.search).get('af') || '');
    const comentarioGeneral = document.getElementById('comentariosGenerales') ? document.getElementById('comentariosGenerales').value.trim() : '';
    
    if (!codigoAffaire) {
      alert('Falta el parámetro del proyecto.');
      return;
    }
    if (!confirm('¿Está seguro de aprobar todos los registros visualizados?')) return;

    fetch('aprobacion_director.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `accion=aprobar_todos_director&codigo_affaire=${encodeURIComponent(codigoAffaire)}&area_funcional=${encodeURIComponent(areaFuncional)}&comentario_general=${encodeURIComponent(comentarioGeneral)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.querySelectorAll('.chk-aprobado-director').forEach(chk => { chk.checked = true; });
        document.querySelectorAll('.chk-rechazado-director').forEach(chk => { chk.checked = false; });
        document.querySelectorAll('.txt-comentario-director').forEach(txt => { txt.value = comentarioGeneral; });
        document.querySelectorAll('tr[data-row-id]').forEach(row => {
          row.style.backgroundColor = '#d4edda';
          setTimeout(() => { row.style.backgroundColor = ''; }, 1000);
        });
      } else {
        alert('Error: ' + (data.message || 'Error desconocido'));
      }
    })
    .catch(() => {
      alert('Error de conexión al aprobar todos');
    });
  }
  
  // Botón Rechazar Todos
  if (e.target.id === 'btnRechazarTodos' || e.target.closest('#btnRechazarTodos')) {
  
  // Botón Rechazar Todos
  if (e.target.id === 'btnRechazarTodos' || e.target.closest('#btnRechazarTodos')) {
    const ctx = document.getElementById('directorCtx');
    const codigoAffaire = (ctx && ctx.dataset && ctx.dataset.p) ? ctx.dataset.p : (new URLSearchParams(window.location.search).get('p') || '');
    const areaFuncional = (ctx && ctx.dataset && ctx.dataset.af) ? ctx.dataset.af : (new URLSearchParams(window.location.search).get('af') || '');
    const comentarioGeneral = document.getElementById('comentariosGenerales') ? document.getElementById('comentariosGenerales').value.trim() : '';
    
    if (!codigoAffaire) {
      alert('Falta el parámetro del proyecto.');
      return;
    }
    if (!confirm('¿Está seguro de rechazar todos los registros visualizados?')) return;
    
    fetch('aprobacion_director.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `accion=rechazar_todos_director&codigo_affaire=${encodeURIComponent(codigoAffaire)}&area_funcional=${encodeURIComponent(areaFuncional)}&comentario_general=${encodeURIComponent(comentarioGeneral)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.querySelectorAll('.chk-rechazado-director').forEach(chk => { chk.checked = true; });
        document.querySelectorAll('.chk-aprobado-director').forEach(chk => { chk.checked = false; });
        document.querySelectorAll('.txt-comentario-director').forEach(txt => { txt.value = comentarioGeneral; });
        document.querySelectorAll('tr[data-row-id]').forEach(row => {
          row.style.backgroundColor = '#f8d7da';
          setTimeout(() => { row.style.backgroundColor = ''; }, 1000);
        });
      } else {
        alert('Error: ' + (data.message || 'Error desconocido'));
      }

    })
    .catch(() => {
      alert('Error de conexión al rechazar todos');
    });
  }
});

document.addEventListener('DOMContentLoaded', function() {
    // Oculta las columnas de Aprob. Director, Rech. Director y Comentario Director en todas las tablas
    const ths = document.querySelectorAll('th');
    ths.forEach(function(th, idx) {
        const text = th.textContent.trim().toLowerCase();
        if (text.includes('aprob') || text.includes('rech') || text.includes('comentario')) {
            th.style.display = 'none';
            // Oculta las celdas correspondientes en cada fila
            const table = th.closest('table');
            if (table) {
                const rows = table.querySelectorAll('tr');
                rows.forEach(function(row) {
                    const tds = row.querySelectorAll('td, th');
                    if (tds.length > idx) {
                        tds[idx].style.display = 'none';
                    }
                });
            }
        }
    });
});
</script>
<style>
.chk-aprobado-director, .chk-rechazado-director, .txt-comentario-director {
    display: none !important;
}
</style>
<?php
$stmt->close();
?>
