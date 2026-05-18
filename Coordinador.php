<?php
ob_start();
// Eliminar BOM y asegurar que no haya salida antes de los headers
session_start();
require_once 'include.php';
require_once __DIR__ . '/vendor/autoload.php';

// Verificar si el usuario está logueado, si no, redirigir a login
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

// Incluir ajuste de encabezado de tabla (después de la verificación de sesión)
echo '<link rel="stylesheet" href="css/tabla-encabezado-ajuste.css">';
echo '<link rel="stylesheet" href="css/tabla-ceco-ajuste.css">';
echo '<link rel="stylesheet" href="css/asignacion-comentario-badge.css">';
echo '<link rel="stylesheet" href="css/asignacion-celda-corner.css">';

// Incluir encabezado global (contiene encabezado.png en la esquina superior izquierda)
// Todas las redirecciones y procesamiento POST deben ocurrir antes de incluir archivos que generen salida

// --- Lógica de guardado y redirección ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_asignacion'])) {
    // ...existing code de procesamiento POST y redirección...
    // (copiado desde el bloque POST original, hasta el header/exit final)
    // ...
    // (el resto del código POST ya está antes de los includes)
}


require_once 'includes/header.php';
require_once 'menu.php';

// Usar $conn definido en include.php/config.php
if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Error: No se pudo establecer la conexión a la base de datos.');
}

/** @var mysqli $conn */
$all_columns = [];
$form_columns = [];
$primary_key_column = '';
$pk_type = 's'; // Por defecto string
$sql_columns = "SELECT * FROM asignación LIMIT 1";
if ($result = $conn->query($sql_columns)) {
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        $all_columns[] = $field->name;
        if ($field->flags & MYSQLI_PRI_KEY_FLAG) {
            $primary_key_column = $field->name;
            if ($field->type == MYSQLI_TYPE_LONG || $field->type == MYSQLI_TYPE_LONGLONG || $field->type == MYSQLI_TYPE_INT24) {
                $pk_type = 'i';
            }
        }
        if (!($field->flags & MYSQLI_PRI_KEY_FLAG && $field->flags & MYSQLI_AUTO_INCREMENT_FLAG)) {
            $form_columns[] = $field->name;
        }
    }
    $result->free();

    // Excluir el campo Nombre_Empleado_Completo del conjunto de campos del formulario
    $form_columns = array_values(array_filter($form_columns, function($c){
        return $c !== 'Nombre_Empleado_Completo' && $c !== 'Nov_2025' && $c !== 'Dic_2025';
    }));
}

$assignment_cost_month_columns = array_values(array_filter($form_columns, function($column) {
    if (!preg_match('/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_(2025|2026|2027|2028|2029|2030)$/', $column)) {
        return false;
    }
    $parts = explode('_', $column);
    $month = $parts[0] ?? '';
    $year = isset($parts[1]) ? (int)$parts[1] : 0;
    if ($year < 2026) {
        return false;
    }
    if ($year === 2026 && in_array($month, ['Ene', 'Feb'], true)) {
        return false;
    }
    return true;
}));
$extract_assignment_project_name = function($value) {
    $parts = explode('||', (string)$value);
    return trim((string)($parts[0] ?? ''));
};
$normalize_assignment_budget_key = function($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
    }
    $value = strtoupper($value);
    $value = preg_replace('/[^A-Z0-9]/', '', $value);
    return trim((string)$value);
};
$parse_assignment_numeric = function($value) {
    if ($value === null) {
        return 0.0;
    }
    $value = trim((string)$value);
    if ($value === '') {
        return 0.0;
    }
    $normalized = str_replace(',', '', $value);
    return is_numeric($normalized) ? (float)$normalized : 0.0;
};
$sum_assignment_cost_hours_from_row = function(array $row) use ($assignment_cost_month_columns, $parse_assignment_numeric) {
    $total = 0.0;
    foreach ($assignment_cost_month_columns as $column) {
        $total += $parse_assignment_numeric($row[$column] ?? 0);
    }
    return $total;
};
$sum_assignment_cost_hours_from_post = function(array $postData, $rowIndex) use ($assignment_cost_month_columns, $parse_assignment_numeric) {
    $total = 0.0;
    foreach ($assignment_cost_month_columns as $column) {
        $raw = '';
        if (isset($postData[$column]) && is_array($postData[$column]) && array_key_exists($rowIndex, $postData[$column])) {
            $raw = $postData[$column][$rowIndex];
        }
        $total += $parse_assignment_numeric($raw);
    }
    return $total;
};
$split_assignment_employee_name = function($fullName) {
    $parts = preg_split('/\s+/', trim((string)$fullName));
    $parts = array_values(array_filter($parts, function($value) {
        return trim((string)$value) !== '';
    }));
    $count = count($parts);
    if ($count === 0) {
        return ['nom' => '', 'prenom' => ''];
    }
    if ($count === 1) {
        return ['nom' => $parts[0], 'prenom' => ''];
    }
    if ($count === 2) {
        return ['nom' => $parts[0], 'prenom' => $parts[1]];
    }
    $nomParts = array_slice($parts, 0, 2);
    $prenomParts = array_slice($parts, 2);
    if (empty($prenomParts)) {
        $nomParts = [$parts[0]];
        $prenomParts = array_slice($parts, 1);
    }
    return [
        'nom' => implode(' ', $nomParts),
        'prenom' => implode(' ', $prenomParts),
    ];
};

$message = '';
// Check for message from redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$area_funcional = '';
$nombre_usuario = '';
$rol_usuario = '';
if (isset($_SESSION['usuario'])) {
    $usuario = $_SESSION['usuario'];
    $sql = "SELECT Área_Funcional, Nombre_Usuario, ROL FROM login_usuarios WHERE Usuario = ?";
    
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $param_usuario);
        $param_usuario = $usuario;

        if ($stmt->execute()) {
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($area_funcional_result, $nombre_usuario_result, $rol_usuario_result);
                if ($stmt->fetch()) {
                    $area_funcional = $area_funcional_result;
                    $nombre_usuario = $nombre_usuario_result;
                    $rol_usuario = $rol_usuario_result;
                }
            }
        }
        $stmt->close();
    }
}

$normalized_role = strtoupper(trim((string)$rol_usuario));
$is_super_user = ($normalized_role === 'SUPER');
$normalized_user = strtoupper(trim((string)$usuario));
$requested_area_filter = isset($_GET['area_funcional']) ? trim((string)$_GET['area_funcional']) : '';
if ($requested_area_filter === '' && isset($_POST['page_area_funcional_filter'])) {
    $requested_area_filter = trim((string)$_POST['page_area_funcional_filter']);
}
$name_filter = isset($_GET['nombre_proyecto']) ? trim((string)$_GET['nombre_proyecto']) : '';
if ($name_filter === '' && isset($_POST['summary_nombre_proyecto_filter'])) {
    $name_filter = trim((string)$_POST['summary_nombre_proyecto_filter']);
}
$base_dropdown_areas = [
    'Vías',
    'Topografía',
    'BIM',
    'Vías y Topografía',
    'Geotecnia y Pavimentos',
    'Eléctrica',
    'Hidráulica y Medio Ambiente',
    'Arquitectura y Urbanismo',
    'Mecánica',
    'Estructuras',
    'Dirección de Proyectos',
    'Dirección de Ingeniería',
    'Tecnología',
    'Área_Prueba'
];
$excluded_dropdown_areas = [
    'Planificación y Control',
    'Área_Prueba',
    'Tecnología'
];
$visible_dropdown_areas = array_values(array_filter($base_dropdown_areas, function($area) use ($excluded_dropdown_areas) {
    return !in_array($area, $excluded_dropdown_areas, true);
}));
$can_select_multiple_areas = $is_super_user;
$default_to_all_visible_areas = $is_super_user;

// dropdown especificos
if ($normalized_user === 'RARAQUE') {
    $visible_dropdown_areas = ['Vías y Topografía'];
    $can_select_multiple_areas = true;
    $default_to_all_visible_areas = true;
}

if ($normalized_user === 'JGELVEZ') {
    $visible_dropdown_areas = ['Arquitectura y Urbanismo', 'Estructuras'];
    $can_select_multiple_areas = true;
    $default_to_all_visible_areas = true;
}


