<?php
// This fragment has been deprecated. The project no longer queries the
// database view `costo_asginado_resumen`. Use get_resumen_fragment_new.php
// which renders the resumen without depending on that view.
header('Content-Type: text/html; charset=utf-8');
echo '<div id="resumen-card" class="card p-4 mt-4"><div class="card-body"><h5 class="card-title mb-3">Resumen Ejecutivo de Proyectos</h5><div class="alert alert-info">Fragmento deshabilitado: la vista <code>costo_asginado_resumen</code> ya no se consulta. Utilice la versión actualizada.</div></div></div>';
exit();