$build_area_sql_filter = function($connection, $column) use (&$active_area_funcional, $default_to_all_visible_areas, $visible_dropdown_areas) {
    if (!empty($active_area_funcional)) {
        return " AND $column = '" . $connection->real_escape_string($active_area_funcional) . "' ";
    }
    if ($default_to_all_visible_areas && !empty($visible_dropdown_areas)) {
        $escaped = array_map(function($area) use ($connection) {
            return "'" . $connection->real_escape_string($area) . "'";
        }, $visible_dropdown_areas);
        return " AND $column IN (" . implode(', ', $escaped) . ") ";
    }
    return '';
};
$show_retired = false;
if (isset($_GET['show_retired'])) {
    $show_retired = ($_GET['show_retired'] == '1');
} elseif ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST['show_retired'])) {
    $show_retired = ($_POST['show_retired'] == '1');
}
$active_area_funcional = !empty($area_funcional) ? $area_funcional : '';
if ($default_to_all_visible_areas) {
    $active_area_funcional = '';
    if ($requested_area_filter !== '' && in_array($requested_area_filter, $visible_dropdown_areas, true)) {
        $active_area_funcional = $requested_area_filter;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_asignacion'])) {

    // Prepared statement to validate matricula belongs to user's area
    $stmt_validate = null;
    if ($mysqli) {
        $stmt_validate = $mysqli->prepare("SELECT area_funcional FROM vista_empleados WHERE matricula = ? LIMIT 1");
        // if prepare fails, we'll treat as no validation available (but log)
        if (!$stmt_validate) {
            error_log('Coordinador.php: fallo prepare validacion matricula: ' . $mysqli->error);
        }
    }

    // --- SERVER-SIDE: remove rows the user deleted client-side (persist deletion) ---
    // Determine selected matricula from hidden field or first matricula input
    $posted_matricula = isset($_POST['selected_employee_id']) ? trim($_POST['selected_employee_id']) : '';
    if ($posted_matricula === '' && isset($_POST['matricula']) && is_array($_POST['matricula']) && count($_POST['matricula'])>0) {
        $posted_matricula = trim($_POST['matricula'][0]);
    }

    // Capture posted centro_costos array (if present) so server-side logic can fallback to it
    $posted_centro_costos = [];
    if (isset($_POST['centro_costos']) && is_array($_POST['centro_costos'])) {
        $posted_centro_costos = $_POST['centro_costos'];
    }

    $skip_asignacion_save = false;
    $pto_validation_errors = [];
    if (!empty($assignment_cost_month_columns) && !empty($form_columns) && isset($_POST[$form_columns[0]]) && is_array($_POST[$form_columns[0]])) {
        $row_count_for_budget = count($_POST[$form_columns[0]]);
        $submitted_project_names = [];
        $submitted_project_cecos = [];

        for ($i = 0; $i < $row_count_for_budget; $i++) {
            $project_name = isset($_POST['nombre_proyecto'][$i]) ? $extract_assignment_project_name($_POST['nombre_proyecto'][$i]) : '';
            $project_ceco = isset($posted_centro_costos[$i]) ? trim((string)$posted_centro_costos[$i]) : '';
            if ($project_ceco === '' && isset($_POST['centro_costos'][$i])) {
                $project_ceco = trim((string)$_POST['centro_costos'][$i]);
            }
            if ($project_name !== '') {
                $submitted_project_names[] = $project_name;
            }
            if ($project_ceco !== '') {
                $submitted_project_cecos[] = $project_ceco;
            }
        }

        $submitted_project_names = array_values(array_unique(array_filter($submitted_project_names, function($value) {
            return trim((string)$value) !== '';
        })));
        $submitted_project_cecos = array_values(array_unique(array_filter($submitted_project_cecos, function($value) {
            return trim((string)$value) !== '';
        })));

        if ($posted_matricula !== '' && (!empty($submitted_project_names) || !empty($submitted_project_cecos))) {
            $selected_tarifa_coan = 0.0;
            $selected_area_for_budget = '';
            if ($stmt_budget_emp = $mysqli->prepare("SELECT area_funcional, tarifa_coan FROM vista_empleados WHERE matricula = ? LIMIT 1")) {
                $stmt_budget_emp->bind_param('s', $posted_matricula);
                if ($stmt_budget_emp->execute()) {
                    $res_budget_emp = $stmt_budget_emp->get_result();
                    if ($budget_emp_row = $res_budget_emp->fetch_assoc()) {
                        $selected_area_for_budget = trim((string)($budget_emp_row['area_funcional'] ?? ''));
                        $selected_tarifa_coan = isset($budget_emp_row['tarifa_coan']) ? (float)$budget_emp_row['tarifa_coan'] : 0.0;
                    }
                    $res_budget_emp->free();
                }
                $stmt_budget_emp->close();
            }

            $budget_summary_map = [];
            $summary_sql = "SELECT 
                gp.PROYECTO,
                p.nombre_proyecto,
                COALESCE(cv.total_valorizado_2025, 0) AS total_valorizado_2025,
                (SELECT SUM(
                    (`ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
                    `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
                    `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
                    `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`) * `TARIFA COAN 2`)
                FROM gastos_personal gp2
                WHERE gp2.PROYECTO = gp.PROYECTO AND gp2.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`
                ) AS total_costo,
                COALESCE(
                    vcm.Mar_2026_Costo + vcm.Abr_2026_Costo + vcm.May_2026_Costo + vcm.Jun_2026_Costo + vcm.Jul_2026_Costo + vcm.Ago_2026_Costo + vcm.Sep_2026_Costo + vcm.Oct_2026_Costo + vcm.Nov_2026_Costo + vcm.Dic_2026_Costo +
                    vcm.Ene_2027_Costo + vcm.Feb_2027_Costo + vcm.Mar_2027_Costo + vcm.Abr_2027_Costo + vcm.May_2027_Costo + vcm.Jun_2027_Costo + vcm.Jul_2027_Costo + vcm.Ago_2027_Costo + vcm.Sep_2027_Costo + vcm.Oct_2027_Costo + vcm.Nov_2027_Costo + vcm.Dic_2027_Costo +
                    vcm.Ene_2028_Costo + vcm.Feb_2028_Costo + vcm.Mar_2028_Costo + vcm.Abr_2028_Costo + vcm.May_2028_Costo + vcm.Jun_2028_Costo + vcm.Jul_2028_Costo + vcm.Ago_2028_Costo + vcm.Sep_2028_Costo + vcm.Oct_2028_Costo + vcm.Nov_2028_Costo + vcm.Dic_2028_Costo +
                    vcm.Ene_2029_Costo + vcm.Feb_2029_Costo + vcm.Mar_2029_Costo + vcm.Abr_2029_Costo + vcm.May_2029_Costo + vcm.Jun_2029_Costo + vcm.Jul_2029_Costo + vcm.Ago_2029_Costo + vcm.Sep_2029_Costo + vcm.Oct_2029_Costo + vcm.Nov_2029_Costo + vcm.Dic_2029_Costo +
                    vcm.Ene_2030_Costo + vcm.Feb_2030_Costo + vcm.Mar_2030_Costo + vcm.Abr_2030_Costo + vcm.May_2030_Costo + vcm.Jun_2030_Costo + vcm.Jul_2030_Costo + vcm.Ago_2030_Costo + vcm.Sep_2030_Costo + vcm.Oct_2030_Costo + vcm.Nov_2030_Costo + vcm.Dic_2030_Costo,
                0) AS TOTAL_COSTO_ASIGNADO,
                COALESCE((
                    SELECT SUM(hd.`tiempo_imputado_costo`)
                    FROM horas_dia hd
                    WHERE hd.`codigo_affaire` = gp.PROYECTO
                      AND hd.`area_funcional` = gp.`ÁREA FUNCIONAL`
                      AND hd.`Estado_Aprobacion` = 'Aprobado'
                ), 0) AS total_costo_imputado_aprobado
            FROM gastos_personal gp
            LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
            LEFT JOIN vista_costo_mensual_por_area_proyecto_cc vcm ON gp.`ÁREA FUNCIONAL` = vcm.area_funcional AND p.nombre_proyecto = vcm.nombre_proyecto AND p.centro_costos = vcm.centro_costos
            LEFT JOIN (
                SELECT 
                    CECO_CONEXION,
                    `ÁREA FUNCIONAL`,
                    SUM(`ene_25`+`feb_25`+`mar_25`+`abr_25`+`may_25`+`jun_25`+`jul_25`+`ago_25`+`sep_25`+`oct_25`+`nov_25`+`dic_25`) AS total_valorizado_2025
                FROM costo_valorizado
                GROUP BY CECO_CONEXION, `ÁREA FUNCIONAL`
            ) cv ON gp.PROYECTO = cv.CECO_CONEXION AND gp.`ÁREA FUNCIONAL` = cv.`ÁREA FUNCIONAL`
            WHERE 1=1 ";

            if ($selected_area_for_budget !== '') {
                $summary_sql .= " AND gp.`ÁREA FUNCIONAL` = '" . $mysqli->real_escape_string($selected_area_for_budget) . "' ";
            } else {
                $summary_sql .= $build_area_sql_filter($mysqli, 'gp.`ÁREA FUNCIONAL`');
            }

            $summary_filters = [];
            if (!empty($submitted_project_names)) {
                $escaped_names = array_map(function($value) use ($mysqli) {
                    return "'" . $mysqli->real_escape_string($value) . "'";
                }, $submitted_project_names);
                $summary_filters[] = "p.nombre_proyecto IN (" . implode(', ', $escaped_names) . ")";
            }
            if (!empty($submitted_project_cecos)) {
                $escaped_cecos = array_map(function($value) use ($mysqli) {
                    return "'" . $mysqli->real_escape_string($value) . "'";
                }, $submitted_project_cecos);
                $summary_filters[] = "gp.PROYECTO IN (" . implode(', ', $escaped_cecos) . ")";
            }
            if (!empty($summary_filters)) {
                $summary_sql .= " AND (" . implode(' OR ', $summary_filters) . ") ";
            }
            $summary_sql .= "GROUP BY gp.PROYECTO, p.nombre_proyecto, gp.`ÁREA FUNCIONAL`";

            if ($summary_result = $mysqli->query($summary_sql)) {
                while ($summary_row = $summary_result->fetch_assoc()) {
                    $project_name = trim((string)($summary_row['nombre_proyecto'] ?? ''));
                    $project_ceco = trim((string)($summary_row['PROYECTO'] ?? ''));
                    $label = $project_name !== '' ? $project_name : $project_ceco;
                    $bac_value = isset($summary_row['total_costo']) ? (float)$summary_row['total_costo'] : 0.0;
                    $ac_value = (isset($summary_row['total_valorizado_2025']) ? (float)$summary_row['total_valorizado_2025'] : 0.0)
                        + (isset($summary_row['total_costo_imputado_aprobado']) ? (float)$summary_row['total_costo_imputado_aprobado'] : 0.0);
                    $current_assigned = isset($summary_row['TOTAL_COSTO_ASIGNADO']) ? (float)$summary_row['TOTAL_COSTO_ASIGNADO'] : 0.0;
                    $summary_entry = [
                        'label' => $label,
                        'bac' => $bac_value,
                        'ac' => $ac_value,
                        'current_assigned' => $current_assigned,
                    ];
                    $candidate_keys = array_values(array_unique(array_filter([
                        $normalize_assignment_budget_key($project_name),
                        $normalize_assignment_budget_key($project_ceco),
                    ])));
                    foreach ($candidate_keys as $candidate_key) {
                        $budget_summary_map[$candidate_key] = $summary_entry;
                    }
                }
                $summary_result->free();
            }

            $resolve_budget_key = function($project_name, $project_ceco) use (&$budget_summary_map, $normalize_assignment_budget_key) {
                $project_name_key = $normalize_assignment_budget_key($project_name);
                if ($project_name_key !== '' && isset($budget_summary_map[$project_name_key])) {
                    return $project_name_key;
                }
                $project_ceco_key = $normalize_assignment_budget_key($project_ceco);
                if ($project_ceco_key !== '' && isset($budget_summary_map[$project_ceco_key])) {
                    return $project_ceco_key;
                }
                return $project_name_key !== '' ? $project_name_key : $project_ceco_key;
            };

            $existing_assignment_cost_by_key = [];
            if (!empty($budget_summary_map)) {
                $existing_select_columns = array_merge(['nombre_proyecto', 'centro_costos'], $assignment_cost_month_columns);
                $existing_sql = "SELECT `" . implode('`, `', $existing_select_columns) . "` FROM `asignación` WHERE matricula = ?";
                if ($stmt_existing_assign = $mysqli->prepare($existing_sql)) {
                    $stmt_existing_assign->bind_param('s', $posted_matricula);
                    if ($stmt_existing_assign->execute()) {
                        $res_existing_assign = $stmt_existing_assign->get_result();
                        while ($existing_row = $res_existing_assign->fetch_assoc()) {
                            $existing_hours = $sum_assignment_cost_hours_from_row($existing_row);
                            if ($existing_hours <= 0) {
                                continue;
                            }
                            $existing_name = $extract_assignment_project_name($existing_row['nombre_proyecto'] ?? '');
                            $existing_ceco = trim((string)($existing_row['centro_costos'] ?? ''));
                            $existing_key = $resolve_budget_key($existing_name, $existing_ceco);
                            if ($existing_key === '' || !isset($budget_summary_map[$existing_key])) {
                                continue;
                            }
                            $existing_assignment_cost_by_key[$existing_key] = ($existing_assignment_cost_by_key[$existing_key] ?? 0.0) + ($existing_hours * $selected_tarifa_coan);
                        }
                        $res_existing_assign->free();
                    }
                    $stmt_existing_assign->close();
                }
            }

            $submitted_assignment_cost_by_key = [];
            $submitted_rows_by_key = [];
            for ($i = 0; $i < $row_count_for_budget; $i++) {
                $project_name = isset($_POST['nombre_proyecto'][$i]) ? $extract_assignment_project_name($_POST['nombre_proyecto'][$i]) : '';
                $project_ceco = isset($posted_centro_costos[$i]) ? trim((string)$posted_centro_costos[$i]) : '';
                if ($project_ceco === '' && isset($_POST['centro_costos'][$i])) {
                    $project_ceco = trim((string)$_POST['centro_costos'][$i]);
                }
                $budget_key = $resolve_budget_key($project_name, $project_ceco);
                if ($budget_key === '' || !isset($budget_summary_map[$budget_key])) {
                    continue;
                }
                $submitted_hours = $sum_assignment_cost_hours_from_post($_POST, $i);
                $submitted_assignment_cost_by_key[$budget_key] = ($submitted_assignment_cost_by_key[$budget_key] ?? 0.0) + ($submitted_hours * $selected_tarifa_coan);
                $submitted_rows_by_key[$budget_key][] = $i + 1;
            }

            $affected_budget_keys = array_values(array_unique(array_merge(
                array_keys($existing_assignment_cost_by_key),
                array_keys($submitted_assignment_cost_by_key)
            )));
            foreach ($affected_budget_keys as $budget_key) {
                if (!isset($budget_summary_map[$budget_key])) {
                    continue;
                }
                $summary_entry = $budget_summary_map[$budget_key];
                $bac_value = isset($summary_entry['bac']) ? (float)$summary_entry['bac'] : 0.0;
                $ac_value = isset($summary_entry['ac']) ? (float)$summary_entry['ac'] : 0.0;
                $current_assigned = isset($summary_entry['current_assigned']) ? (float)$summary_entry['current_assigned'] : 0.0;
                $existing_selected_cost = isset($existing_assignment_cost_by_key[$budget_key]) ? (float)$existing_assignment_cost_by_key[$budget_key] : 0.0;
                $submitted_selected_cost = isset($submitted_assignment_cost_by_key[$budget_key]) ? (float)$submitted_assignment_cost_by_key[$budget_key] : 0.0;
                $projected_assigned_cost = max(0.0, $current_assigned - $existing_selected_cost) + $submitted_selected_cost;
                $projected_total_cost = $ac_value + $projected_assigned_cost;
                $is_increasing_assignment = $projected_assigned_cost > ($current_assigned + 0.01);

                if ($is_increasing_assignment && $projected_total_cost > ($bac_value + 0.01)) {
                    $pto_validation_errors[] = [
                        'label' => $summary_entry['label'] ?? $budget_key,
                        'rows' => array_values(array_unique($submitted_rows_by_key[$budget_key] ?? [])),
                        'bac' => $bac_value,
                        'ac' => $ac_value,
                        'assigned' => $projected_assigned_cost,
                        'total' => $projected_total_cost,
                    ];
                }
            }
        }
    }

    if (!empty($pto_validation_errors)) {
        $skip_asignacion_save = true;
        $error_projects = [];
        foreach ($pto_validation_errors as $validation_error) {
            $project_label = trim((string)($validation_error['label'] ?? ''));
            if ($project_label !== '') {
                $error_projects[] = $project_label;
            }
        }
        $error_projects = array_values(array_unique($error_projects));
        $project_suffix = !empty($error_projects) ? ': ' . htmlspecialchars(implode(', ', $error_projects)) . '.' : '.';
        $message .= '<div class="alert alert-danger" role="alert"><strong>No se guardó.</strong> Se superó el PTO' . $project_suffix . '</div>';
    }

    // Only attempt server-side deletion if we have a primary key column
    if (!$skip_asignacion_save && !empty($primary_key_column) && $posted_matricula !== '') {
        // Collect posted PKs
        $posted_pks = [];
        if (isset($_POST[$primary_key_column]) && is_array($_POST[$primary_key_column])) {
            foreach ($_POST[$primary_key_column] as $v) {
                $v2 = trim($v);
                if ($v2 !== '') $posted_pks[] = $v2;
            }
        }

        // Build list of month columns to inspect (Nov_2025 .. Dic_2030)
        $monthCols = array_values(array_filter($form_columns, function($c){
            return preg_match('/_(2025|2026|2027|2028|2029|2030)$/', $c);
        }));

        // If there are month columns, fetch existing rows for this matricula and delete missing ones when allowed
        if (!empty($monthCols)) {
            // Build SELECT list (primary key + months)
            $selectFields = "`$primary_key_column`";
            foreach ($monthCols as $mc) $selectFields .= ", `$mc`";

            $sql_exist = "SELECT $selectFields FROM `asignación` WHERE matricula = ?";
            if ($stmt_exist = $mysqli->prepare($sql_exist)) {
                $stmt_exist->bind_param('s', $posted_matricula);
                if ($stmt_exist->execute()) {
                    $res_exist = $stmt_exist->get_result();
                    while ($erow = $res_exist->fetch_assoc()) {
                        $pkv = isset($erow[$primary_key_column]) ? (string)$erow[$primary_key_column] : '';
                        if ($pkv === '') continue;
                        // If this pk is not present in posted pks, consider deletion
                        if (!in_array($pkv, $posted_pks, true)) {
                            // Check months: allow delete only if all month values are null/empty or numeric zero
                            $allBlankOrZero = true;
                            foreach ($monthCols as $mc) {
                                $raw = isset($erow[$mc]) ? $erow[$mc] : null;
                                if ($raw === null || $raw === '') continue;
                                // normalize numeric
                                $norm = str_replace(',', '', (string)$raw);
                                if (is_numeric($norm)) {
                                    if (floatval($norm) != 0.0) { $allBlankOrZero = false; break; }
                                } else {
                                    // non-numeric non-empty => treat as non-empty
                                    $allBlankOrZero = false; break;
                                }
                            }
                            if ($allBlankOrZero) {
                                // delete
                                $sql_del = "DELETE FROM `asignación` WHERE `$primary_key_column` = ? LIMIT 1";
                                if ($stmt_del = $mysqli->prepare($sql_del)) {
                                    $stmt_del->bind_param($pk_type, $pkv);
                                    if (!$stmt_del->execute()) {
                                        $_SESSION['message'] = '<div class="alert alert-danger" role="alert">Error al eliminar registro ' . htmlspecialchars($pkv) . ': ' . $stmt_del->error . '</div>';
                                    }
                                    $stmt_del->close();
                                }
                                // Redirigir después de eliminar
                                header('Location: ' . $_SERVER['PHP_SELF']);
                                exit();
                            } else {
                                $_SESSION['message'] = '<div class="alert alert-warning" role="alert">No se eliminó la fila ' . htmlspecialchars($pkv) . ' porque contiene valores en meses.</div>';
                                header('Location: ' . $_SERVER['PHP_SELF']);
                                exit();
                            }
                        }
                    }
                    $res_exist->free();
                }
                $stmt_exist->close();
            }
        }
    }

    if (!$skip_asignacion_save && !empty($form_columns) && isset($_POST[$form_columns[0]])) {

        // --- Guardado de asignación ---
        $row_count = count($_POST[$form_columns[0]]);
        // ...continúa el resto del procesamiento y guardado...

        if (!empty($rows_missing_project)) {
            $invalid_projects[] = 'Filas sin nombre_proyecto pero con valores en meses: ' . implode(', ', $rows_missing_project);
        }

        if (!empty($invalid_projects)) {
            $unique = array_values(array_unique($invalid_projects));
            $msg_text = 'No se guardó. Los siguientes proyectos no están autorizados o faltan: ' . implode(', ', $unique);
            $_SESSION['message'] = '<div class="alert alert-danger" role="alert">' . htmlspecialchars($msg_text) . '</div>';
            $posted_selected = isset($_POST['selected_employee_id']) ? trim($_POST['selected_employee_id']) : '';
            $posted_show_retired = isset($_POST['show_retired']) && $_POST['show_retired'] == '1' ? '1' : null;
            $posted_area_filter = isset($_POST['page_area_funcional_filter']) ? trim((string)$_POST['page_area_funcional_filter']) : '';
            $posted_name_filter = isset($_POST['summary_nombre_proyecto_filter']) ? trim((string)$_POST['summary_nombre_proyecto_filter']) : '';
            $redirect = $_SERVER['PHP_SELF'];
            $qs = [];
            if ($posted_selected !== '') $qs['matricula'] = $posted_selected;
            if ($posted_show_retired !== null) $qs['show_retired'] = $posted_show_retired;
            if ($posted_area_filter !== '') $qs['area_funcional'] = $posted_area_filter;
            if ($posted_name_filter !== '') $qs['nombre_proyecto'] = $posted_name_filter;
            if (!empty($qs)) $redirect .= '?' . http_build_query($qs);
            header("Location: " . $redirect);
            exit();
        }

        for ($i = 0; $i < $row_count; $i++) {
            $pk_value = isset($_POST[$primary_key_column][$i]) ? $_POST[$primary_key_column][$i] : null;

            // server-side: validate matricula belongs to user's area
            $matricula_val = isset($_POST['matricula'][$i]) ? trim($_POST['matricula'][$i]) : '';
            if ($stmt_validate) {
                $stmt_validate->bind_param('s', $matricula_val);
                if ($stmt_validate->execute()) {
                    $res_val = $stmt_validate->get_result();
                    if ($row_val = $res_val->fetch_assoc()) {
                        $area_of_emp = $row_val['area_funcional'];
                        $employee_area_allowed = true;
                        if (!empty($active_area_funcional)) {
                            $employee_area_allowed = ($area_of_emp === $active_area_funcional);
                        } elseif ($default_to_all_visible_areas) {
                            $employee_area_allowed = in_array($area_of_emp, $visible_dropdown_areas, true);
                        }
                        if (!$employee_area_allowed) {
                            // Do not process this row -- it doesn't belong to the active area
                            $message .= '<div class="alert alert-danger" role="alert">La matrícula ' . htmlspecialchars($matricula_val) . ' no pertenece al área funcional activa y se omitió.</div>';
                            continue;
                        }
                    } else {
                        $message .= '<div class="alert alert-danger" role="alert">Matrícula ' . htmlspecialchars($matricula_val) . ' no encontrada en la vista de empleados; fila omitida.</div>';
                        continue;
                    }
                }
            } else {
                // If validation stmt not prepared, as safety require matricula to be non-empty
                if ($matricula_val === '') {
                    $message .= '<div class="alert alert-danger" role="alert">Matrícula vacía en una fila; fila omitida.</div>';
                    continue;
                }
            }

            // Asegurar que centro_costos se derive correctamente del proyecto seleccionado
            if (in_array('centro_costos', $form_columns)) {
                $proy_val_full = isset($_POST['nombre_proyecto'][$i]) ? trim((string)$_POST['nombre_proyecto'][$i]) : '';
                $proy_parts = explode('||', $proy_val_full);
                $cc_from_proy = isset($proy_parts[1]) ? trim($proy_parts[1]) : '';
                if ($cc_from_proy !== '') {
                    $_POST['centro_costos'][$i] = $cc_from_proy;
                } else {
                    $_POST['centro_costos'][$i] = isset($posted_centro_costos[$i]) ? trim($posted_centro_costos[$i]) : '';
                }
            }

            if (!isset($pk_value) || $pk_value === '') { // INSERT
                $project_val = isset($_POST['nombre_proyecto'][$i]) ? trim((string)$_POST['nombre_proyecto'][$i]) : '';
                $project_lc = mb_strtolower($project_val);
                // If duplicate warnings exist, they remain but do not block saving
                if ($project_val !== '' && isset($counts_projects[$project_lc]) && $counts_projects[$project_lc] > 1 && isset($firstIndexMap[$project_lc]) && $firstIndexMap[$project_lc] !== $i) {
                    $message .= '<div class="alert alert-warning" role="alert">Advertencia: proyecto duplicado en la fila ' . ($i+1) . ' (existe otra fila con el mismo proyecto).</div>';
                }
                $columns = "`" . implode("`, `", $form_columns) . "`";
                $placeholders = implode(", ", array_fill(0, count($form_columns), '?'));
                $sql_insert = "INSERT INTO asignación ($columns) VALUES ($placeholders)";
                if ($stmt = $mysqli->prepare($sql_insert)) {
                    $types = str_repeat('s', count($form_columns));
                    $values = [];
                    foreach ($form_columns as $column) {
                        $val = (isset($_POST[$column]) && is_array($_POST[$column]) && array_key_exists($i, $_POST[$column])) ? $_POST[$column][$i] : '';
                        if ($column === 'nombre_proyecto') {
                            $parts = explode('||', $val);
                            $val = trim($parts[0]);
                        }

                        // Forzar que en el primer guardado (INSERT) no se registren horas por mes
                        // (debe guardar el proyecto primero y luego editar/guardar nuevamente para horas)
                        if (preg_match('/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_(2025|2026|2027|2028|2029|2030)$/', $column)) {
                            $val = '';
                        }
                        $values[] = $val;
                    }
                    $stmt->bind_param($types, ...$values);
                    if (!$stmt->execute()) {
                        $message = '<div class="alert alert-danger" role="alert">Error al crear el registro: ' . $stmt->error . '</div>';
                        break;
                    }
                    $stmt->close();
                }
            } else { // UPDATE
                // Previously we prevented saving rows with forbidden or duplicate projects on update.
                // Now we only record a warning and allow the DB UPDATE to proceed.
                $project_val_full = isset($_POST['nombre_proyecto'][$i]) ? trim((string)$_POST['nombre_proyecto'][$i]) : '';
                $project_val_parts = explode('||', $project_val_full);
                $project_val = trim($project_val_parts[0]);
                $project_lc = mb_strtolower($project_val);
                if ($project_val !== '' && isset($counts_projects[$project_lc]) && $counts_projects[$project_lc] > 1 && isset($firstIndexMap[$project_lc]) && $firstIndexMap[$project_lc] !== $i) {
                    $message .= '<div class="alert alert-warning" role="alert">Advertencia: proyecto duplicado en la fila ' . ($i+1) . ' (existe otra fila con el mismo proyecto).</div>';
                }

                $set_clause = [];
                $values = [];
                $types = '';
                foreach ($form_columns as $column) {
                    if($column != $primary_key_column){
                        $set_clause[] = "`$column` = ?";
                        $val = (isset($_POST[$column]) && is_array($_POST[$column]) && isset($_POST[$column][$i])) ? $_POST[$column][$i] : '';
                        if ($column === 'nombre_proyecto') {
                            $parts = explode('||', $val);
                            $val = trim($parts[0]);
                        }
                        $values[] = $val;
                        $types .= 's';
                    }
                }
                $values[] = $pk_value;
                $types .= $pk_type;
                $sql_update = "UPDATE asignación SET " . implode(", ", $set_clause) . " WHERE `$primary_key_column` = ?";
                if ($stmt = $mysqli->prepare($sql_update)) {
                    $stmt->bind_param($types, ...$values);
                    if (!$stmt->execute()) {
                        $message = '<div class="alert alert-danger" role="alert">Error al actualizar el registro: ' . $stmt->error . '</div>';
                        break;
                    }
                    $stmt->close();
                }
            }
        }
    }

    if (empty($message)) {
        $_SESSION['message'] = '<div class="alert alert-success" role="alert">Registros guardados exitosamente.</div>';
    } else {
        $_SESSION['message'] = $message; // Store error message
    }

    // Preserve selection (matricula) and show_retired flag after redirect
    $posted_selected = isset($_POST['selected_employee_id']) ? trim($_POST['selected_employee_id']) : '';
    $posted_show_retired = isset($_POST['show_retired']) && $_POST['show_retired'] == '1' ? '1' : null;
    $posted_area_filter = isset($_POST['page_area_funcional_filter']) ? trim((string)$_POST['page_area_funcional_filter']) : '';
    $posted_name_filter = isset($_POST['summary_nombre_proyecto_filter']) ? trim((string)$_POST['summary_nombre_proyecto_filter']) : '';
    $redirect = $_SERVER['PHP_SELF'];
    $qs = [];
    if ($posted_selected !== '') $qs['matricula'] = $posted_selected;
    if ($posted_show_retired !== null) $qs['show_retired'] = $posted_show_retired;
    if ($posted_area_filter !== '') $qs['area_funcional'] = $posted_area_filter;
    if ($posted_name_filter !== '') $qs['nombre_proyecto'] = $posted_name_filter;
    if (!empty($qs)) $redirect .= '?' . http_build_query($qs);

    if ($stmt_validate) $stmt_validate->close();
    header('Location: ' . $redirect);
    exit();
}

// Fetch assignment data filtered by selected matricula (if provided)
$asignacion_data = [];
$selected_matricula = isset($_GET['matricula']) ? trim($_GET['matricula']) : '';
$selected_employee_area = '';
$selected_employee_tarifa = 0.0;
if ($selected_matricula === '' && $_SERVER["REQUEST_METHOD"] === 'POST') {
    $selected_matricula = isset($_POST['selected_employee_id']) ? trim((string)$_POST['selected_employee_id']) : '';
    if ($selected_matricula === '' && isset($_POST['matricula']) && is_array($_POST['matricula']) && count($_POST['matricula']) > 0) {
        $selected_matricula = trim((string)$_POST['matricula'][0]);
    }
}

if ($selected_matricula !== '') {
    $sql_selected_emp = "SELECT area_funcional, tarifa_coan FROM vista_empleados WHERE matricula = ? LIMIT 1";
    if ($stmt_selected_emp = $mysqli->prepare($sql_selected_emp)) {
        $stmt_selected_emp->bind_param('s', $selected_matricula);
        if ($stmt_selected_emp->execute()) {
            $res_selected_emp = $stmt_selected_emp->get_result();
            if ($selected_emp_row = $res_selected_emp->fetch_assoc()) {
                $selected_employee_area = isset($selected_emp_row['area_funcional']) ? trim((string)$selected_emp_row['area_funcional']) : '';
                $selected_employee_tarifa = isset($selected_emp_row['tarifa_coan']) ? (float)$selected_emp_row['tarifa_coan'] : 0.0;
            }
            $res_selected_emp->free();
        }
        $stmt_selected_emp->close();
    }

    if (!empty($active_area_funcional) && $selected_employee_area !== '' && $selected_employee_area !== $active_area_funcional) {
        $selected_matricula = '';
        $selected_employee_area = '';
        $selected_employee_tarifa = 0.0;
    }
}

if ($selected_matricula !== '' && $default_to_all_visible_areas && empty($active_area_funcional) && $selected_employee_area !== '' && !in_array($selected_employee_area, $visible_dropdown_areas, true)) {
    $selected_matricula = '';
    $selected_employee_area = '';
    $selected_employee_tarifa = 0.0;
}

$has_asignacion_rows = false;
if ($selected_matricula !== '') {
    // Prepared statement to fetch only rows for this matricula
    $sql_data = "SELECT * FROM `asignación` WHERE matricula = ?";
    if ($stmt_a = $mysqli->prepare($sql_data)) {
        $stmt_a->bind_param('s', $selected_matricula);
        if ($stmt_a->execute()) {
            $res_a = $stmt_a->get_result();
            while ($row = $res_a->fetch_assoc()) {
                $asignacion_data[] = $row;
            }
            if (count($asignacion_data) > 0) $has_asignacion_rows = true;
            $res_a->free();
        }
        $stmt_a->close();
    } else {
        // Fallback: try non-prepared query (not ideal)
        error_log('Coordinador.php: fallo prepare en consulta asignación: ' . $mysqli->error);
        $fallback_sql = "SELECT * FROM `asignación` WHERE matricula = '" . $mysqli->real_escape_string($selected_matricula) . "'";
        if ($result = $mysqli->query($fallback_sql)) {
            while ($row = $result->fetch_assoc()) {
                $asignacion_data[] = $row;
            }
            $result->free();
        }
    }
}

// If no rows found, keep asignacion_data empty and provide an empty template for JS to create rows
if (empty($asignacion_data)) {
    $has_asignacion_rows = false;
}

// ==================== SECCIÓN RESUMEN DE PROYECTOS ====================
// Usar la lista visible de áreas para el dropdown del resumen
$areas = array_values(array_unique($visible_dropdown_areas));
if (!$can_select_multiple_areas && !empty($area_funcional) && !in_array($area_funcional, $areas, true)) {
    array_unshift($areas, $area_funcional);
}

$area_filter = $active_area_funcional;

$areas_for_dropdown = $can_select_multiple_areas ? $areas : array_values(array_unique(array_filter([$area_filter])));
if (empty($areas_for_dropdown)) {
    $areas_for_dropdown = $areas;
}

// Construir consulta principal con filtros opcionales

$sql_resumen = "SELECT 
    gp.PROYECTO,
    p.nombre_proyecto,
    p.nature_imputation,
    gp.`ÁREA FUNCIONAL`,
    MIN(gp.`FECHA INICIO PROYECTO`) AS fecha_inicio,
    MAX(gp.`FECHA FIN PROYECTO`) AS fecha_fin,
    afep.PORCENTAJE_AVANCE_FISICO_EJECUTADO,
    afep.PORCENTAJE_AVANCE_FISICO_PROGRAMADO,
    SUM(
        gp.`ene25`+gp.`feb25`+gp.`mar25`+gp.`abr25`+gp.`may25`+gp.`jun25`+gp.`jul25`+gp.`ago25`+gp.`sep25`+gp.`oct25`+gp.`nov25`+gp.`dic25`+
        gp.`ene26`+gp.`feb26`+gp.`mar26`+gp.`abr26`+gp.`may26`+gp.`jun26`+gp.`jul26`+gp.`ago26`+gp.`sep26`+gp.`oct26`+gp.`nov26`+gp.`dic26`+
        gp.`ene27`+gp.`feb27`+gp.`mar27`+gp.`abr27`+gp.`may27`+gp.`jun27`+gp.`jul27`+gp.`ago27`+gp.`sep27`+gp.`oct27`+gp.`nov27`+gp.`dic27`+
        gp.`ene28`+gp.`feb28`+gp.`mar28`+gp.`abr28`+gp.`may28`+gp.`jun28`+gp.`jul28`+gp.`ago28`+gp.`sep28`+gp.`oct28`+gp.`nov28`+gp.`dic28`
    ) AS total_horas,
    COALESCE(cv.total_valorizado_2025, 0) AS total_valorizado_2025,
    (SELECT SUM(
        (`ene25`+`feb25`+`mar25`+`abr25`+`may25`+`jun25`+`jul25`+`ago25`+`sep25`+`oct25`+`nov25`+`dic25`+
        `ene26`+`feb26`+`mar26`+`abr26`+`may26`+`jun26`+`jul26`+`ago26`+`sep26`+`oct26`+`nov26`+`dic26`+
        `ene27`+`feb27`+`mar27`+`abr27`+`may27`+`jun27`+`jul27`+`ago27`+`sep27`+`oct27`+`nov27`+`dic27`+
        `ene28`+`feb28`+`mar28`+`abr28`+`may28`+`jun28`+`jul28`+`ago28`+`sep28`+`oct28`+`nov28`+`dic28`) * `TARIFA COAN 2`)
    FROM gastos_personal gp2
    WHERE gp2.PROYECTO = gp.PROYECTO AND gp2.`ÁREA FUNCIONAL` = gp.`ÁREA FUNCIONAL`
    ) AS total_costo,
        COALESCE(
            vcm.Mar_2026_Costo + vcm.Abr_2026_Costo + vcm.May_2026_Costo + vcm.Jun_2026_Costo + vcm.Jul_2026_Costo + vcm.Ago_2026_Costo + vcm.Sep_2026_Costo + vcm.Oct_2026_Costo + vcm.Nov_2026_Costo + vcm.Dic_2026_Costo +
            vcm.Ene_2027_Costo + vcm.Feb_2027_Costo + vcm.Mar_2027_Costo + vcm.Abr_2027_Costo + vcm.May_2027_Costo + vcm.Jun_2027_Costo + vcm.Jul_2027_Costo + vcm.Ago_2027_Costo + vcm.Sep_2027_Costo + vcm.Oct_2027_Costo + vcm.Nov_2027_Costo + vcm.Dic_2027_Costo +
            vcm.Ene_2028_Costo + vcm.Feb_2028_Costo + vcm.Mar_2028_Costo + vcm.Abr_2028_Costo + vcm.May_2028_Costo + vcm.Jun_2028_Costo + vcm.Jul_2028_Costo + vcm.Ago_2028_Costo + vcm.Sep_2028_Costo + vcm.Oct_2028_Costo + vcm.Nov_2028_Costo + vcm.Dic_2028_Costo +
            vcm.Ene_2029_Costo + vcm.Feb_2029_Costo + vcm.Mar_2029_Costo + vcm.Abr_2029_Costo + vcm.May_2029_Costo + vcm.Jun_2029_Costo + vcm.Jul_2029_Costo + vcm.Ago_2029_Costo + vcm.Sep_2029_Costo + vcm.Oct_2029_Costo + vcm.Nov_2029_Costo + vcm.Dic_2029_Costo +
            vcm.Ene_2030_Costo + vcm.Feb_2030_Costo + vcm.Mar_2030_Costo + vcm.Abr_2030_Costo + vcm.May_2030_Costo + vcm.Jun_2030_Costo + vcm.Jul_2030_Costo + vcm.Ago_2030_Costo + vcm.Sep_2030_Costo + vcm.Oct_2030_Costo + vcm.Nov_2030_Costo + vcm.Dic_2030_Costo
        , 0) AS TOTAL_COSTO_ASIGNADO,
    COALESCE((
        SELECT SUM(hd.`tiempo_imputado_costo`)
        FROM horas_dia hd
        WHERE hd.`codigo_affaire` = gp.PROYECTO
          AND hd.`area_funcional` = gp.`ÁREA FUNCIONAL`
          AND hd.`Estado_Aprobacion` = 'Aprobado'
    ), 0) AS total_costo_imputado_aprobado
FROM gastos_personal gp
LEFT JOIN proyectos p ON gp.PROYECTO = p.centro_costos
LEFT JOIN vista_costo_mensual_por_area_proyecto_cc vcm ON gp.`ÁREA FUNCIONAL` = vcm.area_funcional AND p.nombre_proyecto = vcm.nombre_proyecto AND p.centro_costos = vcm.centro_costos
LEFT JOIN (
    SELECT 
        CECO_CONEXION, 
        `ÁREA FUNCIONAL`,
        SUM(`ene_25`+`feb_25`+`mar_25`+`abr_25`+`may_25`+`jun_25`+`jul_25`+`ago_25`+`sep_25`+`oct_25`+`nov_25`+`dic_25`) AS total_valorizado_2025
    FROM costo_valorizado
    GROUP BY CECO_CONEXION, `ÁREA FUNCIONAL`
) cv ON gp.PROYECTO = cv.CECO_CONEXION AND gp.`ÁREA FUNCIONAL` = cv.`ÁREA FUNCIONAL`
LEFT JOIN avance_fisico_ejecutado_programado afep ON gp.PROYECTO = afep.PROYECTO AND gp.`ÁREA FUNCIONAL` = afep.AREA_FUNCIONAL
WHERE 1=1 ";

$sql_resumen .= $build_area_sql_filter($mysqli, 'gp.`ÁREA FUNCIONAL`');

if ($name_filter !== '') {
    $sql_resumen .= " AND p.nombre_proyecto = '" . $mysqli->real_escape_string($name_filter) . "' ";
}

$sql_resumen .= "GROUP BY gp.PROYECTO, p.nombre_proyecto, p.nature_imputation, gp.`ÁREA FUNCIONAL`
ORDER BY gp.PROYECTO ASC, gp.`ÁREA FUNCIONAL` ASC;";

$result_resumen = $mysqli->query($sql_resumen);

// Debug helper: when ?debug_resumen=1 is present, log SQL, mysqli error and row count
if (isset($_GET['debug_resumen']) && $_GET['debug_resumen'] == '1') {
    error_log("[DEBUG] Resumen SQL: " . $sql_resumen);
    error_log("[DEBUG] mysqli error: " . $mysqli->error);
    if ($result_resumen instanceof mysqli_result) {
        error_log("[DEBUG] resumen rows: " . $result_resumen->num_rows);
    } else {
        error_log("[DEBUG] resumen result is not a mysqli_result");
    }
}

// Rewind result set by fetching into an array so we can compute totals and render the table
$resumen_rows = [];
$sum_bac = 0.0; // presupuesto a terminación
$sum_ac = 0.0;  // costo valorizado (actual)
$sum_etc = 0.0; // costo por ejecutar
if ($result_resumen && $result_resumen->num_rows > 0) {
    while ($r = $result_resumen->fetch_assoc()) {
        $resumen_rows[] = $r;
        $ac_ajustado = (float)$r['total_valorizado_2025'] + (float)$r['total_costo_imputado_aprobado'];
        $sum_bac += (float)$r['total_costo'];
        $sum_ac += $ac_ajustado;
        $sum_etc += ((float)$r['total_costo'] - $ac_ajustado);
    }
}
// Ensure numeric values
$sum_bac = (float)$sum_bac;
$sum_ac = (float)$sum_ac;
$sum_etc = (float)$sum_etc;


// Obtener lista de proyectos para el filtro
// Solo mostrar el proyecto que está cargando (donde está el pto)
$nombres_proyectos = [];
if (!empty($resumen_rows)) {
    foreach ($resumen_rows as $row) {
        if (!empty($row['nombre_proyecto']) && !empty($row['total_costo'])) {
            $nombres_proyectos[] = $row['nombre_proyecto'];
        }
    }
}
$nombres_proyectos = array_unique($nombres_proyectos);
// Prepare allowed projects list (Resumen Ejecutivo + explicit extras) for front-end validation
$proyectos_resumen = [];
if (!empty($resumen_rows)) {
    foreach ($resumen_rows as $row) {
        if (!empty($row['nombre_proyecto'])) {
            $proyectos_resumen[] = $row['nombre_proyecto'];
        }
    }
}
$allowed_projects = array_unique($proyectos_resumen);

// Agregar todos los proyectos extras solicitados
$extras_for_allowed = array('COMERCIAL', 'VACACIONES / (CGP) CONGES PAYES', 'GESTIÓN INTEGRAL', 'DISPONIBLE', 'TALENTO HUMANO');
foreach ($extras_for_allowed as $ep) {
    if (!in_array($ep, $allowed_projects)) {
        $allowed_projects[] = $ep;
    }
}
// ==================== FIN SECCIÓN RESUMEN DE PROYECTOS ====================

// ==================== SECCIÓN RESUMEN DE USO POR EMPLEADO ====================
$employee_usage_matrix = [];
$employee_usage_month_hours = [];
$month_columns_for_summary = array_filter($form_columns, function($c){
    return preg_match('/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_(2025|2026|2027|2028|2029|2030)$/', $c);
});

$filtered_month_columns = [];
$months_to_exclude_2025 = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct'];
$months_to_exclude_2026 = ['Ene', 'Feb'];

foreach ($month_columns_for_summary as $column) {
    list($month, $year) = explode('_', $column);
    if ($year == 2025) {
        if (!in_array($month, $months_to_exclude_2025)) {
            $filtered_month_columns[] = $column;
        }
    } elseif ($year == 2026) {
        if (!in_array($month, $months_to_exclude_2026, true)) {
            $filtered_month_columns[] = $column;
        }
    } else {
        $filtered_month_columns[] = $column;
    }
}
$month_columns_for_summary = $filtered_month_columns;

if ((!empty($active_area_funcional) || $default_to_all_visible_areas) && count($month_columns_for_summary) > 0) {
    $summary_default_month_hours = [
        '2026' => [
            'Ene' => 175,
            'Feb' => 176,
            'Mar' => 185,
            'Abr' => 177,
            'May' => 167,
            'Jun' => 167,
            'Jul' => 185,
            'Ago' => 160,
            'Sep' => 185,
            'Oct' => 176,
            'Nov' => 160,
            'Dic' => 177,
        ],
        '2025' => 180,
    ];
    $summary_month_number_map = [
        'Ene' => '01',
        'Feb' => '02',
        'Mar' => '03',
        'Abr' => '04',
        'May' => '05',
        'Jun' => '06',
        'Jul' => '07',
        'Ago' => '08',
        'Sep' => '09',
        'Oct' => '10',
        'Nov' => '11',
        'Dic' => '12',
    ];
    $summary_calendar_hours = [];
    $sql_summary_calendar = "
        SELECT
            YEAR(fecha) AS year_num,
            MONTH(fecha) AS month_num,
            SUM(hh_teoricas) AS hh_teoricas_mes
        FROM horas_habiles_calendario
        WHERE fecha IS NOT NULL
        GROUP BY YEAR(fecha), MONTH(fecha)
    ";
    if ($result_summary_calendar = $mysqli->query($sql_summary_calendar)) {
        while ($summary_calendar_row = $result_summary_calendar->fetch_assoc()) {
            $calendar_key = sprintf('%04d-%02d', (int)$summary_calendar_row['year_num'], (int)$summary_calendar_row['month_num']);
            $summary_calendar_hours[$calendar_key] = (float)($summary_calendar_row['hh_teoricas_mes'] ?? 0);
        }
        $result_summary_calendar->free();
    }
    $get_summary_month_hours = function($month_column) use ($summary_month_number_map, $summary_calendar_hours, $summary_default_month_hours) {
        $parts = explode('_', (string)$month_column);
        $month_name = $parts[0] ?? '';
        $year = $parts[1] ?? '';
        if ($month_name !== '' && $year !== '' && isset($summary_month_number_map[$month_name])) {
            $calendar_key = $year . '-' . $summary_month_number_map[$month_name];
            if (isset($summary_calendar_hours[$calendar_key]) && (float)$summary_calendar_hours[$calendar_key] > 0) {
                return (float)$summary_calendar_hours[$calendar_key];
            }
        }
        if (isset($summary_default_month_hours[$year])) {
            if (is_array($summary_default_month_hours[$year]) && isset($summary_default_month_hours[$year][$month_name])) {
                return (float)$summary_default_month_hours[$year][$month_name];
            }
            if (!is_array($summary_default_month_hours[$year])) {
                return (float)$summary_default_month_hours[$year];
            }
        }
        return 180.0;
    };
    $split_summary_employee_name = function($full_name) {
        $parts = preg_split('/\s+/', trim((string)$full_name));
        $parts = array_values(array_filter($parts, function($value) {
            return trim((string)$value) !== '';
        }));
        $count = count($parts);
        if ($count === 0) {
            return ['apellido' => '', 'nombre' => ''];
        }
        if ($count === 1) {
            return ['apellido' => $parts[0], 'nombre' => ''];
        }
        if ($count === 2) {
            return ['apellido' => $parts[0], 'nombre' => $parts[1]];
        }
        $surname_parts = array_slice($parts, 0, 2);
        $name_parts = array_slice($parts, 2);
        if (empty($name_parts)) {
            $surname_parts = [$parts[0]];
            $name_parts = array_slice($parts, 1);
        }
        return [
            'apellido' => implode(' ', $surname_parts),
            'nombre' => implode(' ', $name_parts),
        ];
    };

    foreach ($month_columns_for_summary as $month_column) {
        $employee_usage_month_hours[$month_column] = $get_summary_month_hours($month_column);
    }

    $sum_selects = [];
    foreach ($month_columns_for_summary as $col) {
        $sum_selects[] = "SUM(COALESCE(a.`$col`, 0)) as total_$col";
    }
    $sum_sql_part = implode(', ', $sum_selects);

    $sql_employee_usage = "
        SELECT
            ve.Nombre_Empleado_Completo,
            ve.matricula,
            TRIM(COALESCE(a.nombre_proyecto, '')) AS nombre_proyecto,
            {$sum_sql_part}
        FROM
            vista_empleados ve
        LEFT JOIN
            asignación a ON ve.matricula = a.matricula
                    WHERE 1=1 ";
    $sql_employee_usage .= $build_area_sql_filter($mysqli, 've.area_funcional');
    $sql_employee_usage .= " AND (ve.fechas_retiro >= CURDATE() OR ve.fechas_retiro IS NULL)
        
        GROUP BY
            ve.Nombre_Empleado_Completo, ve.matricula, a.nombre_proyecto
        ORDER BY
            ve.Nombre_Empleado_Completo ASC,
            a.nombre_proyecto ASC
    ";

    if ($result_usage = $mysqli->query($sql_employee_usage)) {
        while ($row = $result_usage->fetch_assoc()) {
            $employee_key = trim((string)($row['matricula'] ?? ''));
            if ($employee_key === '') {
                continue;
            }

            if (!isset($employee_usage_matrix[$employee_key])) {
                $name_parts = $split_summary_employee_name($row['Nombre_Empleado_Completo'] ?? '');
                $employee_usage_matrix[$employee_key] = [
                    'matricula' => $employee_key,
                    'full_name' => (string)($row['Nombre_Empleado_Completo'] ?? ''),
                    'apellido' => $name_parts['apellido'],
                    'nombre' => $name_parts['nombre'],
                    'monthly_hours' => array_fill_keys($month_columns_for_summary, 0.0),
                    'monthly_percentages' => array_fill_keys($month_columns_for_summary, 0),
                    'projects' => [],
                ];
            }

            $project_name = trim((string)($row['nombre_proyecto'] ?? ''));
            $project_monthly_hours = [];
            $project_monthly_percentages = [];
            $project_has_data = false;

            foreach ($month_columns_for_summary as $month_column) {
                $hours = isset($row['total_' . $month_column]) ? (float)$row['total_' . $month_column] : 0.0;
                $employee_usage_matrix[$employee_key]['monthly_hours'][$month_column] += $hours;
                $project_monthly_hours[$month_column] = $hours;
                $month_base = isset($employee_usage_month_hours[$month_column]) ? (float)$employee_usage_month_hours[$month_column] : 0.0;
                $project_monthly_percentages[$month_column] = ($hours > 0 && $month_base > 0) ? (int)round(($hours / $month_base) * 100) : 0;
                if ($hours > 0) {
                    $project_has_data = true;
                }
            }

            if ($project_name !== '' && $project_has_data) {
                $employee_usage_matrix[$employee_key]['projects'][] = [
                    'name' => $project_name,
                    'monthly_hours' => $project_monthly_hours,
                    'monthly_percentages' => $project_monthly_percentages,
                ];
            }
        }
        $result_usage->free();
    }

    foreach ($employee_usage_matrix as $employee_key => $employee_summary) {
        $has_visible_percentage = false;
        foreach ($month_columns_for_summary as $month_column) {
            $total_hours = isset($employee_summary['monthly_hours'][$month_column]) ? (float)$employee_summary['monthly_hours'][$month_column] : 0.0;
            $month_base = isset($employee_usage_month_hours[$month_column]) ? (float)$employee_usage_month_hours[$month_column] : 0.0;
            $percentage = ($total_hours > 0 && $month_base > 0) ? (int)round(($total_hours / $month_base) * 100) : 0;
            $employee_usage_matrix[$employee_key]['monthly_percentages'][$month_column] = $percentage;
            if ($percentage > 0) {
                $has_visible_percentage = true;
            }
        }

        if (!empty($employee_usage_matrix[$employee_key]['projects'])) {
            usort($employee_usage_matrix[$employee_key]['projects'], function($left, $right) {
                return strnatcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
            });
        }

        if (!$has_visible_percentage && empty($employee_usage_matrix[$employee_key]['projects'])) {
            unset($employee_usage_matrix[$employee_key]);
        }
    }
}
// ==================== FIN SECCIÓN RESUMEN DE USO POR EMPLEADO ====================
?>

<?php
// Convierte un porcentaje (0-100) a un color hex interpolado entre rojo->amarillo->verde
function percentToColor($p) {
    $p = max(0, min(100, (float)$p));
    $red = [255, 77, 79]; // rojo aproximado
    $yellow = [255, 204, 0]; // amarillo
    $green = [23, 130, 61]; // #17823d

    if ($p <= 50) {
        $t = $p / 50.0;
        $r = (int)round($red[0] + ($yellow[0] - $red[0]) * $t);
        $g = (int)round($red[1] + ($yellow[1] - $red[1]) * $t);
        $b = (int)round($red[2] + ($yellow[2] - $red[2]) * $t);
    } else {
        $t = ($p - 50.0) / 50.0;
        $r = (int)round($yellow[0] + ($green[0] - $yellow[0]) * $t);
        $g = (int)round($yellow[1] + ($green[1] - $yellow[1]) * $t);
        $b = (int)round($yellow[2] + ($green[2] - $yellow[2]) * $t);
    }

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
?>

<?php
// Cargamos lista de proyectos para el select/autocomplete de nombre_proyecto
// y mapeamos su centro_costos para auto-llenado

$projects_map = [];
$frais_generaux_projects = [];
$frais_generaux_allowed = array(
    'DIRECCIÓN EJECUTIVA',
    'COMERCIAL',
    'FINANZAS',
    'TALENTO HUMANO',
    'IT',
    'LEGAL',
    'ADMINISTRACION',
    'CAPACITACIONES',
    'DISPONIBLE',
    'CONTROL DE GESTION',
    'CONTROL DOCUMENTAL',
    'REINVERSIÓN',
    'HH NO FACTURABLES',
    'GESTIÓN INTEGRAL'
);
$conn_proj = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$conn_proj->connect_error) {
    $sql_proj = "SELECT nombre_proyecto, centro_costos, nature_imputation FROM proyectos ORDER BY nombre_proyecto ASC";
    if ($res_proj = $conn_proj->query($sql_proj)) {
        while ($p = $res_proj->fetch_assoc()) {
            if (!empty($p['nombre_proyecto'])) {
                $projects_map[$p['nombre_proyecto']] = $p['centro_costos'] ?? '';
                if (isset($p['nature_imputation']) && trim($p['nature_imputation']) === 'FRAIS GENERAUX  DIVERS') {
                    if (in_array(trim($p['nombre_proyecto']), $frais_generaux_allowed, true)) {
                        $frais_generaux_projects[] = $p['nombre_proyecto'];
                    }
                }
            }
        }
        $res_proj->free();
    }
    $conn_proj->close();
}
// Unir allowed_projects y frais_generaux_projects, sin duplicados
$project_names = array_keys($projects_map);
$selector_projects = array_values(array_filter(array_unique(array_merge(
    is_array($allowed_projects) ? $allowed_projects : array(),
    $frais_generaux_projects
)), function($name) {
    return trim((string)$name) !== '';
}));

// (no selected_hh lookup)
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: #f4f6fa !important;
        }
        .card {
            box-shadow: 0 2px 12px 0 rgba(60,72,100,.08), 0 1.5px 4px 0 rgba(60,72,100,.04);
            border-radius: 18px !important;
            border: none;
        }
        .container {
            max-width: 95% !important;
            margin: 0 auto;
            padding: 0 15px;
        }
        /* Compact executive-style summary cards */
        .top-cards .card { padding: 10px; border-radius: 8px; }
        .summary-card h6, .assign-card h6 { font-size: 1rem; margin-bottom: 0.75rem; }
        .summary-card .label, .assign-card .label { color:#2f6f36; font-weight:600; font-size:0.92rem; }
        .assign-card .label { color:#2f6f8f; }
        .value-box { background:#fff; padding:5px 10px; border-radius:4px; min-width:90px; text-align:right; display:inline-block; font-weight:600; }
        .small-row { gap:8px; }
        @media (max-width: 992px) {
            .value-box { min-width:80px; }
        }
        .table-responsive {
            overflow-x: auto;
            margin: 0 auto 2.5rem 0;
            width: 100%;
            padding-bottom: 1.5rem;
        }
        .resumen-table th, .resumen-table td {
            border-right: 1px solid rgba(225, 225, 225, 0.5);
            vertical-align: middle;
            padding: 8px 12px;
            min-width: 100px;
            position: relative;
        }
        .resumen-table td:not(:last-child):after,
        .resumen-table th:not(:last-child):after {
            content: '';
            position: absolute;
            right: 0;
            top: 25%;
            height: 50%;
            width: 1px;
            background: rgba(200, 200, 200, 0.3);
        }
        .resumen-table th {
            background: #4C8AA3;
            color: #fff;
            font-weight: 600;
            white-space: normal;
            text-align: center;
        }
        .resumen-table tbody tr:nth-child(even) {
            background: #f4f7fb;
        }
        .resumen-table tbody tr:nth-child(odd) {
            background: #fff;
        }
        .resumen-table th:nth-child(2),
        .resumen-table td:nth-child(2) {
            min-width: 200px;
        }
        .resumen-table th:nth-child(8),
        .resumen-table td:nth-child(8),
        .resumen-table th:nth-child(9),
        .resumen-table td:nth-child(9) {
            min-width: 120px;
        }
        /* Hide less-critical columns by index. Adjusted to keep COSTO ACTUAL visible after adding TOTAL_COSTO_ASIGNADO. */
        #resumen-proyectos-table th:nth-child(4),
        #resumen-proyectos-table td:nth-child(4),
        #resumen-proyectos-table th:nth-child(8),
        #resumen-proyectos-table td:nth-child(8),
        #resumen-proyectos-table th:nth-child(9),
        #resumen-proyectos-table td:nth-child(9),
        #resumen-proyectos-table th:nth-child(10),
        #resumen-proyectos-table td:nth-child(10),
        #resumen-proyectos-table th:nth-child(11),
        #resumen-proyectos-table td:nth-child(11),
        #resumen-proyectos-table th:nth-child(12),
        #resumen-proyectos-table td:nth-child(12),
        #resumen-proyectos-table th:nth-child(13),
        #resumen-proyectos-table td:nth-child(13) {
            display: none;
        }
        .resumen-table {
            background: #fff;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: none;
        }
        .resumen-thead th {
            background: #4C8AA3;
            color: #fff;
            font-weight: 600;
            border: none;
            font-size: 0.85rem;
            letter-spacing: .01em;
            padding-top: 10px;
            padding-bottom: 10px;
            transition: background 0.2s, color 0.2s;
            text-align: left;
            padding-left: 15px;
        }
        .resumen-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: box-shadow 0.18s, background 0.18s, transform 0.18s;
        }
        .resumen-table:not(.no-hover) tbody tr:hover {
            background: #f3f6fa;
            box-shadow: 0 6px 24px 0 rgba(60,72,88,.16);
            transform: translateY(-2px) scale(1.01);
        }
        .resumen-table td {
            border: none;
            vertical-align: middle;
            font-size: 0.9rem;
            background: #fff;
            padding-top: 10px;
            padding-bottom: 10px;
            text-align: left;
            padding-left: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .resumen-table td.text-success,
        .resumen-table td.text-info,
        .resumen-table td.text-warning {
            text-align: center;
            padding-left: 10px;
            padding-right: 10px;
        }
        .resumen-table td:nth-child(2) {
            min-width: 300px;
            max-width: none;
            white-space: normal;
            word-wrap: break-word;
        }
        .resumen-table tbody td {
            color: #000 !important;
            font-weight: 400 !important;
        }
        .resumen-table tbody td .fw-bold {
            font-weight: 400 !important;
            color: inherit !important;
        }
        .project-name {
            color: #17823d !important;
            font-weight: 400 !important;
        }
        .resumen-table.nowrap {
            white-space: nowrap;
        }
        .resumen-table th {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .resumen-table th:nth-child(2) {
            min-width: 300px;
            max-width: none;
        }
        .form-container {
            display: flex;
            flex-wrap: nowrap;
            gap: 1rem; /* increased gap to take advantage of extra vertical space */
            margin-bottom: 0.8rem; /* increased vertical spacing for readability */
            align-items: center;
        }
        /* Reduce centro_costos field width by ~40% */
        .centro-costos-wrap {
            min-width: 120px !important;
            max-width: 160px !important;
        }
        .centro-costos-wrap
        .centro-costos-wrap .form-control {
            width: 100%;
        }
        /* centro-costos wrapper (editable by user) */
        .centro-costos-wrap { position: relative; }
       
        /* Center labels inside the assignment form only */
        #asignacion-form .form-header {
            text-align: center;
            display: block;
            width: 100%;
            margin-bottom: 0.35rem;
        }
        /* Make assignment form inputs and headers match the empleados table text style */
        #form-rows-container,
        #form-rows-container .form-row,
        #form-rows-container .form-control,
        #form-rows-container .form-header {
            font-family: inherit; /* use page/bootstrap font */
            font-size: 0.875rem; /* match table-sm text size */
            color: #212529; /* bootstrap body color */
            font-weight: 400;
        }
        /* Reduce displayed hours font slightly for month inputs to improve visual balance */
        #form-rows-container .col-month input.form-control,
        #form-rows-container .mes-input-wrap input.form-control {
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            padding: .4rem .45rem !important;
        }
        /* Center all inputs in form rows by default (visual alignment) */
        #form-rows-container .form-row .form-control { text-align: center; }
        /* Exceptions: project name and centro_costos should remain left-aligned */
        #form-rows-container .form-row .proyecto-autocomplete,
        #form-rows-container .form-row .centro-costos { text-align: left; }
        /* Default per-field vertical spacing inside each form-column wrapper (mobile-friendly)
           Use larger spacing and padding so fields are more readable inside the expanded card. */
        #form-rows-container .form-row > .mb-3 { margin-bottom: 0.6rem; }
    /* Increase vertical padding inside inputs to make rows more legible */
    #form-rows-container .form-control { padding: .55rem .6rem; height: auto; font-size: 0.95rem; }
    /* Show headers only on the first form-row; keep totals overlay headers unaffected */
    #form-rows-container .form-row .form-header { display: none; }
    #form-rows-container .form-row:first-of-type .form-header { display: block; }
    /* Layout: keep each form-row as a non-wrapping flex row so columns never shift */
    #form-rows-container .form-row { display:flex; gap:8px; align-items:flex-end; flex-wrap:nowrap; }
    /* Column helpers */
    .col-project { min-width:200px; flex:0 0 200px; }
    .col-cc { min-width:120px; flex:0 0 120px; }
    .col-month { min-width:120px; max-width:140px; flex:0 0 120px; }
    /* Make inputs/selects fill their wrappers */
    #form-rows-container .col-project select, #form-rows-container .col-project .form-select, #form-rows-container .col-cc input, #form-rows-container .col-month input { width:100%; box-sizing:border-box; }
        /* Employees table: ensure Fecha Ingreso and Fecha Retiro have the same width
           and enable horizontal scrolling when needed */
        #empleados-table { min-width: 720px; }
        #empleados-table th:nth-child(3),
        #empleados-table td:nth-child(3),
        #empleados-table th:nth-child(4),
        #empleados-table td:nth-child(4) {
            /* reducido ~50% */
            min-width: 80px;
            max-width: 90px;
            white-space: nowrap;
        }
        /* Reducir en ~30% el ancho del campo Nombre (columna 2) y truncar texto largo */
        #empleados-table th:nth-child(2),
        #empleados-table td:nth-child(2) {
            min-width: 112px; /* reduced ~20% from 140px */
            max-width: 192px; /* reduced ~20% from 240px */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        /* Totals row styling: compact and distinct */
        .totals-row { background: #f8f9fb; border-radius: 8px; padding: 0.35rem; }
        .totals-row .form-header { font-size: 0.85rem; color: #333; text-align: center; margin-bottom: .25rem; }
        .totals-row .form-control { background: #eef6ff; border: 1px solid #d0e6ff; font-weight:600; padding: .25rem .4rem; font-size: .95rem; }
        .totals-row .total-label { display:flex; align-items:center; justify-content:center; height:100%; font-weight:600; color:#1f497d; }
        /* Styles for overlay-based totals (absolute positioned) */
        #totals-overlay { pointer-events: none; z-index: 999; }
        #totals-overlay .totals-cell-wrapper {
            background: rgba(76,138,163,0.06);
            border: 1px solid rgba(76,138,163,0.14);
            border-radius: 8px;
            padding: 6px 6px;
            box-shadow: 0 6px 18px rgba(28,43,54,0.06);
            backdrop-filter: blur(2px);
        }
        #totals-overlay .totals-cell-wrapper .form-header {
            font-size: 0.72rem;
            color: #2b586e;
            margin-bottom: 0.25rem;
            text-align: center;
            opacity: 0.95;
        }
        #totals-overlay .totals-cell-wrapper .total-input {
            background: linear-gradient(180deg, #ffffff, #f6fbff);
            border: 1px solid rgba(30,80,120,0.12);
            font-weight: 700;
            color: #0b3b5a;
            padding: 0.18rem 0.4rem;
            text-align: center;
            box-shadow: inset 0 -1px 0 rgba(255,255,255,0.5);
            pointer-events: auto; /* allow selection if needed */
            font-size: 0.9rem;
            line-height: 1.05;
            white-space: normal;
            border-radius: 4px;
        }
        /* percentage text shown under or beside the total inside the same totals wrapper */
        #totals-overlay .total-pct {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            width: 100%;
            text-align: center;
            font-size: 0.78rem;
            margin-top: 8px;
            font-weight: 700;
            opacity: 0.95;
        }
        #totals-overlay .total-pct .usage-row {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: 4px;
            background: rgba(11, 59, 90, 0.07);
            border: 1px solid rgba(11, 59, 90, 0.1);
        }
        #totals-overlay .total-pct .pct-prefix {
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 0.64rem;
            color: #6b7280;
            opacity: 1;
        }
        #totals-overlay .total-pct .pct-value {
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1;
        }
        #totals-overlay .total-pct .hours-value {
            display: inline-block;
            font-size: 0.72rem;
            margin-top: 0;
            font-weight: 700;
            color: #52606d;
            background: rgba(255, 255, 255, 0.88);
            border: 1px dashed rgba(82, 96, 109, 0.2);
            border-radius: 4px;
            padding: 2px 8px;
        }
        #totals-overlay .total-pct .remaining-value {
            display: inline-block;
            font-size: 0.72rem;
            margin-top: 0;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 4px;
            border: 1px solid transparent;
        }
        /* color codes for percentage */
        #totals-overlay .pct-low .pct-value { color: #b8860b; }
        #totals-overlay .pct-eq  .pct-value { color: #1e7e34; }
        #totals-overlay .pct-high .pct-value { color: #8b0000; }
        #totals-overlay .remaining-pending {
            color: #9a6700;
            background: rgba(245, 158, 11, 0.14);
            border-color: rgba(245, 158, 11, 0.28);
        }
        #totals-overlay .remaining-complete {
            color: #166534;
            background: rgba(34, 197, 94, 0.14);
            border-color: rgba(34, 197, 94, 0.26);
        }
        #totals-overlay .remaining-over {
            color: #b42318;
            background: rgba(239, 68, 68, 0.14);
            border-color: rgba(239, 68, 68, 0.26);
        }
        
    /* centro-costos-wrap keeps its wrapper styling; no sticky behavior by default */
        /* Dense compact variant for wide screens */
            @media (min-width: 992px) {
            /* Slightly larger spacing on wide screens too (but not overly large) */
            .form-container { gap: 0.6rem; margin-bottom: 0.36rem; }
            #form-rows-container .form-row > .mb-3 { margin-bottom: 0.36rem; }
            #form-rows-container .form-control { padding: .45rem .5rem; }
            /* Ensure headers remain visible and comfortably sized */
            #form-rows-container .form-row .form-header { margin-bottom: 0.28rem; font-size:0.9rem; display:block; }
            /* Re-assert center alignment in this mode */
            #form-rows-container .form-row .form-control { text-align: center; }
            #form-rows-container .form-row .proyecto-autocomplete,
            #form-rows-container .form-row .centro-costos { text-align: left; }
        }
        /* Slightly reduce Empleados panel width (~20% less than default col-md-4) on medium+ screens */
        @media (min-width: 768px) {
            .empleados-panel { flex: 0 0 26.6667% !important; max-width: 26.6667% !important; }
            .asignacion-panel { flex: 0 0 73.3333% !important; max-width: 73.3333% !important; }
        }
        /* Make the asignacion form a column-flex so the action buttons can be anchored to the bottom */
        #asignacion-form { display: flex; flex-direction: column; min-height: 100%; }
        #form-rows-container { flex: 1 1 auto; }
        .asignacion-actions { display: flex; gap: 0.6rem; align-items: center; margin-top: auto; padding-top: 0.6rem; flex-wrap: wrap; }
        .asignacion-actions .btn { min-width: 110px; }
        /* Small remove control at the start of each form-row */
        .form-container .row-remove {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            background: transparent;
            color: #8b0000;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 6px;
            cursor: pointer;
            user-select: none;
        }
        .form-container .row-remove:hover { background: rgba(0,0,0,0.04); }
        .row-remove-msg {
            position: absolute; background: #fff3cd; border:1px solid #ffeeba; padding:6px 8px; border-radius:6px; font-size:0.85rem; color:#856404; z-index:1200; box-shadow:0 4px 12px rgba(0,0,0,0.08);
        }
        /* Brief highlight for focused duplicate target */
        .duplicate-focus {
            animation: duplicatePulse 1s ease-in-out 1;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.18) inset, 0 2px 8px rgba(0,0,0,0.08);
            border-color: #ffc107 !important;
        }
        @keyframes duplicatePulse {
            0% { transform: translateY(0) scale(1); }
            40% { transform: translateY(-2px) scale(1.01); }
            100% { transform: translateY(0) scale(1); }
        }
        /* Locked month inputs when project is restricted */
        .months-locked { pointer-events: none; }
        .months-locked .form-control[readonly], .form-control.months-locked { background: #f1f3f5; color:#495057; cursor:not-allowed; }
    </style>
</head>

<body class="container py-5">

    <!-- Título principal con icono y color morado pastel -->
    <div class="d-flex justify-content-center align-items-center mb-3" style="gap: 10px;">
        <i class="bi bi-people-fill" style="font-size: 2.1rem; color: #8436c9;"></i>
        <h1 style="color: #8436c9; font-weight: 700; font-size: 1.7rem; margin: 0; letter-spacing: 0.5px; text-align: center;">ASIGNACION DE PERSONAL</h1>
    </div>

    <script>
    // Preserve scroll position across reloads/navigations for a better UX
    (function(){
        try {
            var key = 'coordinador_scroll_pos';
            function savePos(){ try { sessionStorage.setItem(key, String(window.scrollY || window.pageYOffset || 0)); } catch(e){} }
            function restoreOnce(){
                try {
                    var saved = sessionStorage.getItem(key);
                    if (saved !== null) {
                        var y = parseInt(saved, 10) || 0;
                        // try multiple times to counter scripts that refocus/scroll later
                        var attempts = [0, 50, 150, 400, 800];
                        attempts.forEach(function(delay, idx){
                            setTimeout(function(){ try { window.scrollTo(0, y); } catch(e){} }, delay);
                        });
                        // remove key after a short delay so subsequent unrelated loads don't reuse it
                        setTimeout(function(){ try { sessionStorage.removeItem(key); } catch(e){} }, 1200);
                    }
                } catch(e) { /* silent */ }
            }

            // Save before navigation/refresh
            window.addEventListener('beforeunload', savePos, {capture:true});

            // Also save when forms are submitted programmatically
            document.addEventListener('submit', function(e){ savePos(); }, true);

            // Restore after DOM is ready and after window load (cover both cases)
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                setTimeout(restoreOnce, 10);
            } else {
                document.addEventListener('DOMContentLoaded', function(){ setTimeout(restoreOnce, 10); });
            }
            window.addEventListener('load', function(){ setTimeout(restoreOnce, 20); });

            // helper to preserve scroll around DOM swaps
            window.__preserveScroll = function(fn){
                var x = window.pageXOffset || 0;
                var y = window.pageYOffset || 0;
                try { fn(); } catch(e) { console.error(e); }
                window.scrollTo(x, y);
            };
        } catch(e) { console.error(e); }
    })();
    </script>

    <div class="row">
        <div class="col-12 col-md-6">
            <div id="user-card" class="card p-2" style="display:none; max-height:70px; overflow:hidden;">
                <div class="card-body py-2">
                    <h6 class="card-title text-start mb-0" style="color:#4C8AA3; font-weight:600; font-size:0.95rem;">
                        <?php
                        // Mostrar información del usuario (tarjeta compacta)
                        echo htmlspecialchars($nombre_usuario);
                        ?>
                    </h6>
                    <div class="small text-muted" style="margin-top:2px;">
                        <?php echo 'Área: ' . htmlspecialchars(!empty($active_area_funcional) ? $active_area_funcional : $area_funcional); ?>
                    </div>
                    <!-- Asignación: tabla moderna bajo Empleados -->
                    <div class="card mt-3 p-3">
                        <h6 style="font-weight:700; color:#4C8AA3;">Asignación (vista rápida)</h6>
                        <div class="table-responsive" style="max-height:320px; overflow:auto;">
                            <table id="asignacion-table-modern" class="table table-sm table-striped table-bordered align-middle" style="min-width:1100px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Matricula</th>
                                        <th>Nombre</th>
                                        <th>Centro Costos</th>
                                        <th>Nombre Proyecto</th>
                                        <?php
                                        $months = [];
                                        $spanMonths = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                                        foreach ([2026,2027,2028] as $y) {
                                            foreach ($spanMonths as $m) {
                                                // Ocultar Ene_2026 y Feb_2026
                                                if ($y == 2026 && ($m == 'Ene' || $m == 'Feb')) continue;
                                                $months[] = $m . '_' . $y;
                                            }
                                        }
                                        foreach ($months as $ms) {
                                            echo '<th>' . htmlspecialchars(str_replace('_', ' ', $ms)) . '</th>';
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($asignacion_data)): ?>
                                        <?php foreach ($asignacion_data as $ar): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ar['matricula'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($ar['Nombre_Empleado_Completo'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($ar['centro_costos'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($ar['nombre_proyecto'] ?? ''); ?></td>
                                                <?php foreach ($months as $ms): ?>
                                                    <td><?php echo isset($ar[$ms]) && $ar[$ms] !== null && $ar[$ms] !== '' ? number_format((float)$ar[$ms], 2, ',', '.') : '-'; ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="<?php echo 4 + count($months); ?>" class="text-center">No hay asignaciones para la matrícula seleccionada.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top:8px;">
                            <button type="button" id="quick-add-asignacion" class="btn btn-sm btn-outline-primary">Agregar asignación</button>
                            <small class="text-muted ms-2">(Abre el formulario de asignación para editar)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Move the compact user card into the header placeholder so it appears beside the logo
    document.addEventListener('DOMContentLoaded', function() {
        try {
            var placeholder = document.querySelector('.page-title-placeholder');
            var userCard = document.getElementById('user-card');
            if (placeholder && userCard) {
                // ensure placeholder uses flex and aligns items
                placeholder.style.display = 'flex';
                placeholder.style.alignItems = 'center';
                placeholder.style.gap = '12px';
                // move the card into the placeholder
                placeholder.appendChild(userCard);
                // adjust small styles for inline display
                userCard.style.maxHeight = '';
                userCard.style.overflow = 'visible';
                userCard.style.margin = '0';
                userCard.style.padding = '6px';
            }
        } catch (e) { console && console.warn && console.warn('Failed moving user card to header', e); }
    });
    </script>

    <!-- Mover Resumen Ejecutivo de Proyectos aquí (antes de Empleados / Formulario de Asignación) -->
    <!-- Nota: las referencias a la vista `costo_asginado_resumen` fueron eliminadas -->
    <style>
    #resumen-card, #resumen-proyectos-table {
        display: none !important;
    }
    </style>
    <div class="card p-4 mt-4" id="resumen-card">
        <div class="card-body">
            <h5 class="card-title mb-3">Resumen Ejecutivo de Proyectos</h5>
            
            <div class="mb-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-auto" style="min-width:250px;">
                        <label class="form-label" style="font-weight: bold; color: #4C4C4C;"><i class="bi bi-diagram-3" style="margin-right:6px;"></i>Área Funcional</label>
                        <select id="area_funcional_select" name="area_funcional" class="form-select" style="width:100%" onchange="this.form.submit()" <?= $can_select_multiple_areas ? '' : 'disabled' ?>>
                            <?php if ($default_to_all_visible_areas): ?>
                                <option value="" <?= $area_filter === '' ? 'selected' : '' ?>>-- Todas --</option>
                            <?php endif; ?>
                            <?php if (!empty($areas_for_dropdown)): ?>
                                <?php foreach($areas_for_dropdown as $area_option): ?>
                                    <option value="<?= htmlspecialchars($area_option) ?>" <?= ($area_option === $area_filter) ? 'selected' : '' ?>><?= htmlspecialchars($area_option) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">Sin áreas disponibles</option>
                            <?php endif; ?>
                        </select>
                        <?php if (!$can_select_multiple_areas && $area_filter !== ''): ?>
                            <input type="hidden" name="area_funcional" value="<?= htmlspecialchars($area_filter) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-auto" style="min-width:250px;">
                        <label class="form-label" style="font-weight: bold; color: #4C4C4C;"><i class="bi bi-folder2-open" style="margin-right:6px;"></i>Nombre Proyecto</label>
                        <select id="nombre_proyecto_select" name="nombre_proyecto" class="form-select" style="width:100%" onchange="this.form.submit()">
                            <option value="">-- Todos --</option>
                            <?php foreach($nombres_proyectos as $np): ?>
                                <option value="<?= htmlspecialchars($np) ?>" <?= (isset($name_filter) && $np === $name_filter) ? 'selected' : '' ?>><?= htmlspecialchars($np) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($show_retired): ?>
                        <input type="hidden" name="show_retired" value="1">
                    <?php endif; ?>
                    <div class="col-auto">
                        <a href="Coordinador.php" class="btn btn-outline-secondary">Limpiar filtros</a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table id="resumen-proyectos-table" class="table resumen-table align-middle text-center mb-0" style="background:#fff; width: 100%;">
                <style>
                /* La columna CECO se ajusta proporcionalmente al contenido */
                #resumen-proyectos-table th:nth-child(2),
                #resumen-proyectos-table td:nth-child(2) {
                    width: auto;
                    max-width: none;
                    min-width: 40px;
                    text-align: center;
                    white-space: nowrap;
                }
                </style>
                    <thead class="resumen-thead">
                        <tr>
                            <th>ÁREA FUNCIONAL</th>
                            <th>CECO</th>
                            <th>NOMBRE PROYECTO</th>
                            <th>NATURE IMPUTATION</th>
                            <th>TOTAL_COSTO_ASIGNADO</th>
                            <th>PENDIENTE POR ASIGNAR</th>
                            <th>PTO A TERMINACIÓN (BAC)</th>
                            <th>COSTO ACTUAL (AC)</th>
                            <th>COSTO TEORICO</th>
                            <th>COSTO POR EJECUTAR (ETC)</th>
                            <th>% AVANCE FÍSICO EJECUTADO</th>
                            <th>% AVANCE FÍSICO PROGRAMADO</th>
                            <th>% COSTO COAN EJECUTADO</th>
                            <th>CPI</th>
                            <th>SPI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($resumen_rows)): ?>
                            <?php foreach($resumen_rows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['ÁREA FUNCIONAL'] ?? '-') ?></td>
                                    <td><span class="project-name"><?= htmlspecialchars($row['PROYECTO']) ?></span></td>
                                    <td><?= htmlspecialchars($row['nombre_proyecto'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['nature_imputation'] ?? '-') ?></td>
                                    <?php
                                        $total_asignado = isset($row['TOTAL_COSTO_ASIGNADO']) ? (float)$row['TOTAL_COSTO_ASIGNADO'] : 0.0;
                                        $ac_ajustado_fila = (float)$row['total_valorizado_2025'] + (float)$row['total_costo_imputado_aprobado'];
                                    ?>
                                    <td class="text-success">$ <?= number_format($total_asignado, 0, '', '.') ?></td>
                                    <td class="text-danger">
                                        <?php
                                            $bac = isset($row['total_costo']) ? (float)$row['total_costo'] : 0.0;
                                            $pendiente = $bac - ($ac_ajustado_fila + $total_asignado);
                                            echo '$ ' . number_format($pendiente, 0, '', '.');
                                        ?>
                                    </td>
                                    <td class="text-success">$ <?= number_format((float)$row['total_costo'], 0, '', '.') ?></td>
                                    <td class="text-info">$ <?= number_format($ac_ajustado_fila, 0, '', '.') ?></td>
                                    <?php 
                                        $costo_teorico = 0;
                                        $pct_ejecutado = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'];
                                        if ((float)$row['total_costo'] > 0) {
                                            $costo_teorico = ($pct_ejecutado / 100.0) * (float)$row['total_costo'];
                                        }
                                    ?>
                                    <td class="text-secondary">$ <?= number_format($costo_teorico, 0, '', '.') ?></td>
                                    <td class="text-warning">$ <?= number_format((float)$row['total_costo'] - $ac_ajustado_fila, 0, '', '.') ?></td>
                                    <td><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'], 0) ?>%</td>
                                    <td><?= number_format((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'], 0) ?>%</td>
                                    <td><?php 
                                        $porcentaje_coan = 0;
                                        if ((float)$row['total_costo'] > 0) {
                                            $porcentaje_coan = ($ac_ajustado_fila / (float)$row['total_costo']) * 100;
                                        }
                                        echo number_format($porcentaje_coan, 0) . '%';
                                    ?></td>
                                    <td class="text-center" style="background-color: <?php 
                                        $cpi = 0;
                                        $ac = $ac_ajustado_fila;
                                        if ($ac > 0) {
                                            $cpi = $costo_teorico / $ac;
                                        }
                                        if ($cpi == 0) echo '#f8f9fa';
                                        else if ($cpi >= 1) echo '#98d8a7';
                                        else echo '#ffb3b3';
                                    ?>; color: <?php 
                                        if ($cpi == 0) echo '#666666';
                                        else if ($cpi >= 1) echo '#2d5a3a';
                                        else echo '#8b0000';
                                    ?>; font-weight: bold; padding: 5px;"><?php
                                        echo number_format($cpi, 2);
                                    ?></td>
                                    <td class="text-center" style="background-color: <?php 
                                        $spi = 0;
                                        if ((float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'] > 0) {
                                            $spi = (float)$row['PORCENTAJE_AVANCE_FISICO_EJECUTADO'] / (float)$row['PORCENTAJE_AVANCE_FISICO_PROGRAMADO'];
                                        }
                                        if ($spi == 0) echo '#f8f9fa';
                                        else if ($spi >= 1) echo '#98d8a7';
                                        else echo '#ffb3b3';
                                    ?>; color: <?php 
                                        if ($spi == 0) echo '#666666';
                                        else if ($spi >= 1) echo '#2d5a3a';
                                        else echo '#8b0000';
                                    ?>; font-weight: bold; padding: 5px;"><?php
                                        echo number_format($spi, 2);
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="14" class="text-center">No hay datos para mostrar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Filtros para la gráfica BAC vs AC vs Total Asignado por Proyecto -->
    <!-- Filtros para la gráfica BAC vs AC vs Total Asignado por Proyecto FUERA del card -->
    <div class="row" style="margin-top: 1rem;">
        <form method="get" class="row g-2 align-items-end mb-3" style="margin-bottom: 3.5rem; margin-left: 2.5rem;">
            <div class="col-auto" style="min-width:250px;">
                <label class="form-label" style="font-weight: bold; color: #4C4C4C;"><i class="bi bi-diagram-3" style="margin-right:6px;"></i>Área Funcional</label>
                <select id="area_funcional_select_chart" name="area_funcional" class="form-select" style="width:100%" onchange="this.form.submit()">
                    <option value="">-- Todas --</option>
                    <?php if (!empty($areas_for_dropdown)): ?>
                        <?php foreach($areas_for_dropdown as $area_option): ?>
                            <option value="<?= htmlspecialchars($area_option) ?>" <?= ($area_option === $area_filter) ? 'selected' : '' ?>><?= htmlspecialchars($area_option) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-auto" style="min-width:250px;">
                <label class="form-label" style="font-weight: bold; color: #4C4C4C;"><i class="bi bi-folder2-open" style="margin-right:6px;"></i>Nombre Proyecto</label>
                <select id="nombre_proyecto_select_chart" name="nombre_proyecto" class="form-select" style="width:100%" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <?php foreach($nombres_proyectos as $np): ?>
                        <option value="<?= htmlspecialchars($np) ?>" <?= (isset($name_filter) && $np === $name_filter) ? 'selected' : '' ?>><?= htmlspecialchars($np) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <a href="Coordinador.php" class="btn btn-outline-secondary">Limpiar filtros</a>
            </div>
        </form>
        <div style="height: 2.5rem;"></div>
        <div class="col-12">
            <div class="card p-3">
                <div class="card-body">
                    <h5 class="card-title" style="color: #4C8AA3; font-weight: bold; margin-top: -2.2rem; font-size: 1.5rem; display: flex; align-items: center; gap: 12px; justify-content: center; text-align: center;">
                        <span style="display: flex; align-items: center;">
                            <i class="bi bi-bar-chart-fill" style="font-size: 1.7rem; color: #4C8AA3; margin-right: 10px;"></i>
                        </span>
                        Resumen Ejecutivo - Gráfica BAC vs AC / Total Asignado
                    </h5>
                    <p class="small text-muted" style="text-align: center;">Valores por proyecto: BAC (PTO a terminación), AC (Costo actual) y Total Asignado (CECO).</p>
                    <div class="resumen-chart-wrapper" style="height:520px; max-height:600px;">
                        <canvas id="resumenChart" style="width:100%;height:100%;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Preparar los arrays para la gráfica a partir de $resumen_rows
    $chart_labels = array();
    $chart_bac = array();
    $chart_ac = array();
    $chart_total_asignado = array();

    if (!empty($resumen_rows) && is_array($resumen_rows)) {
        // Determinar si el filtro de área funcional está en 'todas' o vacío
        $area_funcional_filtro = isset($active_area_funcional) ? trim($active_area_funcional) : '';
        $agrupar_por_proyecto = ($area_funcional_filtro === '' || mb_strtolower($area_funcional_filtro) === 'todas');

        if ($agrupar_por_proyecto) {
            // Agrupar por nombre de proyecto (sumar valores si hay duplicados)
            $proyectos_agrupados = array();
            foreach ($resumen_rows as $row) {
                $label = '';
                if (!empty($row['nombre_proyecto'])) $label = $row['nombre_proyecto'];
                elseif (!empty($row['PROYECTO'])) $label = $row['PROYECTO'];
                else $label = 'Proyecto';
                $label = mb_substr(trim($label), 0, 80);

                $bac = isset($row['total_costo']) ? (float)$row['total_costo'] : 0.0;
                $ac = (isset($row['total_valorizado_2025']) ? (float)$row['total_valorizado_2025'] : 0.0)
                    + (isset($row['total_costo_imputado_aprobado']) ? (float)$row['total_costo_imputado_aprobado'] : 0.0);
                $total_asignado = isset($row['TOTAL_COSTO_ASIGNADO']) ? (float)$row['TOTAL_COSTO_ASIGNADO'] : 0.0;

                if (!isset($proyectos_agrupados[$label])) {
                    $proyectos_agrupados[$label] = [
                        'bac' => 0.0,
                        'ac' => 0.0,
                        'asignado' => 0.0
                    ];
                }
                $proyectos_agrupados[$label]['bac'] += $bac;
                $proyectos_agrupados[$label]['ac'] += $ac;
                $proyectos_agrupados[$label]['asignado'] += $total_asignado;
            }
            // Ordenar por BAC (descendente)
            uasort($proyectos_agrupados, function($a, $b) {
                if ($a['bac'] == $b['bac']) return 0;
                return ($a['bac'] > $b['bac']) ? -1 : 1;
            });
            foreach ($proyectos_agrupados as $label => $vals) {
                $chart_labels[] = $label;
                $chart_bac[] = $vals['bac'];
                $chart_ac[] = $vals['ac'];
                $chart_total_asignado[] = $vals['asignado'];
            }
        } else {
            // Comportamiento original: cada fila es un item
            $chart_items = array();
            foreach ($resumen_rows as $row) {
                $label = '';
                if (!empty($row['nombre_proyecto'])) $label = $row['nombre_proyecto'];
                elseif (!empty($row['PROYECTO'])) $label = $row['PROYECTO'];
                else $label = 'Proyecto';

                $bac = isset($row['total_costo']) ? (float)$row['total_costo'] : 0.0;
                $ac = (isset($row['total_valorizado_2025']) ? (float)$row['total_valorizado_2025'] : 0.0)
                    + (isset($row['total_costo_imputado_aprobado']) ? (float)$row['total_costo_imputado_aprobado'] : 0.0);

                $total_asignado = isset($row['TOTAL_COSTO_ASIGNADO']) ? (float)$row['TOTAL_COSTO_ASIGNADO'] : 0.0;

                $chart_items[] = array(
                    'label' => mb_substr(trim($label), 0, 80),
                    'bac' => $bac,
                    'ac' => $ac,
                    'asignado' => $total_asignado
                );
            }
            // Ordenar por BAC (descendente)
            usort($chart_items, function($a, $b) {
                if ($a['bac'] == $b['bac']) return 0;
                return ($a['bac'] > $b['bac']) ? -1 : 1;
            });
            foreach ($chart_items as $it) {
                $chart_labels[] = $it['label'];
                $chart_bac[] = $it['bac'];
                $chart_ac[] = $it['ac'];
                $chart_total_asignado[] = $it['asignado'];
            }
        }
    }

    $js_labels = json_encode($chart_labels, JSON_UNESCAPED_UNICODE);
    $js_bac = json_encode($chart_bac, JSON_NUMERIC_CHECK);
    $js_ac = json_encode($chart_ac, JSON_NUMERIC_CHECK);
    $js_total_asignado = json_encode($chart_total_asignado, JSON_NUMERIC_CHECK);
    ?>

    <!-- Incluir Chart.js desde CDN y renderizar la gráfica -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            try {
                const labels = <?php echo $js_labels; ?> || [];
                const bacData = <?php echo $js_bac; ?> || [];
                const acData = <?php echo $js_ac; ?> || [];
                const asignadoData = <?php echo $js_total_asignado; ?> || [];

                const ctx = document.getElementById('resumenChart').getContext('2d');
                // Destroy previous instance if any (in case of partial reloads)
                if (ctx._chartInstance) { ctx._chartInstance.destroy(); }

                ctx._chartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            // BAC on its own stack so it doesn't stack with AC/Asignado
                            { label: 'BAC (PTO a terminación)', data: bacData, backgroundColor: '#5394ad', stack: 'bac' },
                            // AC and Total Asignado share the same stack so they appear apiladas
                            { label: 'AC (Costo actual)', data: acData, backgroundColor: '#3ec97a', stack: 'ac_stack' },
                            { label: 'Total Asignado (CECO)', data: asignadoData, backgroundColor: '#7c5fd4', stack: 'ac_stack' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const v = context.parsed.y || 0;
                                        return context.dataset.label + ': $' + v.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            // Enable stacking on both axes; datasets with the same `stack` id will stack together.
                            x: {
                                stacked: true,
                                ticks: { autoSkip: true, maxRotation: 45, minRotation: 0 },
                                grid: {
                                    display: true,
                                    drawTicks: true,
                                    color: 'rgba(120,120,120,0.18)', // gris oscuro muy leve
                                    lineWidth: 2,
                                    borderDash: [2, 6], // línea punteada sutil
                                }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 50000000,
                                    callback: function(value) { return '$' + Number(value).toLocaleString(); }
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Error al renderizar la gráfica de resumen:', e);
            }
        })();
    </script>

    <div class="row mt-4">
        <div class="col-md-4 empleados-panel">
            <div class="card p-4">
                <div class="card-body">
                    <h5 class="card-title mb-3 d-flex align-items-center" style="gap: 8px; color:#444950; font-weight:700;">
                        <i class="bi bi-people" style="color:#444950; font-size:1.3rem;"></i>
                        <span style="font-weight:700; color:#444950;">Listado Colaboradores</span>
                    </h5>
                    <?php
                    // Cargamos desde la vista vista_empleados filtrando por Área Funcional del usuario logueado
                    // y excluyendo empleados retirados (fechas_retiro < today) por defecto.
                    // Se puede mostrar también los retirados si se activa el filtro correspondiente.

                    // Checkbox UI para togglear mostrar empleados retirados
                    ?>
                    <form method="get" class="mb-2" id="show-retired-form">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="showRetiredToggle" name="show_retired" value="1" <?php echo $show_retired ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <label class="form-check-label" for="showRetiredToggle">Mostrar empleados retirados</label>
                        </div>
                        <?php if (!empty($active_area_funcional)): ?>
                            <input type="hidden" name="area_funcional" value="<?php echo htmlspecialchars($active_area_funcional); ?>">
                        <?php endif; ?>
                        <?php if ($name_filter !== ''): ?>
                            <input type="hidden" name="nombre_proyecto" value="<?php echo htmlspecialchars($name_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($selected_matricula)): ?>
                            <input type="hidden" name="matricula" value="<?php echo htmlspecialchars($selected_matricula); ?>">
                        <?php endif; ?>
                    </form>
                    <?php

                    $conn_emps = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                    if ($conn_emps->connect_error) {
                        echo '<div class="alert alert-danger">Error al conectar para cargar empleados.</div>';
                    } else {
                        // Usamos prepared statement para evitar inyecciones y filtrar por área funcional
                        $sql_emps = "SELECT * FROM vista_empleados WHERE 1=1";
                        $sql_emps .= $build_area_sql_filter($conn_emps, 'area_funcional');
                        if (!$show_retired) {
                            $sql_emps .= " AND (fechas_retiro IS NULL OR fechas_retiro >= CURDATE())";
                        }
                        $sql_emps .= " ORDER BY Nombre_Empleado_Completo ASC";
                        $res_emps = $conn_emps->query($sql_emps);
                    ?>
                    <div style="max-height:420px; overflow:auto;">
                        <table class="table table-hover table-sm" id="empleados-table">
                            <thead>
                                <tr>
                                    <th style="width:1%;"></th>
                                    <th>Nombre</th>
                                    <th class="text-center" style="min-width:110px; max-width:130px;">Tarifa COAN</th>
                                    <th>Fecha Ingreso</th>
                                    <th>Fecha Retiro</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($res_emps && $res_emps->num_rows > 0): ?>
                                <?php while ($er = $res_emps->fetch_assoc()): ?>
                                    <?php
                                        // Determinar un identificador para el empleado (id o matricula si existen)
                                        if (isset($er['id'])) { $emp_id = $er['id']; }
                                        elseif (isset($er['matricula'])) { $emp_id = $er['matricula']; }
                                        else { $emp_id = htmlspecialchars($er['Nombre_Empleado_Completo'] . '|' . ($er['fecha_ingreso'] ?? '')); }
                                    ?>
                                    <?php
                                        // Detectar si el empleado está retirado (fecha anterior a hoy)
                                        $is_retired = false;
                                        $fech_retiro_val = isset($er['fechas_retiro']) ? $er['fechas_retiro'] : null;
                                        $employee_full_name = trim((string)($er['Nombre_Empleado_Completo'] ?? ''));
                                        $employee_name_parts = $split_assignment_employee_name($employee_full_name);
                                        $employee_nom = trim((string)($er['Nom'] ?? $employee_name_parts['nom']));
                                        $employee_prenom = trim((string)($er['Prenom'] ?? $employee_name_parts['prenom']));
                                        if (!empty($fech_retiro_val)) {
                                            // Comparación segura asumiendo formato YYYY-MM-DD
                                            $is_retired = (strtotime($fech_retiro_val) < strtotime(date('Y-m-d')));
                                        }
                                        $row_class = ($show_retired && $is_retired) ? 'table-warning' : '';

                                        // Matricula del empleado (si existe en la vista)
                                        $row_matricula = isset($er['matricula']) ? $er['matricula'] : $emp_id;
                                        $is_selected = (!empty($selected_matricula) && $selected_matricula == $row_matricula);
                                    ?>
                                    <tr class="<?php echo $row_class . ($is_selected ? ' table-primary' : ''); ?>" data-id="<?php echo htmlspecialchars($emp_id); ?>" data-matricula="<?php echo htmlspecialchars($row_matricula); ?>" data-horas-diarias="<?php echo htmlspecialchars($er['horas_diarias'] ?? ''); ?>" data-tarifa-coan="<?php echo htmlspecialchars($er['tarifa_coan'] ?? ''); ?>" data-full-name="<?php echo htmlspecialchars($employee_full_name); ?>" data-nom="<?php echo htmlspecialchars($employee_nom); ?>" data-prenom="<?php echo htmlspecialchars($employee_prenom); ?>">
                                        <td><input type="radio" name="selected_employee_radio" value="<?php echo htmlspecialchars($row_matricula); ?>" <?php echo $is_selected ? 'checked' : ''; ?>></td>
                                        <td>
                                            <?php echo htmlspecialchars($er['Nombre_Empleado_Completo'] ?? ''); ?>
                                            <input type="hidden" class="horas_diarias_hidden" value="<?php echo htmlspecialchars($er['horas_diarias'] ?? ''); ?>">
                                            <input type="hidden" class="tarifa_coan_hidden" value="<?php echo htmlspecialchars($er['tarifa_coan'] ?? ''); ?>">
                                            <?php /* Oculto para usuario final: badge de Retirado */ ?>
                                        </td>
                                        <td class="tarifa-coan-cell text-center" style="min-width:110px; max-width:130px;">
                                            <?php echo isset($er['tarifa_coan']) && $er['tarifa_coan'] !== '' ? '$ ' . number_format((float)$er['tarifa_coan'], 2, ',', '.') : '-'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($er['fecha_ingreso'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($er['fechas_retiro'] ?? ''); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No se encontraron empleados en la vista.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <script>
                    // Mapeo de proyectos a centro_costos desde PHP a JS
                    var projectsMap = {};
                    <?php if (isset($projects_map) && is_array($projects_map)): ?>
                        <?php foreach ($projects_map as $pn => $cc): ?>
                            projectsMap[<?php echo json_encode($pn); ?>] = <?php echo json_encode($cc); ?>;
                        <?php endforeach; ?>
                    <?php endif; ?>

                    // Función para actualizar el campo centro_costos según el proyecto seleccionado
                    function updateCentroCostos(selectElem) {
                        var row = selectElem.closest('.form-row, .form-container');
                        if (!row) return;
                        var proyecto = selectElem.value ? selectElem.value.trim() : '';
                        var centroInput = row.querySelector('input[name="centro_costos[]"]');
                        if (centroInput) {
                            // Buscar por clave exacta y por clave sin espacios extra
                            if (projectsMap.hasOwnProperty(proyecto)) {
                                centroInput.value = projectsMap[proyecto];
                            } else {
                                // Buscar ignorando espacios y mayúsculas
                                var found = false;
                                for (var key in projectsMap) {
                                    if (key && key.trim().toLowerCase() === proyecto.toLowerCase()) {
                                        centroInput.value = projectsMap[key];
                                        found = true;
                                        break;
                                    }
                                }
                                if (!found) centroInput.value = '';
                            }
                        }
                        // Guardado automático por AJAX solo si hay PK y centro_costos no vacío
                        var pkInput = row.querySelector('input[name="<?php echo $primary_key_column; ?>[]"]');
                        if (pkInput && pkInput.value && centroInput && centroInput.value) {
                            var formData = new FormData();
                            formData.append('pk', pkInput.value);
                            formData.append('centro_costos', centroInput.value);
                            fetch('update_centro_costos.php', {
                                method: 'POST',
                                body: formData,
                                credentials: 'same-origin'
                            })
                            .then(function(resp) { return resp.json(); })
                            .then(function(data) {
                                if (!data.success && data.error !== 'Datos incompletos') {
                                    alert('Error guardando centro_costos: ' + (data.error || 'Error desconocido'));
                                } else if (data.success) {
                                    // Actualizar el resumen para reflejar cambios que afecten a CECO/Costos
                                    if (typeof refreshResumen === 'function') {
                                        try { refreshResumen(); } catch (e) { /* ignore */ }
                                    }
                                }
                            })
                            .catch(function() {
                                alert('Error de red al guardar centro_costos');
                            });
                        }
                    }

                    // Asignar evento a selects existentes y sincronizar centro_costos al cargar
                    function syncAllCentroCostos() {
                        document.querySelectorAll('.proyecto-autocomplete').forEach(function(sel) {
                            sel.removeEventListener('change', sel._ccChangeListener || (()=>{}));
                            sel.removeEventListener('input', sel._ccInputListener || (()=>{}));
                            sel._ccChangeListener = function() { updateCentroCostos(sel); };
                            sel._ccInputListener = function() { updateCentroCostos(sel); };
                            sel.addEventListener('change', sel._ccChangeListener);
                            sel.addEventListener('input', sel._ccInputListener);
                            updateCentroCostos(sel); // sincroniza valor al cargar
                        });
                    }
                    syncAllCentroCostos();

                    // Si se agregan filas dinámicamente, también asignar el evento y sincronizar
                    var addRowBtn = document.getElementById('add-row-btn');
                    if (addRowBtn) {
                        addRowBtn.addEventListener('click', function() {
                            setTimeout(function() {
                                syncAllCentroCostos();
                            }, 100);
                        });
                    }

                    // Función para refrescar el Resumen Ejecutivo por AJAX (reemplaza el card entero)
                    function refreshResumen() {
                        var savedY = window.pageYOffset || window.scrollY || 0;
                        var params = [];
                        var areaSelect = document.getElementById('area_funcional_select');
                        if (areaSelect && areaSelect.value) {
                            params.push('area_funcional=' + encodeURIComponent(areaSelect.value));
                        } else {
                            var areaHidden = document.querySelector('#resumen-card input[name="area_funcional"]');
                            if (areaHidden && areaHidden.value) {
                                params.push('area_funcional=' + encodeURIComponent(areaHidden.value));
                            }
                        }
                        var np = document.getElementById('nombre_proyecto_select');
                        if (np && np.value) params.push('nombre_proyecto=' + encodeURIComponent(np.value));
                        var url = 'get_resumen_fragment_new.php' + (params.length ? ('?' + params.join('&')) : '');
                        fetch(url, { credentials: 'same-origin' })
                            .then(function(resp){ return resp.text(); })
                            .then(function(html){
                                try {
                                    var parser = new DOMParser();
                                    var doc = parser.parseFromString(html, 'text/html');
                                    var newCard = doc.querySelector('#resumen-card');
                                    var oldCard = document.querySelector('#resumen-card');
                                    if (newCard && oldCard && oldCard.parentNode) {
                                        // preserve scroll around the DOM swap
                                        try {
                                            var parent = oldCard.parentNode;
                                            parent.replaceChild(newCard, oldCard);
                                            // restore scroll
                                            window.scrollTo(0, savedY);
                                        } catch(e) {
                                            // fallback: set session key then reload
                                            try { sessionStorage.setItem('coordinador_scroll_pos', String(savedY)); } catch(se) {}
                                            window.location.reload();
                                        }
                                        // Rebind the refresh button inside the new fragment
                                        var rbtn = document.getElementById('refresh-resumen-btn');
                                        if (rbtn) rbtn.addEventListener('click', function(){ refreshResumen(); });
                                        if (typeof window.initResumenProjectSelect === 'function') {
                                            window.initResumenProjectSelect();
                                        }
                                    }
                                } catch (e) {
                                    // If parsing fails, fallback to full reload (preserve scroll)
                                    console.error('Error parsing resumen fragment:', e);
                                    try { sessionStorage.setItem('coordinador_scroll_pos', String(savedY)); } catch(se) {}
                                    window.location.reload();
                                }
                            })
                            .catch(function(err){ console.error('Error fetching resumen fragment:', err); });
                    }

                    // bind manual refresh button if present
                    var refreshBtnExisting = document.getElementById('refresh-resumen-btn');
                    if (refreshBtnExisting) refreshBtnExisting.addEventListener('click', function(){ refreshResumen(); });
                    </script>
                    <?php
                        if (isset($res_emps) && $res_emps) $res_emps->free();
                        $conn_emps->close();
                    }
                    ?>
                </div>
            </div>
        </div>

    <?php
    // Determinar proyectos bloqueados: si (AC + Total Asignado) > BAC entonces bloqueado
    $blocked_projects = array();
    if (!empty($resumen_rows) && is_array($resumen_rows)) {
        foreach ($resumen_rows as $r) {
            $bac = isset($r['total_costo']) ? (float)$r['total_costo'] : 0.0;
            $ac = (isset($r['total_valorizado_2025']) ? (float)$r['total_valorizado_2025'] : 0.0)
                + (isset($r['total_costo_imputado_aprobado']) ? (float)$r['total_costo_imputado_aprobado'] : 0.0);
            $total_asignado = isset($r['TOTAL_COSTO_ASIGNADO']) ? (float)$r['TOTAL_COSTO_ASIGNADO'] : 0.0;

            if (($ac + $total_asignado) > $bac) {
                $label = '';
                if (!empty($r['nombre_proyecto'])) $label = $r['nombre_proyecto'];
                elseif (!empty($r['PROYECTO'])) $label = $r['PROYECTO'];
                if ($label !== '') $blocked_projects[] = $label;
            }
        }
    }
    $blocked_projects = array_values(array_unique($blocked_projects));
    // Lowercase map for comparisons in templates
    $blocked_projects_lc = array_map(function($v){ return mb_strtolower(trim($v)); }, $blocked_projects);

    // Proyectos ya asignados a la matrícula seleccionada (para no repetir en el dropdown)
    $normalize_assigned_project_key = function($value) {
        $value = trim((string)$value);
        if ($value === '') return '';
        // quitar tildes y normalizar
        if (function_exists('iconv')) {
            $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($trans !== false) $value = $trans;
        }
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = preg_replace('/[^a-z0-9]/', '', $value);
        return trim((string)$value);
    };
    $assigned_projects_keyset = [];
    $assigned_cecos_keyset = [];
    $normalize_assigned_ceco_key = function($value) {
        $value = trim((string)$value);
        if ($value === '') return '';
        $value = strtoupper($value);
        $value = preg_replace('/\s+/', '', $value);
        return trim((string)$value);
    };
    if (!empty($asignacion_data) && is_array($asignacion_data)) {
        foreach ($asignacion_data as $r) {
            $pname = $extract_assignment_project_name($r['nombre_proyecto'] ?? '');
            $k = $normalize_assigned_project_key($pname);
            if ($k !== '') $assigned_projects_keyset[$k] = true;

            $ceco = trim((string)($r['centro_costos'] ?? ''));
            $ck = $normalize_assigned_ceco_key($ceco);
            if ($ck !== '') $assigned_cecos_keyset[$ck] = true;
        }
    }

    $assignment_month_to_sql_date = function($columnName) {
        $parts = explode('_', (string)$columnName);
        $monthMap = [
            'Ene' => '01', 'Feb' => '02', 'Mar' => '03', 'Abr' => '04',
            'May' => '05', 'Jun' => '06', 'Jul' => '07', 'Ago' => '08',
            'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dic' => '12'
        ];
        $month = $parts[0] ?? '';
        $year = $parts[1] ?? '';
        if ($month === '' || $year === '' || !isset($monthMap[$month])) {
            return '';
        }
        return $year . '-' . $monthMap[$month] . '-01';
    };

    $assignment_comments_map = [];
    if ($selected_matricula !== '' && $mysqli instanceof mysqli && !$mysqli->connect_error) {
        $sql_assignment_comments = "SELECT numero_de_empleado, codigo_affaire, fecha, comentario FROM comentarios_asignacion WHERE numero_de_empleado = ?";
        if ($stmt_assignment_comments = $mysqli->prepare($sql_assignment_comments)) {
            $stmt_assignment_comments->bind_param('s', $selected_matricula);
            if ($stmt_assignment_comments->execute()) {
                $res_assignment_comments = $stmt_assignment_comments->get_result();
                while ($comment_row = $res_assignment_comments->fetch_assoc()) {
                    $comment_employee = trim((string)($comment_row['numero_de_empleado'] ?? ''));
                    $comment_affaire = strtoupper(trim((string)($comment_row['codigo_affaire'] ?? '')));
                    $comment_date = trim((string)($comment_row['fecha'] ?? ''));
                    if ($comment_employee === '' || $comment_affaire === '' || $comment_date === '') {
                        continue;
                    }
                    $comment_key = $comment_employee . '|' . $comment_affaire . '|' . $comment_date;
                    $assignment_comments_map[$comment_key] = [
                        'comentario' => (string)($comment_row['comentario'] ?? ''),
                    ];
                }
                $res_assignment_comments->free();
            }
            $stmt_assignment_comments->close();
        } else {
            error_log('Coordinador.php: fallo prepare comentarios_asignacion: ' . $mysqli->error);
        }
    }
    ?>

    <style>
        /* En filas nuevas (sin guardar) ocultar casillas de meses y mostrar un solo mensaje */
        #form-rows-container .form-row.row-unsaved .mes-input-wrap { display: none !important; }
        #form-rows-container .form-row .save-project-first-msg { display: none; }
        #form-rows-container .form-row.row-unsaved .save-project-first-msg {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 520px;
            margin-bottom: 0.5rem;
        }

        /* Centro de costos se mantiene para lógica/guardado, pero se oculta al usuario */
        #asignacion-form .col-cc { display: none !important; }

        #form-rows-container .assignment-month-cell {
            position: relative;
            padding-top: 0;
        }

        #form-rows-container .assignment-month-input-shell {
            position: relative;
            width: 100px;
            max-width: 100px;
        }

        #form-rows-container .assignment-month-input-shell .form-control {
            width: 100%;
            max-width: 100%;
            min-width: 100%;
            padding-right: 1.15rem;
        }

        #form-rows-container .assignment-comment-trigger {
            position: absolute;
            top: 50%;
            right: 0.35rem;
            width: 18px;
            height: 18px;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.95);
            color: #17823d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: translateY(-50%);
            transition: opacity 0.18s ease, transform 0.18s ease, background 0.18s ease;
            cursor: pointer;
            z-index: 3;
            box-shadow: 0 0 0 1px rgba(23, 130, 61, 0.15);
        }

        #form-rows-container .assignment-month-input-shell:hover .assignment-comment-trigger,
        #form-rows-container .assignment-month-input-shell:focus-within .assignment-comment-trigger,
        #form-rows-container .assignment-month-cell:hover .assignment-comment-trigger,
        #form-rows-container .assignment-month-cell:focus-within .assignment-comment-trigger {
            opacity: 1;
            transform: translateY(-50%);
        }

        #form-rows-container .assignment-comment-trigger:hover {
            background: #17823d;
            color: #fff;
        }

        #assignment-comment-modal .modal-header {
            background: linear-gradient(135deg, #4C8AA3 0%, #17823d 100%);
            color: #fff;
        }

        #assignment-comment-modal .comment-meta {
            background: #f6faf8;
            border: 1px solid #d5e9dc;
            border-radius: 10px;
            padding: 0.9rem 1rem;
            margin-bottom: 1rem;
        }

        #assignment-comment-modal .comment-meta-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #5f6b76;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        #assignment-comment-modal .comment-meta-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #23313d;
        }
    </style>

    <div class="col-md-8 asignacion-panel">
            <div class="card p-4">
                <div class="card-body">
                    <h5 class="card-title mb-4 d-flex align-items-center" style="gap: 8px; color:#444950; font-weight:700;">
                        <i class="bi bi-ui-checks-grid" style="color:#444950; font-size:1.3rem;"></i>
                        <span style="font-weight:700; color:#444950;">Formulario de Asignación</span>
                    </h5>
                    <?php echo $message; ?>
                    <div style="overflow-x: auto;">
                        <form id="asignacion-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" id="selected_employee_id" name="selected_employee_id" value="<?php echo htmlspecialchars($selected_matricula); ?>">
                            <input type="hidden" id="show_retired_hidden" name="show_retired" value="<?php echo $show_retired ? '1' : '0'; ?>">
                            <input type="hidden" name="page_area_funcional_filter" value="<?php echo htmlspecialchars($active_area_funcional); ?>">
                            <input type="hidden" name="summary_nombre_proyecto_filter" value="<?php echo htmlspecialchars($name_filter); ?>">
                            <!-- Encabezado de columnas -->
                            <div class="form-row form-header-row" style="display: flex; gap: 8px;">
                                <?php
                                // Mostrar primero Nombre Proyecto y Centro Costos
                                if (in_array('nombre_proyecto', $form_columns)) {
                                    echo '<div class="col-project" style="font-weight: bold; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">'
                                        . '<span style="width:100%; display:block; text-align:center;">Nombre Proyecto</span>'
                                        . '</div>';
                                }
                                if (in_array('centro_costos', $form_columns)) {
                                    echo '<div class="col-cc" style="font-weight: bold; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">'
                                        . '<span style="width:100%; display:block; text-align:center;">Centro Costos</span>'
                                        . '</div>';
                                }
                                // Luego los meses y otros campos (excluyendo nombre_proyecto, centro_costos, matricula)
                                foreach ($form_columns as $column) {
                                    if ($column !== 'nombre_proyecto' && $column !== 'centro_costos' && $column !== 'matricula') {
                                        // Ocultar visualmente Ene_2026 y Feb_2026 en encabezado
                                        if ($column === 'Ene_2026' || $column === 'Feb_2026') continue;
                                        $display = htmlspecialchars(str_replace('_', ' ', $column));
                                        // Si es columna mes, mostrar la etiqueta en el encabezado (una sola vez)
                                        if (preg_match('/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_[0-9]{4}$/', $column)) {
                                            echo '<div class="col-month" style="max-width: 140px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;">'
                                                . '<span style="font-size: 0.95rem; color: #17823d; font-weight: 700; margin-bottom: 2px; text-align: center; letter-spacing: 0.01em; width: 100%; display: block;">' . $display . '</span>'
                                                . '</div>';
                                        } else {
                                            // Para otros campos, mostrar el nombre tal cual pero alineado con los inputs
                                            echo '<div style="min-width: 120px; max-width: 140px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; margin-bottom: 0.5rem;">'
                                                . '<span style="font-size: 0.95rem; color: #17823d; font-weight: 600; margin-bottom: 2px; text-align: center; letter-spacing: 0.01em; width: 100%; display: block;">' . $display . '</span>'
                                                . '</div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                            <script>
                            // Forzar actualización de centro_costos antes de enviar el formulario
                            document.getElementById('asignacion-form').addEventListener('submit', function(e) {
                                document.querySelectorAll('.proyecto-autocomplete').forEach(function(sel) {
                                    if (typeof updateCentroCostos === 'function') updateCentroCostos(sel);
                                });
                            });
                            </script>
                            <div id="form-rows-container">
                                <?php if ($has_asignacion_rows): ?>
                                    <?php foreach ($asignacion_data as $index => $row) : ?>
                                        <?php
                                            $row_pk_value = (!empty($primary_key_column) && isset($row[$primary_key_column])) ? trim((string)$row[$primary_key_column]) : '';
                                            $row_is_unsaved = ($row_pk_value === '');
                                            $row_project_name = $extract_assignment_project_name($row['nombre_proyecto'] ?? '');
                                            $row_project_key = $normalize_assigned_project_key($row_project_name);
                                            $row_ceco_key = $normalize_assigned_ceco_key($row['centro_costos'] ?? '');
                                            if ($row_ceco_key === '' && isset($projects_map[$row_project_name])) {
                                                $row_ceco_key = $normalize_assigned_ceco_key($projects_map[$row_project_name]);
                                            }
                                        ?>
                                        <div class="form-container form-row<?php echo $row_is_unsaved ? ' row-unsaved' : ''; ?>">
                                            <div class="row-remove" title="Eliminar fila (sólo si está vacía)" role="button" aria-label="Eliminar fila">-</div>
                                            <?php if (!empty($primary_key_column)) : ?>
                                            <input type="hidden" name="<?php echo $primary_key_column; ?>[]" value="<?php echo htmlspecialchars($row[$primary_key_column]); ?>">
                                            <?php endif; ?>
                                            <?php 
                                            foreach ($form_columns as $column) : 
                                                // Ocultar visualmente Ene_2026 y Feb_2026 en inputs
                                                if ($column === 'Ene_2026' || $column === 'Feb_2026') continue;
                                                $style = 'min-width: 200px;';
                                                $required = '';
                                                $val = '';
                                                if ($column === 'nombre_proyecto') {
                                                    $rawVal = isset($row[$column]) ? $row[$column] : '';
                                                    if (preg_match('/_(2025|2026|2027|2028|2029|2030)$/', $column)) {
                                                        if ($rawVal === null || $rawVal === '' || (is_numeric($rawVal) && floatval($rawVal) == 0)) {
                                                            $val = '';
                                                        } else {
                                                            $val = $rawVal;
                                                        }
                                                    } else {
                                                        $val = $rawVal;
                                                    }
                                                    $val = htmlspecialchars($val);
                                                    // Campo nombre_proyecto
                                                    ?>
                                                        <div class="mb-3 col-project" style="min-width: 200px;">
                                                            <select aria-label="<?php echo htmlspecialchars($column); ?>" class="form-select proyecto-autocomplete" id="<?php echo $column; ?>_<?php echo $index; ?>" name="<?php echo $column; ?>[]" <?php echo $required; ?> style="width:100%; min-width:120px;">
                                                            <option value="">Seleccione un proyecto...</option>
                                                            <?php foreach ($selector_projects as $pn): ?>
                                                                <?php
                                                                    $pn_key = $normalize_assigned_project_key($pn);
                                                                    $pn_ceco = isset($projects_map[$pn]) ? trim((string)$projects_map[$pn]) : '';
                                                                    $pn_ceco_key = $normalize_assigned_ceco_key($pn_ceco);

                                                                    if ($pn_key !== '' && isset($assigned_projects_keyset[$pn_key]) && $pn_key !== $row_project_key) {
                                                                        continue;
                                                                    }

                                                                    if ($pn_ceco_key !== '' && isset($assigned_cecos_keyset[$pn_ceco_key]) && $pn_ceco_key !== $row_ceco_key) {
                                                                        continue;
                                                                    }
                                                                ?>
                                                                <?php $cc = isset($projects_map[$pn]) ? $projects_map[$pn] : ''; ?>
                                                                <option value="<?php echo htmlspecialchars($pn); ?>" <?php echo ($val == htmlspecialchars($pn) ? 'selected' : ''); ?>><?php echo htmlspecialchars($pn); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3 col-cc" style="min-width: 120px;">
                                                        <?php
                                                        $cc_value = '';
                                                        // Intento directo
                                                        if (isset($projects_map[$val])) {
                                                            $cc_value = $projects_map[$val];
                                                        } else {
                                                            // Búsqueda tolerante: ignorar mayúsculas/minúsculas, espacios y tildes
                                                            $normalize = function($s) {
                                                                $s = mb_strtolower($s);
                                                                $s = preg_replace('/[áàäâ]/u', 'a', $s);
                                                                $s = preg_replace('/[éèëê]/u', 'e', $s);
                                                                $s = preg_replace('/[íìïî]/u', 'i', $s);
                                                                $s = preg_replace('/[óòöô]/u', 'o', $s);
                                                                $s = preg_replace('/[úùüû]/u', 'u', $s);
                                                                $s = preg_replace('/[^a-z0-9]/u', '', $s);
                                                                return $s;
                                                            };
                                                            $val_norm = $normalize($val);
                                                            foreach ($projects_map as $kpn => $kcc) {
                                                                $kpn_norm = $normalize($kpn);
                                                                if ($val_norm === $kpn_norm) {
                                                                    $cc_value = $kcc;
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        // Si sigue vacío, buscar por centro de costo ya guardado en la fila
                                                        if (empty($cc_value) && isset($row['centro_costos']) && !empty($row['centro_costos'])) {
                                                            foreach ($projects_map as $kpn => $kcc) {
                                                                if (trim($kcc) === trim($row['centro_costos'])) {
                                                                    $cc_value = $kcc;
                                                                    break;
                                                                }
                                                            }
                                                            // Si aún así no se encuentra, usar el valor guardado
                                                            if (empty($cc_value)) {
                                                                $cc_value = $row['centro_costos'];
                                                            }
                                                        }
                                                        ?>
                                                        <input type="text" class="form-control" name="centro_costos[]" id="centro_costos_<?php echo $index; ?>" value="<?php echo htmlspecialchars($cc_value); ?>" readonly>
                                                    </div>
                                                    <?php
                                                } elseif ($column === 'centro_costos' || $column === 'matricula') {
                                                    // No mostrar visualmente
                                                    if ($column === 'matricula') {
                                                        ?>
                                                        <input type="hidden" name="<?php echo $column; ?>[]" id="<?php echo $column; ?>_<?php echo $index; ?>" value="<?php echo htmlspecialchars(isset($row[$column]) ? $row[$column] : ''); ?>">
                                                        <?php
                                                    }
                                                } else {
                                                    $is_month_column = (bool)preg_match('/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_[0-9]{4}$/', $column);
                                                    ?>
                                                    <div class="mes-input-wrap col-month<?php echo $is_month_column ? ' assignment-month-cell' : ''; ?>"<?php echo $is_month_column ? ' data-month-column="' . htmlspecialchars($column) . '"' : ''; ?> style="min-width: 120px; max-width: 140px; display: <?php echo $row_is_unsaved ? 'none' : 'flex'; ?>; flex-direction: column; align-items: center; margin-bottom: 0.5rem;">
                                                        <?php if (!$is_month_column) : ?>
                                                            <label for="<?php echo $column; ?>_<?php echo $index; ?>" style="font-size: 0.85rem; color: #17823d; font-weight: 600; margin-bottom: 2px; text-align: center; letter-spacing: 0.01em;">
                                                                <?php echo str_replace('_', ' ', $column); ?>
                                                            </label>
                                                        <?php endif; ?>
                                                        <?php
                                                        $cell_val = isset($row[$column]) ? $row[$column] : '';
                                                        if ($cell_val === null || $cell_val === '' || (is_numeric($cell_val) && floatval($cell_val) == 0)) {
                                                            $cell_val = '';
                                                        }
                                                        ?>
                                                        <?php if ($is_month_column): ?>
                                                            <div class="assignment-month-input-shell">
                                                                <input aria-label="<?php echo htmlspecialchars($column); ?>" type="text" class="form-control text-center" id="<?php echo $column; ?>_<?php echo $index; ?>" name="<?php echo $column; ?>[]" value="<?php echo htmlspecialchars($cell_val); ?>" <?php echo $required; ?> style="font-weight: bold; font-size: 1.05rem; background: #f8fafb; border: 1px solid #e0e0e0; max-width: 100px; min-width: 80px;">
                                                                <?php
                                                                $has_comment = false;
                                                                if (isset($assignment_comments_map) && is_array($assignment_comments_map)) {
                                                                    $commentDate = $assignment_month_to_sql_date($column);
                                                                    $key = trim((string)($row['matricula'] ?? '')) . '|' . (isset($row['centro_costos']) ? strtoupper(trim((string)$row['centro_costos'])) : '') . '|' . $commentDate;
                                                                    $has_comment = isset($assignment_comments_map[$key]) && trim((string)($assignment_comments_map[$key]['comentario'] ?? '')) !== '';
                                                                }
                                                                if ($has_comment): ?>
                                                                    <span class="cell-corner-badge"></span>
                                                                <?php endif; ?>
                                                                <button type="button" class="assignment-comment-trigger" data-month-column="<?php echo htmlspecialchars($column); ?>" aria-label="Agregar comentario" onclick="openAssignmentCommentModal(this); return false;">
                                                                    <i class="bi bi-chat-left-text"></i>
                                                                </button>
                                                            </div>
                                                        <?php else: ?>
                                                            <input aria-label="<?php echo htmlspecialchars($column); ?>" type="text" class="form-control text-center" id="<?php echo $column; ?>_<?php echo $index; ?>" name="<?php echo $column; ?>[]" value="<?php echo htmlspecialchars($cell_val); ?>" <?php echo $required; ?> style="font-weight: bold; font-size: 1.05rem; background: #f8fafb; border: 1px solid #e0e0e0; max-width: 100px; min-width: 80px;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                }
                                            endforeach; ?>

                                            <?php if ($row_is_unsaved): ?>
                                                <div class="alert alert-warning save-project-first-msg" role="note" style="margin:0;">Guarde primero el proyecto para registrar las horas.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">No hay asignaciones para la matrícula seleccionada. Use "Agregar Fila" para crear una nueva asignación.</div>
                                <?php endif; ?>
                            </div>
                        </form>
                        <div class="clearfix"></div>
                        <div class="asignacion-actions w-100" style="display:block; margin-top: 12rem; margin-bottom: 1rem; text-align:left;">
                            <div style="display:inline-flex; align-items:center; gap: 0.75rem;">
                                <button type="button" id="add-row-btn" class="btn btn-secondary btn-sm asignacion-btn-igual" style="width: 120px; height: 40px; padding: 0; font-size: 1rem; display: flex; align-items: center; justify-content: center;">Agregar Fila</button>
                                <?php if ($has_asignacion_rows): ?>
                                    <button type="submit" form="asignacion-form" name="submit_asignacion" class="btn btn-primary btn-sm asignacion-btn-igual" style="width: 120px; height: 40px; padding: 0; font-size: 1rem; display: flex; align-items: center; justify-content: center; background-color: #17823d; border-color: #17823d;">Guardar</button>
                                <?php else: ?>
                                    <button type="submit" form="asignacion-form" name="submit_asignacion" class="btn btn-primary btn-sm asignacion-btn-igual" style="width: 120px; height: 40px; padding: 0; font-size: 1rem; display: flex; align-items: center; justify-content: center; background-color: #17823d; border-color: #17823d; display:none;">Guardar</button>
                                <?php endif; ?>
                            </div>
                        </div>

                            <!-- Totales por columna (se generan dinámicamente desde Nov_2025 hasta Dic_2030) -->
                            <!-- (placeholder removed; totals will be rendered as a form-row inside #form-rows-container) -->
                        </form>

                        <!-- Template row for JS cloning -->
                        <template id="row-template">
                            <div class="form-container form-row row-unsaved">
                                <div class="row-remove" title="Eliminar fila (sólo si está vacía)" role="button" aria-label="Eliminar fila">-</div>
                                <?php if (!empty($primary_key_column)) : ?>
                                <input type="hidden" name="<?php echo $primary_key_column; ?>[]" value="">
                                <?php endif; ?>
                                <?php foreach ($form_columns as $column) : ?>
                                    <?php if ($column === 'matricula'): ?>
                                        <input type="hidden" name="<?php echo $column; ?>[]" value="">
                                    <?php elseif ($column === 'centro_costos'): ?>
                                        <div class="mb-3 col-cc" style="min-width: 120px;">
                                            <input type="text" class="form-control" name="centro_costos[]" value="" readonly>
                                        </div>
                                    <?php else: ?>
                                        <?php
                                            $tplColClass = 'mb-3';
                                            if ($column === 'nombre_proyecto') { $tplColClass .= ' col-project'; }
                                            elseif (preg_match('/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_[0-9]{4}$/', $column)) { $tplColClass .= ' col-month'; }
                                            else { $tplColClass .= ' col-month'; }

                                            $is_tpl_month = (bool)preg_match('/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_[0-9]{4}$/', $column);
                                        ?>
                                        <div class="<?php echo ($is_tpl_month ? 'mes-input-wrap assignment-month-cell ' : '') . $tplColClass; ?>"<?php echo $is_tpl_month ? ' data-month-column="' . htmlspecialchars($column) . '"' : ''; ?> style="min-width: 200px; <?php echo $is_tpl_month ? 'display:none;' : ''; ?>">
                                            <?php if ($column === 'nombre_proyecto'): ?>
                                                <select aria-label="<?php echo htmlspecialchars($column); ?>" class="form-select proyecto-autocomplete" name="<?php echo $column; ?>[]">
                                                    <option value="">Seleccione un proyecto...</option>
                                                    <?php foreach ($selector_projects as $pn): ?>
                                                        <?php
                                                            $pn_key = $normalize_assigned_project_key($pn);
                                                            $pn_ceco = isset($projects_map[$pn]) ? trim((string)$projects_map[$pn]) : '';
                                                            $pn_ceco_key = $normalize_assigned_ceco_key($pn_ceco);
                                                            if (($pn_key !== '' && isset($assigned_projects_keyset[$pn_key]))
                                                                || ($pn_ceco_key !== '' && isset($assigned_cecos_keyset[$pn_ceco_key]))) {
                                                                continue;
                                                            }
                                                        ?>
                                                        <?php $cc = isset($projects_map[$pn]) ? $projects_map[$pn] : ''; ?>
                                                        <option value="<?php echo htmlspecialchars($pn); ?>"><?php echo htmlspecialchars($pn); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php else: ?>
                                                <?php if ($is_tpl_month): ?>
                                                    <div class="assignment-month-input-shell">
                                                        <input aria-label="<?php echo htmlspecialchars($column); ?>" type="text" class="form-control" name="<?php echo $column; ?>[]" value="">
                                                        <button type="button" class="assignment-comment-trigger" data-month-column="<?php echo htmlspecialchars($column); ?>" aria-label="Agregar comentario" onclick="openAssignmentCommentModal(this); return false;">
                                                            <i class="bi bi-chat-left-text"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <input aria-label="<?php echo htmlspecialchars($column); ?>" type="text" class="form-control" name="<?php echo $column; ?>[]" value="">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <div class="alert alert-warning save-project-first-msg" role="note" style="margin:0;">Guarde primero el proyecto para registrar las horas.</div>
                            </div>
                        </template>

                        <!-- Datalist de proyectos para autocompletado: limitar a los proyectos mostrados en Resumen Ejecutivo
                             más unas entradas explícitas solicitadas. Excluir proyectos ya usados por la matrícula seleccionada. -->
                        <datalist id="proyectos-list">
                            <?php
                                foreach ($selector_projects as $pn):
                            ?>
                                <?php
                                    $pn_key = $normalize_assigned_project_key($pn);
                                    $pn_ceco = isset($projects_map[$pn]) ? trim((string)$projects_map[$pn]) : '';
                                    $pn_ceco_key = $normalize_assigned_ceco_key($pn_ceco);
                                    if (($pn_key !== '' && isset($assigned_projects_keyset[$pn_key]))
                                        || ($pn_ceco_key !== '' && isset($assigned_cecos_keyset[$pn_ceco_key]))) {
                                        continue;
                                    }
                                ?>
                                <option value="<?php echo htmlspecialchars($pn); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignment-comment-modal" tabindex="-1" aria-labelledby="assignment-comment-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignment-comment-modal-label">Comentario de Asignación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="comment-meta">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="comment-meta-label">Colaborador</div>
                                <div class="comment-meta-value" id="assignment-comment-employee">-</div>
                            </div>
                            <div class="col-md-6">
                                <div class="comment-meta-label">Proyecto</div>
                                <div class="comment-meta-value" id="assignment-comment-project">-</div>
                            </div>
                            <div class="col-md-6">
                                <div class="comment-meta-label">Centro de costo</div>
                                <div class="comment-meta-value" id="assignment-comment-affaire">-</div>
                            </div>
                            <div class="col-md-6">
                                <div class="comment-meta-label">Fecha</div>
                                <div class="comment-meta-value" id="assignment-comment-date">-</div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="assignment-comment-numero-empleado">
                    <input type="hidden" id="assignment-comment-nom">
                    <input type="hidden" id="assignment-comment-prenom">
                    <input type="hidden" id="assignment-comment-codigo-affaire">
                    <input type="hidden" id="assignment-comment-project-name-hidden">
                    <input type="hidden" id="assignment-comment-fecha-sql">

                    <div class="mb-3">
                        <label for="assignment-comment-text" class="form-label fw-semibold">Comentario</label>
                        <textarea id="assignment-comment-text" class="form-control" rows="5" placeholder="Escriba el comentario para esta asignación..."></textarea>
                    </div>
                    <div id="assignment-comment-feedback" class="small text-muted"></div>
                </div>
                <div class="modal-footer justify-content-end gap-2">
                    <button type="button" class="btn btn-danger" id="assignment-comment-delete-btn">Eliminar comentario</button>
                    <button type="button" class="btn btn-success" id="assignment-comment-save-btn" style="background-color: #17823d; border-color: #17823d;">Guardar comentario</button>
                </div>
            </div>
        </div>
    </div>

        </div>
    </div>

    <!-- SECCIÓN RESUMEN DE USO POR EMPLEADO -->
    <style>
        .usage-summary-table {
            background: #fff;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .usage-summary-table .usage-toggle-col {
            width: 50px;
            min-width: 50px;
            max-width: 50px;
            text-align: center;
        }
        .usage-summary-table .usage-name-col {
            min-width: 190px;
        }
        .usage-summary-table .usage-month-head {
            min-width: 105px;
        }
        .usage-summary-table thead th {
            background-color: #5A8CA7;
            color: #fff;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            border: none;
        }
        .usage-summary-row,
        .usage-hours-summary-row {
            cursor: pointer;
            background: #fff;
            transition: all 0.2s ease;
        }
        .usage-summary-row:hover,
        .usage-hours-summary-row:hover {
            background: #f0f7fd !important;
            box-shadow: 0 2px 8px rgba(76, 138, 163, 0.15);
        }
        .usage-summary-row.expanded,
        .usage-hours-summary-row.expanded {
            background: #e8f4f8 !important;
        }
        .usage-summary-row.no-details,
        .usage-hours-summary-row.no-details {
            cursor: default;
        }
        .usage-summary-row.no-details:hover,
        .usage-hours-summary-row.no-details:hover {
            background: #fff !important;
            box-shadow: none;
        }
        .usage-summary-table .usage-parent-toggle-cell,
        .usage-summary-table .usage-child-toggle-cell {
            text-align: center;
        }
        .usage-summary-table .usage-parent-name-cell {
            text-align: left;
            padding: 14px 15px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #2c3e50;
            border-bottom: 1px solid #e0e0e0;
            background: inherit;
        }
        .usage-summary-table .usage-parent-value-cell {
            text-align: center;
            padding: 14px 15px;
            font-weight: 600;
            font-size: 0.95rem;
            color: #2c3e50;
            border-bottom: 1px solid #e0e0e0;
            background: inherit;
        }
        .usage-toggle-icon,
        .usage-hours-toggle-icon {
            display: inline-block;
            font-size: 1.1rem;
            color: #5A8CA7;
            font-weight: 700;
            line-height: 1;
        }
        .usage-detail-row td,
        .usage-hours-detail-row td {
            background: #fafafa !important;
            font-size: 0.88rem;
            color: #666;
            border-bottom: 1px solid #f0f0f0;
        }
        .usage-detail-row:hover td,
        .usage-hours-detail-row:hover td {
            background: #f5f9fc !important;
        }
        .usage-project-cell {
            text-align: left;
            padding: 10px 15px 10px 50px !important;
            color: #555;
        }
        .usage-project-branch {
            margin-right: 8px;
            color: #999;
            font-weight: 700;
        }
        .usage-project-name {
            color: #444;
        }
        .usage-percent {
            font-weight: 700;
        }
    </style>
    <div class="card p-4 mt-4" style="background-color: #e3f0fa;">
        <div class="card-body">
            <h5 class="card-title mb-3 d-flex align-items-center" style="gap: 8px; color:#444950; font-weight:700;">
                <i class="bi bi-bar-chart-fill" style="color:#4dc18f; font-size:1.3rem;"></i>
                <span style="font-weight:700; color:#444950;">Resumen de Uso por Colaborador (%)</span>
            </h5>
            <div class="table-responsive">
                <table class="table tabla-empleados align-middle mb-0 usage-summary-table">
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th class="usage-toggle-col" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;"></th>
                            <th class="usage-name-col" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;">APELLIDO</th>
                            <th class="usage-name-col" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;">NOMBRE</th>
                            <?php foreach ($month_columns_for_summary as $month): ?>
                                <th class="usage-month-head text-center" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;">
                                    <?php echo htmlspecialchars($month); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employee_usage_matrix)): ?>
                            <tr>
                                <td colspan="<?php echo count($month_columns_for_summary) + 3; ?>" class="text-center">No hay datos para mostrar.</td>
                            </tr>
                        <?php else: ?>
                            <?php $usage_employee_index = 0; ?>
                            <?php foreach ($employee_usage_matrix as $employee_summary): ?>
                                <?php
                                    $usage_employee_index++;
                                    $has_project_details = !empty($employee_summary['projects']);
                                ?>
                                <tr class="usage-summary-row<?php echo $has_project_details ? '' : ' no-details'; ?>" data-usage-employee-id="<?php echo $usage_employee_index; ?>"<?php echo $has_project_details ? ' onclick="toggleUsageEmployeeDetail(' . $usage_employee_index . ')"' : ''; ?>>
                                    <td class="usage-parent-toggle-cell" style="padding: 14px 8px; border-bottom: 1px solid #e0e0e0;">
                                        <?php if ($has_project_details): ?>
                                            <i class="bi bi-chevron-right usage-toggle-icon" id="usage-toggle-icon-<?php echo $usage_employee_index; ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="usage-parent-name-cell"><?php echo htmlspecialchars($employee_summary['apellido']); ?></td>
                                    <td class="usage-parent-name-cell"><?php echo htmlspecialchars($employee_summary['nombre']); ?></td>
                                    <?php foreach ($month_columns_for_summary as $month): ?>
                                        <?php
                                            $total_hours = isset($employee_summary['monthly_hours'][$month]) ? (float)$employee_summary['monthly_hours'][$month] : 0.0;
                                            $total_percentage = isset($employee_summary['monthly_percentages'][$month]) ? (int)$employee_summary['monthly_percentages'][$month] : 0;
                                            $month_hours = isset($employee_usage_month_hours[$month]) ? (float)$employee_usage_month_hours[$month] : 0.0;
                                            $cell_title = 'Horas asignadas: ' . number_format($total_hours, 2, ',', '.') . ' | HH mes: ' . number_format($month_hours, 0, ',', '.');
                                        ?>
                                        <td class="usage-parent-value-cell" title="<?php echo htmlspecialchars($cell_title); ?>">
                                            <?php if ($total_percentage > 0): ?>
                                                <span class="usage-percent" style="color: <?php echo htmlspecialchars(percentToColor($total_percentage)); ?>;">
                                                    <?php echo $total_percentage; ?>%
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php foreach ($employee_summary['projects'] as $project_summary): ?>
                                    <tr class="usage-detail-row usage-detail-employee-<?php echo $usage_employee_index; ?>" style="display: none;">
                                        <td class="usage-child-toggle-cell"></td>
                                        <td colspan="2" class="usage-project-cell">
                                            <i class="bi bi-arrow-return-right usage-project-branch"></i>
                                            <span class="usage-project-name"><?php echo htmlspecialchars($project_summary['name']); ?></span>
                                        </td>
                                        <?php foreach ($month_columns_for_summary as $month): ?>
                                            <?php
                                                $project_hours = isset($project_summary['monthly_hours'][$month]) ? (float)$project_summary['monthly_hours'][$month] : 0.0;
                                                $project_percentage = isset($project_summary['monthly_percentages'][$month]) ? (int)$project_summary['monthly_percentages'][$month] : 0;
                                                $month_hours = isset($employee_usage_month_hours[$month]) ? (float)$employee_usage_month_hours[$month] : 0.0;
                                                $project_title = 'Horas proyecto: ' . number_format($project_hours, 2, ',', '.') . ' | HH mes: ' . number_format($month_hours, 0, ',', '.');
                                            ?>
                                            <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; background: #fafafa; border-bottom: 1px solid #f0f0f0;" title="<?php echo htmlspecialchars($project_title); ?>">
                                                <?php if ($project_percentage > 0): ?>
                                                    <span style="color: <?php echo htmlspecialchars(percentToColor($project_percentage)); ?>; font-weight: 600;">
                                                        <?php echo $project_percentage; ?>%
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card p-4 mt-4" style="background-color: #e3f7e8;">
        <div class="card-body">
            <h5 class="card-title mb-3 d-flex align-items-center" style="gap: 8px; color:#444950; font-weight:700;">
                <i class="bi bi-clock-history" style="color:#4dc18f; font-size:1.3rem;"></i>
                <span style="font-weight:700; color:#444950;">Resumen de Uso por Colaborador (Horas)</span>
            </h5>
            <div class="table-responsive">
                <table class="table tabla-empleados align-middle mb-0 usage-summary-table">
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th class="usage-toggle-col" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;"></th>
                            <th class="usage-name-col" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;">APELLIDO</th>
                            <th class="usage-name-col" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;">NOMBRE</th>
                            <?php foreach ($month_columns_for_summary as $month): ?>
                                <th class="usage-month-head text-center" style="background-color:#4dc18f !important; color:#fff !important; font-weight:bold;">
                                    <?php echo htmlspecialchars($month); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employee_usage_matrix)): ?>
                            <tr>
                                <td colspan="<?php echo count($month_columns_for_summary) + 3; ?>" class="text-center">No hay datos para mostrar.</td>
                            </tr>
                        <?php else: ?>
                            <?php $usage_hours_employee_index = 0; ?>
                            <?php foreach ($employee_usage_matrix as $employee_summary): ?>
                                <?php
                                    $usage_hours_employee_index++;
                                    $has_project_details = !empty($employee_summary['projects']);
                                ?>
                                <tr class="usage-hours-summary-row<?php echo $has_project_details ? '' : ' no-details'; ?>" data-usage-hours-employee-id="<?php echo $usage_hours_employee_index; ?>"<?php echo $has_project_details ? ' onclick="toggleUsageEmployeeHoursDetail(' . $usage_hours_employee_index . ')"' : ''; ?>>
                                    <td class="usage-parent-toggle-cell" style="padding: 14px 8px; border-bottom: 1px solid #e0e0e0;">
                                        <?php if ($has_project_details): ?>
                                            <i class="bi bi-chevron-right usage-hours-toggle-icon" id="usage-hours-toggle-icon-<?php echo $usage_hours_employee_index; ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="usage-parent-name-cell"><?php echo htmlspecialchars($employee_summary['apellido']); ?></td>
                                    <td class="usage-parent-name-cell"><?php echo htmlspecialchars($employee_summary['nombre']); ?></td>
                                    <?php foreach ($month_columns_for_summary as $month): ?>
                                        <?php
                                            $total_hours = isset($employee_summary['monthly_hours'][$month]) ? (float)$employee_summary['monthly_hours'][$month] : 0.0;
                                            $month_hours = isset($employee_usage_month_hours[$month]) ? (float)$employee_usage_month_hours[$month] : 0.0;
                                            $cell_title = 'Horas asignadas: ' . number_format($total_hours, 2, ',', '.') . ' | HH mes: ' . number_format($month_hours, 0, ',', '.');
                                        ?>
                                        <td class="usage-parent-value-cell" title="<?php echo htmlspecialchars($cell_title); ?>">
                                            <?php if ($total_hours > 0): ?>
                                                <span class="usage-percent" style="color: #2c3e50;">
                                                    <?php echo number_format($total_hours, 2, ',', '.'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php foreach ($employee_summary['projects'] as $project_summary): ?>
                                    <tr class="usage-hours-detail-row usage-hours-detail-employee-<?php echo $usage_hours_employee_index; ?>" style="display: none;">
                                        <td class="usage-child-toggle-cell"></td>
                                        <td colspan="2" class="usage-project-cell">
                                            <i class="bi bi-arrow-return-right usage-project-branch"></i>
                                            <span class="usage-project-name"><?php echo htmlspecialchars($project_summary['name']); ?></span>
                                        </td>
                                        <?php foreach ($month_columns_for_summary as $month): ?>
                                            <?php
                                                $project_hours = isset($project_summary['monthly_hours'][$month]) ? (float)$project_summary['monthly_hours'][$month] : 0.0;
                                                $month_hours = isset($employee_usage_month_hours[$month]) ? (float)$employee_usage_month_hours[$month] : 0.0;
                                                $project_title = 'Horas proyecto: ' . number_format($project_hours, 2, ',', '.') . ' | HH mes: ' . number_format($month_hours, 0, ',', '.');
                                            ?>
                                            <td style="text-align: center; padding: 10px 15px; font-size: 0.88rem; color: #666; background: #fafafa; border-bottom: 1px solid #f0f0f0;" title="<?php echo htmlspecialchars($project_title); ?>">
                                                <?php if ($project_hours > 0): ?>
                                                    <span style="color: #555; font-weight: 600;">
                                                        <?php echo number_format($project_hours, 2, ',', '.'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function toggleUsageEmployeeDetail(id) {
            document.querySelectorAll('.usage-detail-row').forEach(function(row) {
                if (!row.classList.contains('usage-detail-employee-' + id)) {
                    row.style.display = 'none';
                }
            });
            document.querySelectorAll('.usage-toggle-icon').forEach(function(icon) {
                if (icon.id !== 'usage-toggle-icon-' + id) {
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                }
            });
            document.querySelectorAll('.usage-summary-row').forEach(function(row) {
                if (row.getAttribute('data-usage-employee-id') !== String(id)) {
                    row.classList.remove('expanded');
                }
            });

            var rows = document.querySelectorAll('.usage-detail-employee-' + id);
            var icon = document.getElementById('usage-toggle-icon-' + id);
            var parentRow = document.querySelector('.usage-summary-row[data-usage-employee-id="' + id + '"]');
            var isOpen = rows.length > 0 && rows[0].style.display !== 'none';

            if (isOpen) {
                rows.forEach(function(row) { row.style.display = 'none'; });
                if (icon) {
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                }
                if (parentRow) parentRow.classList.remove('expanded');
            } else {
                rows.forEach(function(row) { row.style.display = 'table-row'; });
                if (icon) {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-down');
                }
                if (parentRow) parentRow.classList.add('expanded');
            }
        }

        function toggleUsageEmployeeHoursDetail(id) {
            document.querySelectorAll('.usage-hours-detail-row').forEach(function(row) {
                if (!row.classList.contains('usage-hours-detail-employee-' + id)) {
                    row.style.display = 'none';
                }
            });
            document.querySelectorAll('.usage-hours-toggle-icon').forEach(function(icon) {
                if (icon.id !== 'usage-hours-toggle-icon-' + id) {
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                }
            });
            document.querySelectorAll('.usage-hours-summary-row').forEach(function(row) {
                if (row.getAttribute('data-usage-hours-employee-id') !== String(id)) {
                    row.classList.remove('expanded');
                }
            });

            var rows = document.querySelectorAll('.usage-hours-detail-employee-' + id);
            var icon = document.getElementById('usage-hours-toggle-icon-' + id);
            var parentRow = document.querySelector('.usage-hours-summary-row[data-usage-hours-employee-id="' + id + '"]');
            var isOpen = rows.length > 0 && rows[0].style.display !== 'none';

            if (isOpen) {
                rows.forEach(function(row) { row.style.display = 'none'; });
                if (icon) {
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                }
                if (parentRow) parentRow.classList.remove('expanded');
            } else {
                rows.forEach(function(row) { row.style.display = 'table-row'; });
                if (icon) {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-down');
                }
                if (parentRow) parentRow.classList.add('expanded');
            }
        }
    </script>
    <!-- FIN SECCIÓN RESUMEN DE USO POR EMPLEADO -->

    <!-- Tabla: contenido de la vista `costo_asignado_resumen` filtrada por el área funcional del usuario -->
    <?php
    // Construir mapa desde $resumen_rows (clave normalizada por CECO y por nombre_proyecto) -> bac/ac
    $resumen_map = array();
    $normalize_key_for_map = function($s) {
        $s = trim((string)$s);
        $s = mb_strtoupper($s, 'UTF-8');
        $s = preg_replace('/[^A-Z0-9]/u', '', $s);
        return $s;
    };
    if (!empty($resumen_rows)) {
        foreach ($resumen_rows as $rr) {
            $ceco = isset($rr['PROYECTO']) ? $rr['PROYECTO'] : '';
            $proj = isset($rr['nombre_proyecto']) ? $rr['nombre_proyecto'] : '';
            $bac = isset($rr['total_costo']) ? (float)$rr['total_costo'] : 0.0;
            $ac = (isset($rr['total_valorizado_2025']) ? (float)$rr['total_valorizado_2025'] : 0.0)
                + (isset($rr['total_costo_imputado_aprobado']) ? (float)$rr['total_costo_imputado_aprobado'] : 0.0);
            $k1 = $normalize_key_for_map($ceco);
            if ($k1 === '') $k1 = trim((string)$ceco);
            if ($k1 !== '') $resumen_map[$k1] = array('bac' => $bac, 'ac' => $ac);
            $k2 = $normalize_key_for_map($proj);
            if ($k2 === '') $k2 = trim((string)$proj);
            if ($k2 !== '') $resumen_map[$k2] = array('bac' => $bac, 'ac' => $ac);
        }
    }

    $pto_validation_summary_map = array();
    $resolve_pto_validation_key = function($project_name, $project_ceco) use (&$pto_validation_summary_map, $normalize_assignment_budget_key) {
        $project_name_key = $normalize_assignment_budget_key($project_name);
        if ($project_name_key !== '' && isset($pto_validation_summary_map[$project_name_key])) {
            return $project_name_key;
        }
        $project_ceco_key = $normalize_assignment_budget_key($project_ceco);
        if ($project_ceco_key !== '' && isset($pto_validation_summary_map[$project_ceco_key])) {
            return $project_ceco_key;
        }
        return $project_name_key !== '' ? $project_name_key : $project_ceco_key;
    };
    if (!empty($resumen_rows)) {
        foreach ($resumen_rows as $rr) {
            $project_name = trim((string)($rr['nombre_proyecto'] ?? ''));
            $project_ceco = trim((string)($rr['PROYECTO'] ?? ''));
            $label = $project_name !== '' ? $project_name : $project_ceco;
            $summary_entry = array(
                'label' => $label,
                'bac' => isset($rr['total_costo']) ? (float)$rr['total_costo'] : 0.0,
                'ac' => (isset($rr['total_valorizado_2025']) ? (float)$rr['total_valorizado_2025'] : 0.0)
                    + (isset($rr['total_costo_imputado_aprobado']) ? (float)$rr['total_costo_imputado_aprobado'] : 0.0),
                'currentAssigned' => isset($rr['TOTAL_COSTO_ASIGNADO']) ? (float)$rr['TOTAL_COSTO_ASIGNADO'] : 0.0,
            );
            $summary_keys = array_values(array_unique(array_filter([
                $normalize_assignment_budget_key($project_name),
                $normalize_assignment_budget_key($project_ceco),
            ])));
            foreach ($summary_keys as $summary_key) {
                $pto_validation_summary_map[$summary_key] = $summary_entry;
            }
        }
    }

    $selected_employee_assignment_cost_map = array();
    if ($selected_employee_tarifa > 0 && !empty($asignacion_data)) {
        foreach ($asignacion_data as $assignment_row) {
            $assignment_name = $extract_assignment_project_name($assignment_row['nombre_proyecto'] ?? '');
            $assignment_ceco = trim((string)($assignment_row['centro_costos'] ?? ''));
            $assignment_key = $resolve_pto_validation_key($assignment_name, $assignment_ceco);
            if ($assignment_key === '' || !isset($pto_validation_summary_map[$assignment_key])) {
                continue;
            }
            $assignment_hours = $sum_assignment_cost_hours_from_row($assignment_row);
            if ($assignment_hours <= 0) {
                continue;
            }
            $selected_employee_assignment_cost_map[$assignment_key] = ($selected_employee_assignment_cost_map[$assignment_key] ?? 0.0) + ($assignment_hours * $selected_employee_tarifa);
        }
    }

    // Intentamos leer la vista. Probamos dos posibles nombres (con y sin 's' por errores previos en el código/BD).
    $view_candidates = array('costo_asignado_resumen', 'costo_asginado_resumen');
    $view_found = null;
    $view_rows = array();
    $assigned_view_error = '';

    try {
        $conn_view = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($conn_view->connect_error) throw new Exception('Conexión fallida: ' . $conn_view->connect_error);

        // Buscar primer candidate que exista en information_schema
        foreach ($view_candidates as $cand) {
            $cand_esc = $conn_view->real_escape_string($cand);
            $db_esc = $conn_view->real_escape_string(DB_NAME);
            $chk = "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema='" . $db_esc . "' AND table_name='" . $cand_esc . "'";
            $cres = $conn_view->query($chk);
            if ($cres) {
                $crow = $cres->fetch_assoc();
                $cres->free();
                if (!empty($crow) && isset($crow['cnt']) && intval($crow['cnt']) > 0) { $view_found = $cand; break; }
            }
        }

        if ($view_found !== null) {
            // Try to align connection collation to avoid 'Illegal mix of collations' errors
            @$conn_view->set_charset('utf8mb4');
            // Prefer consistent collation for the connection
            @$conn_view->query("SET collation_connection = 'utf8mb4_unicode_ci'");
            @$conn_view->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");

            // Attempt to read the view. If the view definition contains REPLACE/CONCAT with mixed collations,
            // this SELECT may still fail. In that case record the error and skip showing the table.
            $resv_all = $conn_view->query("SELECT * FROM `" . $conn_view->real_escape_string($view_found) . "`");
            if ($resv_all !== false) {
                // Collect raw rows first
                $raw_view_rows = array();
                while ($r = $resv_all->fetch_assoc()) $raw_view_rows[] = $r;
                $resv_all->free();

                $view_rows = $raw_view_rows; // default

                // Opción de depuración: mostrar todas las filas sin filtrar por área si se solicita
                $show_all_view = isset($_GET['view_all']) && $_GET['view_all'] === '1';
                if ($show_all_view) {
                    $assigned_view_error = 'Modo depuración: mostrando todas las filas de la vista sin filtrar por área.';
                }

                // Filtrar en PHP por columnas que representen el área funcional de forma tolerante
                if (!$show_all_view && (!empty($active_area_funcional) || $default_to_all_visible_areas) && !empty($raw_view_rows)) {
                    // Normalizador para nombres de columna: quitar acentos, espacios y caracteres no alfanuméricos, en minúscula
                    $normalize_col = function($s) {
                        $s = mb_strtolower((string)$s, 'UTF-8');
                        // quitar acentos básicos
                        $s = str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'], ['a','e','i','o','u','a','e','i','o','u','n','n'], $s);
                        $s = preg_replace('/[^a-z0-9]/', '', $s);
                        return $s;
                    };

                    // Buscar la columna que contiene el área funcional
                    $area_col_candidate = null;
                    $firstRow = $raw_view_rows[0];
                    foreach (array_keys($firstRow) as $colname) {
                        $nc = $normalize_col($colname);
                        if (in_array($nc, array('areafuncional','areafunc','area_funcional','areafuncion'), true) || strpos($nc, 'area') !== false) { $area_col_candidate = $colname; break; }
                    }

                    $filtered = array();
                    if ($area_col_candidate !== null) {
                        foreach ($raw_view_rows as $vr) {
                            $val = isset($vr[$area_col_candidate]) ? $vr[$area_col_candidate] : null;
                            if ($val === null) continue;
                            // comparar valores normalizados (sin acentos, trim, lowercase)
                            $normalize_val = function($v) {
                                $v = trim((string)$v);
                                $v = mb_strtolower($v, 'UTF-8');
                                $v = str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'], ['a','e','i','o','u','a','e','i','o','u','n','n'], $v);
                                $v = preg_replace('/\s+/', ' ', $v);
                                return $v;
                            };
                            if ((!empty($active_area_funcional) && $normalize_val($val) === $normalize_val($active_area_funcional)) || (empty($active_area_funcional) && $default_to_all_visible_areas && in_array(trim((string)$val), $visible_dropdown_areas, true))) {
                                $filtered[] = $vr;
                            }
                        }
                    } else {
                        // No se detectó columna de área por nombre: intentar buscar en cualquier columna comparando valores
                        foreach ($raw_view_rows as $vr) {
                            $matched = false;
                            foreach ($vr as $vcol) {
                                if ($vcol === null) continue;
                                $nv = mb_strtolower(trim((string)$vcol), 'UTF-8');
                                $nv = str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'], ['a','e','i','o','u','a','e','i','o','u','n','n'], $nv);
                                if ((!empty($active_area_funcional) && $nv === mb_strtolower(trim((string)$active_area_funcional), 'UTF-8')) || (empty($active_area_funcional) && $default_to_all_visible_areas && in_array(trim((string)$vcol), $visible_dropdown_areas, true))) { $matched = true; break; }
                            }
                            if ($matched) $filtered[] = $vr;
                        }
                    }

                    // If filtering reduced rows to zero, surface a diagnostic message but keep original rows empty
                    if (count($filtered) > 0) {
                        $view_rows = $filtered;
                    } else {
                        // no matches for area — surface friendly info for debugging
                        $rows_before = count($raw_view_rows);
                        $assigned_view_error = 'La vista devolvió ' . $rows_before . " fila(s), pero ninguna coincide con el filtro de áreas visible. Verifique el nombre de la columna de área y los valores en la vista.";
                        // keep $view_rows empty so table shows "No hay datos para mostrar"
                        $view_rows = array();
                    }
                }
            } else {
                $err = $conn_view->error;
                // If it's a collation error, include a friendly message explaining the cause
                if (stripos($err, 'Illegal mix of collations') !== false) {
                    $assigned_view_error = 'Error consultando costo_asignado_resumen: mezcla ilegal de collations en la definición de la vista (REPLACE/CONCAT). Se evitó ejecutar la consulta para no romper la página.';
                    error_log('Coordinador.php: Illegal mix of collations al leer ' . $view_found . ': ' . $err);
                } else {
                    $assigned_view_error = 'Error leyendo la vista ' . $view_found . ': ' . $err;
                    error_log('Coordinador.php: fallo al leer ' . $view_found . ': ' . $err);
                }
            }
        } else {
            $assigned_view_error = 'La vista costo_asignado_resumen no se encontró en la base de datos ' . DB_NAME;
        }

        $conn_view->close();
    } catch (Exception $e) {
        $assigned_view_error = 'Error consultando costo_asignado_resumen: ' . $e->getMessage();
        error_log('Coordinador.php: fallo al leer vista costo_asignado_resumen: ' . $e->getMessage());
        $view_rows = array();
    }
    ?>

    <div class="card p-4 mt-4" style="background-color: #e3f0fa;">
        <div class="card-body">
            <h5 class="card-title mb-3 d-flex align-items-center" style="gap: 8px; color:#444950; font-weight:700;">
                <i class="bi bi-cash-coin" style="color:#444950; font-size:1.3rem;"></i>
                <span style="font-weight:700; color:#444950;">Costo Asignado - Vista (costo_asignado_resumen)</span>
                <?php
                    // botón rápido para depuración: ver todas las filas de la vista sin filtrar por área
                    $qs = $_SERVER['QUERY_STRING'] ?? '';
                    parse_str($qs, $qs_arr);
                    $is_view_all = isset($qs_arr['view_all']) && $qs_arr['view_all'] === '1';
                    if ($is_view_all) {
                        $qs_arr['view_all'] = '0';
                        $off_qs = http_build_query($qs_arr);
                        $off_url = $_SERVER['PHP_SELF'] . ($off_qs ? ('?' . $off_qs) : '');
                        echo ' <a href="' . htmlspecialchars($off_url) . '" class="btn btn-sm ms-2" style="background-color:#b8d6ee; color:#23405a; font-weight:600; border:none;">Ocultar todas</a>';
                    } else {
                        $qs_arr['view_all'] = '1';
                        $on_qs = http_build_query($qs_arr);
                        $on_url = $_SERVER['PHP_SELF'] . ($on_qs ? ('?' . $on_qs) : '');
                        echo ' <a href="' . htmlspecialchars($on_url) . '" class="btn btn-sm ms-2" style="background-color:#b8d6ee; color:#23405a; font-weight:600; border:none;">Mostrar todas</a>';
                    }
                ?>
            </h5>
            <?php if (!empty($assigned_view_error)): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($assigned_view_error); ?></div>
            <?php endif; ?>
            <div class="table-responsive">
                <style>
                #view-costo-asignado-table th {
                    text-align: center !important;
                    vertical-align: middle !important;
                }
                #view-costo-asignado-table th.bac-header {
                    background: #4C8AA3 !important;
                    color: #fff !important;
                }
                #view-costo-asignado-table th.ac-header {
                    background: #7be687 !important;
                    color: #fff !important;
                }
                #view-costo-asignado-table th.total-asignado-header {
                    background: #a259d9 !important;
                    color: #fff !important;
                }
                #view-costo-asignado-table th.pendiente-header {
                    background: #6F6F6F !important;
                    color: #fff !important;
                }
                #view-costo-asignado-table th.base-header {
                    background: #ededed !important;
                    color: #222 !important;
                }
                #view-costo-asignado-table td {
                    color: #000 !important;
                }
                </style>
                <style>
                #view-costo-asignado-table {
                    font-size: 0.93rem;
                    border-radius: 10px;
                    overflow: hidden;
                    background: #fafdff;
                }
                #view-costo-asignado-table th, #view-costo-asignado-table td {
                    padding: 6px 10px !important;
                    border: 1px solid #d3e0ea !important;
                    vertical-align: middle !important;
                }
                #view-costo-asignado-table tr:nth-child(even) td {
                    background: #f2f7fa;
                }
                #view-costo-asignado-table tr:nth-child(odd) td {
                    background: #fafdff;
                }
                #view-costo-asignado-table thead th {
                    border-bottom: 2px solid #b8d6ee !important;
                }
                </style>
                <table class="table table-sm table-bordered" id="view-costo-asignado-table">
                    <thead>
                        <tr>
                            <?php if (!empty($view_rows)): ?>
                                <?php
                                    $first = $view_rows[0];
                                    $cols = array_keys($first);
                                        foreach ($cols as $col) {
                                            if (mb_strtolower($col) === 'total_suma') continue;
                                            // Insert BAC and AC columns just before Total_Costo_Asignado
                                            if (mb_strtolower($col) === 'total_costo_asignado') {
                                                echo '<th class="bac-header">PTO A TERMINACIÓN (BAC)</th>';
                                                echo '<th class="ac-header">COSTO ACTUAL (AC)</th>';
                                            }
                                            // Encabezados base personalizados
                                            $col_lc = mb_strtolower($col);
                                            if (in_array($col_lc, ['centro_costos','nombre_proyecto','area_funcional'])) {
                                                echo '<th class="base-header">' . mb_strtoupper(htmlspecialchars($col), 'UTF-8') . '</th>';
                                            } elseif ($col_lc === 'total_costo_asignado') {
                                                echo '<th class="total-asignado-header">' . mb_strtoupper(htmlspecialchars($col), 'UTF-8') . '</th>';
                                            } else {
                                                echo '<th>' . mb_strtoupper(htmlspecialchars($col), 'UTF-8') . '</th>';
                                            }
                                            // Insert PENDIENTE POR ASIGNAR just after Total_Costo_Asignado
                                            if ($col_lc === 'total_costo_asignado') {
                                                echo '<th class="pendiente-header">PENDIENTE POR ASIGNAR</th>';
                                            }
                                        }
                                        // BAC and AC columns at the end if not already inserted
                                        if (!in_array('total_costo_asignado', array_map('mb_strtolower', $cols))) {
                                            echo '<th>PTO A TERMINACIÓN (BAC)</th>';
                                            echo '<th>COSTO ACTUAL (AC)</th>';
                                        }
                                ?>
                            <?php else: ?>
                                <th>No hay datos para mostrar</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($view_rows)): ?>
                            <?php foreach ($view_rows as $vr): ?>
                                <?php
                                    // Buscar el valor de centro_costos (o CECO) en la fila
                                    $centro_val = null;
                                    foreach ($vr as $kcentro => $vcentro) {
                                        $lk = mb_strtolower($kcentro);
                                        if (strpos($lk, 'centro') !== false || strpos($lk, 'ceco') !== false || strpos($lk, 'centro_costos') !== false) {
                                            $centro_val = $vcentro;
                                            break;
                                        }
                                    }
                                    // Si centro_costos está vacío, nulo o solo espacios, saltar la fila
                                    if ($centro_val === null || trim((string)$centro_val) === '') continue;
                                ?>
                                <tr>
                                    <?php
                                        // Recorremos las columnas para imprimirlas en el mismo orden que el thead
                                        $cols = array_keys($vr);
                                        foreach ($cols as $kcol) {
                                            if (mb_strtolower($kcol) === 'total_suma') continue;
                                            // Insert BAC y AC justo antes de Total_Costo_Asignado
                                            if (mb_strtolower($kcol) === 'total_costo_asignado') {
                                                // BAC y AC
                                                $centro_val_inner = null;
                                                foreach ($vr as $kcentro2 => $vcentro2) {
                                                    $lk2 = mb_strtolower($kcentro2);
                                                    if (strpos($lk2, 'centro') !== false || strpos($lk2, 'ceco') !== false || strpos($lk2, 'centro_costos') !== false) { $centro_val_inner = $vcentro2; break; }
                                                }
                                                if ($centro_val_inner === null) {
                                                    foreach ($vr as $vcol2) {
                                                        if (is_string($vcol2) && preg_match('/\d{2,}/', $vcol2)) { $centro_val_inner = $vcol2; break; }
                                                    }
                                                }
                                                $bac_out = '-';
                                                $ac_out = '-';
                                                $pendiente_out = '-';
                                                if ($centro_val_inner !== null) {
                                                    $normc = $normalize_key_for_map($centro_val_inner);
                                                    if ($normc === '') $normc = trim((string)$centro_val_inner);
                                                    if (isset($resumen_map[$normc])) {
                                                        $bac_val = (float)$resumen_map[$normc]['bac'];
                                                        $ac_val = (float)$resumen_map[$normc]['ac'];
                                                        $bac_out = '$ ' . number_format($bac_val, 0, '', '.');
                                                        $ac_out = '$ ' . number_format($ac_val, 0, '', '.');
                                                        // Obtener Costo Asignado
                                                        $costo_asignado = 0.0;
                                                        if (isset($vr['TOTAL_COSTO_ASIGNADO'])) {
                                                            $costo_asignado = (float)str_replace(['$', '.', ',',' '], '', $vr['TOTAL_COSTO_ASIGNADO']);
                                                            if (is_string($vr['TOTAL_COSTO_ASIGNADO'])) {
                                                                $costo_asignado = floatval(str_replace(['$', '.', ',',' '], '', $vr['TOTAL_COSTO_ASIGNADO']));
                                                            }
                                                        }
                                                        $pendiente = $bac_val - ($ac_val + $costo_asignado);
                                                        $pendiente_out = '$ ' . number_format($pendiente, 0, '', '.');
                                                    } else {
                                                        $digitsA = preg_replace('/[^0-9]/', '', (string)$centro_val_inner);
                                                        if ($digitsA !== '') {
                                                            foreach ($resumen_map as $mk => $mv) {
                                                                $digitsB = preg_replace('/[^0-9]/', '', (string)$mk);
                                                                if ($digitsB !== '' && $digitsA === $digitsB) {
                                                                    $bac_out = '$ ' . number_format((float)$mv['bac'], 0, '', '.');
                                                                    $ac_out = '$ ' . number_format((float)$mv['ac'], 0, '', '.');
                                                                    $costo_asignado = 0.0;
                                                                    if (isset($vr['TOTAL_COSTO_ASIGNADO'])) {
                                                                        $costo_asignado = (float)str_replace(['$', '.', ',',' '], '', $vr['TOTAL_COSTO_ASIGNADO']);
                                                                        if (is_string($vr['TOTAL_COSTO_ASIGNADO'])) {
                                                                            $costo_asignado = floatval(str_replace(['$', '.', ',',' '], '', $vr['TOTAL_COSTO_ASIGNADO']));
                                                                        }
                                                                    }
                                                                    $pendiente = (float)$mv['bac'] - ((float)$mv['ac'] + $costo_asignado);
                                                                    $pendiente_out = '$ ' . number_format($pendiente, 0, '', '.');
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                                echo '<td class="text-success">' . $bac_out . '</td>';
                                                echo '<td class="text-info">' . $ac_out . '</td>';
                                            }
                                            // Celda normal
                                            if (mb_strtolower($kcol) === 'total_costo_asignado') {
                                                $nuevo_valor = null;
                                                if (!empty($resumen_rows)) {
                                                    foreach ($resumen_rows as $r) {
                                                        if (
                                                            (isset($r['PROYECTO']) && isset($vr['centro_costos']) && $r['PROYECTO'] == $vr['centro_costos']) &&
                                                            (isset($r['nombre_proyecto']) && isset($vr['nombre_proyecto']) && $r['nombre_proyecto'] == $vr['nombre_proyecto']) &&
                                                            (isset($r['ÁREA FUNCIONAL']) && isset($vr['area_funcional']) && $r['ÁREA FUNCIONAL'] == $vr['area_funcional'])
                                                        ) {
                                                            $nuevo_valor = $r['TOTAL_COSTO_ASIGNADO'] ?? 0;
                                                            break;
                                                        }
                                                    }
                                                }
                                                if (($nuevo_valor === null || $nuevo_valor == 0) && isset($vr['centro_costos']) && isset($vr['nombre_proyecto']) && isset($vr['area_funcional'])) {
                                                    $conn_fallback = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                                                    if (!$conn_fallback->connect_error) {
                                                        $cc = $conn_fallback->real_escape_string($vr['centro_costos']);
                                                        $np = $conn_fallback->real_escape_string($vr['nombre_proyecto']);
                                                        $af = $conn_fallback->real_escape_string($vr['area_funcional']);
                                                        $sql_fallback = "SELECT ";
                                                        $meses = [
                                                            'Mar_2026_Costo','Abr_2026_Costo','May_2026_Costo','Jun_2026_Costo','Jul_2026_Costo','Ago_2026_Costo','Sep_2026_Costo','Oct_2026_Costo','Nov_2026_Costo','Dic_2026_Costo',
                                                            'Ene_2027_Costo','Feb_2027_Costo','Mar_2027_Costo','Abr_2027_Costo','May_2027_Costo','Jun_2027_Costo','Jul_2027_Costo','Ago_2027_Costo','Sep_2027_Costo','Oct_2027_Costo','Nov_2027_Costo','Dic_2027_Costo',
                                                            'Ene_2028_Costo','Feb_2028_Costo','Mar_2028_Costo','Abr_2028_Costo','May_2028_Costo','Jun_2028_Costo','Jul_2028_Costo','Ago_2028_Costo','Sep_2028_Costo','Oct_2028_Costo','Nov_2028_Costo','Dic_2028_Costo',
                                                            'Ene_2029_Costo','Feb_2029_Costo','Mar_2029_Costo','Abr_2029_Costo','May_2029_Costo','Jun_2029_Costo','Jul_2029_Costo','Ago_2029_Costo','Sep_2029_Costo','Oct_2029_Costo','Nov_2029_Costo','Dic_2029_Costo',
                                                            'Ene_2030_Costo','Feb_2030_Costo','Mar_2030_Costo','Abr_2030_Costo','May_2030_Costo','Jun_2030_Costo','Jul_2030_Costo','Ago_2030_Costo','Sep_2030_Costo','Oct_2030_Costo','Nov_2030_Costo','Dic_2030_Costo'
                                                        ];
                                                        $sql_fallback .= 'SUM(' . implode('+', $meses) . ') as suma_meses';
                                                        $sql_fallback .= " FROM vista_costo_mensual_por_area_proyecto_cc WHERE centro_costos='$cc' AND nombre_proyecto='$np' AND area_funcional='$af'";
                                                        $res_fallback = $conn_fallback->query($sql_fallback);
                                                        if ($res_fallback && $rowfb = $res_fallback->fetch_assoc()) {
                                                            $nuevo_valor = $rowfb['suma_meses'] ?? 0;
                                                        }
                                                        if ($res_fallback) $res_fallback->free();
                                                        $conn_fallback->close();
                                                    }
                                                }
                                                echo '<td class="text-success">$ ' . number_format((float)($nuevo_valor !== null ? $nuevo_valor : $vr[$kcol]), 0, '', '.') . '</td>';
                                                // Insertar PENDIENTE POR ASIGNAR justo después de TOTAL_COSTO_ASIGNADO
                                                echo '<td class="text-warning">' . $pendiente_out . '</td>';
                                            } else {
                                                echo '<td>' . htmlspecialchars((string)$vr[$kcol]) . '</td>';
                                            }
                                        }
                                    ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td class="text-center">No hay datos para mostrar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/coordinador-assignment-comment-modal.js"></script>
    
    <script>
    function initResumenProjectSelect() {
        var $projectSelect = $('#nombre_proyecto_select');
        var $areaSelect = $('#area_funcional_select');

        if ($areaSelect.length && typeof $areaSelect.select2 === 'function') {
            if ($areaSelect.hasClass('select2-hidden-accessible')) {
                $areaSelect.select2('destroy');
            }
            $areaSelect.select2({
                placeholder: 'Buscar área funcional...',
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: 0,
                minimumInputLength: 0,
                language: {
                    noResults: function() {
                        return "No hay coincidencias";
                    },
                    searching: function() {
                        return "Buscando...";
                    }
                }
            });
        }

        if (!$projectSelect.length || typeof $projectSelect.select2 !== 'function') {
            return;
        }
        if ($projectSelect.hasClass('select2-hidden-accessible')) {
            $projectSelect.select2('destroy');
        }
        $projectSelect.select2({
            placeholder: 'Buscar nombre de proyecto...',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 0,
            minimumInputLength: 0,
            language: {
                noResults: function() {
                    return "No hay coincidencias";
                },
                searching: function() {
                    return "Buscando...";
                }
            }
        });
    }

    window.initResumenProjectSelect = initResumenProjectSelect;

    $(document).ready(function() {
        initResumenProjectSelect();
    });
    </script>
    
    <script>
    document.getElementById('add-row-btn').addEventListener('click', function() {
        const container = document.getElementById('form-rows-container');
        const template = document.getElementById('row-template');
        if (!template) return;
        const clone = template.content.cloneNode(true);

        // Prefill matricula field in the clone if present
        const selectedMatricula = (new URLSearchParams(window.location.search)).get('matricula') || document.getElementById('selected_employee_id').value || '';
        if (selectedMatricula) {
            const matriculaInputs = clone.querySelectorAll('input[name^="matricula"]');
            matriculaInputs.forEach(inp => inp.value = selectedMatricula);
        }

        // Append clone
        container.appendChild(clone);

        // Focus first proyecto-autocomplete input if present
        const latest = container.lastElementChild;
        if (latest) {
            const pa = latest.querySelector('.proyecto-autocomplete');
            if (pa) pa.focus();
        }

        // Show the Save button if it was hidden
        const saveBtn = document.querySelector('button[name="submit_asignacion"]');
        if (saveBtn && saveBtn.style.display === 'none') {
            saveBtn.style.display = '';
        }

        // Re-render and recalculate totals after adding a row (avoid overlay over the new row)
        if (typeof renderTotalsUI === 'function') {
            try { renderTotalsUI(); } catch (e) {}
        }
        if (typeof updateColumnTotals === 'function') updateColumnTotals();
        if (typeof updateAllRowTotals === 'function') updateAllRowTotals();
        if (typeof syncAssignmentCommentButtons === 'function') {
            try { syncAssignmentCommentButtons(); } catch (e) {}
        }
    });
    </script>
    <script>
    // Projects map (nombre_proyecto -> centro_costos) from PHP
    const projectsMap = <?php echo json_encode($projects_map, JSON_UNESCAPED_UNICODE); ?> || {};
    // Normalized map for case-insensitive / trimmed lookups (key -> centro_costos)
    const projectsMapNormalized = {};
    // helper: normalize string (trim, collapse spaces, lowercase, remove diacritics)
    function normalizeKey(s) {
        if (s === null || s === undefined) return '';
        try {
            let t = String(s).trim().replace(/\s+/g, ' ');
            // remove diacritics if supported
            try {
                t = t.normalize('NFD').replace(/\p{Diacritic}/gu, '');
            } catch (e) {
                // fallback: basic replacement for common accents
                t = t.replace(/[áàäâãÁÀÄÂÃ]/g,'a').replace(/[éèëêÉÈËÊ]/g,'e').replace(/[íìïîÍÌÏÎ]/g,'i').replace(/[óòöôõÓÒÖÔÕ]/g,'o').replace(/[úùüûÚÙÜÛ]/g,'u').replace(/[ñÑ]/g,'n');
            }
            return t.toLowerCase();
        } catch (e) { return String(s).toLowerCase(); }
    }
    Object.keys(projectsMap).forEach(function(k){
        if (k === null || k === undefined) return;
        try {
            const nk = normalizeKey(k);
            projectsMapNormalized[nk] = projectsMap[k];
        } catch (e) { /* ignore normalization errors */ }
    });

    // Full selector catalog used by the assignment form
    const allowedProjects = <?php echo json_encode(array_values($selector_projects), JSON_UNESCAPED_UNICODE); ?> || [];
    const assignmentCommentsState = {
        map: <?php echo json_encode($assignment_comments_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {},
        currentTrigger: null,
        modalInstance: null
    };

    const assignmentMonthNumberMap = {
        'Ene': '01',
        'Feb': '02',
        'Mar': '03',
        'Abr': '04',
        'May': '05',
        'Jun': '06',
        'Jul': '07',
        'Ago': '08',
        'Sep': '09',
        'Oct': '10',
        'Nov': '11',
        'Dic': '12'
    };

    function splitAssignmentEmployeeFullName(fullName) {
        const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) return { nom: '', prenom: '' };
        if (parts.length === 1) return { nom: parts[0], prenom: '' };
        if (parts.length === 2) return { nom: parts[0], prenom: parts[1] };
        return {
            nom: parts.slice(0, 2).join(' '),
            prenom: parts.slice(2).join(' ')
        };
    }

    function getAssignmentMonthSqlDate(monthColumn) {
        const parts = String(monthColumn || '').split('_');
        const monthName = parts[0] || '';
        const year = parts[1] || '';
        if (!monthName || !year || !assignmentMonthNumberMap[monthName]) return '';
        return year + '-' + assignmentMonthNumberMap[monthName] + '-01';
    }

    function formatAssignmentSqlDate(sqlDate) {
        const parts = String(sqlDate || '').split('-');
        if (parts.length !== 3) return sqlDate || '';
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function buildAssignmentCommentKey(numeroEmpleado, codigoAffaire, fechaSql) {
        return String(numeroEmpleado || '').trim() + '|' + String(codigoAffaire || '').trim().toUpperCase() + '|' + String(fechaSql || '').trim();
    }

    function getSelectedAssignmentEmployeeContext() {
        const selectedRadio = document.querySelector('#empleados-table input[name="selected_employee_radio"]:checked');
        const selectedRow = selectedRadio ? selectedRadio.closest('tr') : null;
        const hiddenEmployee = document.getElementById('selected_employee_id');
        const matricula = selectedRow?.dataset?.matricula || hiddenEmployee?.value || '';
        const fullName = selectedRow?.dataset?.fullName || '';
        const derivedNames = splitAssignmentEmployeeFullName(fullName);
        return {
            numeroEmpleado: matricula,
            fullName: fullName,
            nom: selectedRow?.dataset?.nom || derivedNames.nom,
            prenom: selectedRow?.dataset?.prenom || derivedNames.prenom
        };
    }

    function getAssignmentCommentContext(trigger) {
        const row = trigger ? trigger.closest('.form-row') : null;
        if (!row) return null;

        const projectSelect = row.querySelector('.proyecto-autocomplete');
        const projectName = extractProjectNameForBudget(projectSelect ? projectSelect.value : '');
        const centerInput = row.querySelector('input[name="centro_costos[]"]');
        const codigoAffaire = String(centerInput ? centerInput.value : '').trim();
        const monthColumn = trigger.dataset.monthColumn || row.querySelector('.assignment-month-cell')?.dataset?.monthColumn || '';
        const fechaSql = getAssignmentMonthSqlDate(monthColumn);
        const employee = getSelectedAssignmentEmployeeContext();

        if (!employee.numeroEmpleado || !projectName || !codigoAffaire || !fechaSql) {
            if (trigger) {
                trigger.setAttribute('data-comment-error', JSON.stringify({
                    numeroEmpleado: employee.numeroEmpleado || '',
                    projectName: projectName || '',
                    codigoAffaire: codigoAffaire || '',
                    fechaSql: fechaSql || ''
                }));
            }
            return null;
        }

        return {
            numeroEmpleado: employee.numeroEmpleado,
            nom: employee.nom,
            prenom: employee.prenom,
            fullName: employee.fullName,
            codigoAffaire: codigoAffaire,
            nombreProyecto: projectName,
            fechaSql: fechaSql,
            fechaDisplay: formatAssignmentSqlDate(fechaSql),
            monthColumn: monthColumn,
            key: buildAssignmentCommentKey(employee.numeroEmpleado, codigoAffaire, fechaSql),
            row: row,
            trigger: trigger
        };
    }

    function setAssignmentCommentTriggerState(trigger, hasComment) {
        if (!trigger) return;
        trigger.classList.toggle('has-comment', !!hasComment);
        trigger.setAttribute('title', hasComment ? 'Ver o editar comentario' : 'Agregar comentario');
    }

    function syncAssignmentCommentButtons() {
        document.querySelectorAll('.assignment-comment-trigger').forEach(function(trigger) {
            const ctx = getAssignmentCommentContext(trigger);
            const hasComment = !!(ctx && assignmentCommentsState.map[ctx.key] && String(assignmentCommentsState.map[ctx.key].comentario || '').trim() !== '');
            setAssignmentCommentTriggerState(trigger, hasComment);
        });
    }

    function openAssignmentCommentModal(trigger) {
        const ctx = getAssignmentCommentContext(trigger);
        if (!ctx) {
            let detail = '';
            try {
                detail = trigger && trigger.getAttribute('data-comment-error') ? ('\nDetalle: ' + trigger.getAttribute('data-comment-error')) : '';
            } catch (e) {}
            alert('Primero debe seleccionar un colaborador y un proyecto válido para registrar el comentario.' + detail);
            return;
        }

        const modalEl = document.getElementById('assignment-comment-modal');
        if (!modalEl) return;

        assignmentCommentsState.currentTrigger = trigger;
        if (typeof bootstrap !== 'undefined') {
            if (!assignmentCommentsState.modalInstance) {
                assignmentCommentsState.modalInstance = new bootstrap.Modal(modalEl);
            }
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
            modalEl.removeAttribute('aria-hidden');
            modalEl.setAttribute('aria-modal', 'true');
            document.body.classList.add('modal-open');
        }

        document.getElementById('assignment-comment-numero-empleado').value = ctx.numeroEmpleado;
        document.getElementById('assignment-comment-nom').value = ctx.nom;
        document.getElementById('assignment-comment-prenom').value = ctx.prenom;
        document.getElementById('assignment-comment-codigo-affaire').value = ctx.codigoAffaire;
        document.getElementById('assignment-comment-project-name-hidden').value = ctx.nombreProyecto;
        document.getElementById('assignment-comment-fecha-sql').value = ctx.fechaSql;
        document.getElementById('assignment-comment-employee').textContent = ctx.fullName || ((ctx.nom + ' ' + ctx.prenom).trim()) || '-';
        document.getElementById('assignment-comment-project').textContent = ctx.nombreProyecto || '-';
        document.getElementById('assignment-comment-affaire').textContent = ctx.codigoAffaire || '-';
        document.getElementById('assignment-comment-date').textContent = ctx.fechaDisplay || '-';
        document.getElementById('assignment-comment-text').value = assignmentCommentsState.map[ctx.key]?.comentario || '';
        document.getElementById('assignment-comment-feedback').textContent = 'Cargando comentario...';

        if (assignmentCommentsState.modalInstance) {
            assignmentCommentsState.modalInstance.show();
        }

        fetch('get_comentario_asignacion.php?' + new URLSearchParams({
            numero_de_empleado: ctx.numeroEmpleado,
            codigo_affaire: ctx.codigoAffaire,
            fecha: ctx.fechaSql
        }).toString(), { credentials: 'same-origin' })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (!data || !data.success) {
                    document.getElementById('assignment-comment-feedback').textContent = (data && data.message) ? data.message : 'No fue posible consultar el comentario.';
                    return;
                }
                const comentario = String(data.comentario || '');
                document.getElementById('assignment-comment-text').value = comentario;
                document.getElementById('assignment-comment-feedback').textContent = comentario.trim() !== '' ? 'Comentario cargado.' : 'No hay comentario registrado para esta casilla.';
                if (comentario.trim() !== '') {
                    assignmentCommentsState.map[ctx.key] = { comentario: comentario };
                }
                syncAssignmentCommentButtons();
            })
            .catch(function() {
                document.getElementById('assignment-comment-feedback').textContent = 'Error de conexión al consultar el comentario.';
            });
    }

    // No exponer aquí, se expondrá al final del body para asegurar disponibilidad global

    function saveAssignmentComment() {
        const trigger = assignmentCommentsState.currentTrigger;
        const ctx = trigger ? getAssignmentCommentContext(trigger) : null;
        if (!ctx) {
            alert('No se encontró el contexto de la asignación para guardar el comentario.');
            return;
        }

        const saveBtn = document.getElementById('assignment-comment-save-btn');
        const feedbackEl = document.getElementById('assignment-comment-feedback');
        const commentText = String(document.getElementById('assignment-comment-text').value || '');
        const requestBody = new URLSearchParams({
            numero_de_empleado: ctx.numeroEmpleado,
            nom: ctx.nom,
            prenom: ctx.prenom,
            codigo_affaire: ctx.codigoAffaire,
            nombre_proyecto: ctx.nombreProyecto,
            comentario: commentText,
            fecha: ctx.fechaSql
        });

        if (saveBtn) saveBtn.disabled = true;
        if (feedbackEl) feedbackEl.textContent = 'Guardando comentario...';

        fetch('save_comentario_asignacion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: requestBody.toString(),
            credentials: 'same-origin'
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (!data || !data.success) {
                    if (feedbackEl) feedbackEl.textContent = (data && data.message) ? data.message : 'No fue posible guardar el comentario.';
                    return;
                }
                assignmentCommentsState.map[ctx.key] = { comentario: commentText };
                setAssignmentCommentTriggerState(trigger, commentText.trim() !== '');
                if (feedbackEl) feedbackEl.textContent = 'Comentario guardado correctamente.';
            })
            .catch(function() {
                if (feedbackEl) feedbackEl.textContent = 'Error de conexión al guardar el comentario.';
            })
            .finally(function() {
                if (saveBtn) saveBtn.disabled = false;
            });
    }

    const ptoValidationContext = {
        selectedTarifaCoan: <?php echo json_encode((float)$selected_employee_tarifa); ?>,
        assignmentCostMonths: <?php echo json_encode(array_values($assignment_cost_month_columns), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [],
        summaryByKey: <?php echo json_encode($pto_validation_summary_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {},
        existingSelectedCostByKey: <?php echo json_encode($selected_employee_assignment_cost_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {}
    };

    function normalizeBudgetValidationKey(value) {
        if (value === null || value === undefined) return '';
        return normalizeKey(value).replace(/[^a-z0-9]/g, '').toUpperCase();
    }

    function extractProjectNameForBudget(value) {
        if (value === null || value === undefined) return '';
        const parts = String(value).split('||');
        return (parts[0] || '').trim();
    }

    function parseBudgetValidationNumber(value) {
        if (value === null || value === undefined) return 0;
        const normalized = String(value).trim().replace(/,/g, '');
        if (normalized === '') return 0;
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function resolveBudgetValidationKey(projectName, projectCeco) {
        const nameKey = normalizeBudgetValidationKey(projectName);
        if (nameKey && Object.prototype.hasOwnProperty.call(ptoValidationContext.summaryByKey, nameKey)) {
            return nameKey;
        }
        const cecoKey = normalizeBudgetValidationKey(projectCeco);
        if (cecoKey && Object.prototype.hasOwnProperty.call(ptoValidationContext.summaryByKey, cecoKey)) {
            return cecoKey;
        }
        return nameKey || cecoKey;
    }

    function formatBudgetValidationMoney(value) {
        return Number(value || 0).toLocaleString('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeBudgetValidationHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const commentModalEl = document.getElementById('assignment-comment-modal');
        const commentSaveBtn = document.getElementById('assignment-comment-save-btn');
        if (commentModalEl) {
            commentModalEl.addEventListener('hidden.bs.modal', function() {
                assignmentCommentsState.currentTrigger = null;
                document.getElementById('assignment-comment-feedback').textContent = '';
            });
        }
        if (commentSaveBtn) {
            commentSaveBtn.addEventListener('click', saveAssignmentComment);
        }

        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.assignment-comment-trigger');
            if (!trigger) return;
            e.preventDefault();
            e.stopPropagation();
            openAssignmentCommentModal(trigger);
        });

        syncAssignmentCommentButtons();

        const form = document.getElementById('asignacion-form');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            if (!window.Swal) return;

            const selectedTarifa = parseBudgetValidationNumber(ptoValidationContext.selectedTarifaCoan);
            if (!selectedTarifa || !Array.isArray(ptoValidationContext.assignmentCostMonths) || ptoValidationContext.assignmentCostMonths.length === 0) {
                return;
            }

            const submittedCostByKey = {};
            const rowsByKey = {};
            const formRows = Array.from(form.querySelectorAll('#form-rows-container .form-row'));

            formRows.forEach(function(row, index) {
                const projectInput = row.querySelector('.proyecto-autocomplete');
                if (!projectInput) return;

                const projectName = extractProjectNameForBudget(projectInput.value || '');
                const centroCostosInput = row.querySelector('input[name="centro_costos[]"]');
                let projectCeco = centroCostosInput ? String(centroCostosInput.value || '').trim() : '';
                if (!projectCeco && projectName) {
                    if (Object.prototype.hasOwnProperty.call(projectsMap, projectName)) {
                        projectCeco = projectsMap[projectName];
                    } else {
                        const normalizedName = normalizeKey(projectName);
                        if (normalizedName && Object.prototype.hasOwnProperty.call(projectsMapNormalized, normalizedName)) {
                            projectCeco = projectsMapNormalized[normalizedName];
                        }
                    }
                }

                const budgetKey = resolveBudgetValidationKey(projectName, projectCeco);
                if (!budgetKey || !Object.prototype.hasOwnProperty.call(ptoValidationContext.summaryByKey, budgetKey)) {
                    return;
                }

                let submittedHours = 0;
                ptoValidationContext.assignmentCostMonths.forEach(function(column) {
                    const monthInput = row.querySelector('[name="' + column + '[]"]');
                    submittedHours += parseBudgetValidationNumber(monthInput ? monthInput.value : '');
                });

                submittedCostByKey[budgetKey] = (submittedCostByKey[budgetKey] || 0) + (submittedHours * selectedTarifa);
                rowsByKey[budgetKey] = rowsByKey[budgetKey] || [];
                rowsByKey[budgetKey].push(index + 1);
            });

            const affectedKeys = Array.from(new Set(
                Object.keys(submittedCostByKey).concat(Object.keys(ptoValidationContext.existingSelectedCostByKey || {}))
            ));
            const validationErrors = [];

            affectedKeys.forEach(function(budgetKey) {
                const summaryEntry = ptoValidationContext.summaryByKey[budgetKey];
                if (!summaryEntry) return;

                const currentAssigned = parseBudgetValidationNumber(summaryEntry.currentAssigned);
                const existingSelected = parseBudgetValidationNumber(ptoValidationContext.existingSelectedCostByKey[budgetKey]);
                const submittedSelected = parseBudgetValidationNumber(submittedCostByKey[budgetKey]);
                const projectedAssigned = Math.max(0, currentAssigned - existingSelected) + submittedSelected;
                const projectedTotal = parseBudgetValidationNumber(summaryEntry.ac) + projectedAssigned;
                const bacValue = parseBudgetValidationNumber(summaryEntry.bac);
                const isIncreasingAssignment = projectedAssigned > (currentAssigned + 0.01);

                if (isIncreasingAssignment && projectedTotal > (bacValue + 0.01)) {
                    validationErrors.push({
                        label: summaryEntry.label || budgetKey,
                        rows: Array.from(new Set(rowsByKey[budgetKey] || [])),
                        total: projectedTotal,
                        bac: bacValue
                    });
                }
            });

            if (validationErrors.length === 0) {
                return;
            }

            e.preventDefault();
            const projectLabels = Array.from(new Set(validationErrors.map(function(error) {
                return (error.label || '').trim();
            }).filter(Boolean)));
            const projectSuffix = projectLabels.length
                ? ': ' + escapeBudgetValidationHtml(projectLabels.join(', ')) + '.'
                : '.';

            Swal.fire({
                icon: 'error',
                title: 'No se puede guardar',
                html: 'Se superó el PTO' + projectSuffix,
                confirmButtonText: 'Entendido',
                customClass: { popup: 'swal2-border-radius' }
            });
        });
    });

    // (no horas_cal_map exposure)

    // Helper: immediate remote lookup (no debounce) with cache
    function remoteLookupImmediate(nombre) {
        return new Promise(function(resolve){
            const key = String(nombre || '').trim().toLowerCase();
            if (!key) return resolve(null);
            if (remoteCache[key]) return resolve(remoteCache[key]);
            fetch('get_centro_costos.php?nombre_proyecto=' + encodeURIComponent(nombre), { method: 'GET', credentials: 'same-origin' })
                .then(function(resp){ return resp.json(); })
                .then(function(json){ remoteCache[key] = json; try { if ((new URLSearchParams(window.location.search).get('debug')) === '1') console.log('[DEBUG] remoteLookupImmediate fetch', { key: key, nombre: nombre, resp: json }); } catch(e){} resolve(json); })
                .catch(function(err){ try { if ((new URLSearchParams(window.location.search).get('debug')) === '1') console.log('[DEBUG] remoteLookupImmediate fetch error', { key: key, nombre: nombre, err: err }); } catch(e){} resolve(null); });
        });
    }

    // Handle project input/change for a given input element; SIEMPRE consulta AJAX para obtener centro_costos actualizado
    function handleProjectInput(input, options) {
        options = options || {};
        let val = '';
        let ccFromText = '';
        if (input.tagName === 'SELECT') {
            val = input.options[input.selectedIndex]?.value || '';
            // Extraer centro de costos del texto visible (antes del guion)
            const text = input.options[input.selectedIndex]?.textContent || '';
            const dashIdx = text.indexOf('-');
            if (dashIdx > 0) {
                ccFromText = text.substring(0, dashIdx).trim();
            }
        } else {
            val = input.value || '';
        }
        val = val.trim();
        const isDebug = (new URLSearchParams(window.location.search).get('debug') === '1');
        if (isDebug) console.log('[DEBUG] handleProjectInput start', { val: val, rawVal: input.value || '' });
        // Buscar el input de centro_costos de forma robusta
        let row = input.closest('.form-row');
        let cc = null;
        if (row) {
            cc = row.querySelector('.centro-costos');
        }
        // Si no lo encuentra, buscar en ancestros cercanos
        if (!cc) {
            let parent = input.parentElement;
            for (let i = 0; i < 4 && parent; i++) {
                cc = parent.querySelector?.('.centro-costos');
                if (cc) break;
                parent = parent.parentElement;
            }
        }
        if (!cc) return;

        // Normalize value for comparisons (trim, lowercase, remove diacritics)
        const normVal = normalizeKey(val);
        input.classList.remove('is-invalid');

        // SIEMPRE consulta AJAX para obtener centro_costos actualizado
        if (cc) {
            if (ccFromText) {
                cc.value = ccFromText;
            } else if (val !== '') {
                remoteLookupImmediate(val).then(function(res){
                    if (isDebug) console.log('[DEBUG] AJAX resultado get_centro_costos:', res);
                    if (res && res.success && res.centro_costos) {
                        cc.value = res.centro_costos;
                        // Si el nombre_proyecto exacto es diferente, actualizar el select/input
                        if (res.nombre_proyecto && input.tagName === 'SELECT') {
                            for (let i = 0; i < input.options.length; i++) {
                                if (input.options[i].value === res.nombre_proyecto) {
                                    input.selectedIndex = i;
                                    break;
                                }
                            }
                        } else if (res.nombre_proyecto && input.tagName !== 'SELECT') {
                            input.value = res.nombre_proyecto;
                        }
                        try { projectsMap[val] = res.centro_costos; projectsMapNormalized[normVal] = res.centro_costos; } catch(e){}
                        if (isDebug) console.log('[DEBUG] filled from remote', { key: val, res: res });
                    } else {
                        cc.value = '';
                        // Mostrar mensaje si no se encuentra el centro de costos
                        try { showTempMsg(input, 'No se encontró el centro de costos para este proyecto'); } catch(e){}
                        if (isDebug) console.log('[DEBUG] remote lookup no result', { key: val, res: res });
                    }
                }).catch(function(){ cc.value = ''; });
            } else {
                cc.value = '';
            }
        }

        // Duplicate detection and month locking (same logic as before)
        const allProjInputs = Array.from(document.querySelectorAll('.proyecto-autocomplete'));
        const countsMap = {};
        const firstInstance = {};
        allProjInputs.forEach(function(pi){
            const vv = (pi.value || '').trim().toLowerCase();
            if (!vv) return;
            countsMap[vv] = (countsMap[vv] || 0) + 1;
            if (!(vv in firstInstance)) firstInstance[vv] = pi;
        });

        const val_lc = val.toLowerCase();
        const isDuplicateThis = val_lc && countsMap[val_lc] > 1 && firstInstance[val_lc] !== input;
        const monthPattern = /_(2025|2026|2027|2028|2029|2030)$/;
        const monthInputs = Array.from(row.querySelectorAll('input')).filter(function(i){ return i.name && monthPattern.test(i.name); });
        if (isDuplicateThis) {
            monthInputs.forEach(function(mi){ mi.readOnly = true; mi.classList.add('months-locked'); });
        } else {
            monthInputs.forEach(function(mi){ mi.readOnly = false; mi.classList.remove('months-locked'); });
        }

        // Mark duplicates across all inputs
        const counts = {};
        const firstInst = {};
        allProjInputs.forEach(function(pi){
            const v = (pi.value || '').trim().toLowerCase();
            if (!v) return;
            counts[v] = (counts[v] || 0) + 1;
            if (!(v in firstInst)) firstInst[v] = pi;
        });
        allProjInputs.forEach(function(pi){
            const v = (pi.value || '').trim().toLowerCase();
            if (v && counts[v] > 1 && firstInst[v] !== pi) {
                pi.classList.add('is-invalid'); pi.classList.add('is-duplicate');
            } else {
                pi.classList.remove('is-duplicate');
                pi.classList.remove('is-invalid');
            }
        });

        if (val !== '' && counts[val.toLowerCase()] > 1) {
            const target = firstInst[val.toLowerCase()];
            if (target && target !== input) {
                try { target.focus(); target.classList.add('duplicate-focus'); setTimeout(function(){ target.classList.remove('duplicate-focus'); }, 1100); } catch(e){}
            }
        }

        try { refreshProyectosDatalist(); } catch (e) {}
        try { syncAssignmentCommentButtons(); } catch (e) {}
    }

    // Delegated input handler: debounce-friendly, uses debounced remote lookup
    function datalistOptionSelected(input) {
        try {
            const listId = input.getAttribute('list');
            if (!listId) return false;
            const dl = document.getElementById(listId);
            if (!dl) return false;
            const v = (input.value || '').trim();
            if (!v) return false;
            // check options (case-insensitive, normalized)
            const opts = Array.from(dl.querySelectorAll('option')).map(o => o.value).filter(Boolean);
            const nv = normalizeKey(v);
            for (let i=0;i<opts.length;i++) {
                if (normalizeKey(opts[i]) === nv) return true;
            }
            return false;
        } catch (e) { return false; }
    }

    // Vincular evento change a todos los selects de proyecto para autollenar centro_costos y actualizar el span visual
    document.addEventListener('DOMContentLoaded', function() {
        function updateCentroCostosLabel(selectEl) {
            if (!selectEl) return;
            let row = selectEl.closest('.form-row');
            if (!row) return;
            let label = row.querySelector('.centro-costos-label');
            let cc = '';
            if (selectEl.selectedIndex > 0) {
                const text = selectEl.options[selectEl.selectedIndex].textContent || '';
                const dashIdx = text.indexOf('-');
                if (dashIdx > 0) {
                    cc = text.substring(0, dashIdx).trim();
                }
            }
            if (label) {
                label.textContent = cc;
                label.setAttribute('data-cc', cc);
            }
        }

        function bindProjectSelects() {
            document.querySelectorAll('.proyecto-autocomplete').forEach(function(el) {
                if (el.tagName === 'SELECT') {
                    el.removeEventListener('change', el._projectChangeHandler || (()=>{}));
                    el._projectChangeHandler = function() {
                        handleProjectInput(el, {immediate:true});
                        updateCentroCostosLabel(el);
                    };
                    el.addEventListener('change', el._projectChangeHandler);
                    updateCentroCostosLabel(el);
                } else {
                    el.removeEventListener('input', el._projectInputHandler || (()=>{}));
                    el._projectInputHandler = function() { handleProjectInput(el, {immediate:true}); };
                    el.addEventListener('input', el._projectInputHandler);
                }
            });
        }

        bindProjectSelects();
        document.getElementById('add-row-btn')?.addEventListener('click', function() {
            setTimeout(bindProjectSelects, 100);
        });
    });

    document.addEventListener('input', function(e){
        if (e.target && e.target.classList && e.target.classList.contains('proyecto-autocomplete')) {
            // If the current value matches one of the datalist options, treat as immediate selection
            if (datalistOptionSelected(e.target)) handleProjectInput(e.target, { immediate: true });
            else handleProjectInput(e.target, { immediate: false });
        }
    });

    // Also handle change events (e.g., selection committed) — do immediate remote lookup
    document.addEventListener('change', function(e){ if (e.target && e.target.classList && e.target.classList.contains('proyecto-autocomplete')) handleProjectInput(e.target, { immediate: true }); });

    // Extra: some browsers/inputs may not fire change reliably for datalist selection; also trigger on blur (capture)
    document.addEventListener('blur', function(e){
        try {
            const t = e.target;
            if (t && t.classList && t.classList.contains('proyecto-autocomplete')) {
                // ensure centro_costos is filled (immediate)
                handleProjectInput(t, { immediate: true });
            }
        } catch (err) { /* silent */ }
    }, true);

    // Simple debounce helper and cache for remote lookups
    const remoteCache = {};
    function debounce(fn, wait) {
        let t = null;
        return function(...args) {
            if (t) clearTimeout(t);
            t = setTimeout(function(){ t = null; fn.apply(this, args); }, wait);
        };
    }

    const remoteLookupCentroCostos = (function(){
        const debounced = debounce(function(nombre, cb){
            const key = String(nombre).trim().toLowerCase();
            if (!key) return cb && cb(null);
            if (remoteCache[key]) return cb && cb(remoteCache[key]);
            // fetch endpoint
            fetch('get_centro_costos.php?nombre_proyecto=' + encodeURIComponent(nombre), { method: 'GET', credentials: 'same-origin' })
                .then(function(resp){ return resp.json(); })
                .then(function(json){ remoteCache[key] = json; try { if ((new URLSearchParams(window.location.search).get('debug')) === '1') console.log('[DEBUG] remoteLookupCentroCostos fetch', { key: key, nombre: nombre, resp: json }); } catch(e){} cb && cb(json); })
                .catch(function(err){ try { if ((new URLSearchParams(window.location.search).get('debug')) === '1') console.log('[DEBUG] remoteLookupCentroCostos fetch error', { key: key, nombre: nombre, err: err }); } catch(e){} cb && cb(null); });
        }, 220);

        return function(nombre){
            return new Promise(function(resolve){ debounced(nombre, resolve); });
        };
    })();

    // Debug panel (visible only if ?debug=1 is present) — shows server-side maps and runtime caches
    (function(){
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('debug') !== '1') return;
            // Create overlay
            const dbg = document.createElement('div');
            dbg.style.position = 'fixed';
            dbg.style.right = '12px';
            dbg.style.bottom = '12px';
            dbg.style.zIndex = 99999;
            dbg.style.maxWidth = '520px';
            dbg.style.maxHeight = '60vh';
            dbg.style.overflow = 'auto';
            dbg.style.background = 'rgba(0,0,0,0.75)';
            dbg.style.color = '#fff';
            dbg.style.padding = '10px';
            dbg.style.borderRadius = '8px';
            dbg.style.fontSize = '12px';
            dbg.id = 'debug-panel';

            const title = document.createElement('div');
            title.style.fontWeight = '700';
            title.style.marginBottom = '6px';
            title.textContent = 'DEBUG: projects_map / allowed_projects';
            dbg.appendChild(title);

            const pre = document.createElement('pre');
            pre.style.whiteSpace = 'pre-wrap';
            pre.style.color = '#dcdcdc';
            pre.textContent = 'projectsMap: ' + JSON.stringify(projectsMap, null, 2) + '\n\nprojectsMapNormalized: ' + JSON.stringify(projectsMapNormalized, null, 2) + '\n\nallowedProjects: ' + JSON.stringify(allowedProjects, null, 2);
            dbg.appendChild(pre);

            const cacheBtn = document.createElement('button');
            cacheBtn.textContent = 'Show remoteCache';
            cacheBtn.className = 'btn btn-sm btn-light';
            cacheBtn.style.marginTop = '8px';
            cacheBtn.addEventListener('click', function(){
                alert('remoteCache: ' + JSON.stringify(typeof remoteCache !== 'undefined' ? remoteCache : {}));
            });
            dbg.appendChild(cacheBtn);

            document.body.appendChild(dbg);
        } catch (e) { console && console.error && console.error(e); }
    })();

    // Also on page load, force-fill centro_costos inputs for all rows (even if already filled)
    (function(){
        document.querySelectorAll('.form-row').forEach(function(row, idx){
            const p = row.querySelector('.proyecto-autocomplete');
            const cc = row.querySelector('.centro-costos');
            if (p && cc) {
                const v = p.value || '';
                let ccVal = '';
                if (projectsMap[v] !== undefined) ccVal = projectsMap[v];
                else if (projectsMapNormalized[normalizeKey(v)] !== undefined) ccVal = projectsMapNormalized[normalizeKey(v)];
                cc.value = ccVal !== undefined ? ccVal : '';
                // If debug mode, add a small "Fill CC" button for manual trigger and inspection
                try {
                    if ((new URLSearchParams(window.location.search).get('debug')) === '1') {
                        // ensure we only add one button
                        if (!row.querySelector('.debug-fill-cc')) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-sm btn-outline-info debug-fill-cc';
                            btn.style.marginLeft = '6px';
                            btn.textContent = 'Fill CC';
                            btn.addEventListener('click', function(ev){
                                ev.preventDefault();
                                try {
                                    console.log('[DEBUG] manual Fill CC clicked for', p.value);
                                    handleProjectInput(p, { immediate: true });
                                    // briefly highlight filled centro-costos
                                    const origBg = cc.style.backgroundColor;
                                    cc.style.backgroundColor = '#ffffcc';
                                    setTimeout(function(){ cc.style.backgroundColor = origBg; }, 900);
                                } catch(err) { console && console.error && console.error(err); }
                            });
                            // append after the project input if inline, otherwise to the row
                            p.parentNode && p.parentNode.insertBefore(btn, p.nextSibling);
                        }
                    }
                } catch (e) { /* ignore debug injection errors */ }
            }
        });
        // Initial total calc for month columns and per-row totals
        if (typeof updateColumnTotals === 'function') updateColumnTotals();
        if (typeof updateAllRowTotals === 'function') updateAllRowTotals();
    })();

    // Rebuild the proyectos datalist client-side preserving the full selector catalog
    function refreshProyectosDatalist() {
        try {
            const datalist = document.getElementById('proyectos-list');
            if (!datalist) return;
            // original options were rendered server-side; gather the set from DOM default options
            const original = Array.from(datalist.querySelectorAll('option')).map(o => o.value).filter(Boolean);

            // clear datalist
            datalist.innerHTML = '';

            // add back all original options
            original.forEach(function(val){
                const opt = document.createElement('option');
                opt.value = val;
                datalist.appendChild(opt);
            });
        } catch (e) { console && console.error && console.error(e); }
    }

    // Call refresh on load and on mutations (rows added/removed)
    setTimeout(function(){ refreshProyectosDatalist(); }, 80);
    const formRowsContainer = document.getElementById('form-rows-container');
    if (formRowsContainer) {
        const moD = new MutationObserver(function(muts){
            // small debounce
            setTimeout(refreshProyectosDatalist, 40);
            setTimeout(function(){ try { syncAssignmentCommentButtons(); } catch (e) {} }, 50);
        });
        moD.observe(formRowsContainer, { childList: true, subtree: true });
    }

    // Also refresh when any proyecto-autocomplete value changes
    document.addEventListener('input', function(e){
        if (e.target && e.target.classList && e.target.classList.contains('proyecto-autocomplete')) {
            refreshProyectosDatalist();
        }
    });

    // On load, enforce month-locking for existing rows
    (function(){
        const monthPattern = /_(2025|2026|2027|2028|2029|2030)$/;
        // Precompute duplicates and first-instance map
        const allProjInputs = Array.from(document.querySelectorAll('.proyecto-autocomplete'));
        const countsMap = {};
        const firstInstance = {};
        allProjInputs.forEach(function(pi){
            const vv = (pi.value || '').trim().toLowerCase();
            if (!vv) return;
            countsMap[vv] = (countsMap[vv] || 0) + 1;
            if (!(vv in firstInstance)) firstInstance[vv] = pi;
        });

        document.querySelectorAll('.form-row').forEach(function(row){
            const p = row.querySelector('.proyecto-autocomplete');
            if (!p) return;
            const val = (p.value || '').trim();
            const val_lc = val.toLowerCase();
            const isDuplicateThis = val_lc && countsMap[val_lc] > 1 && firstInstance[val_lc] !== p;
            const monthInputs = Array.from(row.querySelectorAll('input')).filter(function(i){ return i.name && monthPattern.test(i.name); });
            if (isDuplicateThis) {
                monthInputs.forEach(function(mi){ mi.readOnly = true; mi.classList.add('months-locked'); });
            } else {
                monthInputs.forEach(function(mi){ mi.readOnly = false; mi.classList.remove('months-locked'); });
            }
        });

        // Also watch for rows added dynamically and initialize them
        const container = document.getElementById('form-rows-container');
        if (container) {
            const mo = new MutationObserver(function(muts){
                muts.forEach(function(m){
                    if (m.addedNodes && m.addedNodes.length) {
                        for (let i = 0; i < m.addedNodes.length; i++) {
                            const nd = m.addedNodes[i];
                            // ignore changes created by overlay itself
                            if (nd && nd.id === 'totals-overlay') continue;
                            // Re-render totals UI and recalc totals when real rows change
                            try { renderTotalsUI(); updateColumnTotals(); if (typeof updateAllRowTotals === 'function') updateAllRowTotals(); } catch(e) { console && console.error && console.error(e); }
                            break;
                        }
                    }
                });
            });
            mo.observe(container, { childList: true, subtree: false });
        }
    })();

    // Toggle required on nombre_proyecto when months contain values
    (function(){
        function rowNeedsProject(row) {
            if (!row) return false;
            const monthPattern = /_(2025|2026|2027|2028|2029|2030)/;
            const inputs = Array.from(row.querySelectorAll('input'));
            for (let i=0;i<inputs.length;i++){
                const inp = inputs[i];
                if (!inp.name) continue;
                if (monthPattern.test(inp.name)) {
                    const raw = (inp.value || '').toString().trim();
                    if (raw === '') continue;
                    const norm = raw.replace(/,/g, '');
                    if (!isNaN(Number(norm)) && Number(norm) !== 0) return true;
                    if (isNaN(Number(norm)) && raw !== '') return true; // non-numeric non-empty
                }
            }
            return false;
        }

        function refreshRowRequirements() {
            document.querySelectorAll('.form-row').forEach(function(row){
                const proj = row.querySelector('.proyecto-autocomplete');
                if (!proj) return;
                if (rowNeedsProject(row)) {
                    proj.setAttribute('required', 'required');
                } else {
                    proj.removeAttribute('required');
                }
            });
            // Also re-evaluate Save button enabled state
            document.dispatchEvent(new Event('input'));
        }

        // Watch for input changes on month fields and on project fields
        document.addEventListener('input', function(e){
            const t = e.target;
            if (!t || !t.name) return;
            if (/_(2025|2026|2027|2028|2029|2030)$/.test(t.name) || t.classList.contains('proyecto-autocomplete')) {
                // find row
                const row = t.closest('.form-row');
                if (row) {
                    const proj = row.querySelector('.proyecto-autocomplete');
                    if (proj) {
                        if (rowNeedsProject(row)) proj.setAttribute('required', 'required');
                        else proj.removeAttribute('required');
                    }
                }
                // Also refresh duplicates/validation by triggering the same input handler (already attached)
            }
        });

        // Refresh when rows change (added/removed)
        const container = document.getElementById('form-rows-container');
        if (container) {
            const moReq = new MutationObserver(function(){ setTimeout(refreshRowRequirements, 40); });
            moReq.observe(container, { childList: true, subtree: false });
        }

        // Run once on load
        setTimeout(refreshRowRequirements, 60);
    })();
    </script>


    <script>
    // Increase the height of the Asignación card by 40% (runtime calculation)
    (function(){
        function expandAsignacionHeight() {
            try {
                const panel = document.querySelector('.asignacion-panel .card');
                if (!panel) return;
                // compute current rendered height
                const rect = panel.getBoundingClientRect();
                const newMin = Math.round(rect.height * 1.4);
                panel.style.minHeight = newMin + 'px';
            } catch (e) { /* silent */ }
        }
        // Run after a short delay to let layout settle
        setTimeout(expandAsignacionHeight, 120);
        // Recompute on resize
        window.addEventListener('resize', function(){ setTimeout(expandAsignacionHeight, 80); });
    })();
    </script>
    <script>
    // Employee selection handling
    (function(){
        const table = document.getElementById('empleados-table');
        if (!table) return;
        const hiddenInput = document.getElementById('selected_employee_id');
        function currentPageParams() {
            const params = new URLSearchParams(window.location.search);
            const areaSelect = document.getElementById('area_funcional_select');
            const areaHidden = document.querySelector('#resumen-card input[name="area_funcional"]');
            const projectSelect = document.getElementById('nombre_proyecto_select');
            const showRetiredToggle = document.getElementById('showRetiredToggle');

            if (areaSelect && areaSelect.value) params.set('area_funcional', areaSelect.value);
            else if (areaHidden && areaHidden.value) params.set('area_funcional', areaHidden.value);
            else params.delete('area_funcional');

            if (projectSelect && projectSelect.value) params.set('nombre_proyecto', projectSelect.value);
            else params.delete('nombre_proyecto');

            if (showRetiredToggle && showRetiredToggle.checked) params.set('show_retired', '1');
            else params.delete('show_retired');

            return params;
        }

        // Delegate click on radios -> navigate to same page with matricula param
        table.addEventListener('change', function(e){
            if (e.target && e.target.name === 'selected_employee_radio') {
                const val = e.target.value;
                const params = currentPageParams();
                params.set('matricula', val);
                // Navigate to same page with params
                window.location = window.location.pathname + '?' + params.toString();
            }
        });

        // Also allow clicking the row to select (will trigger change via radio)
        table.querySelectorAll('tbody tr').forEach(function(r){
            r.addEventListener('click', function(ev){
                const radio = r.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        // If page loaded with matricula selected, set hidden input value so form can submit it
        if (hiddenInput) {
            const params = new URLSearchParams(window.location.search);
            const m = params.get('matricula');
            if (m) hiddenInput.value = m;
        }
    })();
    </script>
    <script>
    // Sync the show_retired hidden input in the form with the toggle switch
    (function(){
        const toggle = document.getElementById('showRetiredToggle');
        const hidden = document.getElementById('show_retired_hidden');
        if (!toggle || !hidden) return;
        toggle.addEventListener('change', function(){
            hidden.value = toggle.checked ? '1' : '0';
        });
    })();
    </script>
    <script>
    // Totales por columna: Nov_2025 .. Dic_2030
    (function(){
        const monthsNames = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        // form columns from PHP
        const formColumns = <?php echo json_encode(array_values($form_columns), JSON_UNESCAPED_UNICODE); ?> || [];
        const monthBaseHours = <?php echo json_encode($employee_usage_month_hours, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};

        function buildMonthColumns(startYear, startMonthIndex, endYear, endMonthIndex) {
            const cols = [];
            let y = startYear;
            let m = startMonthIndex; // 0-based index for monthsNames
            while (y < endYear || (y === endYear && m <= endMonthIndex)) {
                cols.push(monthsNames[m] + '_' + y);
                m++;
                if (m > 11) { m = 0; y++; }
            }
            return cols;
        }

        const monthCols = buildMonthColumns(2025, 10, 2030, 11); // Nov (index10) 2025 -> Dic (11) 2030

        function parseNumberSafe(v) {
            if (v === null || v === undefined) return 0;
            v = String(v).trim();
            if (v === '') return 0;
            const commaCount = (v.match(/,/g) || []).length;
            const dotCount = (v.match(/\./g) || []).length;
            if (commaCount === 1 && dotCount === 0) {
                v = v.replace(',', '.');
            } else {
                v = v.replace(/,/g, '');
            }
            const n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        }

        function formatNumberTwoDecimals(n) {
            return Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        }

        function getBaseHoursForColumn(col) {
            const configuredBase = Number(monthBaseHours[col]);
            if (Number.isFinite(configuredBase) && configuredBase > 0) {
                return configuredBase;
            }

            const baseHours2026 = {
                'Ene': 175,'Feb': 176,'Mar': 185,'Abr': 177,'May': 167,'Jun': 167,
                'Jul': 185,'Ago': 160,'Sep': 185,'Oct': 176,'Nov': 160,'Dic': 177
            };
            const parts = (col || '').split('_');
            const mon = parts[0] || '';
            const yr = parts[1] || '';
            return (yr === '2026') ? (baseHours2026[mon] || 180) : 180;
        }

        // Render totals inputs into the #totals-container
        function renderTotalsUI() {
            // Remove any existing overlay first
            const existingOverlay = document.getElementById('totals-overlay');
            if (existingOverlay) existingOverlay.remove();

            const rowsContainer = document.getElementById('form-rows-container');
            if (!rowsContainer) return;

            // ensure container is positioned so absolute children position correctly
            const containerStyle = getComputedStyle(rowsContainer);
            if (containerStyle.position === 'static') rowsContainer.style.position = 'relative';

            const overlay = document.createElement('div');
            overlay.id = 'totals-overlay';
            overlay.style.position = 'absolute';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.pointerEvents = 'none';
            rowsContainer.appendChild(overlay);

            // try to get a reference set of wrappers for names: prefer a saved row; fallback to any row; then template
            const template = document.getElementById('row-template');
            let nameRefRow = rowsContainer.querySelector('.form-row:not(.totals-row):not(.row-unsaved)');
            if (!nameRefRow) nameRefRow = rowsContainer.querySelector('.form-row:not(.totals-row)');
            let nameRefs = null;
            if (nameRefRow) nameRefs = Array.from(nameRefRow.children);
            else if (template && template.content && template.content.firstElementChild) nameRefs = Array.from(template.content.firstElementChild.children);
            if (!nameRefs || nameRefs.length === 0) return;

            // Use a saved row for geometry (width/left), but anchor totals BELOW the last row (even if it's unsaved)
            const savedRows = Array.from(rowsContainer.querySelectorAll('.form-row:not(.totals-row):not(.row-unsaved)'));
            const geomRow = savedRows.length ? savedRows[savedRows.length - 1] : nameRefRow;
            const allRowsAny = Array.from(rowsContainer.querySelectorAll('.form-row:not(.totals-row)'));
            const bottomRow = allRowsAny.length ? allRowsAny[allRowsAny.length - 1] : (geomRow || nameRefRow);
            const containerRect = rowsContainer.getBoundingClientRect();
            const bottomRect = bottomRow ? bottomRow.getBoundingClientRect() : null;
            const baseTop = bottomRect ? (bottomRect.bottom - containerRect.top + 6) : 0;

            nameRefs.forEach(function(wrapper, idx){
                // determine the column name from the nameRef wrapper
                const inp = wrapper.querySelector('input, select, textarea');
                let colName = '';
                if (inp && inp.name) colName = inp.name.replace(/\[\]$/, '').replace(/\[\d+\]$/, '');

                if (monthCols.indexOf(colName) !== -1) {
                    // get the corresponding wrapper in the geometry row for width/left
                    let geomElem = null;
                    if (geomRow && geomRow.children && geomRow.children.length > idx) {
                        geomElem = geomRow.children[idx];
                    }
                    // fallback to nameRef wrapper
                    if (!geomElem) geomElem = wrapper;

                    const rect = geomElem.getBoundingClientRect();
                    const left = rect.left - containerRect.left;
                    const width = rect.width;
                    const top = baseTop; // small gap already included

                    const totWrapper = document.createElement('div');
                    totWrapper.className = 'totals-cell-wrapper';
                    totWrapper.setAttribute('data-col', colName);
                    totWrapper.style.position = 'absolute';
                    totWrapper.style.left = left + 'px';
                    totWrapper.style.top = top + 'px';
                    totWrapper.style.width = width + 'px';
                    totWrapper.style.pointerEvents = 'none';
                    totWrapper.style.boxSizing = 'border-box';

                    const label = document.createElement('div');
                    label.className = 'form-header';
                    // Mostrar versión con espacio para meses (Ene_2026 -> Ene 2026)
                    var labelText = colName;
                    if (/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic)_[0-9]{4}$/.test(colName)) {
                        labelText = colName.replace(/_/g, ' ');
                    }
                    label.textContent = labelText;
                    if (/^(Ene|Feb|Mar|Abr|May|Jun|Jul|Ago|Sep|Oct|Nov|Dic) [0-9]{4}$/.test(labelText)) {
                        label.style.color = '#17823d';
                    }
                    label.style.fontSize = '0.75rem';
                    label.style.textAlign = 'center';

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.readOnly = true;
                    input.className = 'form-control total-input';
                    input.style.display = 'block';
                    input.style.width = '100%';
                    input.style.pointerEvents = 'auto';
                    input.style.boxSizing = 'border-box';
                    input.id = 'total_' + colName;
                    input.setAttribute('data-col', colName);
                    // start empty; we'll populate after computing totals
                    input.value = '';

                    totWrapper.appendChild(label);
                    totWrapper.appendChild(input);

                    // percentage placeholder (will be filled by updateColumnTotals)
                    const pctSpan = document.createElement('span');
                    pctSpan.className = 'total-pct';
                    pctSpan.id = 'pct_' + colName;
                    const remainingNode = document.createElement('div');
                    remainingNode.className = 'remaining-value';
                    remainingNode.textContent = '';
                    pctSpan.appendChild(remainingNode);
                    const usageRow = document.createElement('div');
                    usageRow.className = 'usage-row';
                    // create inner prefix and value nodes
                    const prefix = document.createElement('span');
                    prefix.className = 'pct-prefix';
                    prefix.textContent = 'Uso:';
                    const valueNode = document.createElement('span');
                    valueNode.className = 'pct-value';
                    valueNode.textContent = '';
                    usageRow.appendChild(prefix);
                    usageRow.appendChild(valueNode);
                    pctSpan.appendChild(usageRow);
                    // small node to show raw hours under the percentage
                    const hoursNode = document.createElement('div');
                    hoursNode.className = 'hours-value';
                    hoursNode.textContent = '';
                    pctSpan.appendChild(hoursNode);
                    totWrapper.appendChild(pctSpan);

                    overlay.appendChild(totWrapper);
                }
            });
            
        }

        // Calculate sums for each month column and update UI
        function updateColumnTotals() {
            const totals = {};
            const hasValue = {};
            monthCols.forEach(c => { totals[c] = 0; hasValue[c] = false; });

            // Find any input whose name contains one of the monthCols
            const allInputs = Array.from(document.querySelectorAll('input'));
            allInputs.forEach(function(inp){
                if (!inp.name) return;
                monthCols.forEach(function(col){
                    if (inp.name.indexOf(col) !== -1) {
                        const raw = (inp.value || '').toString().trim();
                        if (raw !== '') hasValue[col] = true;
                        totals[col] += parseNumberSafe(inp.value);
                    }
                });
            });

            // Update UI: if there are no non-empty inputs for the column, leave the total blank
            monthCols.forEach(function(col){
                const out = document.getElementById('total_' + col);
                if (!out) return;
                if (!hasValue[col] && totals[col] === 0) {
                    out.value = '';
                } else {
                    // Show integer total (no decimals) in the total input
                    const tot = totals[col] || 0;
                    const totRounded = Math.round(Number(tot));
                    const totStr = totRounded.toLocaleString(undefined, { maximumFractionDigits: 0 });
                    out.value = totStr;

                    // Compute percentage and set colored pct span
                    const base = getBaseHoursForColumn(col);
                    const pctVal = base > 0 ? Math.round((totRounded / base) * 100) : 0;
                    const remaining = Math.round(base - tot);
                    const pctEl = document.getElementById('pct_' + col);
                    if (pctEl) {
                        // set value part
                        const valNode = pctEl.querySelector('.pct-value');
                        if (valNode) valNode.textContent = pctVal + '%';
                        const hoursNode = pctEl.querySelector('.hours-value');
                        if (hoursNode) hoursNode.textContent = (base || 180) + ' h';
                        const remainingNode = pctEl.querySelector('.remaining-value');
                        if (remainingNode) {
                            remainingNode.classList.remove('remaining-pending', 'remaining-complete', 'remaining-over');
                            if (remaining > 0) {
                                remainingNode.textContent = 'Faltan: ' + remaining.toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' h';
                                remainingNode.classList.add('remaining-pending');
                            } else if (remaining < 0) {
                                remainingNode.textContent = 'Excede: ' + Math.abs(remaining).toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' h';
                                remainingNode.classList.add('remaining-over');
                            } else {
                                remainingNode.textContent = 'Completo';
                                remainingNode.classList.add('remaining-complete');
                            }
                        }
                        // remove existing classes from the wrapper
                        pctEl.classList.remove('pct-low','pct-eq','pct-high');
                        if (pctVal < 100) pctEl.classList.add('pct-low');
                        else if (pctVal === 100) pctEl.classList.add('pct-eq');
                        else pctEl.classList.add('pct-high');
                    }
                }
            });
        }

        // Initial render (deferred slightly to ensure rows/template exist)
        setTimeout(function(){
            try { renderTotalsUI(); updateColumnTotals(); if (typeof updateAllRowTotals === 'function') updateAllRowTotals(); } catch(e) { console && console.error && console.error(e); }
        }, 50);

        // When any input changes, if its name looks like one of our month columns, update totals
        document.addEventListener('input', function(e){
            const t = e.target;
            if (!t || !t.name) return;
            // quick check: does the name include an underscore and a year in our range?
            if (/_(2025|2026|2027|2028|2029|2030)/.test(t.name)) {
                updateColumnTotals(); if (typeof updateAllRowTotals === 'function') updateAllRowTotals();
            }
        });

        // Recalculate when rows are added via the Add Row handler or by DOM changes
        const container = document.getElementById('form-rows-container');
        if (container) {
            const mo = new MutationObserver(function(muts){
                muts.forEach(function(m){
                    if (m.addedNodes && m.addedNodes.length) {
                        for (let i = 0; i < m.addedNodes.length; i++) {
                            const nd = m.addedNodes[i];
                            // ignore changes created by overlay itself
                            if (nd && nd.id === 'totals-overlay') continue;
                            // Re-render totals UI and recalc totals when real rows change
                            try { renderTotalsUI(); updateColumnTotals(); if (typeof updateAllRowTotals === 'function') updateAllRowTotals(); } catch(e) { console && console.error && console.error(e); }
                            break;
                        }
                    }
                });
            });
            mo.observe(container, { childList: true, subtree: false });

            // Also re-render on scroll/resize to reposition overlay elements
            window.addEventListener('resize', function(){ try { renderTotalsUI(); updateColumnTotals(); if (typeof updateAllRowTotals === 'function') updateAllRowTotals(); } catch(e){} });
            container.addEventListener('scroll', function(){ try { renderTotalsUI(); } catch(e){} });
        }

        // Expose for other scripts
        window.updateColumnTotals = updateColumnTotals;
    })();
    
        </script>
        <!-- Per-row totals removed per user request -->
    <script>
    // Ensure only the first form-row displays the per-field headers. New rows will hide headers.
    (function(){
        function refreshRowHeaders() {
            const rows = Array.from(document.querySelectorAll('#form-rows-container .form-row'));
            rows.forEach(function(r, idx){
                const headers = r.querySelectorAll('.form-header');
                headers.forEach(function(h){
                    // keep overlay headers (totals overlay) alone because they are not inside .form-row
                    h.style.display = (idx === 0) ? 'block' : 'none';
                });
            });
        }

        // Run now and whenever rows change
        refreshRowHeaders();
        const container = document.getElementById('form-rows-container');
        if (container) {
            const mo = new MutationObserver(function(){ refreshRowHeaders(); });
            mo.observe(container, { childList: true, subtree: false });
        }

        // Also hook into Add Row button to refresh after adding
        const addBtn = document.getElementById('add-row-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function(){ setTimeout(refreshRowHeaders, 40); });
        }
    })();

    // Row remove handler: remove only if row inputs are empty
    (function(){
        function showTempMsg(elem, msg) {
            const rect = elem.getBoundingClientRect();
            const msgEl = document.createElement('div');
            msgEl.className = 'row-remove-msg';
            msgEl.textContent = msg;
            document.body.appendChild(msgEl);
            msgEl.style.left = (rect.left + window.scrollX + 24) + 'px';
            msgEl.style.top = (rect.top + window.scrollY - 8) + 'px';
            setTimeout(function(){ msgEl.remove(); }, 2200);
        }

        document.addEventListener('click', function(e){
            const rem = e.target.closest && e.target.closest('.row-remove');
            if (!rem) return;
            const row = rem.closest('.form-row');
            if (!row) return;


            // Allow removal ONLY if all month fields (Nov_2025 .. Dic_2030) are blank or equal to 0
            function parseNum(v){
                if (v === null || v === undefined) return 0;
                v = String(v).trim();
                if (v === '') return 0;
                v = v.replace(/,/g, '');
                const n = parseFloat(v);
                return isNaN(n) ? 0 : n;
            }
            // monthCols is defined globally for totals; fallback to explicit list if missing
            const monthsToCheck = (typeof monthCols !== 'undefined' && Array.isArray(monthCols) && monthCols.length) ? monthCols.slice() : ['Nov_2025','Dec_2025','Nov_2026','Dec_2026','Nov_2027','Dec_2027','Nov_2028','Dec_2028','Nov_2029','Dec_2029','Nov_2030','Dec_2030'];
            // normalize possible Spanish month names (ensure our expected Nov_2025..Dic_2030 are used by page)
            // We'll simply check any input inside the row whose name contains the month token
            let allBlankOrZero = true;
            for (let mi = 0; mi < monthsToCheck.length; mi++) {
                const m = monthsToCheck[mi];
                if (!m) continue;
                // find any matching input inside the row
                const inp = row.querySelector('[name*="' + m + '"]');
                if (!inp) continue; // treat missing input as blank
                const raw = (inp.value || '').toString().trim();
                const v = parseNum(raw);
                if (raw !== '' && Math.abs(v) > 0) { allBlankOrZero = false; break; }
            }

            if (!allBlankOrZero) {
                showTempMsg(rem, 'No se puede eliminar: algunos meses tienen valor distinto de 0');
                return;
            }

            // Safe to remove
            row.remove();
            // refresh headers/totals
            try { renderTotalsUI(); updateColumnTotals(); } catch(e){}
            try { (function(){ const rows = Array.from(document.querySelectorAll('#form-rows-container .form-row')); rows.forEach(function(r, idx){ const headers = r.querySelectorAll('.form-header'); headers.forEach(function(h){ h.style.display = (idx === 0) ? 'block' : 'none'; }); }); })(); } catch(e){}
        });
    })();
    </script>
    <script>
    // Keep the show-retired GET form's hidden matricula updated when user selects an employee
    (function(){
        const showForm = document.getElementById('show-retired-form');
        if (!showForm) return;
        // update the hidden matricula whenever selection changes
        const table = document.getElementById('empleados-table');
        if (!table) return;
        table.addEventListener('change', function(e){
            if (e.target && e.target.name === 'selected_employee_radio') {
                const val = e.target.value;
                let hiddenMat = showForm.querySelector('input[name="matricula"]');
                if (!hiddenMat) {
                    hiddenMat = document.createElement('input');
                    hiddenMat.type = 'hidden';
                    hiddenMat.name = 'matricula';
                    showForm.appendChild(hiddenMat);
                }
                hiddenMat.value = val;
            }
        });
    })();
    </script>
    
<?php ob_end_flush(); ?>
</body>
<script>
// Ajustar el contenido principal al expandir/contraer el menú
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    let mainContent = document.querySelector('.main-content');
    if (!mainContent) {
        // Si no existe, intentar crearlo dinámicamente envolviendo el contenido
        const bodyChildren = Array.from(document.body.children).filter(el => el.id !== 'sidebar' && el.tagName !== 'SCRIPT');
        if (bodyChildren.length) {
            mainContent = document.createElement('div');
            mainContent.className = 'main-content';
            bodyChildren.forEach(el => mainContent.appendChild(el));
            document.body.appendChild(mainContent);
        }
    }
    function ajustarContenido() {
        if (!mainContent) return;
        if (sidebar.classList.contains('collapsed')) {
            mainContent.style.marginLeft = '72px';
            mainContent.style.width = 'calc(100% - 72px)';
        } else {
            mainContent.style.marginLeft = '240px';
            mainContent.style.width = 'calc(100% - 240px)';
        }
        // Para móviles
        if (window.innerWidth <= 900) {
            mainContent.style.marginLeft = '0';
            mainContent.style.width = '100%';
        }
    }
    if (sidebar) {
        const observer = new MutationObserver(ajustarContenido);
        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        window.addEventListener('resize', ajustarContenido);
        ajustarContenido();
    }
});
</script>
</html>