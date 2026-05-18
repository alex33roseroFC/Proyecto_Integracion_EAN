<?php
session_start();

// Verificar si es una petición AJAX
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
$is_json_request = isset($_GET['action']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($is_ajax || $is_json_request) {
    // start buffering so that any stray warnings/notices don't corrupt the JSON output
    ob_start();

    // always return JSON for AJAX/JSON requests
    header('Content-Type: application/json');

    // turn off display errors (they'll be logged instead) and make mysqli throw exceptions
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // helper to send a response and clean buffer
    function json_out($payload) {
        $buf = ob_get_clean();
        if ($buf !== '') {
            error_log("DEBUG stray output before JSON: $buf");
        }
        echo json_encode($payload);
        exit;
    }

    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        http_response_code(401);
        json_out(['error' => 'No autorizado']);
    }
    
    include 'includes/db_connection.php';

    // shutdown handler will log and respond if a fatal error occurs
    register_shutdown_function(function(){
        $err = error_get_last();
        if ($err && ($err['type'] & (E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR))) {
            error_log("FATAL shutdown: " . print_r($err, true));
            // ensure we still return JSON
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success'=>false,'message'=>'Fatal: '.$err['message']]);
            exit;
        }
    });
    // Consulta hh_teoricas del mes seleccionado
    function get_hh_teoricas_mes($mysqli, $mes, $ano) {
        $sql = "SELECT SUM(hh_teoricas) as hh_teoricas_mes FROM horas_habiles_calendario WHERE MONTH(fecha) = ? AND YEAR(fecha) = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $mes, $ano);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $hh_teoricas = $row && isset($row['hh_teoricas_mes']) ? floatval($row['hh_teoricas_mes']) : 0;
        $stmt->close();
        return $hh_teoricas;
    }

    // small helper that prepares a statement and logs failures – used in save_hours debug
    function prepare_and_log($mysqli, $sql) {
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("DEBUG prepare failed: " . $mysqli->error . " -- SQL: " . $sql);
        } else {
            error_log("DEBUG prepare succeeded: " . $sql);
        }
        return $stmt;
    }

    function is_truthy_db_flag($value) {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'si'], true);
    }

    function is_unlimited_activity($codigo_affaire, $nombre_affaire = '') {
        $unlimited_codes = ['9300006', '9300008', '9300002', '9300066', '9300011'];
        $normalized_code = trim((string)$codigo_affaire);

        if ($normalized_code !== '' && in_array($normalized_code, $unlimited_codes, true)) {
            return true;
        }

        $exceptions_projects = [
            'COMERCIAL',
            'REINVERSIÓN',
            'VACACIONES / (CGP) CONGES PAYES',
            'TARDES EN FAMILIA / (CPA) CONGES PARENTAUX',
            'LICENCIA REMUNERADA REMUNERADA / (ABA) ABS. AUTORISEE REMUNEREE',
            'DDC - DÍA DE CUMPLEAÑOS',
            'INCAPACIDAD POR ENFERMEDAD / (MAL) MALADIE'
        ];

        $normalized_name = trim((string)$nombre_affaire);
        if ($normalized_name === '') {
            return false;
        }

        foreach ($exceptions_projects as $exception_project) {
            if (stripos($normalized_name, $exception_project) !== false) {
                return true;
            }
        }

        return false;
    }

    function resolve_activity_storage_fields($project_code, $project_name, $subcenter_code = null, $subcenter_name = null) {
        $normalized_project_code = trim((string)$project_code);
        $normalized_project_name = trim((string)$project_name);
        $normalized_subcenter_code = trim((string)$subcenter_code);
        $normalized_subcenter_name = trim((string)$subcenter_name);

        if ($normalized_subcenter_code !== '') {
            return [
                'codigo_affaire' => $normalized_subcenter_code,
                'nombre_proyect' => $normalized_subcenter_name !== '' ? $normalized_subcenter_name : $normalized_project_name,
            ];
        }

        return [
            'codigo_affaire' => $normalized_project_code,
            'nombre_proyect' => $normalized_project_name,
        ];
    }

    function get_cost_center_assignment_context($mysqli, $codigo_affaire, $nombre_affaire = '', $cod_sub_ceco = null, $nombre_sub_ceco = null) {
        $context = [
            'activity_code' => trim((string)$codigo_affaire),
            'activity_name' => trim((string)$nombre_affaire),
            'assignment_center_cost' => trim((string)$codigo_affaire),
            'assignment_project_name' => trim((string)$nombre_affaire),
            'subcenter_code' => trim((string)$cod_sub_ceco),
            'subcenter_name' => trim((string)$nombre_sub_ceco),
            'nature_imputation' => null,
            'is_subcenter' => false,
        ];

        $lookup_subcenter = $context['subcenter_code'] !== '' ? $context['subcenter_code'] : $context['activity_code'];

        if ($lookup_subcenter !== '') {
            $sql_subcenter = "SELECT CENTRO_COSTO, SUB_CENTRO, NOMBRE_SUB_CENTRO FROM sub_centros_costos WHERE TRIM(SUB_CENTRO) = TRIM(?) LIMIT 1";
            $stmt_subcenter = $mysqli->prepare($sql_subcenter);
            if ($stmt_subcenter) {
                $stmt_subcenter->bind_param("s", $lookup_subcenter);
                $stmt_subcenter->execute();
                $result_subcenter = $stmt_subcenter->get_result();
                $row_subcenter = $result_subcenter ? $result_subcenter->fetch_assoc() : null;
                $stmt_subcenter->close();

                if ($row_subcenter) {
                    $context['is_subcenter'] = true;
                    $parent_center_cost = isset($row_subcenter['CENTRO_COSTO']) ? trim((string)$row_subcenter['CENTRO_COSTO']) : '';
                    $db_subcenter_code = isset($row_subcenter['SUB_CENTRO']) ? trim((string)$row_subcenter['SUB_CENTRO']) : '';
                    $db_subcenter_name = isset($row_subcenter['NOMBRE_SUB_CENTRO']) ? trim((string)$row_subcenter['NOMBRE_SUB_CENTRO']) : '';

                    if ($parent_center_cost !== '') {
                        $context['assignment_center_cost'] = $parent_center_cost;
                    }
                    if ($db_subcenter_code !== '') {
                        $context['subcenter_code'] = $db_subcenter_code;
                    }
                    if ($context['subcenter_name'] === '' && $db_subcenter_name !== '') {
                        $context['subcenter_name'] = $db_subcenter_name;
                    }
                }
            }
        }

        if ($context['assignment_center_cost'] !== '') {
            $sql_project = "SELECT nombre_proyecto, nature_imputation FROM proyectos WHERE centro_costos = ? LIMIT 1";
            $stmt_project = $mysqli->prepare($sql_project);
            if ($stmt_project) {
                $stmt_project->bind_param("s", $context['assignment_center_cost']);
                $stmt_project->execute();
                $result_project = $stmt_project->get_result();
                $row_project = $result_project ? $result_project->fetch_assoc() : null;
                $stmt_project->close();

                if ($row_project) {
                    $project_name = isset($row_project['nombre_proyecto']) ? trim((string)$row_project['nombre_proyecto']) : '';
                    if ($project_name !== '') {
                        $context['assignment_project_name'] = $project_name;
                    }
                    if (isset($row_project['nature_imputation'])) {
                        $context['nature_imputation'] = $row_project['nature_imputation'];
                    }
                }
            }
        }

        if ($context['is_subcenter']) {
            if ($context['activity_name'] === '') {
                $context['activity_name'] = $context['subcenter_name'] !== ''
                    ? $context['subcenter_name']
                    : $context['assignment_project_name'];
            }
        } elseif ($context['activity_name'] === '') {
            $context['activity_name'] = $context['assignment_project_name'];
        }

        return $context;
    }

    function get_assignment_hours_usage($mysqli, $numero_empleado, $assignment_center_cost, $fecha_inicio, $fecha_fin) {
        $sql_usage = "SELECT SUM(hd.tiempo_imputado_horas) as horas_registradas
                      FROM horas_dia hd
                      LEFT JOIN sub_centros_costos sc ON TRIM(sc.SUB_CENTRO) = TRIM(hd.codigo_affaire)
                      WHERE hd.numero_empleado = ?
                        AND COALESCE(NULLIF(TRIM(sc.CENTRO_COSTO), ''), TRIM(hd.codigo_affaire)) = TRIM(?)
                        AND hd.fecha >= ?
                        AND hd.fecha <= ?";
        $stmt_usage = $mysqli->prepare($sql_usage);
        $stmt_usage->bind_param("ssss", $numero_empleado, $assignment_center_cost, $fecha_inicio, $fecha_fin);
        $stmt_usage->execute();
        $result_usage = $stmt_usage->get_result();
        $row_usage = $result_usage ? $result_usage->fetch_assoc() : null;
        $stmt_usage->close();

        return $row_usage && isset($row_usage['horas_registradas']) ? floatval($row_usage['horas_registradas']) : 0;
    }

    function build_activity_identity($mysqli, $codigo_affaire, $nombre_proyect = '', $id = 0, $cod_sub_ceco = '', $nombre_sub_ceco = '') {
        $context = get_cost_center_assignment_context($mysqli, $codigo_affaire, $nombre_proyect, $cod_sub_ceco, $nombre_sub_ceco);
        $parent_code = trim((string)$context['assignment_center_cost']);
        $parent_name = trim((string)$context['assignment_project_name']);
        $subcenter_code = trim((string)$context['subcenter_code']);
        $subcenter_name = trim((string)$context['subcenter_name']);

        if ($parent_name === '') {
            $parent_name = trim((string)$nombre_proyect);
        }

        return [
            'activity_key' => $parent_code . '||' . $subcenter_code,
            'codigo_affaire' => $parent_code,
            'nombre_proyect' => $parent_name,
            'cod_sub_ceco' => $subcenter_code,
            'nombre_sub_ceco' => $subcenter_name,
            'id' => intval($id),
        ];
    }

    function ensure_activity_bucket(&$actividades_agrupadas, $identity) {
        $activity_key = $identity['activity_key'];
        if (!isset($actividades_agrupadas[$activity_key])) {
            $actividades_agrupadas[$activity_key] = [
                'activity_key' => $activity_key,
                'codigo_affaire' => $identity['codigo_affaire'],
                'nombre_proyect' => $identity['nombre_proyect'],
                'horas' => array_fill(0, 7, 0),
                'tiene_registro' => array_fill(0, 7, false),
                'aprobado' => array_fill(0, 7, false),
                'rechazado' => array_fill(0, 7, false),
                'comentado' => array_fill(0, 7, false),
                'cod_sub_ceco' => array_fill(0, 7, $identity['cod_sub_ceco']),
                'nombre_sub_ceco' => array_fill(0, 7, $identity['nombre_sub_ceco']),
                'id' => $identity['id']
            ];
        } elseif (intval($actividades_agrupadas[$activity_key]['id']) <= 0 && intval($identity['id']) > 0) {
            $actividades_agrupadas[$activity_key]['id'] = intval($identity['id']);
        }

        return $activity_key;
    }

    function is_hour_record_locked($row) {
        $estado_aprobacion = isset($row['Estado_Aprobacion']) ? strtolower(trim((string)$row['Estado_Aprobacion'])) : '';

        return is_truthy_db_flag(isset($row['aprobado_coordinador']) ? $row['aprobado_coordinador'] : null)
            || is_truthy_db_flag(isset($row['aprobado_director']) ? $row['aprobado_director'] : null)
            || $estado_aprobacion === 'aprobado';
    }

    function normalize_role_name($role) {
        return strtoupper(trim((string)$role));
    }

    function can_filter_employee_dropdown_for_role($role) {
        return in_array(normalize_role_name($role), ['SUPER', 'COORD', 'MIX', 'MIX2'], true);
    }

    function uses_area_employee_filter_for_role($role) {
        return in_array(normalize_role_name($role), ['COORD', 'MIX', 'MIX2'], true);
    }

    function build_empleado_nombre($row, $fallback = '') {
        $nom = isset($row['nom']) ? trim((string)$row['nom']) : '';
        $prenom = isset($row['prenom']) ? trim((string)$row['prenom']) : '';
        $nombre = trim($nom . ' ' . $prenom);

        if ($nombre !== '') {
            return $nombre;
        }

        if (isset($row['Nombre_Usuario']) && trim((string)$row['Nombre_Usuario']) !== '') {
            return trim((string)$row['Nombre_Usuario']);
        }

        return trim((string)$fallback);
    }

    function get_logged_user_employee_context($mysqli, $usuario, $matricula) {
        $context = [
            'matricula' => trim((string)$matricula),
            'nombre' => trim((string)$usuario),
            'area_funcional' => ''
        ];

        if ($usuario !== '') {
            $sql_login = "SELECT Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional FROM login_usuarios WHERE Usuario = ? LIMIT 1";
            $stmt_login = $mysqli->prepare($sql_login);
            $stmt_login->bind_param("s", $usuario);
            $stmt_login->execute();
            $result_login = $stmt_login->get_result();
            $row_login = $result_login ? $result_login->fetch_assoc() : null;
            $stmt_login->close();

            if ($row_login) {
                if ($context['matricula'] === '' && isset($row_login['Matricula']) && trim((string)$row_login['Matricula']) !== '') {
                    $context['matricula'] = trim((string)$row_login['Matricula']);
                }
                if (isset($row_login['Nombre_Usuario']) && trim((string)$row_login['Nombre_Usuario']) !== '') {
                    $context['nombre'] = trim((string)$row_login['Nombre_Usuario']);
                }
                if (isset($row_login['area_funcional']) && trim((string)$row_login['area_funcional']) !== '') {
                    $context['area_funcional'] = trim((string)$row_login['area_funcional']);
                }
            }
        }

        if ($context['matricula'] === '') {
            return $context;
        }

        $sql_empleado = "SELECT matricula, nom, prenom, area_funcional FROM empleados WHERE TRIM(matricula) = TRIM(?) LIMIT 1";
        $stmt_empleado = $mysqli->prepare($sql_empleado);
        $stmt_empleado->bind_param("s", $context['matricula']);
        $stmt_empleado->execute();
        $result_empleado = $stmt_empleado->get_result();
        $row_empleado = $result_empleado ? $result_empleado->fetch_assoc() : null;
        $stmt_empleado->close();

        if ($row_empleado) {
            if (isset($row_empleado['matricula']) && trim((string)$row_empleado['matricula']) !== '') {
                $context['matricula'] = trim((string)$row_empleado['matricula']);
            }

            $nombre_empleado = build_empleado_nombre($row_empleado, $context['nombre']);
            if ($nombre_empleado !== '') {
                $context['nombre'] = $nombre_empleado;
            }
        }

        return $context;
    }

    function get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario, $fecha_retiro_corte) {
        $role = normalize_role_name($rol_usuario);
        $logged_user_context = get_logged_user_employee_context($mysqli, $usuario, $matricula);
        $current_matricula = isset($logged_user_context['matricula']) ? trim((string)$logged_user_context['matricula']) : trim((string)$matricula);
        $requested_matricula = isset($_GET['matricula']) ? trim((string)$_GET['matricula']) : $current_matricula;

        // Si el usuario selecciona explícitamente una matrícula distinta, permitimos ver sus registros
        // (excepto si la matrícula está vacía)
        if ($requested_matricula !== '' && $requested_matricula !== $current_matricula) {
            return $requested_matricula;
        }

        // Lógica original para casos normales
        return $current_matricula !== '' ? $current_matricula : $requested_matricula;
    }

    $usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
    $matricula = isset($_SESSION['matricula']) ? trim((string)$_SESSION['matricula']) : '';
    $rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
    $rol_usuario_normalizado = normalize_role_name($rol_usuario);
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $fecha_retiro_corte = '2026-01-31';
    
    try {
        // Nuevo endpoint: obtener estado del mes desde horas_habiles_calendario
        if ($action === 'get_estado_mes') {
            $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
            $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
            include 'includes/db_connection.php';
            $sql = "SELECT estado FROM horas_habiles_calendario WHERE MONTH(fecha) = ? AND YEAR(fecha) = ? LIMIT 1";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("ii", $month, $year);
            $stmt->execute();
            $result = $stmt->get_result();
            $estado = '';
            if ($row = $result->fetch_assoc()) {
                $estado = $row['estado'];
            }
            $stmt->close();
            json_out(['estado' => $estado]);
        }
        
        // ===== GET EMPLEADOS (para SUPER) =====
    if ($action === 'get_empleados') {
        if ($rol_usuario_normalizado !== 'SUPER') {
            json_out(['error' => 'No autorizado']);
        }
        
        try {
            $sql = "SELECT lu.Matricula, lu.Nombre_Usuario
                    FROM login_usuarios lu
                    INNER JOIN empleados e ON e.matricula = lu.Matricula
                    WHERE e.fechas_retiro > ?
                    ORDER BY lu.Nombre_Usuario ASC";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("s", $fecha_retiro_corte);
            $stmt->execute();
            $result = $stmt->get_result();
            $empleados = [];
            
            while ($row = $result->fetch_assoc()) {
                $empleados[] = $row;
            }
            $stmt->close();
            
            json_out($empleados);
        } catch (Exception $e) {
            json_out(['error' => $e->getMessage()]);
        }
    }

    // ===== GET MES ACTIVO =====
    if ($action === 'get_active_month') {
        try {
            $sql = "SELECT YEAR(fecha) AS year, MONTH(fecha) AS month
                    FROM horas_habiles_calendario
                    WHERE LOWER(TRIM(estado)) = 'activo'
                    ORDER BY fecha DESC
                    LIMIT 1";
            $result = $mysqli->query($sql);

            if ($result && $row = $result->fetch_assoc()) {
                json_out([
                    'success' => true,
                    'year' => intval($row['year']),
                    'month' => intval($row['month'])
                ]);
            }

            json_out([
                'success' => true,
                'year' => intval(date('Y')),
                'month' => intval(date('m'))
            ]);
        } catch (Exception $e) {
            json_out([
                'success' => false,
                'message' => $e->getMessage(),
                'year' => intval(date('Y')),
                'month' => intval(date('m'))
            ]);
        }
    }

    // ===== GET EMPLEADOS POR ÁREA (para COORD/MIX/MIX2) =====
    if ($action === 'get_empleados_area') {
        if (!uses_area_employee_filter_for_role($rol_usuario_normalizado)) {
            json_out(['error' => 'No autorizado']);
        }

        // Áreas permitidas para usuarios especiales
        // =============================
        // Áreas permitidas para usuarios especiales
        //
        // Para agregar un nuevo usuario con áreas personalizadas:
        // 1. Agregue una nueva entrada al array $usuarios_areas_especiales.
        //    - La clave debe ser el nombre de usuario EXACTO (en mayúsculas).
        //    - El valor debe ser un array con los nombres de las áreas permitidas (deben coincidir con los valores en la base de datos).
        // 2. Ejemplo para agregar el usuario 'JPEREZ' con áreas 'Finanzas' y 'Comercial':
        //      'JPEREZ' => ['Finanzas', 'Comercial'],
        // 3. No olvide separar cada entrada con una coma.
        // 4. Los usuarios aquí definidos podrán ver y filtrar empleados de todas las áreas listadas, y tendrán la opción "-- Todas --" en el filtro.
        // 5. Si desea que un usuario tenga acceso a todas las áreas, simplemente incluya todas las áreas relevantes en el array.
        //
        // Ejemplo de estructura:
        // $usuarios_areas_especiales = [
        //     'JGELVEZ' => ['Arquitectura y Urbanismo', 'Estructuras'],
        //     'JPEREZ'  => ['Finanzas', 'Comercial'],
        // ];
        // =============================
        $usuarios_areas_especiales = [
            // IMPORTANTE: Asegúrate de que los nombres de área NO tengan apóstrofes ni espacios extra.
            // Por ejemplo, debe ser 'Arquitectura y Urbanismo' (sin apóstrofe al final).
            'JGELVEZ' => ['Arquitectura y Urbanismo', 'Estructuras'],
            // Ejemplo: 'OTROUSER' => ['Área X', 'Área Y'],
        ];

        try {
            $logged_user_context = get_logged_user_employee_context($mysqli, $usuario, $matricula);
            $mi_matricula = isset($logged_user_context['matricula']) ? trim((string)$logged_user_context['matricula']) : $matricula;
            $mi_nombre = isset($logged_user_context['nombre']) ? trim((string)$logged_user_context['nombre']) : $usuario;
            $area_funcional = isset($logged_user_context['area_funcional']) ? trim((string)$logged_user_context['area_funcional']) : '';

            $identificador_usuario = strtoupper(trim($usuario));
            $areas_especiales = array_key_exists($identificador_usuario, $usuarios_areas_especiales) ? $usuarios_areas_especiales[$identificador_usuario] : null;

            if ($areas_especiales) {
                // Traer empleados de todas las áreas permitidas
                $placeholders = implode(',', array_fill(0, count($areas_especiales), '?'));
                $sql = "SELECT DISTINCT
                            TRIM(e.matricula) AS Matricula,
                            COALESCE(
                                NULLIF(TRIM(CONCAT(COALESCE(e.nom, ''), ' ', COALESCE(e.prenom, ''))), ''),
                                TRIM(e.matricula)
                            ) AS Nombre_Usuario
                        FROM empleados e
                        WHERE TRIM(COALESCE(e.area_funcional, '')) IN ($placeholders)
                        AND e.fechas_retiro > ?
                        ORDER BY Nombre_Usuario ASC";
                $params = array_merge($areas_especiales, [$fecha_retiro_corte]);
                $types = str_repeat('s', count($areas_especiales)) . 's';
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $empleados = [];
                while ($row = $result->fetch_assoc()) {
                    $empleados[] = $row;
                }
                $stmt->close();
            } else {
                // Lógica normal: solo su área
                if ($area_funcional === '') {
                    json_out([
                        [
                            'Matricula' => $mi_matricula,
                            'Nombre_Usuario' => $mi_nombre
                        ]
                    ]);
                }
                $sql = "SELECT DISTINCT
                            TRIM(e.matricula) AS Matricula,
                            COALESCE(
                                NULLIF(TRIM(CONCAT(COALESCE(e.nom, ''), ' ', COALESCE(e.prenom, ''))), ''),
                                TRIM(e.matricula)
                            ) AS Nombre_Usuario
                        FROM empleados e
                        WHERE TRIM(COALESCE(e.area_funcional, '')) = TRIM(?)
                        AND e.fechas_retiro > ?
                        ORDER BY Nombre_Usuario ASC";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("ss", $area_funcional, $fecha_retiro_corte);
                $stmt->execute();
                $result = $stmt->get_result();
                $empleados = [];
                while ($row = $result->fetch_assoc()) {
                    $empleados[] = $row;
                }
                $stmt->close();
            }

            $ya_existe_logueado = false;
            foreach ($empleados as $emp) {
                if (isset($emp['Matricula']) && trim($emp['Matricula']) === $mi_matricula) {
                    $ya_existe_logueado = true;
                    break;
                }
            }

            if (!$ya_existe_logueado && $mi_matricula !== '') {
                $empleados[] = [
                    'Matricula' => $mi_matricula,
                    'Nombre_Usuario' => $mi_nombre
                ];
            }

            json_out($empleados);
        } catch (Exception $e) {
            json_out(['error' => $e->getMessage()]);
        }
    }
    
    // ===== GET PROYECTOS =====
    if ($action === 'get_proyectos') {
        header('Content-Type: application/json');
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $target_matricula = get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario_normalizado, $fecha_retiro_corte);
        $selected_week_start = isset($_GET['week_start']) ? trim((string)$_GET['week_start']) : '';
        
        try {
            // Traer proyectos asignados al empleado y marcar qué opciones ya existen en la semana actual.
            $reference_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_week_start)
                ? $selected_week_start
                : date('Y-m-d');
            $week_timestamp = strtotime($reference_date);
            if ($week_timestamp === false) {
                $week_timestamp = time();
            }
            $fecha_lunes = date('Y-m-d', strtotime('monday this week', $week_timestamp));
            $fecha_domingo = date('Y-m-d', strtotime('sunday this week', $week_timestamp));

            $existing_week_codes = [];
            $sql_existing = "SELECT codigo_affaire FROM cargue_horas
                             WHERE numero_de_empleado = ?
                               AND fecha >= ?
                               AND fecha <= ?
                               AND (estado = 'activo' OR estado IS NULL OR estado = '')";
            $stmt_existing = $mysqli->prepare($sql_existing);
            $stmt_existing->bind_param("sss", $target_matricula, $fecha_lunes, $fecha_domingo);
            $stmt_existing->execute();
            $result_existing = $stmt_existing->get_result();
            while ($row_existing = $result_existing->fetch_assoc()) {
                $existing_code = isset($row_existing['codigo_affaire']) ? trim((string)$row_existing['codigo_affaire']) : '';
                if ($existing_code !== '') {
                    $existing_week_codes[$existing_code] = true;
                }
            }
            $stmt_existing->close();

            $sql = "SELECT DISTINCT p.id, p.centro_costos, p.nombre_proyecto 
                    FROM proyectos p
                    WHERE EXISTS (
                        SELECT 1 FROM `asignación` a 
                        WHERE a.centro_costos = p.centro_costos 
                        AND a.matricula = ?
                    )";

            if (!empty($search)) {
                $search = '%' . $search . '%';
                $sql .= " AND (p.centro_costos LIKE ? OR p.nombre_proyecto LIKE ?)";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("sss", $target_matricula, $search, $search);
            } else {
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("s", $target_matricula);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $proyectos = [];

            while ($row = $result->fetch_assoc()) {
                $row['principal_added'] = isset($existing_week_codes[trim((string)$row['centro_costos'])]);
                // Traer subcentros si existen para este centro de costos
                $row['subcentros'] = [];
                $sql_sc = "SELECT SUB_CENTRO, NOMBRE_SUB_CENTRO FROM sub_centros_costos WHERE CENTRO_COSTO = ? AND COALESCE(TRIM(ESTADO),'') = 'ACTIVO'";
                $stmt_sc = $mysqli->prepare($sql_sc);
                if ($stmt_sc) {
                    $stmt_sc->bind_param("s", $row['centro_costos']);
                    $stmt_sc->execute();
                    $res_sc = $stmt_sc->get_result();
                    while ($rsc = $res_sc->fetch_assoc()) {
                        $sub_code = isset($rsc['SUB_CENTRO']) ? trim((string)$rsc['SUB_CENTRO']) : '';
                        $rsc['already_added'] = $sub_code !== '' && isset($existing_week_codes[$sub_code]);
                        $row['subcentros'][] = $rsc;
                    }
                    $stmt_sc->close();
                }

                $proyectos[] = $row;
            }

            $stmt->close();
            json_out($proyectos);
        } catch (Exception $e) {
            json_out(['error' => $e->getMessage()]);
        }
    }
    
    // ===== GET ACTIVIDADES =====
    if ($action === 'get_actividades') {
        $fecha_actual = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
        $target_matricula = get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario_normalizado, $fecha_retiro_corte);
        
        $timestamp = strtotime($fecha_actual);
        $lunes = strtotime('monday this week', $timestamp);
        $domingo = strtotime('sunday this week', $timestamp);
        
        $fecha_lunes = date('Y-m-d', $lunes);
        $fecha_domingo = date('Y-m-d', $domingo);

        $actividades_agrupadas = [];

        $sql_actividades = "SELECT codigo_affaire, nombre_proyect, id
                            FROM cargue_horas
                            WHERE numero_de_empleado = ?
                              AND fecha >= ?
                              AND fecha <= ?
                              AND (estado = 'activo' OR estado IS NULL OR estado = '')";
        $stmt_actividades = $mysqli->prepare($sql_actividades);
        $stmt_actividades->bind_param("sss", $target_matricula, $fecha_lunes, $fecha_domingo);
        $stmt_actividades->execute();
        $result_actividades = $stmt_actividades->get_result();

        while ($row = $result_actividades->fetch_assoc()) {
            $identity = build_activity_identity(
                $mysqli,
                isset($row['codigo_affaire']) ? $row['codigo_affaire'] : '',
                isset($row['nombre_proyect']) ? $row['nombre_proyect'] : '',
                isset($row['id']) ? $row['id'] : 0
            );
            ensure_activity_bucket($actividades_agrupadas, $identity);
        }

        $stmt_actividades->close();
        
        // Ahora traer las horas de esta semana desde horas_dia
        $sql_horas = "SELECT codigo_affaire, nombre_affaire, fecha, tiempo_imputado_horas, aprobado_coordinador, aprobado_director, rechazado_coordinador, comentario, Estado_Aprobacion, cod_sub_ceco, nombre_sub_ceco 
                      FROM horas_dia 
                      WHERE numero_empleado = ? 
                      AND fecha >= ? 
                      AND fecha <= ?";
        
        $stmt_horas = $mysqli->prepare($sql_horas);
        $stmt_horas->bind_param("sss", $target_matricula, $fecha_lunes, $fecha_domingo);
        $stmt_horas->execute();
        $result_horas = $stmt_horas->get_result();
        
        while ($row_horas = $result_horas->fetch_assoc()) {
            $identity = build_activity_identity(
                $mysqli,
                isset($row_horas['codigo_affaire']) ? $row_horas['codigo_affaire'] : '',
                isset($row_horas['nombre_affaire']) ? $row_horas['nombre_affaire'] : '',
                0,
                isset($row_horas['cod_sub_ceco']) ? $row_horas['cod_sub_ceco'] : '',
                isset($row_horas['nombre_sub_ceco']) ? $row_horas['nombre_sub_ceco'] : ''
            );
            $key = ensure_activity_bucket($actividades_agrupadas, $identity);

            $fecha_ts = strtotime($row_horas['fecha']);
            $dia_semana = (int)date('w', $fecha_ts) - 1;
            if ($dia_semana < 0) $dia_semana = 6;
            
            $actividades_agrupadas[$key]['horas'][$dia_semana] = $row_horas['tiempo_imputado_horas'];
            $actividades_agrupadas[$key]['tiene_registro'][$dia_semana] = true;
            $actividades_agrupadas[$key]['aprobado'][$dia_semana] = is_hour_record_locked($row_horas);
            $rechazado_raw = isset($row_horas['rechazado_coordinador']) ? $row_horas['rechazado_coordinador'] : 0;
            $actividades_agrupadas[$key]['rechazado'][$dia_semana] = is_truthy_db_flag($rechazado_raw);
            $comentario_raw = isset($row_horas['comentario']) ? (string)$row_horas['comentario'] : '';
            $actividades_agrupadas[$key]['comentado'][$dia_semana] = trim($comentario_raw) !== '';

            $actividades_agrupadas[$key]['cod_sub_ceco'][$dia_semana] = $identity['cod_sub_ceco'];
            $actividades_agrupadas[$key]['nombre_sub_ceco'][$dia_semana] = $identity['nombre_sub_ceco'];
        }
        
        $stmt_horas->close();
        json_out(array_values($actividades_agrupadas));
    }
    
    // ===== GET WEEK TOTAL HOURS =====
    if ($action === 'get_week_hours') {
        $fecha_actual = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
        $target_matricula = get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario_normalizado, $fecha_retiro_corte);
        $range_start = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
        $range_end = isset($_GET['end']) ? trim((string)$_GET['end']) : '';
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $range_start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $range_end)) {
            $fecha_lunes = $range_start;
            $fecha_domingo = $range_end;
        } else {
            $timestamp = strtotime($fecha_actual);
            $lunes = strtotime('monday this week', $timestamp);
            $domingo = strtotime('sunday this week', $timestamp);
            
            $fecha_lunes = date('Y-m-d', $lunes);
            $fecha_domingo = date('Y-m-d', $domingo);
        }
        
        $sql = "SELECT SUM(tiempo_imputado_horas) as total 
                FROM horas_dia 
                WHERE numero_empleado = ? 
                AND fecha >= ? 
                AND fecha <= ?";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sss", $target_matricula, $fecha_lunes, $fecha_domingo);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $total = $row['total'] ? floatval($row['total']) : 0;
        json_out(['total' => $total]);
        $stmt->close();
    }
    
    // ===== CHECK MONTH APPROVAL STATUS =====
    if ($action === 'check_month_approval') {
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
        $target_matricula = get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario_normalizado, $fecha_retiro_corte);
        
        // Calcular mes en formato "ene-26", "feb-26", etc.
        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $mes_formato = $meses[$month] . '-' . substr($year, -2);
        
        // Verificar si existe aprobación para este mes
        $sql = "SELECT id, estado FROM aprobaciones_horas 
                WHERE numero_empleado = ? 
                AND mes = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ss", $target_matricula, $mes_formato);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $is_approved = false;
        $estado_actual = '';
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $estado_actual = isset($row['estado']) ? trim(strtolower($row['estado'])) : '';
            
            // Habilitar el botón de rechazar si existe un envío y NO está rechazado
            // Es decir: si estado es 'enviado' o 'aprobado'
            $is_approved = ($estado_actual === 'enviado' || $estado_actual === 'aprobado');
        }
        $stmt->close();
        json_out([
            'is_approved' => $is_approved,
            'estado' => $estado_actual,
            'mes' => $mes_formato,
            'registros' => $result->num_rows
        ]);
    }
    
    // ===== GET PROJECT LIMIT AND USAGE =====
    if ($action === 'get_project_limit') {
        $codigo_affaire = isset($_GET['codigo_affaire']) ? $_GET['codigo_affaire'] : '';
        $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
        $target_matricula = get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario_normalizado, $fecha_retiro_corte);
        $nombre_affaire = isset($_GET['nombre_affaire']) ? trim((string)$_GET['nombre_affaire']) : '';
        
        if (empty($codigo_affaire)) {
            echo json_encode(['error' => 'Código de proyecto requerido']);
            exit;
        }

        $assignment_context = get_cost_center_assignment_context($mysqli, $codigo_affaire, $nombre_affaire);
        $assignment_center_cost = $assignment_context['assignment_center_cost'];
        $assignment_project_name = $assignment_context['assignment_project_name'];
        $es_ilimitado = is_unlimited_activity($assignment_center_cost, $assignment_project_name)
            || is_unlimited_activity($codigo_affaire, $nombre_affaire);
        
        // Obtener el mes de la fecha (columna dinámica en asignación)
        $timestamp = strtotime($fecha);
        $mes = strtoupper(date('M', $timestamp));
        $ano = date('Y', $timestamp);
        $mes_columna = ucwords(substr(date('M', $timestamp), 0, 3)) . '_' . $ano; // Ej: Ene_2026
        
        // Mapear nombres de meses en español a inglés
        $meses_map = [
            'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
            'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
            'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
        ];
        $mes_ingles = date('M', $timestamp);
        $mes_columna = (isset($meses_map[$mes_ingles]) ? $meses_map[$mes_ingles] : $mes_ingles) . '_' . $ano;
        
        // Obtener límite de horas del proyecto del mes actual
        $sql_limit = "SELECT `$mes_columna` as horas_asignadas 
                      FROM `asignación` 
                      WHERE matricula = ? 
                      AND centro_costos = ?";
        
        $stmt_limit = $mysqli->prepare($sql_limit);
        $stmt_limit->bind_param("ss", $target_matricula, $assignment_center_cost);
        $stmt_limit->execute();
        $result_limit = $stmt_limit->get_result();
        $row_limit = $result_limit->fetch_assoc();
        
        $horas_asignadas = $row_limit && isset($row_limit['horas_asignadas']) ? floatval($row_limit['horas_asignadas']) : 0;
        $stmt_limit->close();
        
        // Obtener horas ya registradas en este proyecto en el mes actual
        $fecha_inicio_mes = date('Y-m-01', $timestamp);
        $fecha_fin_mes = date('Y-m-t', $timestamp);
        
        $horas_registradas_total = get_assignment_hours_usage(
            $mysqli,
            $target_matricula,
            $assignment_center_cost,
            $fecha_inicio_mes,
            $fecha_fin_mes
        );
        
        // Verificar si existe un registro para esta fecha específica (para UPDATE)
        $sql_check_fecha = "SELECT tiempo_imputado_horas FROM horas_dia 
                            WHERE numero_empleado = ? 
                            AND codigo_affaire = ? 
                            AND fecha = ?";
        $stmt_check_fecha = $mysqli->prepare($sql_check_fecha);
        $stmt_check_fecha->bind_param("sss", $target_matricula, $codigo_affaire, $fecha);
        $stmt_check_fecha->execute();
        $result_check_fecha = $stmt_check_fecha->get_result();
        $horas_antiguas = 0;
        if ($result_check_fecha->num_rows > 0) {
            $row_check = $result_check_fecha->fetch_assoc();
            $horas_antiguas = floatval($row_check['tiempo_imputado_horas']);
        }
        $stmt_check_fecha->close();
        
        // Si es UPDATE (existe registro), restar las horas antiguas para el cálculo correcto
        $horas_registradas = $horas_registradas_total - $horas_antiguas;
        echo json_encode([
            'codigo_affaire' => $codigo_affaire,
            'codigo_asignacion' => $assignment_center_cost,
            'horas_asignadas' => $horas_asignadas,
            'horas_registradas' => $horas_registradas,
            'horas_disponibles' => $es_ilimitado ? null : max(0, $horas_asignadas - $horas_registradas),
            'es_ilimitado' => $es_ilimitado,
            'mes_columna' => $mes_columna,
            'debug' => [
                'assignment_project_name' => $assignment_project_name,
                'es_update' => $horas_antiguas > 0,
                'horas_antiguas' => $horas_antiguas,
                'horas_registradas_total' => $horas_registradas_total
            ]
        ]);
        exit;
    }
    
    // ===== ADD ACTIVITIES =====
    if ($action === 'add_activities') {
        $input = json_decode(file_get_contents('php://input'), true);
        $project_ids = isset($input['project_ids']) ? $input['project_ids'] : [];
        $project_codes = isset($input['project_codes']) ? $input['project_codes'] : [];

        if (!is_array($project_ids)) {
            json_out(['success' => false, 'message' => 'Formato de proyectos inválido']);
        }
        if (!is_array($project_codes)) {
            json_out(['success' => false, 'message' => 'Formato de códigos de proyectos inválido']);
        }

        // Sanitizar IDs: enteros positivos únicos
        $project_ids = array_values(array_unique(array_filter(array_map(function ($id) {
            return intval($id);
        }, $project_ids), function ($id) {
            return $id > 0;
        })));

        // Sanitizar códigos: string no vacío único
        $project_codes = array_values(array_unique(array_filter(array_map(function ($code) {
            return trim((string)$code);
        }, $project_codes), function ($code) {
            return $code !== '';
        })));
        
        if (empty($project_ids) && empty($project_codes)) {
            json_out(['success' => false, 'message' => 'No hay proyectos seleccionados']);
        }
        
        // ===== VALIDAR QUE EL MES NO ESTÉ APROBADO =====
        // Usar mes y año seleccionados por el usuario
        $selected_month = isset($input['month']) ? intval($input['month']) : intval(date('m'));
        $selected_year = isset($input['year']) ? intval($input['year']) : intval(date('Y'));
        $week_start = isset($input['week_start']) ? trim((string)$input['week_start']) : '';
        // Si el mes viene en formato 0-based (0=enero), sumar 1
        if ($selected_month <= 0) {
            $selected_month = 1;
        } elseif ($selected_month <= 12 && $selected_month >= 1) {
            // nada, ya está correcto
        } else {
            // Si el mes es inválido, usar el mes actual
            $selected_month = intval(date('m'));
        }
        $first_day_month = date('Y-m-01', mktime(0, 0, 0, $selected_month, 1, $selected_year));
        $activity_week_start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)
            ? $week_start
            : $first_day_month;
        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $mes_formato = $meses[$selected_month] . '-' . substr((string)$selected_year, -2);
        
        $sql_check_approval = "SELECT id FROM aprobaciones_horas 
                               WHERE numero_empleado = ? 
                               AND mes = ? 
                               AND estado IN ('enviado', 'aprobado')";
        $stmt_check_approval = $mysqli->prepare($sql_check_approval);
        $stmt_check_approval->bind_param("ss", $matricula, $mes_formato);
        $stmt_check_approval->execute();
        $result_check_approval = $stmt_check_approval->get_result();
        
        if ($result_check_approval->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Este mes ha sido aprobado y no se pueden realizar cambios']);
            $stmt_check_approval->close();
            exit;
        }
        $stmt_check_approval->close();
        
        // Obtener nombre del usuario
        $sql_user = "SELECT Nombre_Usuario FROM login_usuarios WHERE Usuario = ?";
        $stmt = $mysqli->prepare($sql_user);
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $nombre_usuario = isset($user_data['Nombre_Usuario']) ? $user_data['Nombre_Usuario'] : $usuario;
        $stmt->close();
        
        // Obtener proyectos seleccionados por ID y/o por código
        $projects_map = [];

        if (!empty($project_ids)) {
            $placeholders_ids = implode(',', array_fill(0, count($project_ids), '?'));
            $sql_ids = "SELECT id, centro_costos, nombre_proyecto FROM proyectos WHERE id IN ($placeholders_ids)";
            $stmt_ids = $mysqli->prepare($sql_ids);
            $stmt_ids->bind_param(str_repeat('i', count($project_ids)), ...$project_ids);
            $stmt_ids->execute();
            $result_ids = $stmt_ids->get_result();
            while ($row = $result_ids->fetch_assoc()) {
                $projects_map[$row['centro_costos']] = $row;
            }
            $stmt_ids->close();
        }

        if (!empty($project_codes)) {
            $placeholders_codes = implode(',', array_fill(0, count($project_codes), '?'));
            $sql_codes = "SELECT id, centro_costos, nombre_proyecto FROM proyectos WHERE centro_costos IN ($placeholders_codes)";
            $stmt_codes = $mysqli->prepare($sql_codes);
            $stmt_codes->bind_param(str_repeat('s', count($project_codes)), ...$project_codes);
            $stmt_codes->execute();
            $result_codes = $stmt_codes->get_result();
            while ($row = $result_codes->fetch_assoc()) {
                $projects_map[$row['centro_costos']] = $row;
            }
            $stmt_codes->close();
        }

        $selected_projects = array_values($projects_map);

        // Validar que al menos un proyecto seleccionado exista
        if (empty($selected_projects)) {
            json_out(['success' => false, 'message' => 'Los proyectos seleccionados no existen o no son válidos']);
        }
        
        $added_count = 0;

        // project_items may contain per-project entries with subcentro info
        $project_items = isset($input['project_items']) && is_array($input['project_items']) ? $input['project_items'] : [];

        foreach ($selected_projects as $project) {
            $code = $project['centro_costos'];
            $nombre_proyecto = $project['nombre_proyecto'];

            // Find any provided items for this project code (may be multiple subcentros)
            $matches = array_values(array_filter($project_items, function ($it) use ($code) {
                return isset($it['code']) && trim((string)$it['code']) === trim((string)$code);
            }));

            // If no explicit items provided, create one default item without subcentro
            if (empty($matches)) {
                $matches = [[ 'code' => $code, 'cod_sub_ceco' => null, 'nombre_sub_ceco' => null ]];
            }

            foreach ($matches as $item) {
                $cod_sub = isset($item['cod_sub_ceco']) && $item['cod_sub_ceco'] !== '' ? trim((string)$item['cod_sub_ceco']) : null;
                $nombre_sub = isset($item['nombre_sub_ceco']) && $item['nombre_sub_ceco'] !== '' ? trim((string)$item['nombre_sub_ceco']) : null;
                $activity_storage = resolve_activity_storage_fields($code, $nombre_proyecto, $cod_sub, $nombre_sub);
                $stored_codigo_affaire = $activity_storage['codigo_affaire'];
                $stored_nombre_proyect = $activity_storage['nombre_proyect'];

                $sql_check = "SELECT id FROM cargue_horas WHERE numero_de_empleado = ? AND codigo_affaire = ? AND fecha = ?";
                $stmt_check = $mysqli->prepare($sql_check);
                $stmt_check->bind_param("sss", $matricula, $stored_codigo_affaire, $activity_week_start);

                $stmt_check->execute();
                $check_result = $stmt_check->get_result();

                if ($check_result->num_rows === 0) {
                    $sql_insert = "INSERT INTO cargue_horas 
                        (numero_de_empleado, nom, prenom, codigo_affaire, nombre_proyect, fecha, created_at, updated_at, estado) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 'activo')";
                    $stmt_insert = $mysqli->prepare($sql_insert);

                    $partes_nombre = explode(' ', $nombre_usuario, 2);
                    $nom = $partes_nombre[0];
                    $prenom = isset($partes_nombre[1]) ? $partes_nombre[1] : '';

                    $stmt_insert->bind_param(
                        "ssssss",
                        $matricula,
                        $nom,
                        $prenom,
                        $stored_codigo_affaire,
                        $stored_nombre_proyect,
                        $activity_week_start
                    );

                    if ($stmt_insert->execute()) {
                        $added_count++;
                    }
                    $stmt_insert->close();
                }

                $stmt_check->close();
            }
        }

        json_out(['success' => true, 'message' => "Se agregaron $added_count actividades", 'added' => $added_count]);
    }
    
    // ===== DELETE ACTIVITY (Eliminar completamente) =====
    if ($action === 'delete_activity') {
        $input = json_decode(file_get_contents('php://input'), true);
        $activity_id = isset($input['activity_id']) ? $input['activity_id'] : 0;
        
        if (!$activity_id) {
            json_out(['success' => false, 'message' => 'ID inválido']);
        }
        
        // Primero obtener el código_affaire y fecha del registro
        $sql_get = "SELECT codigo_affaire, nombre_proyect, fecha FROM cargue_horas WHERE id = ? AND numero_de_empleado = ?";
        $stmt_get = $mysqli->prepare($sql_get);
        $stmt_get->bind_param("is", $activity_id, $matricula);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        
        if ($result_get->num_rows === 0) {
            $stmt_get->close();
            json_out(['success' => false, 'message' => 'Actividad no encontrada']);
        }
        
        $row_get = $result_get->fetch_assoc();
        $codigo_affaire = $row_get['codigo_affaire'];
        $nombre_proyect = isset($row_get['nombre_proyect']) ? $row_get['nombre_proyect'] : '';
        $fecha = $row_get['fecha'];
        $stmt_get->close();
        $activity_context = get_cost_center_assignment_context($mysqli, $codigo_affaire, $nombre_proyect);
        $delete_codigo_affaire = $activity_context['assignment_center_cost'];
        $delete_cod_sub_ceco = trim((string)$activity_context['subcenter_code']);

        // Mes seleccionado desde frontend (fallback al mes de la actividad)
        $selected_month = isset($input['selected_month']) ? intval($input['selected_month']) : intval(date('m', strtotime($fecha)));
        $selected_year = isset($input['selected_year']) ? intval($input['selected_year']) : intval(date('Y', strtotime($fecha)));
        if ($selected_month < 1 || $selected_month > 12) {
            $selected_month = intval(date('m', strtotime($fecha)));
        }
        if ($selected_year < 2000 || $selected_year > 2100) {
            $selected_year = intval(date('Y', strtotime($fecha)));
        }
        $first_day_selected_month = date('Y-m-01', mktime(0, 0, 0, $selected_month, 1, $selected_year));
        $last_day_selected_month = date('Y-m-t', mktime(0, 0, 0, $selected_month, 1, $selected_year));

        // Semana seleccionada desde frontend (YYYY-MM-DD); fallback a la fecha de la actividad
        $week_start = isset($input['week_start']) ? trim((string)$input['week_start']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_start)) {
            $dia_semana = date('N', strtotime($fecha)); // 1 (lunes) a 7 (domingo)
            $fecha_inicio_semana = date('Y-m-d', strtotime($fecha . ' -' . ($dia_semana - 1) . ' days'));
        } else {
            $fecha_inicio_semana = $week_start;
        }
        $fecha_fin_semana = date('Y-m-d', strtotime($fecha_inicio_semana . ' +6 days'));
        $fecha_validacion = $fecha_inicio_semana;
        
        // ===== VALIDAR QUE EL MES NO ESTÉ APROBADO =====
        $mes_map = [
            'january' => 'Ene', 'february' => 'Feb', 'march' => 'Mar', 'april' => 'Abr',
            'may' => 'May', 'june' => 'Jun', 'july' => 'Jul', 'august' => 'Ago',
            'september' => 'Sep', 'october' => 'Oct', 'november' => 'Nov', 'december' => 'Dic'
        ];
        $nombreMes = strtolower(date('F', strtotime($fecha_validacion)));
        $mesCorto = isset($mes_map[$nombreMes]) ? $mes_map[$nombreMes] : 'Ene';
        $ano = date('y', strtotime($fecha_validacion));
        $mes_format = $mesCorto . '-' . $ano;
        
        $sql_check_approval = "SELECT id FROM aprobaciones_horas WHERE numero_empleado = ? AND mes = ? AND estado IN ('enviado', 'aprobado')";
        $stmt_check = $mysqli->prepare($sql_check_approval);
        $stmt_check->bind_param("ss", $matricula, $mes_format);
        $stmt_check->execute();
        $result_approval = $stmt_check->get_result();
        
        if ($result_approval->num_rows > 0) {
            $stmt_check->close();
            json_out(['success' => false, 'message' => 'Este mes ha sido aprobado y no se pueden realizar cambios']);
        }
        $stmt_check->close();
        
        // Validar que el mes NO esté aprobado antes de permitir cambios en edit_hours
        $sql_check_approval = "SELECT id FROM aprobaciones_horas WHERE numero_empleado = ? AND mes = ? AND estado IN ('enviado', 'aprobado')";
        $stmt_check_edit = $mysqli->prepare($sql_check_approval);
        $stmt_check_edit->bind_param("ss", $matricula, $mes_format);
        $stmt_check_edit->execute();
        $result_approval_edit = $stmt_check_edit->get_result();
        
        if ($result_approval_edit->num_rows > 0) {
            $stmt_check_edit->close();
            json_out(['success' => false, 'message' => 'Este mes ha sido aprobado y no se pueden realizar cambios']);
        }
        $stmt_check_edit->close();

                if ($delete_cod_sub_ceco !== '') {
                        $sql_locked_hours = "SELECT fecha, aprobado_coordinador, aprobado_director, Estado_Aprobacion
                                                                 FROM horas_dia
                                                                 WHERE numero_empleado = ?
                                                                     AND codigo_affaire = ?
                                                                     AND COALESCE(TRIM(cod_sub_ceco), '') = ?
                                                                     AND fecha >= ?
                                                                     AND fecha <= ?";
                        $stmt_locked_hours = $mysqli->prepare($sql_locked_hours);
                        $stmt_locked_hours->bind_param("sssss", $matricula, $delete_codigo_affaire, $delete_cod_sub_ceco, $fecha_inicio_semana, $fecha_fin_semana);
                } else {
                        $sql_locked_hours = "SELECT fecha, aprobado_coordinador, aprobado_director, Estado_Aprobacion
                                                                 FROM horas_dia
                                                                 WHERE numero_empleado = ?
                                                                     AND codigo_affaire = ?
                                                                     AND (cod_sub_ceco IS NULL OR TRIM(cod_sub_ceco) = '')
                                                                     AND fecha >= ?
                                                                     AND fecha <= ?";
                        $stmt_locked_hours = $mysqli->prepare($sql_locked_hours);
                        $stmt_locked_hours->bind_param("ssss", $matricula, $delete_codigo_affaire, $fecha_inicio_semana, $fecha_fin_semana);
                }
        $stmt_locked_hours->execute();
        $result_locked_hours = $stmt_locked_hours->get_result();

        $locked_dates = [];
        while ($row_locked = $result_locked_hours->fetch_assoc()) {
            if (is_hour_record_locked($row_locked)) {
                $locked_dates[] = $row_locked['fecha'];
            }
        }
        $stmt_locked_hours->close();

        if (!empty($locked_dates)) {
            json_out([
                'success' => false,
                'message' => 'No se puede eliminar porque la semana contiene horas aprobadas.'
            ]);
        }
        
        // Recorrer y eliminar solo los 7 días de la semana seleccionada en horas_dia
        $fechas_semana = [];
        for ($i = 0; $i < 7; $i++) {
            $fechas_semana[] = date('Y-m-d', strtotime($fecha_inicio_semana . ' +' . $i . ' days'));
        }

        list($d1, $d2, $d3, $d4, $d5, $d6, $d7) = $fechas_semana;
        if ($delete_cod_sub_ceco !== '') {
            $sql_delete_horas = "DELETE FROM horas_dia WHERE numero_empleado = ? AND codigo_affaire = ? AND COALESCE(TRIM(cod_sub_ceco), '') = ? AND fecha IN (?, ?, ?, ?, ?, ?, ?)";
            $stmt_delete_horas = $mysqli->prepare($sql_delete_horas);
            $stmt_delete_horas->bind_param("ssssssssss", $matricula, $delete_codigo_affaire, $delete_cod_sub_ceco, $d1, $d2, $d3, $d4, $d5, $d6, $d7);
        } else {
            $sql_delete_horas = "DELETE FROM horas_dia WHERE numero_empleado = ? AND codigo_affaire = ? AND (cod_sub_ceco IS NULL OR TRIM(cod_sub_ceco) = '') AND fecha IN (?, ?, ?, ?, ?, ?, ?)";
            $stmt_delete_horas = $mysqli->prepare($sql_delete_horas);
            $stmt_delete_horas->bind_param("sssssssss", $matricula, $delete_codigo_affaire, $d1, $d2, $d3, $d4, $d5, $d6, $d7);
        }
        $stmt_delete_horas->execute();
        $affected_rows = $stmt_delete_horas->affected_rows;
        $stmt_delete_horas->close();

            // Verificar si aún existen horas (>0) para esta actividad en la semana seleccionada
        if ($delete_cod_sub_ceco !== '') {
            $sql_check_any_hours = "SELECT 
                                        COUNT(*) AS total_registros,
                                        SUM(CASE WHEN COALESCE(tiempo_imputado_horas, 0) > 0 THEN 1 ELSE 0 END) AS registros_con_horas
                                    FROM horas_dia
                                    WHERE numero_empleado = ?
                                      AND codigo_affaire = ?
                                      AND COALESCE(TRIM(cod_sub_ceco), '') = ?
                                      AND fecha >= ?
                                      AND fecha <= ?";
            $stmt_check_any_hours = $mysqli->prepare($sql_check_any_hours);
            $stmt_check_any_hours->bind_param("sssss", $matricula, $delete_codigo_affaire, $delete_cod_sub_ceco, $fecha_inicio_semana, $fecha_fin_semana);
        } else {
            $sql_check_any_hours = "SELECT 
                                        COUNT(*) AS total_registros,
                                        SUM(CASE WHEN COALESCE(tiempo_imputado_horas, 0) > 0 THEN 1 ELSE 0 END) AS registros_con_horas
                                    FROM horas_dia
                                    WHERE numero_empleado = ?
                                      AND codigo_affaire = ?
                                      AND (cod_sub_ceco IS NULL OR TRIM(cod_sub_ceco) = '')
                                      AND fecha >= ?
                                      AND fecha <= ?";
            $stmt_check_any_hours = $mysqli->prepare($sql_check_any_hours);
            $stmt_check_any_hours->bind_param("ssss", $matricula, $delete_codigo_affaire, $fecha_inicio_semana, $fecha_fin_semana);
        }
        $stmt_check_any_hours->execute();
        $result_any_hours = $stmt_check_any_hours->get_result();
        $row_any_hours = $result_any_hours->fetch_assoc();
        $stmt_check_any_hours->close();

        $registros_con_horas = $row_any_hours && isset($row_any_hours['registros_con_horas'])
            ? intval($row_any_hours['registros_con_horas'])
            : 0;

        $activity_deleted = false;
        $activity_deleted_rows = 0;

        // Si no quedan horas en la semana seleccionada, eliminar la actividad semanal de cargue_horas
        if ($registros_con_horas === 0) {
            $sql_delete_activity = "DELETE FROM cargue_horas 
                                    WHERE id = ? 
                                      AND numero_de_empleado = ?";
            $stmt_delete_activity = $mysqli->prepare($sql_delete_activity);
            $stmt_delete_activity->bind_param("is", $activity_id, $matricula);
            $stmt_delete_activity->execute();
            $activity_deleted_rows = $stmt_delete_activity->affected_rows;
            $stmt_delete_activity->close();
            $activity_deleted = $activity_deleted_rows > 0;
        }

        if ($affected_rows <= 0 && !$activity_deleted) {
            json_out([
                'success' => false,
                'message' => 'No se eliminaron registros para la semana seleccionada.',
                'debug' => [
                    'empleado' => $matricula,
                    'affaire' => $codigo_affaire,
                    'semana_inicio' => $fecha_inicio_semana,
                    'semana_fin' => $fecha_fin_semana,
                    'fechas' => $fechas_semana,
                    'registros_con_horas' => $registros_con_horas
                ]
            ]);
        }

        json_out([
            'success' => true,
            'deleted_rows' => $affected_rows,
            'activity_deleted' => $activity_deleted,
            'activity_deleted_rows' => $activity_deleted_rows,
            'message' => $activity_deleted
                ? 'Actividad eliminada porque no tenía horas registradas.'
                : 'Horas de la semana eliminadas correctamente.'
        ]);
    }
    
    // ===== SAVE HOURS =====
    if ($action === 'save_hours') {
        error_log("DEBUG save_hours action entered");
        // ensure the horas_dia table contains the needed column
        $check = $mysqli->query("SHOW COLUMNS FROM horas_dia LIKE 'tiempo_imputado_horas'");
        if (!$check || $check->num_rows === 0) {
            json_out([
                'success' => false,
                'message' => "Tabla horas_dia no tiene columna tiempo_imputado_horas"
            ]);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $horas = isset($input['horas']) ? max(0, floatval($input['horas'])) : 0;
        $fecha = isset($input['fecha']) ? $input['fecha'] : '';
        $codigo_affaire = isset($input['codigo_affaire']) ? $input['codigo_affaire'] : '';
        $nombre_affaire = isset($input['nombre_affaire']) ? $input['nombre_affaire'] : '';
        // Subcentro seleccionado en la UI (opcional)
        $cod_sub_ceco = isset($input['cod_sub_ceco']) ? trim((string)$input['cod_sub_ceco']) : NULL;
        $nombre_sub_ceco = isset($input['nombre_sub_ceco']) ? trim((string)$input['nombre_sub_ceco']) : NULL;

        if ($cod_sub_ceco === '') {
            $cod_sub_ceco = NULL;
        }
        if ($nombre_sub_ceco === '') {
            $nombre_sub_ceco = NULL;
        }

        $assignment_context = get_cost_center_assignment_context($mysqli, $codigo_affaire, $nombre_affaire, $cod_sub_ceco, $nombre_sub_ceco);
        $assignment_center_cost = $assignment_context['assignment_center_cost'];
        $assignment_project_name = $assignment_context['assignment_project_name'];
        $nature_imputation = $assignment_context['nature_imputation'];
        if (($cod_sub_ceco === NULL || $cod_sub_ceco === '') && !empty($assignment_context['subcenter_code'])) {
            $cod_sub_ceco = $assignment_context['subcenter_code'];
        }
        if (($nombre_sub_ceco === NULL || $nombre_sub_ceco === '') && !empty($assignment_context['subcenter_name'])) {
            $nombre_sub_ceco = $assignment_context['subcenter_name'];
        }

        // En horas_dia siempre se guarda el centro de costo padre en los campos principales.
        $codigo_affaire = $assignment_center_cost;
        $nombre_affaire = $assignment_project_name;
        
        if (!$fecha || !$codigo_affaire) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        // ===== VALIDAR QUE EL MES NO ESTÉ APROBADO =====
        $mes_map = [
            'january' => 'Ene', 'february' => 'Feb', 'march' => 'Mar', 'april' => 'Abr',
            'may' => 'May', 'june' => 'Jun', 'july' => 'Jul', 'august' => 'Ago',
            'september' => 'Sep', 'october' => 'Oct', 'november' => 'Nov', 'december' => 'Dic'
        ];
        $nombreMes = strtolower(date('F', strtotime($fecha)));
        $ano = date('Y', strtotime($fecha));
        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $month_num = intval(date('m', strtotime($fecha)));
        $mes_formato = $meses[$month_num] . '-' . substr($ano, -2);
        
        $sql_check_approval = "SELECT id FROM aprobaciones_horas 
                               WHERE numero_empleado = ? 
                               AND mes = ?
                               AND estado IN ('enviado', 'aprobado')";
        $stmt_check_approval = $mysqli->prepare($sql_check_approval);
        $stmt_check_approval->bind_param("ss", $matricula, $mes_formato);
        $stmt_check_approval->execute();
        $result_check_approval = $stmt_check_approval->get_result();
        
        if ($result_check_approval->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Este mes ha sido aprobado y no se pueden realizar cambios']);
            $stmt_check_approval->close();
            exit;
        }
        $stmt_check_approval->close();
        
        // Validar que no sea sábado ni domingo
        $fechaTimestamp = strtotime($fecha);
        $diaSemana = date('w', $fechaTimestamp); // 0=domingo, 1=lunes, ..., 6=sábado
        
        if ($diaSemana == 0 || $diaSemana == 6) {
            echo json_encode(['success' => false, 'message' => 'No se pueden registrar horas en sábados y domingos']);
            exit;
        }

        // Validar que no sea festivo colombiano
        $festividadesColombia = [
            '2026-01-01', // Año nuevo
           // '2026-03-30', // Lunes de Semana Santa
            //'2026-03-31', // Martes de Semana Santa
            '2026-05-01', // Día del trabajador
            '2026-05-14', // Ascensión del Señor
            '2026-06-04', // Corpus Christi
            '2026-06-11', // Sagrado Corazón
            '2026-07-01', // San Pedro y San Pablo
            '2026-07-20', // Grito de Independencia
            '2026-08-07', // Batalla de Boyacá
            '2026-08-17', // Asunción de María
            '2026-10-12', // Día de la Raza
            '2026-11-01', // Todos los Santos
            '2026-11-11', // Independencia de Cartagena
            '2026-12-08', // Inmaculada Concepción
            '2026-12-25'  // Navidad
        ];
        
        if (in_array($fecha, $festividadesColombia)) {
            echo json_encode(['success' => false, 'message' => 'No se pueden registrar horas en días festivos']);
            exit;
        }
        
        // Obtener nombre completo del usuario
        $sql_user = "SELECT Nombre_Usuario FROM login_usuarios WHERE Usuario = ?";
        $stmt_user = $mysqli->prepare($sql_user);
        $stmt_user->bind_param("s", $usuario);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        $row_user = $result_user->fetch_assoc();
        $nombre_completo = $row_user ? $row_user['Nombre_Usuario'] : $usuario;
        $stmt_user->close();
        
        // Obtener límite de horas diarias
        $limite_horas = 9; // valor por defecto
        $sql_limite = "SELECT horas_diarias FROM empleados WHERE matricula = ?";
        $stmt_limite = $mysqli->prepare($sql_limite);
        $stmt_limite->bind_param("s", $matricula);
        $stmt_limite->execute();
        $result_limite = $stmt_limite->get_result();
        if ($result_limite && $result_limite->num_rows > 0) {
            $row_limite = $result_limite->fetch_assoc();
            $limite_horas = floatval($row_limite['horas_diarias']);
        }
        $stmt_limite->close();

        // Regla especial: si el calendario diario es 9h, los viernes el máximo permitido es 8h
        $limite_horas_dia = $limite_horas;
        if (intval($diaSemana) === 5 && floatval($limite_horas) == 9.0) {
            $limite_horas_dia = 8;
        }
        
        // Obtener cat_coan de la tabla empleados
        $cat_coan = NULL;
        $sql_cat = "SELECT cat_coan FROM empleados WHERE matricula = ?";
        $stmt_cat = $mysqli->prepare($sql_cat);
        
        if ($stmt_cat) {
            $stmt_cat->bind_param("s", $matricula);
            $stmt_cat->execute();
            $result_cat = $stmt_cat->get_result();
            
            if ($result_cat && $result_cat->num_rows > 0) {
                $row_cat = $result_cat->fetch_assoc();
                $cat_coan = isset($row_cat['cat_coan']) ? intval($row_cat['cat_coan']) : NULL;
            }
            $stmt_cat->close();
        }
        
        // Obtener area_funcional y tarifa_coan de app_reporte_inputhh_detalle
        $area_funcional = NULL;
        $tarifa_coan = NULL;
        
        // Intentar obtener datos del empleado
        $sql_detalle = "SELECT area_funcional, tarifa_coan FROM empleados WHERE matricula = ? LIMIT 1";
        $stmt_detalle = $mysqli->prepare($sql_detalle);
        
        if ($stmt_detalle) {
            $stmt_detalle->bind_param("s", $matricula);
            $stmt_detalle->execute();
            $result_detalle = $stmt_detalle->get_result();
            
            if ($result_detalle && $result_detalle->num_rows > 0) {
                $row_detalle = $result_detalle->fetch_assoc();
                $area_funcional = isset($row_detalle['area_funcional']) ? $row_detalle['area_funcional'] : NULL;
                $tarifa_coan = isset($row_detalle['tarifa_coan']) ? floatval($row_detalle['tarifa_coan']) : NULL;
            }
            $stmt_detalle->close();
        }
        
        // Verificar si ya existe registro con esta fecha y usuario
        if ($cod_sub_ceco !== NULL && $cod_sub_ceco !== '') {
            $sql_check = "SELECT id, tiempo_imputado_horas, aprobado_coordinador, aprobado_director, Estado_Aprobacion, cod_sub_ceco FROM horas_dia WHERE numero_empleado = ? AND codigo_affaire = ? AND fecha = ? AND COALESCE(TRIM(cod_sub_ceco),'') = ?";
            $stmt_check = prepare_and_log($mysqli, $sql_check);
            $stmt_check->bind_param("ssss", $matricula, $codigo_affaire, $fecha, $cod_sub_ceco);
        } else {
            $sql_check = "SELECT id, tiempo_imputado_horas, aprobado_coordinador, aprobado_director, Estado_Aprobacion, cod_sub_ceco FROM horas_dia WHERE numero_empleado = ? AND codigo_affaire = ? AND fecha = ? AND (cod_sub_ceco IS NULL OR TRIM(cod_sub_ceco) = '')";
            $stmt_check = prepare_and_log($mysqli, $sql_check);
            $stmt_check->bind_param("sss", $matricula, $codigo_affaire, $fecha);
        }
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $exists = $result_check->num_rows > 0;
        $horas_antiguas = 0;
        $registro_aprobado = false;
        $existing_id = null;
        if ($exists) {
            $row_check = $result_check->fetch_assoc();
            $existing_id = isset($row_check['id']) ? $row_check['id'] : null;
            $horas_antiguas = floatval($row_check['tiempo_imputado_horas']);
            $registro_aprobado = is_hour_record_locked($row_check);
        }
        $stmt_check->close();

        if ($registro_aprobado) {
            echo json_encode(['success' => false, 'message' => 'Esta hora ya fue aprobada y no se puede modificar']);
            exit;
        }

        if (!$exists && $horas <= 0) {
            json_out([
                'success' => true,
                'message' => 'No había horas registradas para actualizar.',
                'normalized_hours' => 0,
                'noop' => true
            ]);
        }
        
        // Debug: también buscar en cargue_horas por si acaso, solo si la columna existe
        $existe_en_cargue = false;
        $col_check = $mysqli->query("SHOW COLUMNS FROM cargue_horas LIKE 'tiempo_imputado_horas'");
        if ($col_check && $col_check->num_rows > 0) {
            $sql_check_cargue = "SELECT tiempo_imputado_horas FROM cargue_horas WHERE numero_de_empleado = ? AND codigo_affaire = ? AND fecha = ?";
            $stmt_check_cargue = $mysqli->prepare($sql_check_cargue);
            if ($stmt_check_cargue) {
                $stmt_check_cargue->bind_param("sss", $matricula, $codigo_affaire, $fecha);
                $stmt_check_cargue->execute();
                $result_check_cargue = $stmt_check_cargue->get_result();
                $existe_en_cargue = $result_check_cargue->num_rows > 0;
                $stmt_check_cargue->close();
            } else {
                error_log("DEBUG: prepare failed for debug SELECT on cargue_horas: " . $mysqli->error);
            }
        } else {
            error_log("DEBUG: columna tiempo_imputado_horas no existe en cargue_horas, omitiendo comprobación");
        }
        
        error_log("DEBUG save_hours: matricula=[$matricula], codigo_affaire=[$codigo_affaire], fecha=[$fecha]");
        error_log("DEBUG horas_dia: exists=$exists, registros=" . $result_check->num_rows . ", horas_antiguas=$horas_antiguas");
        if (isset($result_check_cargue)) {
            error_log("DEBUG cargue_horas: existe=$existe_en_cargue, registros=" . $result_check_cargue->num_rows);
        } else {
            error_log("DEBUG cargue_horas: consulta no ejecutada (columna ausente)");
        }
        error_log("DEBUG horas_nuevas=$horas");
        
        $debug_info = [
            'matricula' => $matricula,
            'codigo_affaire' => $codigo_affaire,
            'fecha' => $fecha,
            'existe_en_horas_dia' => $exists,
            'registros_en_horas_dia' => $result_check->num_rows,
            'horas_antiguas' => $horas_antiguas,
            'existe_en_cargue_horas' => $existe_en_cargue,
            'registros_en_cargue_horas' => $result_check_cargue->num_rows,
            'horas_nuevas' => $horas
        ];
        
        // Calcular suma TOTAL de horas para ese día (todos los proyectos)
        // EXCLUYE el registro actual si es UPDATE
        if ($exists && $existing_id !== null) {
            $sql_total = "SELECT SUM(tiempo_imputado_horas) as total FROM horas_dia WHERE numero_empleado = ? AND fecha = ? AND id != ?";
            $stmt_total = $mysqli->prepare($sql_total);
            $stmt_total->bind_param("ssi", $matricula, $fecha, $existing_id);
        } else {
            $sql_total = "SELECT SUM(tiempo_imputado_horas) as total FROM horas_dia WHERE numero_empleado = ? AND fecha = ?";
            $stmt_total = $mysqli->prepare($sql_total);
            $stmt_total->bind_param("ss", $matricula, $fecha);
        }
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $row_total = $result_total->fetch_assoc();
        $horas_actuales = floatval($row_total['total']) ?? 0;
        $stmt_total->close();
        
        // Validar que la nueva suma no exceda el límite
        // Suma actual (sin el registro que estamos editando) + horas nuevas
        if (($horas_actuales + $horas) > $limite_horas_dia) {
            echo json_encode(['success' => false, 'message' => "No se pueden registrar más de $limite_horas_dia horas por día. Total actual: $horas_actuales horas"]);
            exit;
        }
        
        // ===== VALIDAR LÍMITE DE HORAS POR PROYECTO =====
        // Obtener el mes de la fecha (columna dinámica en asignación)
        $mes_map = [
            'january' => 'Ene', 'february' => 'Feb', 'march' => 'Mar', 'april' => 'Abr',
            'may' => 'May', 'june' => 'Jun', 'july' => 'Jul', 'august' => 'Ago',
            'september' => 'Sep', 'october' => 'Oct', 'november' => 'Nov', 'december' => 'Dic'
        ];
        $nombreMes = strtolower(date('F', strtotime($fecha)));
        $ano = date('Y', strtotime($fecha));
        $mes_columna = (isset($mes_map[$nombreMes]) ? $mes_map[$nombreMes] : 'Ene') . '_' . $ano;
        
        // Obtener límite de horas del proyecto del mes actual
        $sql_limit = "SELECT `$mes_columna` as horas_asignadas FROM `asignación` WHERE matricula = ? AND centro_costos = ?";
        $stmt_limit = $mysqli->prepare($sql_limit);
        $stmt_limit->bind_param("ss", $matricula, $assignment_center_cost);
        $stmt_limit->execute();
        $result_limit = $stmt_limit->get_result();
        $row_limit = $result_limit->fetch_assoc();
        $horas_asignadas = $row_limit && isset($row_limit['horas_asignadas']) ? floatval($row_limit['horas_asignadas']) : 0;
        $stmt_limit->close();
        
        // Obtener horas ya registradas en este proyecto en el mes actual
        $fecha_inicio_mes = date('Y-m-01', strtotime($fecha));
        $fecha_fin_mes = date('Y-m-t', strtotime($fecha));
        
        // Contar TODAS las horas registradas del proyecto en el mes
        $horas_registradas_total = get_assignment_hours_usage(
            $mysqli,
            $matricula,
            $assignment_center_cost,
            $fecha_inicio_mes,
            $fecha_fin_mes
        );
        
        // Si es UPDATE (edición), el total sin contar el registro actual es: total - horas_antiguas
        // Si es INSERT (nuevo), el total es como está
        if ($exists) {
            $horas_registradas = max(0, $horas_registradas_total - $horas_antiguas);
        } else {
            $horas_registradas = $horas_registradas_total;
        }
        
        error_log("DEBUG calculo: existe=$exists, total=$horas_registradas_total, antiguas=$horas_antiguas, registradas_sin_actual=$horas_registradas, nuevas=$horas");
        
        // Validar que no se exceda el límite del proyecto
        $esExcepcion = is_unlimited_activity($assignment_center_cost, $assignment_project_name)
            || is_unlimited_activity($codigo_affaire, $nombre_affaire);
        
        // Si no es un proyecto de excepción, validar que tenga horas asignadas
        if ($horas > 0 && !$esExcepcion && $horas_asignadas <= 0) {
            echo json_encode([
                'success' => false, 
                'message' => "No hay horas asignadas para este proyecto en el mes seleccionado.\n" .
                             "Límite del mes: $horas_asignadas horas"
            ]);
            exit;
        }
        
        // Para UPDATE: validar contra el total que quedaría
        // Para INSERT: validar contra el total que resultaría
        $total_resultante = $horas_registradas + $horas;
        
        if ($horas > 0 && !$esExcepcion && $horas_asignadas > 0 && $total_resultante > $horas_asignadas) {
            $faltante = $total_resultante - $horas_asignadas;
            $horasDisponibles = max(0, $horas_asignadas - $horas_registradas);
            
            // Mensaje diferente para UPDATE vs INSERT
            if ($exists) {
                $mensaje = "Cambio no permitido.\n" .
                          "Límite del mes: $horas_asignadas horas\n" .
                          "Horas sin este registro: $horas_registradas horas\n" .
                          "Nuevas horas: $horas horas\n" .
                          "Total resultante: $total_resultante horas\n" .
                          "Exceso: $faltante horas";
            } else {
                $mensaje = "No se pueden registrar $horas horas en este proyecto.\n" .
                          "Límite del mes: $horas_asignadas horas\n" .
                          "Ya registradas: $horas_registradas horas\n" .
                          "Disponibles: $horasDisponibles horas\n" .
                          "Exceso: $faltante horas";
            }
            
            echo json_encode([
                'success' => false, 
                'message' => $mensaje,
                'debug' => array_merge($debug_info, [
                    'horas_registradas_total' => $horas_registradas_total,
                    'horas_registradas_sin_actual' => $horas_registradas,
                    'total_resultante' => $total_resultante,
                    'horas_asignadas' => $horas_asignadas
                ])
            ]);
            exit;
        }
        
        // Calcular tiempo_imputado_costo = tiempo_imputado_horas * tarifa_coan
        $tiempo_imputado_costo = NULL;
        if ($tarifa_coan !== NULL && $horas > 0) {
            $tiempo_imputado_costo = floatval($horas) * floatval($tarifa_coan);
        }
        
        // Obtener nom y prenom de la tabla empleados
        $nom = NULL;
        $prenom = NULL;
        $sql_empleado = "SELECT nom, prenom FROM empleados WHERE matricula = ?";
        $stmt_empleado = $mysqli->prepare($sql_empleado);
        if ($stmt_empleado) {
            $stmt_empleado->bind_param("s", $matricula);
            $stmt_empleado->execute();
            $result_empleado = $stmt_empleado->get_result();
            if ($result_empleado && $result_empleado->num_rows > 0) {
                $row_empleado = $result_empleado->fetch_assoc();
                $nom = $row_empleado['nom'];
                $prenom = $row_empleado['prenom'];
            }
            $stmt_empleado->close();
        }
        
        if (!isset($nombre_sub_ceco) || $nombre_sub_ceco === '') {
            $nombre_sub_ceco = $assignment_context['is_subcenter'] ? $assignment_context['subcenter_name'] : NULL;
        }
        if (!isset($cod_sub_ceco) || $cod_sub_ceco === '') {
            $cod_sub_ceco = $assignment_context['is_subcenter'] ? $assignment_context['subcenter_code'] : NULL;
        }
        
        if ($exists) {
            // UPDATE si ya existe
            $sql_update = "UPDATE horas_dia SET tiempo_imputado_horas = ?, nombre_affaire = ?, area_funcional = ?, tarifa_coan = ?, cat_coan = ?, nombre_sub_ceco = ?, cod_sub_ceco = ?, tiempo_imputado_costo = ?, nom = ?, prenom = ?, nature_imputation = ?, aprobado_coordinador = ?, rechazado_coordinador = ?, comentario_coordinador = ?, aprobado_director = ?, rechazado_director = ?, comentario_director = ?, horas_registradas = ?, horas_teoricas = ?, Estado_Aprobacion = ? WHERE numero_empleado = ? AND codigo_affaire = ? AND fecha = ?";
            $stmt_update = $mysqli->prepare($sql_update);
            if (!$stmt_update) {
                error_log("DEBUG prepare failed: " . $mysqli->error . " -- SQL: " . $sql_update);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error en prepare',
                    'mysqli_error' => $mysqli->error,
                    'sql' => $sql_update,
                    'debug' => isset($debug_info) ? $debug_info : null
                ]);
                exit;
            }
            // Valores por defecto para los nuevos campos (puedes ajustarlos si vienen del front)
            $aprobado_coordinador = NULL;
            $rechazado_coordinador = NULL;
            $comentario_coordinador = NULL;
            $aprobado_director = NULL;
            $rechazado_director = NULL;
            $comentario_director = NULL;
            $horas_registradas = NULL;
            $horas_teoricas = NULL;
            $Estado_Aprobacion = "Aprobado En Curso";
            // corrected types: 23 parameters total
            // dynamic binding for update as well to avoid errors
            $params = [&$horas, &$nombre_affaire, &$area_funcional, &$tarifa_coan, &$cat_coan,
                       &$nombre_sub_ceco, &$cod_sub_ceco, &$tiempo_imputado_costo, &$nom, &$prenom,
                       &$nature_imputation, &$aprobado_coordinador, &$rechazado_coordinador,
                       &$comentario_coordinador, &$aprobado_director, &$rechazado_director,
                       &$comentario_director, &$horas_registradas, &$horas_teoricas, &$Estado_Aprobacion,
                       &$matricula, &$codigo_affaire, &$fecha];
            $types = str_repeat('s', count($params));
            error_log("DEBUG update bind types: $types count=" . count($params));
            call_user_func_array([$stmt_update, 'bind_param'], array_merge([$types], $params));

            if (!$stmt_update->execute()) {
                error_log("DEBUG execute failed (UPDATE): " . $stmt_update->error . " -- SQL: " . $sql_update);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al actualizar',
                    'stmt_error' => $stmt_update->error,
                    'mysqli_error' => $mysqli->error,
                    'sql' => $sql_update,
                    'debug' => isset($debug_info) ? $debug_info : null
                ]);
                $stmt_update->close();
                exit;
            }
            echo json_encode(['success' => true, 'message' => 'Horas actualizadas correctamente']);
            $stmt_update->close();
        } else {
            // INSERT si no existe
            $sql_insert = "INSERT INTO horas_dia (nombre_completo, codigo_affaire, nombre_affaire, numero_empleado, fecha, tiempo_imputado_horas, comentario, area_funcional, tarifa_coan, cat_coan, nombre_sub_ceco, cod_sub_ceco, tiempo_imputado_costo, nom, prenom, nature_imputation, aprobado_coordinador, rechazado_coordinador, comentario_coordinador, aprobado_director, rechazado_director, comentario_director, horas_registradas, horas_teoricas, Estado_Aprobacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $mysqli->prepare($sql_insert);
            if (!$stmt_insert) {
                error_log("DEBUG prepare failed (INSERT): " . $mysqli->error . " -- SQL: " . $sql_insert);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error en prepare',
                    'mysqli_error' => $mysqli->error,
                    'sql' => $sql_insert,
                    'debug' => isset($debug_info) ? $debug_info : null
                ]);
                exit;
            }
            $comentario = '';
            $aprobado_coordinador = NULL;
            $rechazado_coordinador = NULL;
            $comentario_coordinador = NULL;
            $aprobado_director = NULL;
            $rechazado_director = NULL;
            $comentario_director = NULL;
            $horas_registradas = NULL;
            $horas_teoricas = NULL;
            $Estado_Aprobacion = "Aprobado En Curso";
            // dynamically bind all parameters to avoid manual type/string mismatches
            $params = [&$nombre_completo, &$codigo_affaire, &$nombre_affaire, &$matricula, &$fecha,
                       &$horas, &$comentario, &$area_funcional, &$tarifa_coan, &$cat_coan,
                       &$nombre_sub_ceco, &$cod_sub_ceco, &$tiempo_imputado_costo, &$nom, &$prenom,
                       &$nature_imputation, &$aprobado_coordinador, &$rechazado_coordinador,
                       &$comentario_coordinador, &$aprobado_director, &$rechazado_director,
                       &$comentario_director, &$horas_registradas, &$horas_teoricas, &$Estado_Aprobacion];
            $types = str_repeat('s', count($params));
            error_log("DEBUG insert bind types: $types count=" . count($params));
            call_user_func_array([$stmt_insert, 'bind_param'], array_merge([$types], $params));

            if (!$stmt_insert->execute()) {
                error_log("DEBUG execute failed (INSERT): " . $stmt_insert->error . " -- SQL: " . $sql_insert);
                $error_message = "Error al guardar:\n";
                $error_message .= "stmt_error: " . $stmt_insert->error . "\n";
                $error_message .= "mysqli_error: " . $mysqli->error . "\n";
                $error_message .= "SQL: " . $sql_insert . "\n";
                if (isset($debug_info)) {
                    $error_message .= "Debug info: " . print_r($debug_info, true) . "\n";
                }
                echo json_encode([
                    'success' => false,
                    'message' => $error_message
                ]);
                $stmt_insert->close();
                exit;
            }
            echo json_encode(['success' => true, 'message' => 'Horas guardadas correctamente']);
            $stmt_insert->close();
        }
        exit;
    }
    
    // end try
    } catch (\Exception $e) {
        // log and return JSON error to client
        error_log('Exception in cargue_horas.php: ' . $e->getMessage() . " on line " . $e->getLine());
        http_response_code(500);
        json_out(['success' => false, 'message' => 'Excepción: ' . $e->getMessage()]);
    }
    
    // ===== GET MONTH SUMMARY =====
    if ($action === 'get_month_summary') {
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
        $target_matricula = get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario_normalizado, $fecha_retiro_corte);
        // Obtener el estado del mes desde horas_habiles_calendario
        $sql_estado = "SELECT estado FROM horas_habiles_calendario WHERE MONTH(fecha) = ? AND YEAR(fecha) = ? LIMIT 1";
        $stmt_estado = $mysqli->prepare($sql_estado);
        $stmt_estado->bind_param("ii", $month, $year);
        $stmt_estado->execute();
        $result_estado = $stmt_estado->get_result();
        $row_estado = $result_estado->fetch_assoc();
        $estado_mes = $row_estado && isset($row_estado['estado']) ? $row_estado['estado'] : null;
        $stmt_estado->close();
        
        // Obtener primer y último día del mes
        $first_day = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
        $last_day = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year));
        
        // Obtener todas las horas por día para el mes
        $sql = "SELECT fecha, SUM(tiempo_imputado_horas) as total 
                FROM horas_dia 
                WHERE numero_empleado = ? 
                AND fecha >= ? 
                AND fecha <= ?
                GROUP BY fecha";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sss", $target_matricula, $first_day, $last_day);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dailyTotals = [];
        while ($row = $result->fetch_assoc()) {
            $dailyTotals[$row['fecha']] = floatval($row['total']);
        }
        $stmt->close();
        
        // Verificar si ya existe un envío para este mes
        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $mes_formato = $meses[$month] . '-' . substr($year, -2);
        
        $sql_check = "SELECT id, estado FROM aprobaciones_horas 
                      WHERE numero_empleado = ? 
                      AND mes = ?";
        $stmt_check = $mysqli->prepare($sql_check);
        $stmt_check->bind_param("ss", $target_matricula, $mes_formato);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        // Si estado es 'rechazado', permitir nuevo envío. Si es 'enviado' o 'aprobado', no permitir
        $already_submitted = false;
        if ($result_check->num_rows > 0) {
            $row_check = $result_check->fetch_assoc();
            $estado = isset($row_check['estado']) ? strtolower(trim($row_check['estado'])) : '';
            // Solo marcar como submitted si el estado NO es 'rechazado'
            $already_submitted = ($estado !== 'rechazado');
        }
        $stmt_check->close();
        
        // Obtener el calendario (horas diarias permitidas) del empleado
        $sql_calendario = "SELECT horas_diarias FROM empleados WHERE matricula = ?";
        $stmt_calendario = $mysqli->prepare($sql_calendario);
        $stmt_calendario->bind_param("s", $target_matricula);
        $stmt_calendario->execute();
        $result_calendario = $stmt_calendario->get_result();
        $row_calendario = $result_calendario->fetch_assoc();
        $calendario = floatval($row_calendario['horas_diarias']) ?? 8;
        $stmt_calendario->close();

        // Obtener hh_teoricas del mes seleccionado
        $hh_teoricas = get_hh_teoricas_mes($mysqli, $month, $year);
        
        echo json_encode([
            'success' => true,
            'dailyTotals' => $dailyTotals,
            'already_submitted' => $already_submitted,
            'calendario' => $calendario,
            'hh_teoricas' => $hh_teoricas,
            'estado_mes' => $estado_mes
        ]);
        exit;
    }
    
    // ===== SUBMIT APPROVAL =====
    if ($action === 'submit_approval') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $year = isset($input['year']) ? intval($input['year']) : date('Y');
        $month = isset($input['month']) ? intval($input['month']) : date('m');
        
        // Calcular mes en formato "ene-26", "feb-26", etc.
        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $mes_formato = $meses[$month] . '-' . substr($year, -2);
        
        // Obtener primer y último día del mes
        $first_day = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
        $last_day = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $year));
        
        // Calcular horas teóricas (total de horas en el mes)
        $sql_total = "SELECT SUM(tiempo_imputado_horas) as total 
                      FROM horas_dia 
                      WHERE numero_empleado = ? 
                      AND fecha >= ? 
                      AND fecha <= ?";
        
        $stmt_total = $mysqli->prepare($sql_total);
        $stmt_total->bind_param("sss", $matricula, $first_day, $last_day);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $row_total = $result_total->fetch_assoc();
        $horas_teoricas = floatval($row_total['total']) ?? 0;
        $stmt_total->close();
        
        // Obtener el calendario (horas por día) del usuario
        $sql_calendar = "SELECT horas_diarias FROM empleados WHERE matricula = ?";
        $stmt_calendar = $mysqli->prepare($sql_calendar);
        $stmt_calendar->bind_param("s", $matricula);
        $stmt_calendar->execute();
        $result_calendar = $stmt_calendar->get_result();
        $row_calendar = $result_calendar->fetch_assoc();
        $calendario = floatval($row_calendar['horas_diarias']) ?? 8;
        $stmt_calendar->close();
        
        // Verificar si ya existe un envío para este mes
        $sql_check_dup = "SELECT id, estado FROM aprobaciones_horas 
                          WHERE numero_empleado = ? 
                          AND mes = ?";
        $stmt_check_dup = $mysqli->prepare($sql_check_dup);
        $stmt_check_dup->bind_param("ss", $matricula, $mes_formato);
        $stmt_check_dup->execute();
        $result_check_dup = $stmt_check_dup->get_result();
        
        if ($result_check_dup->num_rows > 0) {
            $row_dup = $result_check_dup->fetch_assoc();
            $estado_anterior = $row_dup['estado'];
            
            error_log("DEBUG submit_approval: mes=$mes_formato, estado_anterior=$estado_anterior, trimmed_estado=" . strtolower(trim($estado_anterior)));
            
            // Si el estado anterior fue "rechazado", permitir re-enviar (UPDATE)
            if (strtolower(trim($estado_anterior)) === 'rechazado') {
                $stmt_check_dup->close();
                
                error_log("DEBUG: Detectado estado 'rechazado', procediendo con UPDATE");
                
                // UPDATE de la aprobación rechazada
                $sql_update = "UPDATE aprobaciones_horas 
                               SET horas_teoricas = ?, 
                                   calendario = ?, 
                                   estado = 'enviado' 
                               WHERE numero_empleado = ? 
                               AND mes = ?";
                
                $stmt_update = $mysqli->prepare($sql_update);
                $stmt_update->bind_param("ddss", $horas_teoricas, $calendario, $matricula, $mes_formato);
                
                if ($stmt_update->execute()) {
                    error_log("DEBUG: UPDATE ejecutado exitosamente. Rows affected: " . $stmt_update->affected_rows);
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Horas enviadas nuevamente para aprobación',
                        'mes' => $mes_formato,
                        'horas_teoricas' => $horas_teoricas,
                        'calendario' => $calendario,
                        'debug' => [
                            'estado_anterior' => $estado_anterior,
                            'rows_affected' => $stmt_update->affected_rows
                        ]
                    ]);
                } else {
                    error_log("DEBUG: Error en UPDATE: " . $stmt_update->error);
                    echo json_encode(['success' => false, 'message' => 'Error al enviar las horas: ' . $stmt_update->error]);
                }
                $stmt_update->close();
                exit;
            } else {
                error_log("DEBUG: Estado no es 'rechazado', es: " . $estado_anterior);
                // Si el estado no es "rechazado", no permitir enviar de nuevo
                echo json_encode(['success' => false, 'message' => 'Ya se enviaron las horas de este mes']);
                $stmt_check_dup->close();
                exit;
            }
        }
        $stmt_check_dup->close();
        
        // Insertar en aprobaciones_horas (si es la primera vez)
        $sql_insert = "INSERT INTO aprobaciones_horas 
                       (numero_empleado, mes, horas_teoricas, calendario, estado) 
                       VALUES (?, ?, ?, ?, 'enviado')";
        
        $stmt_insert = $mysqli->prepare($sql_insert);
        $stmt_insert->bind_param("ssdd", $matricula, $mes_formato, $horas_teoricas, $calendario);
        
        if ($stmt_insert->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Horas enviadas para aprobación',
                'mes' => $mes_formato,
                'horas_teoricas' => $horas_teoricas,
                'calendario' => $calendario
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al enviar las horas']);
        }
        $stmt_insert->close();
        exit;
    }
    
    // ===== REJECT APPROVAL (SOLO PARA SUPER) =====
    if ($action === 'reject_approval') {
        $rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
        
        if (strtoupper(trim($rol_usuario)) !== 'SUPER') {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $target_matricula = isset($input['matricula']) ? $input['matricula'] : '';
        $year = isset($input['year']) ? intval($input['year']) : date('Y');
        $month = isset($input['month']) ? intval($input['month']) : date('m');
        
        if (empty($target_matricula)) {
            echo json_encode(['success' => false, 'message' => 'Matrícula requerida']);
            exit;
        }
        
        // Calcular mes en formato "ene-26", "feb-26", etc.
        $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $mes_formato = $meses[$month] . '-' . substr($year, -2);
        
        // Actualizar el estado a 'rechazado'
        $sql_reject = "UPDATE aprobaciones_horas 
                       SET estado = 'rechazado' 
                       WHERE numero_empleado = ? 
                       AND mes = ?";
        
        $stmt_reject = $mysqli->prepare($sql_reject);
        $stmt_reject->bind_param("ss", $target_matricula, $mes_formato);
        
        if ($stmt_reject->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Aprobación rechazada correctamente',
                'mes' => $mes_formato
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al rechazar la aprobación']);
        }
        $stmt_reject->close();
        exit;
    }
    
    // ===== SAVE COMMENT =====
    if ($action === 'save_comment') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $fecha = isset($input['fecha']) ? $input['fecha'] : '';
        $codigo_affaire = isset($input['codigo_affaire']) ? $input['codigo_affaire'] : '';
        $comentario = isset($input['comentario']) ? $input['comentario'] : '';
        
        if (!$fecha || !$codigo_affaire) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }

                $sql_lock_check = "SELECT aprobado_coordinador, aprobado_director, Estado_Aprobacion
                                                     FROM horas_dia
                                                     WHERE numero_empleado = ?
                                                         AND codigo_affaire = ?
                                                         AND fecha = ?
                                                     LIMIT 1";
                $stmt_lock_check = $mysqli->prepare($sql_lock_check);
                $stmt_lock_check->bind_param("sss", $matricula, $codigo_affaire, $fecha);
                $stmt_lock_check->execute();
                $result_lock_check = $stmt_lock_check->get_result();
                $lock_row = $result_lock_check ? $result_lock_check->fetch_assoc() : null;
                $stmt_lock_check->close();

                if ($lock_row && is_hour_record_locked($lock_row)) {
                        echo json_encode(['success' => false, 'message' => 'Esta hora ya fue aprobada y no se puede modificar']);
                        exit;
                }
        
        // Actualizar comentario en horas_dia
        $sql_update = "UPDATE horas_dia 
                       SET comentario = ? 
                       WHERE numero_empleado = ? 
                       AND codigo_affaire = ? 
                       AND fecha = ?";
        
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param("ssss", $comentario, $matricula, $codigo_affaire, $fecha);
        
        if ($stmt_update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Comentario guardado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar']);
        }
        $stmt_update->close();
        exit;
    }
    
    // ===== GET COMMENT =====
    if ($action === 'get_comment') {
        $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';
        $codigo_affaire = isset($_GET['codigo_affaire']) ? $_GET['codigo_affaire'] : '';
        $target_matricula = get_permitted_target_matricula($mysqli, $usuario, $matricula, $rol_usuario_normalizado, $fecha_retiro_corte);
        
        if (!$fecha || !$codigo_affaire) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos', 'comentario' => '']);
            exit;
        }
        
        // Obtener comentario de horas_dia
        $sql_get = "SELECT comentario FROM horas_dia 
                    WHERE numero_empleado = ? 
                    AND codigo_affaire = ? 
                    AND fecha = ?";
        
        $stmt_get = $mysqli->prepare($sql_get);
        $stmt_get->bind_param("sss", $target_matricula, $codigo_affaire, $fecha);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        
        $comentario = '';
        if ($result_get && $result_get->num_rows > 0) {
            $row_get = $result_get->fetch_assoc();
            $comentario = isset($row_get['comentario']) ? $row_get['comentario'] : '';
        }
        $stmt_get->close();
        
        echo json_encode(['success' => true, 'comentario' => $comentario]);
        exit;
    }
    
    echo json_encode(['error' => 'Acción no válida']);
    exit;
}

// ===== HTML NORMAL =====
$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Usuario';
$matricula = isset($_SESSION['matricula']) ? $_SESSION['matricula'] : '';
$rol_usuario = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
$rol_usuario_normalizado = strtoupper(trim($rol_usuario));
$is_super_user = strtoupper(trim($rol_usuario)) === 'SUPER';
$can_filter_empleado = in_array($rol_usuario_normalizado, ['SUPER', 'COORD', 'MIX', 'MIX2'], true);
$uses_area_employee_filter = in_array($rol_usuario_normalizado, ['COORD', 'MIX', 'MIX2'], true);

// Obtener nombre completo si está disponible
$nombre_completo = $usuario;
$area_usuario = '';
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) {
    include 'includes/db_connection.php';
    $sql = "SELECT Usuario, Nombre_Usuario, `Área_Funcional` FROM login_usuarios WHERE Usuario = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nombre_completo = isset($row['Nombre_Usuario']) ? $row['Nombre_Usuario'] : $usuario;
        $area_usuario = isset($row['Área_Funcional']) ? $row['Área_Funcional'] : '';
    }
    $stmt->close();
}

// Obtener límite de horas diarias del usuario (desde tabla empleados)
$limite_horas = 9; // valor por defecto
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) {
    $sql_limite = "SELECT horas_diarias FROM empleados WHERE matricula = ?";
    $stmt_limite = $mysqli->prepare($sql_limite);
    $stmt_limite->bind_param("s", $matricula);
    $stmt_limite->execute();
    $result_limite = $stmt_limite->get_result();
    if ($result_limite && $result_limite->num_rows > 0) {
        $row_limite = $result_limite->fetch_assoc();
        $limite_horas = floatval($row_limite['horas_diarias']);
    }
    $stmt_limite->close();
}

// Obtener iniciales para el avatar
$iniciales = '';
$partes = explode(' ', strtoupper($nombre_completo));
foreach (array_slice($partes, 0, 2) as $parte) {
    if (!empty($parte)) {
        $iniciales .= $parte[0];
    }
}
if (strlen($iniciales) == 1 && !empty($partes[0])) {
    $iniciales = substr($partes[0], 0, 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Horario - Control Presupuestal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --accent-900: #27594a;
            --accent-800: #2f6a56;
            --accent-700: #3fa879;
            --accent-600: #4dc18f;
            --accent-500: #63c99a;
            --accent-400: #83d7b0;
            --accent-300: #b7d7c5;
            --accent-200: #dff2e8;
            --accent-100: #edf8f2;
            --accent-050: #f2f7f4;
            --accent-surface: #e7f4ed;
            --accent-border: #bddbca;
            --accent-text: #17823d;
            --accent-text-strong: #245746;
            --accent-ring: rgba(77, 193, 143, 0.18);
            --accent-shadow: rgba(63, 168, 121, 0.16);
            --accent-shadow-strong: rgba(63, 168, 121, 0.28);
            --sidebar-bg: #24463e;
            --sidebar-bg-soft: rgba(77, 193, 143, 0.14);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f4f8f5;
            color: #0f172a;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 144px;
            background: var(--sidebar-bg);
            color: white;
            padding: 18px 0;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            box-shadow: 0 6px 18px rgba(2, 6, 23, 0.25);
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            margin-bottom: 20px;
        }

        .sidebar-header-icon {
            width: 44px;
            height: 44px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .sidebar-header-icon svg {
            width: 100%;
            height: 100%;
            stroke-width: 2;
        }

        .sidebar-header-text {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        .sidebar-logo {
            padding: 16px;
            text-align: center;
            margin: 16px 12px 20px 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            background: var(--sidebar-bg-soft);
            margin-top: auto;
        }

        .sidebar-logo-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10b981;
        }

        .sidebar-logo-icon svg {
            width: 100%;
            height: 100%;
            stroke-width: 1.5;
        }

        .sidebar-logo-text {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0px;
            opacity: 1;
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .sidebar-logo-role {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.6;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav-item {
            padding: 18px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            margin: 0 12px;
            border-radius: 10px;
        }

        .sidebar-nav-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .sidebar-nav-item.active {
            background: var(--accent-700);
            box-shadow: 0 8px 18px var(--accent-shadow-strong);
        }

        .sidebar-nav-icon {
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .icon svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Main content */
        .main {
            margin-left: 144px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        .topbar-main-title {
            font-size: 28px;
            display: inline-block;
            margin-right: 14px;
        }

        .topbar-dropdown {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 8px 12px;
            border: 1px solid transparent;
            border-radius: 6px;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            color: #1f2937;
            transition: all 0.2s;
        }

        .topbar-dropdown:hover {
            border-color: #e5e7eb;
            background: #f8fafc;
        }

        .topbar-icons {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .topbar-icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f3f4f6;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.2s;
        }

        .topbar-icon-btn:hover {
            background: #e5e7eb;
        }

        .topbar-icon-btn .icon {
            width: 20px;
            height: 20px;
            color: #334155;
        }

        .topbar-back-btn {
            height: 48px;
            padding: 0 28px;
            border: 1.5px solid var(--accent-border);
            border-radius: 12px;
            background: var(--accent-200);
            color: var(--accent-text);
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .topbar-back-btn:hover {
            background: var(--accent-100);
            border-color: var(--accent-400);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #c58b4c;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
        }

        .user-avatar:hover {
            background: #b8794a;
            box-shadow: 0 4px 12px rgba(197, 139, 76, 0.3);
        }

        .user-menu {
            position: absolute;
            top: 50px;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            min-width: 280px;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.2s ease;
        }

        .user-avatar:hover + .user-menu,
        .user-menu:hover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-menu-header {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 12px 12px 0 0;
        }

        .user-menu-name {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
            word-break: break-word;
        }

        .user-menu-email {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .user-menu-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 0;
        }

        .user-menu-items {
            padding: 8px 0;
        }

        .user-menu-item {
            display: block;
            width: 100%;
            padding: 12px 16px;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-size: 13px;
            color: #374151;
            transition: all 0.15s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-menu-item:hover {
            background: #f3f4f6;
            color: #1f2937;
        }

        .user-menu-item.danger:hover {
            background: #fee2e2;
            color: #dc2626;
        }

        .user-menu-item .icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }

        .user-avatar:hover ~ .user-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .content {
            flex: 1;
            padding: 22px;
            overflow-y: auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding: 4px 8px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }

        .kebab {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
            color: #475569;
        }

        .kebab:hover {
            background: var(--accent-100);
            border-color: #e5e7eb;
        }

        .kebab .icon {
            width: 18px;
            height: 18px;
            color: #475569;
        }

        .header-controls {
            background: var(--accent-surface);
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 30px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            border: 1px solid var(--accent-border);
        }

        .controls-left {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            flex: 1;
        }

        .btn-group {
            display: inline-flex;
            border: 1px solid #cfd8ee;
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }

        .btn-square {
            width: 44px;
            height: 40px;
            border: 0;
            border-right: 1px solid #e5e7eb;
            background: white;
            cursor: pointer;
            font-size: 18px;
            color: #334155;
        }

        .btn-square:last-child {
            border-right: 0;
        }

        .btn-square:hover {
            background: #f8fafc;
        }

        .btn-square .icon {
            width: 18px;
            height: 18px;
            color: #334155;
        }

        .btn-square:disabled {
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-soft {
            height: 40px;
            padding: 0 16px;
            border: 1px solid #cfd8ee;
            background: white;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        .btn-soft:hover {
            background: #f8fafc;
        }

        .btn-back {
            background: var(--accent-200);
            border-color: var(--accent-border);
            color: var(--accent-text);
            font-weight: 700;
        }

        .btn-back:hover {
            background: var(--accent-100);
            border-color: var(--accent-400);
        }

        .month-select {
            height: 40px;
            min-width: 300px;
            border: 1px solid #cfd8ee;
            border-radius: 12px;
            background: white;
            padding: 0 14px;
            font-size: 16px;
            font-weight: 500;
            color: #334155;
        }

        .month-select:focus {
            outline: none;
            border-color: var(--accent-600);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        /* Estilos para el dropdown de búsqueda de empleados */
        .employee-search-container {
            position: relative;
            width: min(520px, 42vw);
            margin-left: 16px;
        }

        .employee-search-input {
            height: 40px;
            width: 100%;
            min-width: 360px;
            border: 1px solid #cfd8ee;
            border-radius: 12px;
            background: white;
            padding: 0 14px;
            font-size: 16px;
            font-weight: 500;
            color: #334155;
            box-sizing: border-box;
        }

        .employee-search-input-locked {
            background: #f8fafc;
            color: #0f172a;
            cursor: default;
            caret-color: transparent;
        }

        .employee-search-input:focus {
            outline: none;
            border-color: var(--accent-600);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .employee-search-input-locked:focus {
            border-color: #cfd8ee;
            box-shadow: none;
        }

        .employee-search-input::placeholder {
            color: #a8acb5;
        }

        .employee-dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #cfd8ee;
            border-top: none;
            border-radius: 0 0 12px 12px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .employee-dropdown-list.show {
            display: block;
        }

        .employee-dropdown-item {
            padding: 12px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
            color: #334155;
        }

        .employee-dropdown-item:hover {
            background: #f1f5f9;
        }

        .employee-dropdown-item.selected {
            background: var(--accent-100);
            color: var(--accent-text);
            font-weight: 500;
        }

        .employee-dropdown-item:last-child {
            border-bottom: none;
        }

        .employee-dropdown-empty {
            padding: 12px 14px;
            text-align: center;
            color: #a8acb5;
            font-size: 14px;
        }

        .btn-primary {
            height: 40px;
            padding: 0 18px;
            background: white;
            color: #334155;
            border: 1px solid #cfd8ee;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-left: auto;
        }

        .btn-primary:hover {
            background: #f8fafc;
        }

        .weeks-container {
            display: flex;
            gap: 0;
            margin: -22px -22px 18px -22px;
            overflow: hidden;
            border-radius: 14px 14px 0 0;
            border: 1px solid var(--accent-border);
            background: var(--accent-surface);
            position: relative;
            z-index: 10;
        }

        .week-card {
            flex: 1;
            background: transparent;
            border-radius: 0;
            padding: 10px 9px;
            border: 0;
            box-shadow: none;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 72px;
            border-right: 1px solid var(--accent-border);
        }

        .week-card:last-child {
            border-right: 0;
        }

        .week-card:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .week-card.active {
            background: white;
            box-shadow: 0 8px 18px rgba(2, 6, 23, 0.08);
            position: relative;
            z-index: 1;
            border-radius: 14px;
            margin: 6px;
            border: 1px solid var(--accent-border);
        }

        .week-card h3 {
            font-family: 'Segoe UI', 'Inter', 'Helvetica Neue', Arial, sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--accent-text-strong);
            margin-bottom: 6px;
            text-transform: none;
            letter-spacing: 0;
            line-height: 1.15;
        }

        .week-card.active h3 {
            color: #0f172a;
        }

        .week-info {
            font-size: 10px;
            line-height: 1.45;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .week-info-label {
            color: #667085;
            font-weight: 700;
            letter-spacing: 0.15px;
        }

        .week-hours-display {
            font-family: 'Segoe UI', 'Inter', 'Helvetica Neue', Arial, sans-serif;
            color: #42526b;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0;
            line-height: 1.2;
        }

        .week-alert {
            display: inline-block;
            background: #fef3c7;
            color: #b45309;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
            margin-top: 6px;
        }

        .activities-card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            position: relative;
        }

        .activity-row {
            display: grid;
            grid-template-columns: 200px 1fr 40px 84px;
            gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #eef2f7;
            align-items: center;
        }

        .activity-row:first-child {
            padding-top: 0;
        }

        .activity-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .activity-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .activity-code {
            font-weight: 700;
            color: #1f2937;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .week-alert .icon {
            width: 12px;
            height: 12px;
            color: currentColor;
            display: inline-flex;
            vertical-align: middle;
        }

        .activity-description {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .info-icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: help;
        }

        .info-icon svg {
            color: #9ca3af;
            transition: color 0.2s;
        }

        .info-icon:hover svg {
            color: var(--accent-text);
        }

        .tooltip-limit {
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            pointer-events: none;
        }

        .tooltip-limit::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: #1f2937;
        }

        .hours-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
        }

        .timesheet-head {
            display: grid;
            grid-template-columns: 200px 1fr 40px 84px;
            gap: 12px;
            padding: 14px 0 10px;
            border-bottom: 1px solid #eef2f7;
            margin: 18px 0 4px 0;
            align-items: end;
        }

        .timesheet-head-left {
            min-height: 70px;
        }

        .timesheet-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
        }

        .day-card {
            border-radius: 10px;
            padding: 10px 8px 8px;
            background: white;
            border: 1px solid #e5e7eb;
            min-height: 70px;
        }

        .day-card.active {
            border-color: var(--accent-600);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .day-top {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            text-transform: lowercase;
        }

        .day-sub {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
        }

        .day-meta {
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }

        .day-meta::before {
            content: '';
            display: block;
            width: 72%;
            height: 6px;
            margin: 0 auto 4px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--accent-500), var(--accent-700));
        }

        .day-meta .ratio {
            font-size: 11px;
            color: var(--accent-text);
            font-weight: 800;
        }

        .warn-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #c2410c;
            font-weight: 800;
            font-size: 13px;
        }

        .warn-pill .icon {
            width: 15px;
            height: 15px;
            color: currentColor;
        }

        .weekend {
            background: repeating-linear-gradient(
                135deg,
                rgba(226, 232, 240, 0.75) 0px,
                rgba(226, 232, 240, 0.75) 6px,
                rgba(241, 245, 249, 0.75) 6px,
                rgba(241, 245, 249, 0.75) 12px
            );
        }

        .hour-cell {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 0;
            width: 100%;
            position: relative;
        }

        .hour-cell.rejected .hour-input {
            border-color: #ef4444;
            background: #fef2f2;
            color: #b91c1c;
            font-weight: 700;
        }

        .hour-cell.approved .hour-input {
            border-color: #22c55e;
            background: #f0fdf4;
            color: #166534;
            font-weight: 700;
            cursor: not-allowed;
        }

        .approved-badge {
            position: absolute;
            top: -6px;
            right: 16px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #16a34a;
            color: #ffffff;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            font-weight: 700;
            box-shadow: 0 0 0 2px #fff;
            pointer-events: none;
            z-index: 2;
        }

        .rejected-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #dc2626;
            color: #ffffff;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            font-weight: 700;
            box-shadow: 0 0 0 2px #fff;
            pointer-events: none;
            z-index: 2;
        }

        .comment-corner-badge {
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            border-top: 14px solid var(--accent-400);
            border-left: 14px solid transparent;
            border-top-right-radius: 4px;
            pointer-events: none;
            z-index: 1;
        }

        .hour-input {
            flex: 1;
            width: 100% !important;
            padding: 12px 6px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
            font-size: 13px;
            background: white;
            transition: all 0.2s;
            font-weight: 500;
            min-height: 44px;
        }

        .hour-cell.weekend .hour-input {
            background: transparent;
        }

        .hour-input::placeholder {
            color: #d1d5db;
            font-weight: 500;
        }

        .hour-input:focus {
            outline: none;
            border-color: var(--accent-600);
            box-shadow: 0 0 0 3px var(--accent-ring);
            background: var(--accent-050);
        }

        .hour-input:hover:not(:focus) {
            border-color: #d1d5db;
        }

        .hour-input::-webkit-outer-spin-button,
        .hour-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Contenedor de celda de hora con botón comentario */
        .comment-btn {
            display: none !important;
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
            cursor: pointer;
            transition: all 0.2s;
            align-items: center;
            justify-content: center;
        }

        .comment-btn:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .comment-btn svg {
            width: 16px;
            height: 16px;
            color: #6b7280;
        }

        .hour-input:hover ~ .comment-btn,
        .comment-btn:hover,
        .hour-input:focus ~ .comment-btn {
            display: flex !important;
        }

        .hour-cell.approved:hover .comment-btn {
            display: flex !important;
        }

        .hour-total {
            font-size: 10px;
            color: #6b7280;
            font-weight: 600;
            text-align: center;
        }

        .activity-options {
            display: flex;
            gap: 8px;
            width: 40px;
        }

        .timesheet-spacer {
            width: 40px;
        }

        .timesheet-total-head {
            width: 84px;
            text-align: center;
            font-size: 10px;
            font-weight: 800;
            color: var(--accent-text-strong);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: var(--accent-100);
            border: 1px solid var(--accent-border);
            border-radius: 10px;
            padding: 7px 5px;
            box-shadow: 0 1px 3px var(--accent-shadow);
        }

        .activity-total {
            width: 84px;
            text-align: center;
            font-size: 14px;
            font-weight: 800;
            color: var(--accent-text-strong);
            letter-spacing: -0.2px;
            background: linear-gradient(180deg, var(--accent-050) 0%, var(--accent-200) 100%);
            border: 1px solid var(--accent-border);
            border-radius: 10px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6), 0 2px 6px var(--accent-shadow);
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .btn-icon .icon {
            width: 18px;
            height: 18px;
            color: #64748b;
        }

        .add-activity {
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .btn-add-activity {
            color: var(--accent-text);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
            margin: 0 10px;
        }

        .btn-add-activity:hover {
            color: var(--accent-text-strong);
        }

        .summary-footer {
            display: none;
        }

        .summary-footer-text {
            font-size: 14px;
            color: #1f2937;
            font-weight: 500;
        }

        .summary-footer-diff {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        .warning-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fef3c7;
            color: #b45309;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .day-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 15px;
        }

        .day-header-item {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 16px;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15), 0 0 1px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            max-height: 85vh;
            overflow: hidden;
            animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 28px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fb 0%, #ffffff 100%);
            flex-shrink: 0;
        }

        .modal-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.3px;
        }

        .modal-header-icon {
            font-size: 24px;
            display: inline-flex;
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #9ca3af;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.25s ease;
            flex-shrink: 0;
        }

        .modal-close-btn:hover {
            background: #f3f4f6;
            color: #1f2937;
            transform: rotate(90deg);
        }

        .modal-close-btn:active {
            transform: rotate(90deg) scale(0.95);
        }

        .modal-body {
            padding: 28px;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        .modal-search {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
        }

        .modal-search-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            background: #f9fafb;
            transition: all 0.3s ease;
        }

        .modal-search-input::placeholder {
            color: #a8acb5;
        }

        .modal-search-input:focus {
            outline: none;
            border-color: var(--accent-600);
            background: white;
            box-shadow: 0 0 0 4px var(--accent-ring);
        }

        .project-list {
            display: flex;
            flex-direction: column;
            gap: 16px; /* Más espacio entre actividades */
            max-height: 48vh;
            overflow-y: auto;
            padding-bottom: 16px;
            padding-right: 8px;
        }

        /* Mejora visual: cada actividad (fila) */
        .project-list > * {
            padding: 12px 8px;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            border: 1px solid #f0f0f0;
            transition: background 0.15s;
        }
        .project-list > *:hover {
            background: #f5f7fa;
        }
        }

        #activityModal .modal-body {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #activityModal .project-list {
            flex: 1;
            min-height: 0;
            max-height: none;
            padding-bottom: 18px;
            overscroll-behavior: contain;
        }

        .project-item {
            padding: 16px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .project-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-600), var(--accent-700));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .project-item:hover {
            border-color: var(--accent-600);
            background: var(--accent-050);
            box-shadow: 0 4px 12px var(--accent-shadow);
            transform: translateX(4px);
        }

        .project-item:hover::before {
            transform: scaleX(1);
        }

        .project-item.selected {
            border-color: var(--accent-600);
            background: linear-gradient(135deg, var(--accent-050) 0%, var(--accent-200) 100%);
            box-shadow: 0 4px 16px var(--accent-shadow);
        }

        .project-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }

        .project-code {
            font-weight: 800;
            color: #0f172a;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.2px;
        }

        .project-code .icon {
            width: 16px;
            height: 16px;
            color: #f59e0b;
        }

        .project-name {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            line-height: 1.4;
        }

        .project-checkbox {
            width: 24px;
            height: 24px;
            border: 2.5px solid #cfd8ee;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.25s ease;
            background: white;
            margin-left: 12px;
            color: transparent;
            font-size: 14px;
            font-weight: 700;
        }

        .project-item:hover .project-checkbox {
            border-color: var(--accent-600);
            box-shadow: 0 0 0 2px var(--accent-ring);
        }

        .project-item.selected .project-checkbox {
            background: linear-gradient(135deg, var(--accent-600), var(--accent-700));
            border-color: var(--accent-600);
            color: white;
            font-weight: bold;
            box-shadow: 0 0 0 2px var(--accent-ring);
            transform: scale(1.05);
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-shrink: 0;
            background: #f9fafb;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .modal-btn {
            padding: 11px 24px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            letter-spacing: -0.2px;
            min-width: 100px;
            text-align: center;
        }

        .modal-btn-cancel {
            background: white;
            color: #475569;
            border: 1.5px solid #e2e8f0;
        }

        .modal-btn-cancel:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .modal-btn-cancel:active {
            transform: translateY(0);
        }

        .modal-btn-add {
            background: linear-gradient(135deg, var(--accent-600), var(--accent-700));
            color: white;
            border: none;
            box-shadow: 0 4px 12px var(--accent-shadow-strong);
        }

        .modal-btn-add:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--accent-700), var(--accent-800));
            box-shadow: 0 6px 16px var(--accent-shadow-strong);
            transform: translateY(-2px);
        }

        .modal-btn-add:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 2px 8px var(--accent-shadow);
        }

        .modal-btn-add:disabled {
            background: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
            font-size: 15px;
            font-weight: 500;
        }

        .empty-state .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            color: #d1d5db;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .modal-content {
                max-width: calc(100% - 32px);
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-footer {
                padding: 16px 20px;
                flex-direction: column-reverse;
            }
            
            .modal-btn {
                width: 100%;
                min-width: unset;
            }
            
            .modal-header h2 {
                font-size: 20px;
            }
        }

        @media (max-width: 1024px) {
            .weeks-container {
                border-radius: 14px;
            }

            .activity-row {
                grid-template-columns: 1fr;
            }

            .hours-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 768px) {
            .employee-search-container {
                width: 100%;
                margin-left: 0;
            }

            .employee-search-input {
                min-width: 0;
                width: 100%;
            }

            .sidebar {
                width: 60px;
                padding: 10px 0;
            }

            .sidebar-logo-text {
                font-size: 8px;
            }

            .sidebar-nav-item {
                padding: 12px 10px;
            }

            .main {
                margin-left: 60px;
            }

            .topbar {
                flex-direction: column;
                gap: 15px;
            }

            .btn-primary {
                margin-left: 0;
                width: 100%;
            }

            .header-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .controls-left {
                width: 100%;
            }

            .month-select {
                min-width: 0;
                width: 100%;
            }

            .activity-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .hours-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .footer-bar {
            position: sticky;
            bottom: 0;
            margin-top: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            background: transparent;
        }

        .footer-pill {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px 14px;
            min-width: 360px;
            box-shadow: 0 1px 3px rgba(2, 6, 23, 0.06);
            display: flex;
            gap: 10px;
            align-items: center;
            color: #0f172a;
        }

        .footer-pill .caret {
            color: #64748b;
            font-weight: 900;
            margin-right: 4px;
        }

        .footer-pill .num {
            color: var(--accent-text);
            font-weight: 900;
        }

        .footer-pill .sep {
            width: 1px;
            height: 18px;
            background: #e5e7eb;
        }

        .approve-btn {
            height: 44px;
            padding: 0 18px;
            border-radius: 12px;
            border: 1px solid var(--accent-border);
            background: var(--accent-surface);
            color: #7a9987;
            font-weight: 700;
            font-size: 14px;
            cursor: not-allowed;
            transition: all 0.3s ease;
        }

        .approve-btn:not(:disabled) {
            background: var(--accent-600);
            color: white;
            border: 1px solid var(--accent-600);
            cursor: pointer;
        }

        .approve-btn:not(:disabled):hover {
            background: var(--accent-700);
            border: 1px solid var(--accent-700);
        }

        .reject-btn {
            height: 44px;
            padding: 0 18px;
            border-radius: 12px;
            border: 1px solid #fee2e2;
            background: #fef2f2;
            color: #fca5a5;
            font-weight: 700;
            font-size: 14px;
            cursor: not-allowed;
            transition: all 0.3s ease;
        }

        .reject-btn:not(:disabled) {
            background: #ef4444;
            color: white;
            border: 1px solid #ef4444;
            cursor: pointer;
        }

        .reject-btn:not(:disabled):hover {
            background: #dc2626;
            border: 1px solid #dc2626;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-header-icon"><span class="icon" data-icon="clock" aria-hidden="true"></span></div>
            <div class="sidebar-header-text">CONTROL</div>
        </div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item active">
                <div class="sidebar-nav-icon"><span class="icon" data-icon="clock" aria-hidden="true"></span></div>
                <div>Tiempos</div>
            </li>
        </ul>
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon"><span class="icon" data-icon="user-circle" aria-hidden="true"></span></div>
            <div class="sidebar-logo-text"><?php echo htmlspecialchars($nombre_completo); ?></div>
            <div class="sidebar-logo-role"><?php echo htmlspecialchars($area_usuario); ?></div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-left">
                <div class="topbar-dropdown">
                    <strong class="topbar-main-title">Registro Horario</strong>
                    <div class="employee-search-container">
                        <input type="text" id="empleadoSearch" class="employee-search-input<?php echo $can_filter_empleado ? '' : ' employee-search-input-locked'; ?>" placeholder="<?php echo $can_filter_empleado ? 'Buscar empleado...' : ''; ?>" value="<?php echo $can_filter_empleado ? '' : htmlspecialchars(strtoupper($nombre_completo)); ?>" autocomplete="off" <?php echo $can_filter_empleado ? '' : 'readonly aria-readonly="true"'; ?>>
                        <div id="empleadoDropdown" class="employee-dropdown-list"></div>
                    </div>
                </div>
            </div>
            <div class="topbar-icons">
                <button class="topbar-back-btn" onclick="goBackByRole()" title="Regresar">← Regresar</button>
                <div class="user-avatar" title="<?php echo htmlspecialchars($nombre_completo); ?>"><?php echo htmlspecialchars($iniciales); ?></div>
                <div class="user-menu">
                    <div class="user-menu-header">
                        <div class="user-menu-name"><?php echo htmlspecialchars($nombre_completo); ?></div>
                        <div class="user-menu-email"><?php echo htmlspecialchars($usuario); ?></div>
                        <?php if (!empty($matricula)): ?>
                            <div class="user-menu-email">Matrícula: <?php echo htmlspecialchars($matricula); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Controles de navegación -->
            <div class="header-controls">
                <div class="controls-left">
                    <div class="btn-group" aria-label="Navegación">
                        <button class="btn-square" id="prevWeekBtn" onclick="previousWeek()" title="Semana anterior">‹</button>
                        <button class="btn-square" id="nextWeekBtn" onclick="nextWeek()" title="Semana siguiente">›</button>
                    </div>

                    <button class="btn-soft" onclick="goToday()">Hoy</button>

                    <div class="btn-group" aria-label="Calendario">
                        <button class="btn-square" onclick="focusDate()" title="Seleccionar fecha"><span class="icon" data-icon="calendar" aria-hidden="true"></span></button>
                    </div>

                    <select id="monthYear" class="month-select" onchange="onMonthChange()" aria-label="Mes">
                         <option value="2026-01">enero 2026</option>
                        <option value="2026-02">febrero 2026</option>
                        <option value="2026-03">marzo 2026</option> 
                      <!--  <option value="2026-04">abril 2026</option> -->
                    </select>
                    <span id="estadoMesDisplay" style="margin-left:12px; font-weight:700; color:#0ea5e9;"></span>

                    <input type="date" id="dateInput" onchange="updateView()" style="display:none;">
                </div>
            </div>

            <!-- Vista de semanas -->
            <!-- Actividades -->
            <div class="activities-card">
                <div class="weeks-container" id="weeksContainer"></div>

                <!-- Encabezado de días (como Lucca) -->
                <div class="timesheet-head">
                    <div class="timesheet-head-left"></div>
                    <div class="timesheet-days">
                        <div class="day-card">
                            <div class="day-top">lunes</div>
                            <div class="day-sub">9</div>
                            <div class="day-meta" data-day-index="0">
                                <div class="ratio">0 h 00 / <span class="limit-display">9</span> h 00</div>
                                <div class="warn-pill" style="display:none;"><span class="icon" data-icon="warn" aria-hidden="true"></span>&nbsp;1</div>
                            </div>
                        </div>
                        <div class="day-card">
                            <div class="day-top">martes</div>
                            <div class="day-sub">10</div>
                            <div class="day-meta" data-day-index="1">
                                <div class="ratio">0 h 00 / <span class="limit-display">9</span> h 00</div>
                                <div class="warn-pill" style="display:none;"><span class="icon" data-icon="warn" aria-hidden="true"></span>&nbsp;1</div>
                            </div>
                        </div>
                        <div class="day-card">
                            <div class="day-top">miércoles</div>
                            <div class="day-sub">11</div>
                            <div class="day-meta" data-day-index="2">
                                <div class="ratio">0 h 00 / <span class="limit-display">9</span> h 00</div>
                                <div class="warn-pill" style="display:none;"><span class="icon" data-icon="warn" aria-hidden="true"></span>&nbsp;1</div>
                            </div>
                        </div>
                        <div class="day-card">
                            <div class="day-top">jueves</div>
                            <div class="day-sub">12</div>
                            <div class="day-meta" data-day-index="3">
                                <div class="ratio">0 h 00 / <span class="limit-display">9</span> h 00</div>
                                <div class="warn-pill" style="display:none;"><span class="icon" data-icon="warn" aria-hidden="true"></span>&nbsp;1</div>
                            </div>
                        </div>
                        <div class="day-card">
                            <div class="day-top">viernes</div>
                            <div class="day-sub">13</div>
                            <div class="day-meta" data-day-index="4">
                                <div class="ratio">0 h 00 / <span class="limit-display">9</span> h 00</div>
                                <div class="warn-pill" style="display:none;"><span class="icon" data-icon="warn" aria-hidden="true"></span>&nbsp;1</div>
                            </div>
                        </div>
                        <div class="day-card weekend">
                            <div class="day-top">sábado</div>
                            <div class="day-sub">14</div>
                        </div>
                        <div class="day-card weekend">
                            <div class="day-top">domingo</div>
                            <div class="day-sub">15</div>
                        </div>
                    </div>
                    <div class="timesheet-spacer"></div>
                    <div class="timesheet-total-head">Total</div>
                </div>

                <!-- Lista de actividades -->
                <div id="activitiesList">
                    <!-- Las actividades se cargan dinámicamente aquí -->
                </div>

                <!-- Agregar actividad -->
                <div class="add-activity">
                    <button class="btn-add-activity" onclick="addActivity()">+ Añadir una actividad</button>
                </div>

            </div>

            <!-- Footer (como Lucca) -->
            <div class="footer-bar">
                <div class="footer-pill">
                    <span class="caret">^</span>
                    <span class="num" id="totalHoursDisplay">22 h 00</span>
                    <span style="color:#64748b; font-weight:800;">/ <span id="expectedHoursDisplay">88 h 00</span>
                        <span id="footerEstadoMesDisplay" style="display:none; margin-left:8px; font-weight:700;"></span>
                    </span>
                    <span class="sep"></span>
                    <span style="color:#334155; font-weight:800;">Diferencia: <span id="differenceDisplay" style="color:#334155;">- 66 h 00</span></span>
                </div>
                <div id="buttonContainer">
                    <button class="approve-btn" id="submitBtn" disabled style="display:none;">Enviar para aprobación</button>
                    <button class="reject-btn" id="rejectBtn" disabled style="display:none;">Rechazar Aprobación</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Actividad -->
    <div class="modal-overlay" id="activityModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="modal-header-icon">📋</span> Seleccionar Actividad</h2>
                <button class="modal-close-btn" onclick="closeActivityModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-search">
                    <input type="text" class="modal-search-input" id="projectSearch" placeholder="Buscar por proyecto o código...">
                </div>
                <div class="project-list" id="projectList">
                    <!-- Los proyectos se cargan aquí dinámicamente -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeActivityModal()">Cancelar</button>
                <button class="modal-btn modal-btn-add" id="addSelectedBtn" onclick="addSelectedProjects()" disabled>+ Agregar Seleccionados</button>
            </div>
        </div>
    </div>

    <!-- Modal Error -->
    <div class="modal-overlay" id="errorModal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header" style="border-bottom: 1px solid #fee2e2;">
                <h2 style="color: #dc2626;"><span class="modal-header-icon">⚠️</span> Error</h2>
                <button class="modal-close-btn" onclick="closeErrorModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="errorMessage" style="color: #374151; margin: 0; font-size: 15px; line-height: 1.6; font-weight: 500;"></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeErrorModal()" style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; font-weight: 700;">Entendido</button>
            </div>
        </div>
    </div>

    <!-- Modal Éxito -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header" style="border-bottom: 1px solid #dcfce7;">
                <h2 style="color: #16a34a;"><span class="modal-header-icon">✓</span> Éxito</h2>
                <button class="modal-close-btn" onclick="closeSuccessModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="successMessage" style="color: #374151; margin: 0; font-size: 15px; line-height: 1.6; font-weight: 500;"></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeSuccessModal()" style="background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; font-weight: 700;">Aceptar</button>
            </div>
        </div>
    </div>

    <!-- Modal Comentarios -->
    <div class="modal-overlay" id="commentModal">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--accent-200);">
                <h2 style="color: var(--accent-text);"><span class="modal-header-icon">💬</span> Agregar Comentario</h2>
                <button class="modal-close-btn" onclick="closeCommentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <textarea id="commentText" placeholder="Escribe tu comentario aquí..." style="width: 100%; height: 140px; padding: 14px; border: 2px solid #e5e7eb; border-radius: 12px; font-family: inherit; font-size: 14px; resize: none; transition: all 0.3s ease; background: #f9fafb;" onFocus="this.style.borderColor='var(--accent-600)'; this.style.background='white'" onBlur="this.style.borderColor='#e5e7eb'; this.style.background='#f9fafb'"></textarea>
                <p id="commentInfo" style="margin-top: 16px; font-size: 13px; color: #64748b; font-weight: 600; letter-spacing: -0.2px;"></p>
            </div>
            <div class="modal-footer">
                <button class="modal-btn modal-btn-cancel" onclick="closeCommentModal()">Cancelar</button>
                <button id="saveCommentBtn" class="modal-btn modal-btn-add" onclick="saveComment()" style="background: linear-gradient(135deg, var(--accent-600), var(--accent-700)); box-shadow: 0 4px 12px var(--accent-shadow-strong);">💾 Guardar Comentario</button>
            </div>
        </div>
    </div>

    <script>
        // Límite de horas diarias del usuario desde la BD
        const MAX_HOURS_PER_DAY = <?php echo $limite_horas; ?>;
        
        // Festivales No laborales en Colombia 2026
        const HOLIDAYS_2026 = [
            '2026-01-01', // Año Nuevo
            '2026-02-01', // Festivo
            '2026-03-01', // Festivo
            '2026-03-23', // Festivo
            '2026-04-02', // Festivo
            '2026-04-03', // Festivo
            '2026-05-01', // Día del Trabajo
            '2026-05-18', // Festivo
            '2026-06-08', // Festivo
            '2026-06-15', // Festivo
            '2026-06-29', // Festivo
            '2026-07-20', // Grito de Independencia
            '2026-07-26', // Festivo
            '2026-08-07', // Batalla de Boyacá
            '2026-08-17', // Asunción de María
            '2026-10-12', // Día de la Raza
            '2026-11-02', // Todos los Santos
            '2026-11-16', // Independencia de Cartagena
            '2026-12-08', // Inmaculada Concepción
            '2026-12-25'  // Navidad
        ];
        
        // Función para verificar si una fecha es festivo
        function isHoliday(date) {
            const dateStr = date.toISOString().split('T')[0];
            return HOLIDAYS_2026.includes(dateStr);
        }
        
        // Variables globales para el modal de comentarios
        let commentData = { day: null, activityId: null };
        
        let currentDate = new Date();
        let selectedProjects = new Set();
        let isAddingProjects = false;
        const UNLIMITED_ACTIVITY_CODES = new Set(['9300006', '9300008', '9300002', '9300066', '9300011']);
        const UNLIMITED_ACTIVITY_NAMES = [
            'COMERCIAL',
            'REINVERSIÓN',
            'VACACIONES / (CGP) CONGES PAYES',
            'TARDES EN FAMILIA / (CPA) CONGES PARENTAUX',
            'LICENCIA REMUNERADA REMUNERADA / (ABA) ABS. AUTORISEE REMUNEREE',
            'DDC - DÍA DE CUMPLEAÑOS',
            'INCAPACIDAD POR ENFERMEDAD / (MAL) MALADIE'
        ];
        const DEFAULT_MODAL_ACTIVITIES = [
            { centro_costos: '9300006', nombre_proyecto: 'VACACIONES / (CGP) CONGES PAYES' },
            { centro_costos: '9300008', nombre_proyecto: 'TARDES EN FAMILIA / (CPA) CONGES PARENTAUX' },
            { centro_costos: '9300002', nombre_proyecto: 'LICENCIA REMUNERADA REMUNERADA / (ABA) ABS. AUTORISEE REMUNEREE' },
            { centro_costos: '9300066', nombre_proyecto: 'DDC - DÍA DE CUMPLEAÑOS' },
            { centro_costos: '9300011', nombre_proyecto: 'INCAPACIDAD POR ENFERMEDAD / (MAL) MALADIE' }
        ];

        function normalizeProjectCode(value) {
            return String(value || '').trim();
        }

        function isUnlimitedActivity(projectCode, projectName = '') {
            const normalizedCode = normalizeProjectCode(projectCode);
            if (normalizedCode && UNLIMITED_ACTIVITY_CODES.has(normalizedCode)) {
                return true;
            }

            const normalizedName = String(projectName || '').trim().toUpperCase();
            if (!normalizedName) {
                return false;
            }

            return UNLIMITED_ACTIVITY_NAMES.some(name => normalizedName.includes(name.toUpperCase()));
        }

        function mergeDefaultModalActivities(projects) {
            const sourceProjects = Array.isArray(projects) ? projects : [];
            const mergedProjects = [];
            const seenCodes = new Set();

            // Si el usuario está filtrando por texto, no anteponer las actividades por defecto
            try {
                const searchInput = document.getElementById('projectSearch');
                if (!searchInput || String(searchInput.value || '').trim() === '') {
                    DEFAULT_MODAL_ACTIVITIES.forEach(defaultProject => {
                        const defaultCode = normalizeProjectCode(defaultProject.centro_costos);
                        const existingProject = sourceProjects.find(project => normalizeProjectCode(project && project.centro_costos) === defaultCode);

                        mergedProjects.push(existingProject
                            ? {
                                ...existingProject,
                                centro_costos: defaultCode,
                                nombre_proyecto: String(existingProject.nombre_proyecto || '').trim() || defaultProject.nombre_proyecto
                            }
                            : { ...defaultProject }
                        );

                        seenCodes.add(defaultCode);
                    });
                }
            } catch (e) {
                // si falla la lectura del DOM, continuar sin insertar defaults
                console.warn('mergeDefaultModalActivities: could not read search input', e);
            }

            sourceProjects.forEach(project => {
                const projectCode = normalizeProjectCode(project && project.centro_costos);
                if (!projectCode || seenCodes.has(projectCode)) {
                    return;
                }

                mergedProjects.push(project);
                seenCodes.add(projectCode);
            });

            return mergedProjects;
        }

        function formatLocalDateKey(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }

        function getTempActivitiesStorageKey() {
            const weekKey = formatLocalDateKey(getWeekStartMonday(currentDate));
            const matriculaKey = (typeof selectedMatricula !== 'undefined' && selectedMatricula)
                ? selectedMatricula
                : (typeof currentUserMatricula !== 'undefined' ? currentUserMatricula : '');
            return `gca_temp_activities:${matriculaKey}:${weekKey}`;
        }

        function getTempActivitiesSet() {
            try {
                const raw = sessionStorage.getItem(getTempActivitiesStorageKey());
                if (!raw) return new Set();
                const parsed = JSON.parse(raw);
                if (!Array.isArray(parsed)) return new Set();
                return new Set(parsed.map(code => String(code || '').trim()).filter(Boolean));
            } catch (e) {
                return new Set();
            }
        }

        function saveTempActivitiesSet(tempSet) {
            try {
                sessionStorage.setItem(getTempActivitiesStorageKey(), JSON.stringify(Array.from(tempSet)));
            } catch (e) {
                console.warn('No se pudo guardar actividades temporales:', e);
            }
        }

        // Storage for per-activity temporary metadata (e.g. selected subcentro)
        function getTempActivitiesMetaStorageKey() {
            return getTempActivitiesStorageKey() + ':meta';
        }

        function getTempActivitiesMeta() {
            try {
                const raw = sessionStorage.getItem(getTempActivitiesMetaStorageKey());
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') return {};
                return parsed;
            } catch (e) {
                return {};
            }
        }

            // Storage for full temporary activity objects (to support multiple subcentros per proyecto)
            function getTempActivitiesObjectsStorageKey() {
                return getTempActivitiesStorageKey() + ':objects';
            }

            function getTempActivityObjects() {
                try {
                    const raw = sessionStorage.getItem(getTempActivitiesObjectsStorageKey());
                    if (!raw) return [];
                    const parsed = JSON.parse(raw);
                    if (!Array.isArray(parsed)) return [];
                    return parsed;
                } catch (e) {
                    return [];
                }
            }

            function saveTempActivityObjects(list) {
                try {
                    sessionStorage.setItem(getTempActivitiesObjectsStorageKey(), JSON.stringify(Array.isArray(list) ? list : []));
                } catch (e) {
                    console.warn('No se pudo guardar objetos temporales:', e);
                }
            }

            function addTempActivityObject(obj) {
                if (!obj || !obj.codigo_affaire) return;
                const list = getTempActivityObjects();
                // Ensure unique id
                if (!obj.id) obj.id = 'temp-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
                list.push(obj);
                saveTempActivityObjects(list);
            }

            function removeTempActivityObjectById(id) {
                if (!id) return;
                let list = getTempActivityObjects();
                list = list.filter(x => String(x.id) !== String(id));
                saveTempActivityObjects(list);
            }

        function saveTempActivitiesMeta(metaObj) {
            try {
                sessionStorage.setItem(getTempActivitiesMetaStorageKey(), JSON.stringify(metaObj || {}));
            } catch (e) {
                console.warn('No se pudo guardar metadata temporal:', e);
            }
        }

        function saveTempActivityMeta(code, meta) {
            if (!code) return;
            const key = String(code || '').trim();
            if (!key) return;
            const all = getTempActivitiesMeta();
            all[key] = Object.assign({}, all[key] || {}, meta || {});
            saveTempActivitiesMeta(all);
        }

        function getTempActivityMeta(code) {
            if (!code) return null;
            const all = getTempActivitiesMeta();
            return all[String(code || '').trim()] || null;
        }

        function escapeHtml(unsafe) {
            return String(unsafe || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function addTempActivities(codes) {
            const tempSet = getTempActivitiesSet();
            (codes || []).forEach(code => {
                const normalized = String(code || '').trim();
                if (normalized) tempSet.add(normalized);
            });
            saveTempActivitiesSet(tempSet);
        }

        function removeTempActivity(code) {
            const normalized = String(code || '').trim();
            if (!normalized) return;
            const tempSet = getTempActivitiesSet();
            if (tempSet.delete(normalized)) {
                saveTempActivitiesSet(tempSet);
            }
        }

        function clearTempActivitiesForCurrentView() {
            try {
                sessionStorage.removeItem(getTempActivitiesStorageKey());
            } catch (e) {
                console.warn('No se pudo limpiar actividades temporales:', e);
            }
        }

        window.addEventListener('beforeunload', clearTempActivitiesForCurrentView);

        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').classList.add('active');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('active');
        }

        function showSuccessModal(message) {
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').classList.add('active');
        }

        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }

        function showCommentModal(day, activityId) {
            commentData = { day, activityId };
            const input = document.querySelector(`.hour-input[data-day="${day}"][data-activity-id="${activityId}"]`);
            const codigo = input ? input.dataset.codigo : '';
            const nombre = input ? input.dataset.nombre : '';
            const isApprovedHour = !!input && String(input.dataset.approved || '0') === '1';
            const isOtherEmployee = canFilterEmpleado && selectedMatricula !== currentUserMatricula;
            
            document.getElementById('commentInfo').textContent = `${codigo} - ${nombre} - Día ${day + 1}`;
            
            // Calcular la fecha correcta
            const weekStart = getWeekStartMonday(currentDate);
            
            const fecha = new Date(weekStart);
            fecha.setDate(fecha.getDate() + day);
            const fechaFormato = fecha.toISOString().split('T')[0];
            
            // Obtener comentario existente
            const matriculaParam = getMatriculaParam();
            fetch('cargue_horas_Ajuste_2.php?action=get_comment&fecha=' + fechaFormato + '&codigo_affaire=' + encodeURIComponent(codigo) + matriculaParam)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('commentText').value = data.comentario || '';
                    } else {
                        document.getElementById('commentText').value = '';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('commentText').value = '';
                });
            
            // Si el mes está inactivo, mostrar comentario en modo solo lectura
            const saveBtn = document.getElementById('saveCommentBtn');
            if ((window.estadoMesGlobal && window.estadoMesGlobal.toLowerCase() === 'inactivo') || isApprovedHour || isOtherEmployee) {
                document.getElementById('commentText').readOnly = true;
                if (saveBtn) saveBtn.disabled = true;
            } else {
                document.getElementById('commentText').readOnly = false;
                if (saveBtn) saveBtn.disabled = false;
            }

            document.getElementById('commentModal').classList.add('active');
        }

        function closeCommentModal() {
            document.getElementById('commentModal').classList.remove('active');
            commentData = { day: null, activityId: null };
        }

        function saveComment() {
            const comentario = document.getElementById('commentText').value;
            const { day, activityId } = commentData;
            
            if (!day && day !== 0) return;
            
            // Calcular la fecha correcta
            const weekStart = getWeekStartMonday(currentDate);
            
            const fecha = new Date(weekStart);
            fecha.setDate(fecha.getDate() + day);
            const fechaFormato = fecha.toISOString().split('T')[0];
            
            const input = document.querySelector(`.hour-input[data-day="${day}"][data-activity-id="${activityId}"]`);
            if (!input) return;

            if (String(input.dataset.approved || '0') === '1') {
                showErrorModal('Esta hora ya fue aprobada y no se puede modificar');
                return;
            }

            const codigo_affaire = input.dataset.codigo;
            
            fetch('cargue_horas_Ajuste_2.php?action=save_comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    fecha: fechaFormato,
                    codigo_affaire: codigo_affaire,
                    comentario: comentario
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setCommentMarker(day, activityId, (comentario || '').trim() !== '');
                    closeCommentModal();
                    showSuccessModal('Comentario guardado correctamente');
                } else {
                    showErrorModal('Error al guardar comentario');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error al guardar comentario');
            });
        }

        function setCommentMarker(day, activityId, hasComment) {
            const input = document.querySelector(`.hour-input[data-day="${day}"][data-activity-id="${activityId}"]`);
            if (!input) return;
            const hourCell = input.closest('.hour-cell');
            if (!hourCell) return;

            const existing = hourCell.querySelector('.comment-corner-badge');
            if (hasComment) {
                if (!existing) {
                    const badge = document.createElement('span');
                    badge.className = 'comment-corner-badge';
                    badge.title = 'Tiene comentario';
                    hourCell.appendChild(badge);
                }
            } else if (existing) {
                existing.remove();
            }
        }

        const ICONS = {
            brand: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M4 12a8 8 0 1 0 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M4 12a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.35"/>
                    <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `,
            clock: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `,
            chart: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M4 19V5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M4 19h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M7 15l4-4 3 3 5-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `,
            bell: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M6 9a6 6 0 0 1 12 0c0 7 3 7 3 7H3s3 0 3-7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            `,
            kebab: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 5.25a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5Z" fill="currentColor"/>
                    <path d="M12 10.25a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5Z" fill="currentColor"/>
                    <path d="M12 15.25a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5Z" fill="currentColor"/>
                </svg>
            `,
            calendar: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M7 3v3M17 3v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M4 8h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M6 5h12a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M8 12h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            `,
            warn: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 3 1.8 20.5a1 1 0 0 0 .9 1.5h18.6a1 1 0 0 0 .9-1.5L12 3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M12 9v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 17.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
            `,
            star: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 3.5l2.7 5.7 6.3.9-4.6 4.5 1.1 6.3L12 18.7 6.5 20.9l1.1-6.3L3 10.1l6.3-.9L12 3.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
            `,
            gear: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M19.4 15a7.9 7.9 0 0 0 .1-1 7.9 7.9 0 0 0-.1-1l2-1.5-2-3.5-2.4 1a7.8 7.8 0 0 0-1.7-1l-.4-2.6H11l-.4 2.6a7.8 7.8 0 0 0-1.7 1l-2.4-1-2 3.5 2 1.5a7.9 7.9 0 0 0-.1 1 7.9 7.9 0 0 0 .1 1l-2 1.5 2 3.5 2.4-1a7.8 7.8 0 0 0 1.7 1l.4 2.6h4l.4-2.6a7.8 7.8 0 0 0 1.7-1l2.4 1 2-3.5-2-1.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                </svg>
            `,
            clipboard: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M9 4h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M9 4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M9 6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.35"/>
                </svg>
            `,
            'user-circle': `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 22a10 10 0 1 0-10-10 10 10 0 0 0 10 10Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 12a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M6 20a4 4 0 0 1 12 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            `,
            lock: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M8 10V6a4 4 0 0 1 8 0v4" stroke="currentColor" stroke-width="2"/>
                    <path d="M6 10h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 14v3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            `,
            trash: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m0 0v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            `,
            logout: `
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 8l4 4m0 0l-4 4m4-4H9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            `
        };

        function mountIcons(root = document) {
            root.querySelectorAll('[data-icon]').forEach((node) => {
                if (node.getAttribute('data-icon-mounted') === '1') return;
                const name = node.getAttribute('data-icon');
                const svg = ICONS[name];
                if (!svg) return;
                node.innerHTML = svg;
                node.setAttribute('data-icon-mounted', '1');
            });
        }

        // Mejorar interacción del dropdown en móviles
        document.addEventListener('DOMContentLoaded', function() {
            const userAvatar = document.querySelector('.user-avatar');
            const userMenu = document.querySelector('.user-menu');
            
            if (userAvatar && userMenu) {
                // Toggle del dropdown con click
                userAvatar.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userMenu.style.opacity = userMenu.style.opacity === '1' ? '0' : '1';
                    userMenu.style.visibility = userMenu.style.visibility === 'visible' ? 'hidden' : 'visible';
                });
                
                // Cerrar al hacer click afuera
                document.addEventListener('click', function() {
                    userMenu.style.opacity = '0';
                    userMenu.style.visibility = 'hidden';
                });
                
                // Mantener abierto si se hace click dentro del menu
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });

        function previousWeek() {
            const candidate = new Date(currentDate);
            candidate.setDate(candidate.getDate() - 7);
            currentDate = clampDateToAllowedMonthRange(candidate);
            updateView();
        }

        function nextWeek() {
            const candidate = new Date(currentDate);
            candidate.setDate(candidate.getDate() + 7);
            currentDate = clampDateToAllowedMonthRange(candidate);
            updateView();
        }

        function goToday() {
            currentDate = clampDateToAllowedMonthRange(new Date());
            updateView();
        }

        function goBackByRole() {
            if ((rolUsuario || '').trim() === 'USER') {
                window.location.href = 'Colaborador.php';
            } else {
                window.location.href = 'Balance.php';
            }
        }

        function getMonthNameFromNumber(monthNumber) {
            const months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            return months[Math.max(0, Math.min(11, monthNumber - 1))];
        }

        function getAllowedMonthRange() {
            const monthSelect = document.getElementById('monthYear');
            if (!monthSelect) return null;

            const values = Array.from(monthSelect.options)
                .map(option => (option.value || '').trim())
                .filter(value => /^\d{4}-\d{2}$/.test(value))
                .sort();

            if (values.length === 0) return null;

            const [minYear, minMonth] = values[0].split('-').map(Number);
            const [maxYear, maxMonth] = values[values.length - 1].split('-').map(Number);

            const minDate = new Date(minYear, minMonth - 1, 1);
            const maxDate = new Date(maxYear, maxMonth, 0);
            minDate.setHours(0, 0, 0, 0);
            maxDate.setHours(0, 0, 0, 0);

            return { minDate, maxDate };
        }

        function clampDateToAllowedMonthRange(date) {
            const source = new Date(date);
            source.setHours(0, 0, 0, 0);

            const range = getAllowedMonthRange();
            if (!range) return source;

            if (source < range.minDate) return new Date(range.minDate);
            if (source > range.maxDate) return new Date(range.maxDate);
            return source;
        }

        function updateNavigationButtonsState() {
            const prevBtn = document.getElementById('prevWeekBtn');
            const nextBtn = document.getElementById('nextWeekBtn');
            if (!prevBtn || !nextBtn) return;

            const range = getAllowedMonthRange();
            if (!range) {
                prevBtn.disabled = false;
                nextBtn.disabled = false;
                return;
            }

            const current = new Date(currentDate);
            current.setHours(0, 0, 0, 0);
            prevBtn.disabled = current <= range.minDate;
            nextBtn.disabled = current >= range.maxDate;
        }

        function getWeekStartMonday(baseDate) {
            const monday = new Date(baseDate);
            const day = monday.getDay();
            const offsetToMonday = day === 0 ? -6 : (1 - day);
            monday.setDate(monday.getDate() + offsetToMonday);
            return monday;
        }

        function isDateOutsideSelectedMonth(date) {
            return date.getFullYear() !== currentDate.getFullYear() || date.getMonth() !== currentDate.getMonth();
        }

        function isCurrentMonthInactive() {
            return !!(window.estadoMesGlobal && window.estadoMesGlobal.toLowerCase() === 'inactivo');
        }

        function ensureMonthOptionExists(year, month) {
            const monthSelect = document.getElementById('monthYear');
            if (!monthSelect) return;

            const value = `${year}-${String(month).padStart(2, '0')}`;
            const exists = Array.from(monthSelect.options).some(option => option.value === value);
            if (!exists) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = `${getMonthNameFromNumber(month)} ${year}`;
                monthSelect.appendChild(option);
            }
        }

        function setDefaultActiveMonth() {
            return fetch('cargue_horas_Ajuste_2.php?action=get_active_month')
                .then(response => response.json())
                .then(data => {
                    const year = data && data.year ? parseInt(data.year) : new Date().getFullYear();
                    const month = data && data.month ? parseInt(data.month) : (new Date().getMonth() + 1);

                    const monthSelect = document.getElementById('monthYear');
                    if (monthSelect) {
                        const targetValue = `${year}-${String(month).padStart(2, '0')}`;
                        const exists = Array.from(monthSelect.options).some(option => option.value === targetValue);
                        if (exists) {
                            monthSelect.value = targetValue;
                        }

                        const selectedValue = (monthSelect.value || '').trim();
                        if (/^\d{4}-\d{2}$/.test(selectedValue)) {
                            const [selectedYear, selectedMonth] = selectedValue.split('-').map(Number);
                            const dayToUse = Math.min(currentDate.getDate(), new Date(selectedYear, selectedMonth, 0).getDate());
                            currentDate = new Date(selectedYear, selectedMonth - 1, dayToUse);
                        }
                    }
                })
                .catch(() => {
                    const monthSelect = document.getElementById('monthYear');
                    if (monthSelect && monthSelect.options.length > 0) {
                        const selectedValue = (monthSelect.value || monthSelect.options[0].value || '').trim();
                        if (/^\d{4}-\d{2}$/.test(selectedValue)) {
                            const [selectedYear, selectedMonth] = selectedValue.split('-').map(Number);
                            const dayToUse = Math.min(currentDate.getDate(), new Date(selectedYear, selectedMonth, 0).getDate());
                            currentDate = new Date(selectedYear, selectedMonth - 1, dayToUse);
                        }
                    }
                });
        }

        function updateView() {
            currentDate = clampDateToAllowedMonthRange(currentDate);
            document.getElementById('dateInput').valueAsDate = currentDate;
            
            // Actualizar el dropdown del mes si cambió
            const yearMonth = currentDate.getFullYear() + '-' + String(currentDate.getMonth() + 1).padStart(2, '0');
            const monthSelect = document.getElementById('monthYear');
            if (monthSelect.value !== yearMonth) {
                monthSelect.value = yearMonth;
            }

            updateNavigationButtonsState();
            
            renderWeeks();
            updateDayHeaders();
            loadActivities();
            mountIcons();
        }

        function updateDayHeaders() {
            // Calcular el lunes de la semana actual
            const weekStart = getWeekStartMonday(currentDate);
            
            // Hoy para comparación (sin horas)
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            const dayNames = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
            const dayCards = document.querySelectorAll('.day-card');
            
            dayCards.forEach((card, index) => {
                const dayDate = new Date(weekStart);
                dayDate.setDate(dayDate.getDate() + index);
                const isOutsideSelectedMonth = isDateOutsideSelectedMonth(dayDate);
                
                const dayNum = dayDate.getDate();
                const daySub = card.querySelector('.day-sub');
                if (daySub) {
                    daySub.textContent = dayNum;
                }

                const dayMeta = card.querySelector('.day-meta');
                if (dayMeta) {
                    dayMeta.style.display = isOutsideSelectedMonth || isCurrentMonthInactive() ? 'none' : '';
                }
                
                // Verificar si es fin de semana o festivo
                const isWeekend = index >= 5;
                const isHolidayDay = isHoliday(dayDate);
                
                // Resaltar el día actual
                dayDate.setHours(0, 0, 0, 0);
                if (dayDate.getTime() === today.getTime()) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
                
                // Marcar como weekend si es festivo
                if (isHolidayDay && !isWeekend) {
                    card.classList.add('weekend');
                } else if (!isWeekend) {
                    card.classList.remove('weekend');
                }
            });
            
            // Actualizar límites y advertencias
            updateLimitDisplay();
            updateDayWarnings();
        }

        function updateLimitDisplay() {
            const dayMetas = document.querySelectorAll('[data-day-index]');
            dayMetas.forEach((meta, index) => {
                const limitDisplay = meta.querySelector('.limit-display');
                if (limitDisplay) {
                    limitDisplay.textContent = Math.floor(getDayLimitByIndex(index));
                }
            });
        }

        function getDayLimitByIndex(dayIndex) {
            // Regla especial: con calendario de 9h, viernes máximo 8h
            if (Math.floor(MAX_HOURS_PER_DAY) === 9 && dayIndex === 4) {
                return 8;
            }
            return MAX_HOURS_PER_DAY;
        }

        function updateDayWarnings() {
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to start of day
            
            const weekStart = getWeekStartMonday(currentDate);
            
            const dayMetas = document.querySelectorAll('[data-day-index]');
            dayMetas.forEach((meta, index) => {
                const dayDate = new Date(weekStart);
                dayDate.setDate(dayDate.getDate() + index);
                dayDate.setHours(0, 0, 0, 0);
                const isOutsideSelectedMonth = isDateOutsideSelectedMonth(dayDate);
                
                const warnPill = meta.querySelector('.warn-pill');
                
                // Verificar si es fin de semana o festivo
                const isWeekend = index >= 5;
                const isHolidayDay = isHoliday(dayDate);
                
                // Solo mostrar advertencia si el día ya pasó y es laboral (no fin de semana ni festivo)
                if (!isCurrentMonthInactive() && !isOutsideSelectedMonth && dayDate < today && !isWeekend && !isHolidayDay) {
                    // Calcular horas totales del día
                    const dayInputs = document.querySelectorAll(`.hour-input[data-day="${index}"]`);
                    let totalHours = 0;
                    dayInputs.forEach(input => {
                        totalHours += parseFloat(input.value) || 0;
                    });
                    
                    // Mostrar advertencia si no se completó el límite
                    const maxHoursForDay = getDayLimitByIndex(index);
                    if (totalHours < maxHoursForDay) {
                        warnPill.style.display = 'inline-block';
                    } else {
                        warnPill.style.display = 'none';
                    }
                } else {
                    warnPill.style.display = 'none';
                }
            });
            
            // Actualizar estado del botón de aprobación
            updateApproveButton();
        }

        function updateApproveButton() {
            const submitBtn = document.getElementById('submitBtn');
            const rejectBtn = document.getElementById('rejectBtn');

            // Ocultar temporalmente el botón de envío para todos los usuarios finales
            if (submitBtn) {
                submitBtn.style.display = 'none';
                submitBtn.disabled = true;
            }
            
            // Obtener el mes actual de currentDate
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1; // 1-based month
            
            // Si está viendo a otro empleado
            const isViewingOtherEmployee = selectedMatricula !== currentUserMatricula;
            
            if (isSuper && isViewingOtherEmployee) {
                // Mostrar botón de rechazar
                if (rejectBtn) rejectBtn.style.display = '';
                
                // Hacer un AJAX call para verificar si está aprobado
                const matriculaParam = '&matricula=' + encodeURIComponent(selectedMatricula);
                fetch('cargue_horas_Ajuste_2.php?action=check_month_approval&year=' + year + '&month=' + month + matriculaParam)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Response de check_month_approval:', data);
                        if (rejectBtn) {
                            // Habilitar si existe un envío (is_approved = true significa que existe y no está rechazado)
                            rejectBtn.disabled = !data.is_approved;
                            console.log('Reject button disabled:', rejectBtn.disabled, 'is_approved:', data.is_approved);
                        }
                    })
                    .catch(error => {
                        console.error('Error actualizando botón de rechazo:', error);
                        if (rejectBtn) rejectBtn.disabled = true;
                    });
            } else {
                // Mantener oculto botón de enviar para aprobación
                if (rejectBtn) rejectBtn.style.display = 'none';

                if (isViewingOtherEmployee) {
                    if (submitBtn) submitBtn.disabled = true;
                    return;
                }
                
                // Hacer un AJAX call para obtener el resumen del mes
                const matriculaParam = getMatriculaParam();
                fetch('cargue_horas_Ajuste_2.php?action=get_month_summary&year=' + year + '&month=' + month + matriculaParam)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.dailyTotals) {
                            let allDaysCompleted = true;
                            
                            // Iterar sobre todos los días del mes
                            const firstDay = new Date(year, month - 1, 1);
                            const lastDay = new Date(year, month, 0);
                            
                            for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
                                const dayOfWeek = d.getDay(); // 0=domingo, 1=lunes, ..., 6=sábado
                                
                                // Solo verificar lunes a viernes (dias 1-5)
                                if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                                    const dayDate = d.toISOString().split('T')[0];
                                    const dayHours = data.dailyTotals[dayDate] || 0;
                                    
                                    // Si algún día del mes no completó el límite, el botón no se habilita
                                    if (dayHours < MAX_HOURS_PER_DAY) {
                                        allDaysCompleted = false;
                                        break;
                                    }
                                }
                            }
                            
                            // Habilitar/deshabilitar el botón
                            // Se habilita SOLO si: todos los días están completos AND no se ha enviado aún
                            if (submitBtn) {
                                const can_submit = allDaysCompleted && !data.already_submitted;
                                submitBtn.disabled = !can_submit;
                            }
                        }
                    })
                    .catch(error => console.error('Error actualizando botón de aprobación:', error));
            }
        }

        function onMonthChange() {
            const monthYearValue = document.getElementById('monthYear').value; // Ej: "2026-02"
            const [year, month] = monthYearValue.split('-');
            
            // Obtener el día actual (o el último día del mes si no existe en ese mes)
            const dayToUse = Math.min(currentDate.getDate(), new Date(parseInt(year), parseInt(month), 0).getDate());
            
            // Establecer la fecha manteniendo el mismo día dentro del mes
            currentDate = new Date(parseInt(year), parseInt(month) - 1, dayToUse);
            updateView();
        }

        function checkAndLockMonthIfApproved() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1;
            const matriculaParam = getMatriculaParam();
            
            fetch(`cargue_horas_Ajuste_2.php?action=check_month_approval&year=${year}&month=${month}${matriculaParam}`)
                .then(response => response.json())
                .then(data => {
                    // Si el mes está marcado como 'inactivo' en el calendario, bloquear edición (vista sólo)
                    if (window.estadoMesGlobal && window.estadoMesGlobal.toLowerCase() === 'inactivo') {
                        // Deshabilitar inputs (mostrar valores pero no permitir edición)
                        document.querySelectorAll('.hour-input').forEach(input => {
                            input.disabled = true;
                        });

                        // Permitir abrir el modal de comentarios aunque el mes esté 'inactivo'
                        // (El guardado se controla en `showCommentModal` deshabilitando el botón de guardar)

                        // Deshabilitar añadir actividad
                        const addAct = document.querySelector('.btn-add-activity');
                        if (addAct) {
                            addAct.disabled = true;
                            addAct.style.opacity = '0.5';
                            addAct.style.cursor = 'not-allowed';
                        }

                        // Deshabilitar botones de eliminar/acciones
                        document.querySelectorAll('.btn-icon').forEach(btn => btn.disabled = true);

                        // Deshabilitar botones de envío/rechazo
                        const submitBtn = document.getElementById('submitBtn');
                        const rejectBtn = document.getElementById('rejectBtn');
                        if (submitBtn) submitBtn.disabled = true;
                        if (rejectBtn) rejectBtn.disabled = true;

                        return; // salir: no ejecutar la lógica de aprobación
                    }
                    if (data.is_approved) {
                        // Ocultar inputs y mostrar solo los números
                        document.querySelectorAll('.hour-input').forEach(input => {
                            let value = input.value || '0';
                            value = parseFloat(value);
                            
                            // Si es un número entero, no mostrar decimales
                            let displayValue = value % 1 === 0 ? value.toString() : value.toFixed(2).replace(/\.?0+$/, '');
                            displayValue += 'h';
                            
                            const span = document.createElement('span');
                            span.style.cssText = 'display: flex; align-items: center; justify-content: center; width: 100%; padding: 8px 12px; font-weight: 500; color: #374151; text-align: center;';
                            span.textContent = displayValue;
                            input.style.display = 'none';
                            input.parentElement.insertBefore(span, input);
                        });
                        
                        // Ocultar botones de comentario
                        document.querySelectorAll('.comment-btn').forEach(btn => {
                            btn.style.display = 'none';
                        });
                        
                        // Bloquear botón de agregar actividad
                        const addActivityBtn = document.querySelector('.btn-add-activity');
                        if (addActivityBtn) {
                            addActivityBtn.disabled = true;
                        }
                        
                        // Bloquear botones de eliminar actividad
                        document.querySelectorAll('.btn-icon').forEach(btn => {
                            btn.disabled = true;
                        });
                        
                        // Bloquear botones de aprobación/rechazo
                        const submitBtn = document.getElementById('submitBtn');
                        const rejectBtn = document.getElementById('rejectBtn');
                        const isViewingOtherEmployee = isSuper && selectedMatricula !== currentUserMatricula;
                        
                        if (submitBtn) {
                            submitBtn.disabled = true;
                        }
                        // Solo deshabilitar rejectBtn si NO es SUPER viendo a otro empleado
                        // Si es SUPER viendo otro empleado, el rejectBtn debe mantenerse habilitado si hay un envío
                        if (rejectBtn && !isViewingOtherEmployee) {
                            rejectBtn.disabled = true;
                        }
                    } else {
                        // Desbloquear todo si el mes NO está aprobado
                        
                        // Mostrar inputs normalmente
                        document.querySelectorAll('.hour-input').forEach(input => {
                            input.style.display = '';
                            // Remover los spans que se crearon para mostrar solo números
                            const prevSpan = input.previousElementSibling;
                            if (prevSpan && prevSpan.tagName === 'SPAN') {
                                prevSpan.remove();
                            }
                        });
                        
                        // Mostrar botones de comentario
                        document.querySelectorAll('.comment-btn').forEach(btn => {
                            btn.style.display = '';
                        });
                        
                        // Desbloquear botón de agregar actividad
                        const addActivityBtn = document.querySelector('.btn-add-activity');
                        if (addActivityBtn) {
                            addActivityBtn.disabled = false;
                            addActivityBtn.style.opacity = '';
                            addActivityBtn.style.cursor = '';
                        }
                        
                        // Desbloquear botones de eliminar actividad
                        document.querySelectorAll('.btn-icon').forEach(btn => {
                            btn.disabled = false;
                        });
                        
                        // Actualizar estado de los botones según el contexto
                        updateApproveButton();
                    }
                })
                .catch(error => console.error('Error verificando aprobación del mes:', error));
        }

        function loadActivities() {
            const fecha = currentDate.toISOString().split('T')[0];
            const matriculaParam = getMatriculaParam();
            
            fetch('cargue_horas_Ajuste_2.php?action=get_actividades&fecha=' + fecha + matriculaParam)
                .then(response => response.json())
                .then(data => {
                    renderActivities(data);
                    // Actualizar advertencias, ratios, resumen y horas de semana después de cargar actividades
                    setTimeout(() => {
                        updateDayRatios();
                        updateDayWarnings();
                        updateMonthSummary();
                        updateWeekDisplay(); // Se ejecuta asíncronamente
                        checkAndLockMonthIfApproved(); // Verificar si el mes está aprobado
                    }, 100);
                })
                .catch(error => {
                    console.error('Error cargando actividades:', error);
                    document.getElementById('activitiesList').innerHTML = '<p>Error al cargar actividades</p>';
                });
        }

        function formatRowHours(hours) {
            const safeHours = parseFloat(hours) || 0;
            const h = Math.floor(safeHours);
            const m = Math.round((safeHours % 1) * 60);
            return `${h} h ${String(m).padStart(2, '0')}`;
        }

        function updateActivityRowTotal(rowElement) {
            if (!rowElement) return;
            const inputs = rowElement.querySelectorAll('.hour-input[data-day]');
            let total = 0;
            inputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });

            const totalCell = rowElement.querySelector('.activity-total');
            if (totalCell) {
                totalCell.textContent = formatRowHours(total);
            }
        }

        function renderActivities(actividades) {
            const container = document.getElementById('activitiesList');
            const selectedYear = currentDate.getFullYear();
            const selectedMonth = currentDate.getMonth();
            const tempActivities = getTempActivitiesSet();
            const actividadesFiltradas = (Array.isArray(actividades) ? actividades : []).filter(actividad => {
                const codigo = String((actividad && actividad.codigo_affaire) || '').trim();
                const hasPersistedActivity = Number((actividad && actividad.id) || 0) > 0;
                const hasHours = Array.isArray(actividad && actividad.horas)
                    && actividad.horas.some(valor => parseFloat(valor) > 0);
                return hasPersistedActivity || hasHours || (codigo !== '' && tempActivities.has(codigo));
            });
            
            if (actividadesFiltradas.length === 0) {
                container.innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">No hay actividades. Haz clic en "Añadir una actividad" para agregar una.</p>';
                return;
            }

            container.innerHTML = '';

            actividadesFiltradas.forEach(actividad => {
                const canDeleteActivity = Number(actividad.id) > 0;
                const recordFlags = Array.isArray(actividad.tiene_registro) ? actividad.tiene_registro : [];
                const rejectedFlags = Array.isArray(actividad.rechazado) ? actividad.rechazado : [];
                const commentFlags = Array.isArray(actividad.comentado) ? actividad.comentado : [];
                const approvedFlags = Array.isArray(actividad.aprobado) ? actividad.aprobado : [];
                const hasApprovedHours = approvedFlags.some(flag => !!flag);
                const row = document.createElement('div');
                row.className = 'activity-row';
                row.id = 'activity-' + actividad.id;

                let hoursHtml = '';
                actividad.horas.forEach((horas, index) => {
                    const isWeekend = index >= 5;
                    // Calcular la fecha del día
                    const weekStart = getWeekStartMonday(currentDate);
                    const dayDate = new Date(weekStart);
                    dayDate.setDate(dayDate.getDate() + index);
                    const isHolidayDay = isHoliday(dayDate);
                    const isOutsideSelectedMonth = dayDate.getFullYear() !== selectedYear || dayDate.getMonth() !== selectedMonth;
                    const hasHourRecord = !!recordFlags[index] && !isOutsideSelectedMonth;
                    const isApproved = !!approvedFlags[index] && !isOutsideSelectedMonth;
                    const isRejected = !!rejectedFlags[index] && !isOutsideSelectedMonth;
                    const hasComment = !!commentFlags[index] && !isOutsideSelectedMonth;
                    const isDisabledByCalendar = isWeekend || isHolidayDay || isOutsideSelectedMonth;
                    const isDisabled = isDisabledByCalendar || isApproved;
                    const horaValue = (!isOutsideSelectedMonth && (parseFloat(horas) > 0 || hasHourRecord)) ? horas : '';
                    hoursHtml += `
                        <div class="hour-cell ${isDisabledByCalendar ? 'weekend' : ''} ${isRejected ? 'rejected' : ''} ${isApproved ? 'approved' : ''}">
                            <input type="number" class="hour-input" placeholder="0" min="0" max="${MAX_HOURS_PER_DAY}" step="0.5" 
                                   data-day="${index}" data-activity-id="${actividad.id}" data-codigo="${actividad.codigo_affaire}" data-nombre="${actividad.nombre_proyect}" data-approved="${isApproved ? '1' : '0'}" value="${horaValue}"${isDisabled ? ' disabled' : ''}>
                            ${hasComment ? '<span class="comment-corner-badge" title="Tiene comentario"></span>' : ''}
                            ${isApproved ? '<span class="approved-badge" title="Hora aprobada">✓</span>' : ''}
                            ${isRejected ? '<span class="rejected-badge" title="Rechazado por coordinador">✕</span>' : ''}
                            ${!isDisabledByCalendar ? `<button class=\"comment-btn\" title=\"Agregar comentario\" data-day=\"${index}\" data-activity-id=\"${actividad.id}\"><svg style=\"width: 16px; height: 16px;\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M7 8h10M7 12h4m1 8l-4-2H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-3l-4 2z\"></path></svg></button>` : ''}
                        </div>
                    `;
                });

                row.innerHTML = `
                    <div class="activity-info">
                        <div class="activity-code">
                            ${actividad.codigo_affaire}
                        </div>
                        <div class="activity-description">
                            ${actividad.nombre_proyect}
                            <span class="info-icon" data-codigo="${actividad.codigo_affaire}" data-nombre="${actividad.nombre_proyect}" title="Horas asignadas">
                                <svg style="width: 16px; height: 16px; display: inline; margin-left: 4px; cursor: help;" fill="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                    <text x="12" y="16" text-anchor="middle" font-size="12" font-weight="bold" fill="currentColor">i</text>
                                </svg>
                                <div class="tooltip-limit" style="display: none;"></div>
                            </span>
                        </div>
                    </div>
                    <div class="hours-grid">
                        ${hoursHtml}
                    </div>
                    <div class="activity-options">
                        <button class="btn-icon" title="Eliminar" onclick="deleteActivity(${actividad.id})" ${canDeleteActivity && !hasApprovedHours ? '' : 'disabled style="opacity:0.45; cursor:not-allowed;"'}>
                            <span class="icon" data-icon="trash" aria-hidden="true"></span>
                        </button>
                    </div>
                    <div class="activity-total">0 h 00</div>
                `;

                container.appendChild(row);

                // Mostrar subcentro seleccionado (si existe) dentro de la descripción de la actividad
                try {
                    const descEl = row.querySelector('.activity-description');
                    let displayCodSub = '';
                    let displayNombreSub = '';
                    // Preferir valores por día en actividad (si vienen de la BD)
                    if (Array.isArray(actividad.cod_sub_ceco) && actividad.cod_sub_ceco.length > 0) {
                        // tomar el primer no vacío
                        for (let i = 0; i < actividad.cod_sub_ceco.length; i++) {
                            if (actividad.cod_sub_ceco[i]) { displayCodSub = actividad.cod_sub_ceco[i]; break; }
                        }
                    }
                    if (Array.isArray(actividad.nombre_sub_ceco) && actividad.nombre_sub_ceco.length > 0) {
                        for (let i = 0; i < actividad.nombre_sub_ceco.length; i++) {
                            if (actividad.nombre_sub_ceco[i]) { displayNombreSub = actividad.nombre_sub_ceco[i]; break; }
                        }
                    }
                    // Fallback a metadata temporal (cuando la actividad fue agregada desde el modal)
                    if (!displayCodSub || !displayNombreSub) {
                        const meta = getTempActivityMeta(actividad.codigo_affaire);
                        if (meta) {
                            if (!displayCodSub && meta.cod_sub_ceco) displayCodSub = meta.cod_sub_ceco;
                            if (!displayNombreSub && meta.nombre_sub_ceco) displayNombreSub = meta.nombre_sub_ceco;
                        }
                    }
                    if (descEl && (displayCodSub || displayNombreSub)) {
                        const badge = document.createElement('div');
                        badge.className = 'subcentro-badge';
                        badge.style.marginTop = '6px';
                        badge.style.fontSize = '12px';
                        badge.style.color = '#555';
                        badge.textContent = (displayCodSub ? displayCodSub + ' | ' : '') + (displayNombreSub || '');
                        descEl.appendChild(badge);
                    }
                } catch (e) { console.warn('No se pudo mostrar subcentro en actividad:', e); }

                updateActivityRowTotal(row);

                // Agregar atributos de subcentro a los inputs y event listeners (no bloquear por estado 'inactivo')
                const inputs = row.querySelectorAll('.hour-input');
                inputs.forEach(input => {
                    const idx = parseInt(input.dataset.day, 10);
                    let codSub = '';
                    let nombreSub = '';
                    if (Array.isArray(actividad.cod_sub_ceco) && actividad.cod_sub_ceco[idx]) {
                        codSub = actividad.cod_sub_ceco[idx];
                    }
                    if (Array.isArray(actividad.nombre_sub_ceco) && actividad.nombre_sub_ceco[idx]) {
                        nombreSub = actividad.nombre_sub_ceco[idx];
                    }
                    // Fallback a metadata temporal si existe (cuando la actividad fue agregada desde el modal con selección de subcentro)
                    if (!codSub) {
                        const meta = getTempActivityMeta(actividad.codigo_affaire);
                        if (meta && meta.cod_sub_ceco) codSub = meta.cod_sub_ceco;
                        if (meta && meta.nombre_sub_ceco) nombreSub = meta.nombre_sub_ceco;
                    }
                    if (codSub) input.setAttribute('data-cod-sub-ceco', codSub);
                    if (nombreSub) input.setAttribute('data-nombre-sub-ceco', nombreSub);

                    // Validar en tiempo real que no exceda el límite
                    // (evento input/change se mantiene como antes)
                    // Validar en tiempo real que no exceda el límite
                    input.addEventListener('input', function() {
                    // Validar en tiempo real que no exceda el límite
                        const dayIndex = parseInt(this.dataset.day, 10);
                        const maxHoursForDay = getDayLimitByIndex(dayIndex);
                        if (parseFloat(this.value) > maxHoursForDay) {
                            this.value = maxHoursForDay;
                        }
                        updateActivityRowTotal(row);
                    });
                    input.addEventListener('change', function() {
                        updateActivityRowTotal(row);
                        saveHours(this);
                    });
                });
            });

            // Agregar event listeners a los botones de comentarios
            const commentBtns = container.querySelectorAll('.comment-btn');
            commentBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const day = parseInt(this.dataset.day);
                    const activityId = parseInt(this.dataset.activityId);
                    showCommentModal(day, activityId);
                });
            });

            // Asegurar que el botón de añadir actividad esté habilitado
            const addActivityBtn = document.querySelector('.btn-add-activity');
            if (addActivityBtn) {
                addActivityBtn.disabled = false;
                addActivityBtn.style.opacity = '';
                addActivityBtn.style.cursor = '';
            }

            // Añadir tooltip dinámico para el icono de info en cada fila
            container.querySelectorAll('.info-icon').forEach(icon => {
                const tooltip = icon.querySelector('.tooltip-limit');
                icon.addEventListener('mouseenter', function() {
                    const codigo = this.dataset.codigo;
                    const nombre = this.dataset.nombre || '';
                    const ano = currentDate.getFullYear();
                    const mes = currentDate.getMonth() + 1;
                    const matriculaParam = getMatriculaParam();

                    fetch(`cargue_horas_Ajuste_2.php?action=get_project_limit&codigo_affaire=${encodeURIComponent(codigo)}&nombre_affaire=${encodeURIComponent(nombre)}&fecha=${ano}-${String(mes).padStart(2, '0')}-01${matriculaParam}`)
                        .then(response => response.json())
                        .then(data => {
                            const esExcepcion = !!data.es_ilimitado || isUnlimitedActivity(codigo, nombre);

                            let limitText = '';
                            if (esExcepcion) {
                                limitText = 'Ilimitado';
                            } else if (data.horas_asignadas > 0) {
                                limitText = `${data.horas_asignadas} horas`;
                            } else {
                                limitText = 'Sin asignar';
                            }

                            if (tooltip) {
                                tooltip.textContent = limitText;
                                tooltip.style.display = 'block';
                            }
                        })
                        .catch(error => {
                            console.error('Error obteniendo límite:', error);
                            if (tooltip) {
                                tooltip.textContent = 'Error al cargar';
                                tooltip.style.display = 'block';
                            }
                        });
                });

                icon.addEventListener('mouseleave', function() {
                    if (tooltip) tooltip.style.display = 'none';
                });
            });

            mountIcons(container);
        }
        function deleteActivity(activityId) {
            // Bloquear para usuarios SUPER viendo a otro empleado
            const isOtherEmployee = selectedMatricula !== currentUserMatricula;
            if (canFilterEmpleado && isOtherEmployee) {
                showErrorModal('No puedes eliminar actividades del calendario de otros empleados');
                return;
            }

            const activityRow = document.getElementById('activity-' + activityId);
            const hasApprovedHours = activityRow && activityRow.querySelector('.hour-input[data-approved="1"]');
            if (hasApprovedHours) {
                showErrorModal('Esta actividad contiene horas aprobadas y no se puede modificar');
                return;
            }

            const firstInput = activityRow ? activityRow.querySelector('.hour-input') : null;
            const activityCode = firstInput ? (firstInput.dataset.codigo || '') : '';
            
            if (!confirm('¿Eliminar esta actividad?')) return;

            const weekStart = getWeekStartMonday(currentDate);
            const weekStartLocal = `${weekStart.getFullYear()}-${String(weekStart.getMonth() + 1).padStart(2, '0')}-${String(weekStart.getDate()).padStart(2, '0')}`;
            const selectedMonth = currentDate.getMonth() + 1;
            const selectedYear = currentDate.getFullYear();

            fetch('cargue_horas_Ajuste_2.php?action=delete_activity', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    activity_id: activityId,
                    week_start: weekStartLocal,
                    selected_month: selectedMonth,
                    selected_year: selectedYear
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    removeTempActivity(activityCode);
                    loadActivities();
                    setTimeout(() => {
                        updateDayWarnings();
                        updateWeekDisplay();
                    }, 100);
                } else {
                    let debugInfo = '';
                    if (data.debug) {
                        const inicio = data.debug.semana_inicio || data.debug.lunes || '';
                        const fin = data.debug.semana_fin || data.debug.domingo || '';
                        const fechas = Array.isArray(data.debug.fechas) ? data.debug.fechas.join(', ') : '';
                        debugInfo = '\n[Depuración]\nEmpleado: ' + (data.debug.empleado || '') + '\nAffaire: ' + (data.debug.affaire || '') + '\nInicio semana: ' + inicio + '\nFin semana: ' + fin + (fechas ? '\nFechas: ' + fechas : '');
                    }
                    showErrorModal('Error al eliminar: ' + (data.message || '') + debugInfo);
                }
            })
            .catch(error => showErrorModal('Error de red: ' + error));
        }

        function getTotalHoursForDay(dayIndex) {
            // Obtener todos los inputs para un día específico
            const inputs = document.querySelectorAll(`.hour-input[data-day="${dayIndex}"]`);
            let total = 0;
            inputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                total += value;
            });
            return total;
        }

        function getTotalWeekHours(weekStart, weekEnd) {
            let totalHours = 0;
            
            // Iterar por cada día índice (0-6)
            for (let dayIndex = 0; dayIndex < 7; dayIndex++) {
                // Obtener todos los inputs de este día y sumar
                const dayInputs = document.querySelectorAll(`.hour-input[data-day="${dayIndex}"]`);
                dayInputs.forEach(input => {
                    totalHours += parseFloat(input.value) || 0;
                });
            }
            
            return totalHours;
        }

        async function getTotalWeekHoursAsync(rangeStart, rangeEnd) {
            try {
                const matriculaParam = getMatriculaParam();
                const response = await fetch(`cargue_horas_Ajuste_2.php?action=get_week_hours&start=${encodeURIComponent(rangeStart)}&end=${encodeURIComponent(rangeEnd)}${matriculaParam}`);
                const data = await response.json();
                return data.total || 0;
            } catch (error) {
                console.error('Error obteniendo horas de la semana:', error);
                return 0;
            }
        }

        function formatWeekHourDisplay(registeredHours, expectedHours) {
            const h = Math.floor(registeredHours);
            const m = Math.round((registeredHours % 1) * 60);
            const registered = h.toString().padStart(1, '0') + ' h ' + m.toString().padStart(2, '0');
            const expected = expectedHours.toFixed(0) + ' h 00';
            return `${registered} / ${expected}`;
        }

        async function updateWeekDisplay() {
            // Actualizar TODAS las semanas, no solo la activa
            const weekCards = document.querySelectorAll('.week-card');
            
            for (const card of weekCards) {
                const display = card.querySelector('.week-hours-display');
                if (display && display.dataset.expectedHours) {
                    const visibleStart = (card.dataset.visibleStart || '').trim();
                    const visibleEnd = (card.dataset.visibleEnd || '').trim();
                    if (visibleStart && visibleEnd) {
                        // Obtener horas de la base de datos solo para el tramo visible del mes
                        const registeredHours = await getTotalWeekHoursAsync(visibleStart, visibleEnd);
                        const expectedHours = parseFloat(display.dataset.expectedHours);
                        
                        display.textContent = formatWeekHourDisplay(registeredHours, expectedHours);
                    }
                }
            }
        }

        function updateDayRatios() {
            const dayMetas = document.querySelectorAll('[data-day-index]');
            dayMetas.forEach((meta, index) => {
                const ratioDiv = meta.querySelector('.ratio');
                if (ratioDiv) {
                    const weekStart = getWeekStartMonday(currentDate);
                    const dayDate = new Date(weekStart);
                    dayDate.setDate(dayDate.getDate() + index);
                    const isOutsideSelectedMonth = isDateOutsideSelectedMonth(dayDate);

                    if (isOutsideSelectedMonth || isCurrentMonthInactive()) {
                        ratioDiv.textContent = '';
                        return;
                    }

                    const totalHours = getTotalHoursForDay(index);
                    const limitDisplay = meta.querySelector('.limit-display')?.textContent || '9';
                    const hoursFormatted = totalHours.toFixed(0).padStart(1, '0') + ' h ' + String((totalHours % 1 * 60).toFixed(0)).padStart(2, '0');
                    ratioDiv.innerHTML = `${hoursFormatted} / <span class="limit-display">${limitDisplay}</span> h 00`;
                }
            });
        }

        function updateMonthSummary() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1; // 1-based month
            const matriculaParam = getMatriculaParam();
            
            fetch('cargue_horas_Ajuste_2.php?action=get_month_summary&year=' + year + '&month=' + month + matriculaParam)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let totalHours = 0;
                        
                        // Sumar todas las horas del mes
                        Object.values(data.dailyTotals).forEach(hours => {
                            totalHours += parseFloat(hours) || 0;
                        });
                        
                        // Usar hh_teoricas del backend como total esperado
                        const totalExpected = parseFloat(data.hh_teoricas) || 0;
                        const difference = totalHours - totalExpected;
                        
                        // Formatear números
                        const formatHours = (hours) => {
                            const h = Math.floor(hours);
                            const m = Math.round((hours % 1) * 60);
                            return h.toString().padStart(2, '0') + ' h ' + m.toString().padStart(2, '0');
                        };
                        
                        // Actualizar el display
                        document.getElementById('totalHoursDisplay').textContent = formatHours(totalHours);
                        document.getElementById('expectedHoursDisplay').textContent = formatHours(totalExpected);

                        // Mostrar el estado del mes (arriba)
                        const estadoMesDisplay = document.getElementById('estadoMesDisplay');
                        const footerEstadoMesDisplay = document.getElementById('footerEstadoMesDisplay');
                        if (data.estado_mes) {
                            const texto = String(data.estado_mes).trim();
                            const estado = texto.toLowerCase();
                            let color = '#0ea5e9';
                            let bg = '#e0f2fe';
                            let border = '#bae6fd';
                            let icono = 'ℹ️';

                            if (estado === 'activo') {
                                color = '#15803d';
                                bg = '#dcfce7';
                                border = '#bbf7d0';
                                icono = '✅';
                            } else if (estado === 'inactivo') {
                                color = '#b91c1c';
                                bg = '#fee2e2';
                                border = '#fecaca';
                                icono = '🔒';
                            } else if (estado === 'aprobado') {
                                color = '#22c55e';
                                bg = '#dcfce7';
                                border = '#bbf7d0';
                                icono = '✅';
                            } else if (estado === 'rechazado') {
                                color = '#ef4444';
                                bg = '#fee2e2';
                                border = '#fecaca';
                                icono = '⛔';
                            } else if (estado === 'enviado') {
                                color = '#f59e42';
                                bg = '#fff7ed';
                                border = '#fed7aa';
                                icono = '📤';
                            }

                            estadoMesDisplay.textContent = `Estado: ${icono} ${texto}`;
                            estadoMesDisplay.style.color = color;
                            estadoMesDisplay.style.backgroundColor = bg;
                            estadoMesDisplay.style.border = `1px solid ${border}`;
                            estadoMesDisplay.style.padding = '6px 10px';
                            estadoMesDisplay.style.borderRadius = '999px';
                            estadoMesDisplay.style.display = 'inline-flex';
                            estadoMesDisplay.style.alignItems = 'center';
                            estadoMesDisplay.style.gap = '6px';
                            // Exponer estado global para otros controles si es necesario
                            window.estadoMesGlobal = texto;
                            if (footerEstadoMesDisplay) {
                                footerEstadoMesDisplay.textContent = `(${icono} ${texto})`;
                                footerEstadoMesDisplay.style.color = color;
                                footerEstadoMesDisplay.style.backgroundColor = bg;
                                footerEstadoMesDisplay.style.border = `1px solid ${border}`;
                                footerEstadoMesDisplay.style.padding = '4px 8px';
                                footerEstadoMesDisplay.style.borderRadius = '999px';
                                footerEstadoMesDisplay.style.display = 'none';
                                footerEstadoMesDisplay.style.alignItems = 'center';
                            }
                        } else {
                            estadoMesDisplay.textContent = '';
                            estadoMesDisplay.style.backgroundColor = '';
                            estadoMesDisplay.style.border = '';
                            estadoMesDisplay.style.padding = '';
                            estadoMesDisplay.style.borderRadius = '';
                            estadoMesDisplay.style.display = '';
                            estadoMesDisplay.style.alignItems = '';
                            window.estadoMesGlobal = null;
                            if (footerEstadoMesDisplay) {
                                footerEstadoMesDisplay.textContent = '';
                                footerEstadoMesDisplay.style.backgroundColor = '';
                                footerEstadoMesDisplay.style.border = '';
                                footerEstadoMesDisplay.style.padding = '';
                                footerEstadoMesDisplay.style.borderRadius = '';
                                footerEstadoMesDisplay.style.display = 'none';
                                footerEstadoMesDisplay.style.alignItems = '';
                            }
                        }

                        // Después de actualizar el estado del mes, asegurar bloqueo/desbloqueo correcto
                        if (typeof checkAndLockMonthIfApproved === 'function') {
                            checkAndLockMonthIfApproved();
                        }

                        const differenceElement = document.getElementById('differenceDisplay');
                        const differenceText = (difference >= 0 ? '+ ' : '- ') + formatHours(Math.abs(difference));
                        differenceElement.textContent = differenceText;

                        // Cambiar color según si falta o sobra
                        if (difference >= 0) {
                            differenceElement.style.color = '#22c55e'; // Verde
                        } else {
                            differenceElement.style.color = '#ef4444'; // Rojo
                        }
                    }
                })
                .catch(error => console.error('Error al obtener resumen del mes:', error));
        }

        function saveHours(inputElement) {
            if (String(inputElement.dataset.approved || '0') === '1') {
                showErrorModal('Esta hora ya fue aprobada y no se puede modificar');
                return;
            }

            const rawValue = String(inputElement.value || '').trim();
            const horas = rawValue === '' ? 0 : parseFloat(rawValue);

            if (rawValue === '' || horas === 0) {
                inputElement.value = '0';
            }

            if (Number.isNaN(horas) || horas < 0) {
                showErrorModal('Ingresa un valor de horas válido');
                inputElement.value = '0';
                return;
            }
            
            const day = parseInt(inputElement.dataset.day);
            
            // Prevenir guardar horas en fin de semana (sábado=5, domingo=6)
            if (day >= 5) {
                showErrorModal('No se pueden registrar horas en sábados y domingos');
                inputElement.value = '';
                return;
            }
            
            // Calcular la fecha correcta
            const weekStart = getWeekStartMonday(currentDate);
            
            const fecha = new Date(weekStart);
            fecha.setDate(fecha.getDate() + day);
            const fechaFormato = fecha.toISOString().split('T')[0];
            
            // Prevenir guardar horas en festivos
            if (isHoliday(fecha)) {
                showErrorModal('No se pueden registrar horas en días festivos');
                inputElement.value = '';
                return;
            }
            
            // Validar que la suma total del día no exceda el límite
            const totalHorasDelDia = getTotalHoursForDay(day);
            const maxHoursForDay = getDayLimitByIndex(day);
            if (totalHorasDelDia > maxHoursForDay) {
                showErrorModal(`No se pueden registrar más de ${maxHoursForDay} horas por día. Total actual: ${totalHorasDelDia} horas`);
                inputElement.value = '';
                return;
            }
            
            const codigo_affaire = inputElement.dataset.codigo;
            const nombre_affaire = inputElement.dataset.nombre;

            if (horas === 0) {
                saveHoursToDatabase(inputElement, 0, fechaFormato, codigo_affaire, nombre_affaire);
                return;
            }
            
            // Validar que no exceda el límite de horas asignadas al proyecto
            const matriculaParam = getMatriculaParam();
            fetch(`cargue_horas_Ajuste_2.php?action=get_project_limit&codigo_affaire=${encodeURIComponent(codigo_affaire)}&nombre_affaire=${encodeURIComponent(nombre_affaire)}&fecha=${fechaFormato}${matriculaParam}`)
                .then(response => response.json())
                .then(projectData => {
                    if (projectData.error) {
                        showErrorModal('Error al obtener límite del proyecto');
                        inputElement.value = '';
                        return;
                    }
                    
                    const horasDisponibles = projectData.horas_disponibles;
                    const horasAsignadas = projectData.horas_asignadas;
                    const horasRegistradas = projectData.horas_registradas;
                    
                    const esExcepcion = !!projectData.es_ilimitado || isUnlimitedActivity(codigo_affaire, nombre_affaire);
                    
                    // Si no es un proyecto de excepción, validar que tenga horas asignadas
                    if (!esExcepcion && horasAsignadas <= 0) {
                        showErrorModal(`No hay horas asignadas para este proyecto en el mes seleccionado.\n\nLímite del mes: ${horasAsignadas} horas`);
                        inputElement.value = '';
                        return;
                    }
                    
                    // Verificar si se va a exceder el límite (solo si tiene horas asignadas)
                    if (!esExcepcion && horasAsignadas > 0 && (horasRegistradas + horas) > horasAsignadas) {
                        showErrorModal(
                            `El total de horas ingresadas (${horasRegistradas + horas} h) supera el límite permitido para este proyecto (${horasAsignadas} h).\n\n` +
                            `Por favor, revise y ajuste las horas antes de continuar.`
                        );
                        inputElement.value = '';
                        return;
                    }
                    
                    // Si pasó todas las validaciones, guardar
                    saveHoursToDatabase(inputElement, horas, fechaFormato, codigo_affaire, nombre_affaire);
                })
                .catch(error => {
                    console.error('Error validando límite:', error);
                    showErrorModal('Error al validar límite de proyecto');
                    inputElement.value = '';
                });
        }

        function saveHoursToDatabase(inputElement, horas, fechaFormato, codigo_affaire, nombre_affaire) {
            fetch('cargue_horas_Ajuste_2.php?action=save_hours', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    horas: horas,
                    fecha: fechaFormato,
                    codigo_affaire: codigo_affaire,
                        nombre_affaire: nombre_affaire,
                        cod_sub_ceco: inputElement.getAttribute('data-cod-sub-ceco') || '',
                        nombre_sub_ceco: inputElement.getAttribute('data-nombre-sub-ceco') || ''
                })
            })
            .then(response => {
                // siempre intentar leer texto para diagnosticar fallos
                return response.text().then(text => {
                    if (!response.ok) {
                        console.error('Servidor devolvió estado', response.status, response.statusText);
                        console.error('Respuesta del servidor:', text);
                        throw new Error('HTTP ' + response.status);
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Respuesta no válida JSON:', text);
                        throw e;
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    console.log('Horas guardadas correctamente');
                    removeTempActivity(codigo_affaire);
                    // Opcional: cambiar el color del input para indicar que se guardó
                    inputElement.style.backgroundColor = '#e8f5e9';
                    setTimeout(() => {
                        inputElement.style.backgroundColor = '';
                    }, 1000);
                    // Actualizar advertencias, ratios, resumen y horas de semana después de guardar
                    updateDayRatios();
                    updateDayWarnings();
                    updateWeekDisplay();
                    updateMonthSummary();
                } else {
                    console.error('Error al guardar horas:', data);
                    if (data.debug) {
                        console.log('DEBUG INFO:', data.debug);
                    }
                    showErrorModal('Error: ' + (data.message || 'No se pudieron guardar las horas'));
                    inputElement.value = Number(horas) === 0 ? '0' : '';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error al guardar');
                if (Number(horas) === 0) {
                    inputElement.value = '0';
                }
            });
        }

        function getVisibleWeekRangeWithinMonth(start, end, selectedYear, selectedMonth) {
            const firstDayOfMonth = new Date(selectedYear, selectedMonth, 1);
            const lastDayOfMonth = new Date(selectedYear, selectedMonth + 1, 0);
            const visibleStart = start < firstDayOfMonth ? new Date(firstDayOfMonth) : new Date(start);
            const visibleEnd = end > lastDayOfMonth ? new Date(lastDayOfMonth) : new Date(end);

            if (visibleStart > visibleEnd) {
                return null;
            }

            return { visibleStart, visibleEnd };
        }

        function countBusinessDaysInRange(start, end) {
            let businessDays = 0;

            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const dayOfWeek = d.getDay();
                if (dayOfWeek >= 1 && dayOfWeek <= 5 && !isHoliday(d)) {
                    businessDays++;
                }
            }

            return businessDays;
        }

        function renderWeeks() {
            const container = document.getElementById('weeksContainer');
            container.innerHTML = '';

            // Obtener el mes de currentDate (no del dropdown)
            const selectedYear = currentDate.getFullYear();
            const selectedMonth = currentDate.getMonth(); // JavaScript months are 0-indexed

            // Primer día del mes
            const firstDay = new Date(selectedYear, selectedMonth, 1);
            
            // Último día del mes
            const lastDay = new Date(selectedYear, selectedMonth + 1, 0);

            // Obtener lunes de la semana del primer día del mes
            const firstMonday = new Date(firstDay);
            firstMonday.setDate(firstMonday.getDate() - (firstMonday.getDay() === 0 ? 6 : firstMonday.getDay() - 1));

            // Obtener domingo de la semana del último día del mes
            const lastSunday = new Date(lastDay);
            lastSunday.setDate(lastSunday.getDate() + (7 - lastDay.getDay()) % 7);

            // Generar semanas de este rango
            let weekStart = new Date(firstMonday);

            while (weekStart <= lastSunday) {
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekEnd.getDate() + 6);

                // Incluir cualquier semana que se cruce con el mes seleccionado
                const isWeekInCurrentMonth = weekStart <= lastDay && weekEnd >= firstDay;

                if (isWeekInCurrentMonth) {
                    const visibleRange = getVisibleWeekRangeWithinMonth(weekStart, weekEnd, selectedYear, selectedMonth);
                    const visibleStart = visibleRange ? visibleRange.visibleStart : null;
                    const visibleEnd = visibleRange ? visibleRange.visibleEnd : null;
                    const businessDaysInWeek = visibleStart && visibleEnd
                        ? countBusinessDaysInRange(visibleStart, visibleEnd)
                        : 0;

                    if (businessDaysInWeek === 0) {
                        weekStart.setDate(weekStart.getDate() + 7);
                        continue;
                    }

                    // Verificar si esta es la semana seleccionada (currentDate está en esta semana)
                    const isSelectedWeek = currentDate.getTime() >= weekStart.getTime() && currentDate.getTime() <= weekEnd.getTime();
                    const expectedHoursWeek = businessDaysInWeek * MAX_HOURS_PER_DAY;
                    const expectedHoursFormatted = expectedHoursWeek.toFixed(0) + ' h 00';
                    
                    const weekDiv = document.createElement('div');
                    weekDiv.className = `week-card ${isSelectedWeek ? 'active' : ''}`;
                    weekDiv.setAttribute('data-week-start', weekStart.getTime());
                    weekDiv.dataset.visibleStart = formatLocalDateKey(visibleStart);
                    weekDiv.dataset.visibleEnd = formatLocalDateKey(visibleEnd);
                    weekDiv.style.cursor = 'pointer';
                    
                    weekDiv.innerHTML = `
                        <div>
                            <h3>${formatRange(weekStart, weekEnd, selectedYear, selectedMonth)}</h3>
                            <div class="week-info">
                                <div style="margin-top: 8px;"><span class="week-hours-display" data-expected-hours="${expectedHoursWeek}">0 h 00 / ${expectedHoursFormatted}</span></div>
                            </div>
                        </div>
                    `;
                    
                    // Capturar la fecha correcta para evitar problema de closure
                    const visibleStartCopy = new Date(visibleStart);
                    weekDiv.addEventListener('click', function() {
                        currentDate = new Date(visibleStartCopy);
                        updateView();
                    });
                    
                    container.appendChild(weekDiv);
                }

                weekStart.setDate(weekStart.getDate() + 7);
            }
        }

        function formatRange(start, end, selectedYear = null, selectedMonth = null) {
            const startMonth = getMonthName(start);
            const endMonth = getMonthName(end);

            if (selectedYear === null || selectedMonth === null) {
                if (start.getMonth() === end.getMonth()) {
                    return `${start.getDate()} - ${end.getDate()} ${endMonth}`;
                }
                return `${start.getDate()} ${startMonth} - ${end.getDate()} ${endMonth}`;
            }

            const firstDayOfMonth = new Date(selectedYear, selectedMonth, 1);
            const lastDayOfMonth = new Date(selectedYear, selectedMonth + 1, 0);
            const visibleStart = start < firstDayOfMonth ? firstDayOfMonth : start;
            const visibleEnd = end > lastDayOfMonth ? lastDayOfMonth : end;

            if (visibleStart > visibleEnd) {
                if (start.getMonth() === end.getMonth()) {
                    return `${start.getDate()} - ${end.getDate()} ${endMonth}`;
                }
                return `${start.getDate()} ${startMonth} - ${end.getDate()} ${endMonth}`;
            }

            return `${visibleStart.getDate()} - ${visibleEnd.getDate()} ${getMonthName(visibleEnd)}`;
        }

        function getMonthName(date) {
            const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            return months[date.getMonth()];
        }

        function createTypicalWeek() {
            alert('Crear semana típica');
        }

        function focusDate() {
            document.getElementById('dateInput').showPicker?.();
            document.getElementById('dateInput').focus();
        }

        function addActivity() {
            // Bloquear para usuarios SUPER viendo a otro empleado
            const isOtherEmployee = selectedMatricula !== currentUserMatricula;
            if (canFilterEmpleado && isOtherEmployee) {
                showErrorModal('No puedes agregar actividades al calendario de otros empleados');
                return;
            }
            
            selectedProjects.clear();
            isAddingProjects = false;
            const addBtn = document.getElementById('addSelectedBtn');
            if (addBtn) {
                addBtn.disabled = true;
                addBtn.textContent = '+ Agregar Seleccionados';
            }
            document.getElementById('activityModal').classList.add('active');
            loadProjects();
        }

        function closeActivityModal() {
            document.getElementById('activityModal').classList.remove('active');
            selectedProjects.clear();
            isAddingProjects = false;
            const addBtn = document.getElementById('addSelectedBtn');
            if (addBtn) {
                addBtn.disabled = true;
                addBtn.textContent = '+ Agregar Seleccionados';
            }
        }

        function loadProjects() {
            const searchInput = document.getElementById('projectSearch').value;
            const matriculaParam = getMatriculaParam();
            const monthYearSelect = document.getElementById('monthYear');
            const weekStartLocal = formatLocalDateKey(getWeekStartMonday(currentDate));
            let selectedMonth = currentDate.getMonth() + 1;
            let selectedYear = currentDate.getFullYear();
            if (monthYearSelect && monthYearSelect.value) {
                const parts = monthYearSelect.value.split('-');
                if (parts.length === 2) {
                    selectedYear = parseInt(parts[0], 10) || selectedYear;
                    selectedMonth = parseInt(parts[1], 10) || selectedMonth;
                }
            }
            
            // include_existing=1 para mostrar proyectos que ya tienen registros (permite agregar más después de guardar)
            // Evitar enviar include_existing cuando hay filtro de búsqueda para no activar ramas inconsistentes en el backend
            const baseUrl = 'cargue_horas_Ajuste_2.php?action=get_proyectos&search=' + encodeURIComponent(searchInput) + '&month=' + selectedMonth + '&year=' + selectedYear + '&week_start=' + encodeURIComponent(weekStartLocal) + matriculaParam;
            const url = (searchInput && searchInput.trim() !== '') ? baseUrl : ('cargue_horas_Ajuste_2.php?action=get_proyectos&include_existing=1&' + baseUrl.split('?')[1]);
            fetch(url)
                .then(response => response.text())
                .then(text => {
                    console.log('Raw response text from server (get_proyectos):', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed JSON from get_proyectos:', data);
                        renderProjects(Array.isArray(data) ? mergeDefaultModalActivities(data) : data);
                    } catch (e) {
                        console.error('Failed to parse JSON from get_proyectos response:', e);
                        // Mostrar el texto crudo en la UI para depuración
                        const projectList = document.getElementById('projectList');
                        if (projectList) {
                            projectList.innerHTML = '<div class="empty-state">Error respondiendo servidor: ' + escapeHtml(String(text || '')) + '</div>';
                        }
                    }
                })
                .catch(error => {
                    console.error('Network or fetch error (get_proyectos):', error);
                    const projectList = document.getElementById('projectList');
                    if (projectList) {
                        projectList.innerHTML = '<div class="empty-state">Error de red: ' + (error && error.message ? escapeHtml(error.message) : 'unknown') + '</div>';
                    }
                });
        }

        function renderProjects(projects) {
            const projectList = document.getElementById('projectList');
            projectList.innerHTML = '';

            // Manejo de errores
            if (!Array.isArray(projects)) {
                if (projects && projects.error) {
                    console.error('Server error:', projects.error);
                    projectList.innerHTML = '<div class="empty-state">Error: ' + escapeHtml(String(projects.error)) + '</div>';
                } else {
                    console.warn('get_proyectos returned non-array response:', projects);
                    projectList.innerHTML = '<div class="empty-state">Respuesta inesperada del servidor:</div><pre class="debug-json">' + escapeHtml(JSON.stringify(projects, null, 2)) + '</pre>';
                }
                return;
            }

            if (projects.length === 0) {
                projectList.innerHTML = '<div class="empty-state">No se encontraron proyectos</div>';
                return;
            }

            projects.forEach(project => {
                const principalAlreadyAdded = !!(project && project.principal_added);
                const subcentros = Array.isArray(project && project.subcentros) ? project.subcentros : [];
                const visibleSubcentros = subcentros.filter(sc => !sc || !sc.already_added);
                const showPrincipal = !principalAlreadyAdded;

                if (!showPrincipal && visibleSubcentros.length === 0) {
                    return;
                }

                const projectDiv = document.createElement('div');
                projectDiv.className = 'project-item';
                const rawProjectId = project && project.id != null ? String(project.id).trim() : '';
                const projectId = Number(rawProjectId);
                const hasValidProjectId = Number.isInteger(projectId) && projectId > 0;
                const projectCode = project && project.centro_costos != null ? String(project.centro_costos).trim() : '';
                const selectionKey = hasValidProjectId ? `id:${projectId}` : `cc:${projectCode}`;
                const projectDomId = hasValidProjectId
                    ? 'project-' + projectId
                    : 'project-code-' + projectCode.replace(/[^a-zA-Z0-9_-]/g, '_');

                projectDiv.id = projectDomId;

                projectDiv.dataset.projectId = hasValidProjectId ? String(projectId) : '';
                projectDiv.dataset.projectCode = projectCode;
                projectDiv.dataset.selectionKey = selectionKey;

                if (showPrincipal) {
                    if (selectedProjects.has(selectionKey)) {
                        projectDiv.classList.add('selected');
                    }
                    projectDiv.innerHTML = `
                        <div class="project-info">
                            <div class="project-code">
                                ${project.centro_costos}
                            </div>
                            <div class="project-name">${project.nombre_proyecto}</div>
                        </div>
                        <div class="project-checkbox" aria-hidden="true">✓</div>
                    `;
                    projectDiv.addEventListener('click', () => toggleProject(selectionKey, projectDiv));
                    projectList.appendChild(projectDiv);
                }

                // Si hay subcentros, añadir cada subcentro como entrada separada en la misma lista
                try {
                    if (visibleSubcentros.length > 0) {
                        visibleSubcentros.forEach(sc => {
                            const subDiv = document.createElement('div');
                            subDiv.className = 'project-item subcentro-item';
                            const subCode = sc.SUB_CENTRO || '';
                            const subName = sc.NOMBRE_SUB_CENTRO || '';

                            // Guardar el centro padre en projectCode para que el add_activities siga usando el centro general
                            subDiv.dataset.projectId = '';
                            subDiv.dataset.projectCode = project.centro_costos || '';
                            subDiv.dataset.selectionKey = `cc:${project.centro_costos}`;
                            subDiv.dataset.subcentroCode = subCode;
                            subDiv.dataset.subcentroName = subName;

                            subDiv.innerHTML = `
                                <div class="project-info">
                                    <div class="project-code">${subCode}</div>
                                    <div class="project-name">${subName}</div>
                                </div>
                                <div class="project-checkbox" aria-hidden="true">✓</div>
                            `;

                            subDiv.addEventListener('click', () => {
                                // Toggle selección visual
                                subDiv.classList.toggle('selected');
                                updateSelectedProjectsFromDom();
                                updateAddSelectedButtonState();
                            });

                            projectList.appendChild(subDiv);
                        });
                    }
                } catch (e) {
                    console.warn('Error al renderizar subcentros:', e);
                }
            });

            updateSelectedProjectsFromDom();
            updateAddSelectedButtonState();

            mountIcons(projectList);
        }

        function updateSelectedProjectsFromDom() {
            selectedProjects.clear();
            document.querySelectorAll('#projectList .project-item.selected').forEach(item => {
                const selectionKey = (item.dataset.selectionKey || '').trim();
                if (selectionKey !== '') {
                    selectedProjects.add(selectionKey);
                }
            });
        }

        function updateAddSelectedButtonState() {
            const addBtn = document.getElementById('addSelectedBtn');
            if (!addBtn) return;
            addBtn.disabled = selectedProjects.size === 0 || isAddingProjects;
        }

        function toggleProject(selectionKey, projectElement) {
            if (!selectionKey) return;
            const nowSelected = projectElement.classList.toggle('selected');
            if (nowSelected) {
                selectedProjects.add(selectionKey);
            } else {
                selectedProjects.delete(selectionKey);
            }

            // Mostrar/ocultar selector de subcentros dentro del elemento del proyecto
            try {
                const wrap = projectElement.querySelector('.project-subcentro-wrap');
                if (wrap) {
                    wrap.style.display = nowSelected ? 'block' : 'none';
                }
            } catch (e) { /* ignore */ }

            updateSelectedProjectsFromDom();
            updateAddSelectedButtonState();
        }
        function addSelectedProjects() {
            updateSelectedProjectsFromDom();
            if (selectedProjects.size === 0 || isAddingProjects) return;

            isAddingProjects = true;
            const addBtn = document.getElementById('addSelectedBtn');
            if (addBtn) {
                addBtn.disabled = true;
                addBtn.textContent = 'Agregando...';
            }

            const selectedItems = Array.from(document.querySelectorAll('#projectList .project-item.selected'));
            const projectIds = [];
            const projectCodes = [];

            // Persistir selección de subcentro por proyecto si existe en el UI (select o subcentro-item)
            selectedItems.forEach(item => {
                try {
                    const sel = item.querySelector('.subcentro-select');
                    const code = (item.dataset.projectCode || '').trim();
                    if (sel && sel.value && code) {
                        const nombre = (sel.selectedOptions && sel.selectedOptions[0] && sel.selectedOptions[0].dataset) ? sel.selectedOptions[0].dataset.nombre : '';
                        saveTempActivityMeta(code, { cod_sub_ceco: sel.value, nombre_sub_ceco: nombre });
                    }

                    // Si el item es un subcentro-item y fue seleccionado, guardar esa selección
                    const subCode = (item.dataset.subcentroCode || '').trim();
                    const subName = (item.dataset.subcentroName || '').trim();
                    const parentCode = (item.dataset.projectCode || '').trim();
                    if (subCode && parentCode) {
                        // Guardar la selección del subcentro bajo el código del centro padre
                        saveTempActivityMeta(parentCode, { cod_sub_ceco: subCode, nombre_sub_ceco: subName });
                    }
                } catch (e) { /* ignore */ }
            });

            selectedItems.forEach(item => {
                const rawId = (item.dataset.projectId || '').trim();
                const code = (item.dataset.projectCode || '').trim();

                if (/^\d+$/.test(rawId)) {
                    const id = Number(rawId);
                    if (Number.isInteger(id) && id > 0) {
                        projectIds.push(id);
                    }
                }

                if (code !== '') {
                    projectCodes.push(code);
                }
            });

            // Dedupe projectCodes para evitar duplicados cuando se seleccionan subcentros
            const uniqueProjectCodes = Array.from(new Set(projectCodes));

            if (projectIds.length === 0 && projectCodes.length === 0) {
                isAddingProjects = false;
                if (addBtn) {
                    addBtn.textContent = '+ Agregar Seleccionados';
                    addBtn.disabled = true;
                }
                showErrorModal('No hay actividades seleccionadas para agregar.');
                return;
            }

            // Obtener mes y año seleccionados
            const monthYearSelect = document.getElementById('monthYear');
            let selectedMonth = 0;
            let selectedYear = 0;
            if (monthYearSelect) {
                const value = monthYearSelect.value; // formato: yyyy-mm
                const parts = value.split('-');
                if (parts.length === 2) {
                    selectedYear = parseInt(parts[0], 10);
                    selectedMonth = parseInt(parts[1], 10);
                }
            }

            const weekStartLocal = formatLocalDateKey(getWeekStartMonday(currentDate));

            // Build detailed items including subcentro when selected
            const projectItems = [];
            selectedItems.forEach(item => {
                const code = (item.dataset.projectCode || '').trim();
                const subCode = (item.dataset.subcentroCode || '').trim();
                const subName = (item.dataset.subcentroName || '').trim();
                if (code) {
                    // If this item itself represents a subcentro (rendered as a child), use its subcentro
                    if (subCode) {
                        projectItems.push({ code: code, cod_sub_ceco: subCode, nombre_sub_ceco: subName });
                    } else {
                        // parent project row; check if it has a selected subcentro in the select control
                        const sel = item.querySelector('.subcentro-select');
                        if (sel && sel.value) {
                            const nombre = (sel.selectedOptions && sel.selectedOptions[0] && sel.selectedOptions[0].dataset) ? sel.selectedOptions[0].dataset.nombre : '';
                            projectItems.push({ code: code, cod_sub_ceco: sel.value, nombre_sub_ceco: nombre });
                        } else {
                            projectItems.push({ code: code });
                        }
                    }
                }
            });

            fetch('cargue_horas_Ajuste_2.php?action=add_activities', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    project_ids: projectIds,
                    project_codes: uniqueProjectCodes,
                    project_items: projectItems,
                    month: selectedMonth,
                    year: selectedYear,
                    week_start: weekStartLocal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addTempActivities(uniqueProjectCodes);
                    // Además, crear actividades temporales separadas para cada subcentro seleccionado
                    try {
                        const selectedSubItems = Array.from(document.querySelectorAll('#projectList .project-item.selected'))
                            .filter(it => (it.dataset.subcentroCode || '').trim() !== '');
                        selectedSubItems.forEach((it, idx) => {
                            const parentCode = (it.dataset.projectCode || '').trim();
                            const parentName = (it.dataset.projectName || '').trim() || '';
                            const subCode = (it.dataset.subcentroCode || '').trim();
                            const subName = (it.dataset.subcentroName || '').trim() || '';
                            if (parentCode && subCode) {
                                // Create a temp actividad object compatible with renderActivities
                                const tempActividad = {
                                    id: 'temp-' + Date.now() + '-' + idx,
                                    codigo_affaire: parentCode,
                                    nombre_proyect: parentName,
                                    horas: ['', '', '', '', '', '', ''],
                                    cod_sub_ceco: Array(7).fill(subCode),
                                    nombre_sub_ceco: Array(7).fill(subName)
                                };
                                addTempActivityObject(tempActividad);
                            }
                        });
                    } catch (e) { console.warn('No se pudieron crear actividades temporales por subcentro:', e); }

                    closeActivityModal();
                    loadActivities(); // Recargar actividades sin recargar la página
                    // Actualizar advertencias después de agregar actividades
                    setTimeout(updateDayWarnings, 100);
                } else {
                    isAddingProjects = false;
                    if (addBtn) {
                        updateAddSelectedButtonState();
                        addBtn.textContent = '+ Agregar Seleccionados';
                    }
                    showErrorModal('Error al agregar actividades: ' + (data.message || ''));
                }
            })
            .catch(error => {
                isAddingProjects = false;
                if (addBtn) {
                    updateAddSelectedButtonState();
                    addBtn.textContent = '+ Agregar Seleccionados';
                }
                console.error('Error:', error);
                showErrorModal('Error al agregar actividades');
            });
        }

        // ===== FUNCIONES PARA FILTRO DE EMPLEADOS =====
        const currentUserMatricula = '<?php echo addslashes(trim((string)$matricula)); ?>';
        const currentUserName = '<?php echo addslashes(strtoupper($nombre_completo)); ?>';
        let selectedMatricula = currentUserMatricula;
        const rolUsuario = '<?php echo addslashes($rol_usuario_normalizado); ?>';
        const isSuper = <?php echo $is_super_user ? 'true' : 'false'; ?>;
        const usesAreaEmployeeFilter = <?php echo $uses_area_employee_filter ? 'true' : 'false'; ?>;
        const canFilterEmpleado = <?php echo $can_filter_empleado ? 'true' : 'false'; ?>;
        let allEmpleados = [];

        function getMatriculaParam() {
            return canFilterEmpleado ? '&matricula=' + encodeURIComponent(selectedMatricula) : '';
        }

        function normalizeSearchValue(value) {
            const rawValue = String(value || '').trim().toLowerCase();
            return typeof rawValue.normalize === 'function'
                ? rawValue.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                : rawValue;
        }

        function normalizeMatriculaValue(value) {
            return String(value || '').trim();
        }

        function getEmpleadoDisplayName(emp) {
            if (!emp) return '';
            const nombre = (emp.Nombre_Usuario || '').toUpperCase();
            return emp.Matricula === currentUserMatricula ? `YO: ${nombre}` : nombre;
        }

        function getEmpleadoInputLabel(emp) {
            if (!emp) return '';
            return (emp.Nombre_Usuario || '').toUpperCase();
        }

        function setSelectedEmpleadoInput() {
            const searchInput = document.getElementById('empleadoSearch');
            if (!searchInput) return;
            const empleado = allEmpleados.find(e => e.Matricula === selectedMatricula);
            if (empleado) {
                searchInput.value = getEmpleadoInputLabel(empleado);
            }
        }

        function loadEmpleados() {
            if (!canFilterEmpleado) {
                selectedMatricula = currentUserMatricula;
                allEmpleados = [{
                    Matricula: currentUserMatricula,
                    Nombre_Usuario: currentUserName
                }];
                setSelectedEmpleadoInput();
                return;
            }

            const empleadosAction = usesAreaEmployeeFilter ? 'get_empleados_area' : 'get_empleados';

            fetch('cargue_horas_Ajuste_2.php?action=' + encodeURIComponent(empleadosAction))
                .then(response => response.json())
                .then(data => {
                    if (data && data.error) {
                        throw new Error(data.error);
                    }

                    if (Array.isArray(data)) {
                        const empleadosNormalizados = data.map(emp => ({
                            ...emp,
                            Matricula: normalizeMatriculaValue(emp.Matricula),
                            Nombre_Usuario: (emp.Nombre_Usuario || '').toUpperCase()
                        }));

                        const empleadosSinYoDuplicado = empleadosNormalizados.filter(emp => emp.Matricula !== currentUserMatricula);
                        allEmpleados = [
                            {
                                Matricula: currentUserMatricula,
                                Nombre_Usuario: currentUserName
                            },
                            ...empleadosSinYoDuplicado
                        ];

                        if (!selectedMatricula) {
                            selectedMatricula = currentUserMatricula;
                        }

                        renderEmpleadoDropdown(allEmpleados);
                        setSelectedEmpleadoInput();
                    }
                })
                .catch(error => console.error('Error cargando empleados:', error));
        }

        function renderEmpleadoDropdown(empleados) {
            const dropdown = document.getElementById('empleadoDropdown');
            if (!dropdown) return;
            
            dropdown.innerHTML = '';
            if (empleados.length === 0) {
                dropdown.innerHTML = '<div class="employee-dropdown-empty">No se encontraron empleados</div>';
                return;
            }
            
            empleados.forEach(emp => {
                const item = document.createElement('div');
                item.className = 'employee-dropdown-item';
                if (emp.Matricula === selectedMatricula) {
                    item.classList.add('selected');
                }
                item.textContent = getEmpleadoDisplayName(emp);
                item.onclick = () => selectEmpleado(emp.Matricula);
                dropdown.appendChild(item);
            });
        }

        function filterEmpleados(searchText) {
            const normalizedSearch = normalizeSearchValue(searchText);
            if (!normalizedSearch) return allEmpleados;

            return allEmpleados.filter(emp => {
                const normalizedNombre = normalizeSearchValue(emp.Nombre_Usuario);
                const normalizedMatricula = normalizeSearchValue(emp.Matricula);
                const normalizedDisplay = normalizeSearchValue(getEmpleadoDisplayName(emp));

                return normalizedNombre.includes(normalizedSearch)
                    || normalizedMatricula.includes(normalizedSearch)
                    || normalizedDisplay.includes(normalizedSearch);
            });
        }

        function selectEmpleado(matricula) {
            if (!canFilterEmpleado) {
                selectedMatricula = currentUserMatricula;
                setSelectedEmpleadoInput();
                return;
            }

            selectedMatricula = matricula;
            const searchInput = document.getElementById('empleadoSearch');
            const dropdown = document.getElementById('empleadoDropdown');
            
            // Actualizar el input con el nombre del empleado
            const empleado = allEmpleados.find(e => e.Matricula === matricula);
            if (empleado && searchInput) {
                searchInput.value = getEmpleadoInputLabel(empleado);
            }
            
            // Cerrar el dropdown
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            
            // Ejecutar el cambio
            changeEmpleado();
        }

        function changeEmpleado() {
            // Si selecciona a otra persona, bloquear la edición (excepto los botones de aprobación)
            const isOtherEmployee = selectedMatricula !== currentUserMatricula;
            const addActivityBtn = document.querySelector('.btn-add-activity');
            const hourInputs = document.querySelectorAll('.hour-input');
            const commentBtns = document.querySelectorAll('.comment-btn');
            const deleteActivityBtns = document.querySelectorAll('.btn-icon');
            
            if (addActivityBtn) addActivityBtn.disabled = isOtherEmployee;
            hourInputs.forEach(input => input.disabled = isOtherEmployee);
            commentBtns.forEach(btn => btn.disabled = isOtherEmployee);
            deleteActivityBtns.forEach(btn => btn.disabled = isOtherEmployee);
            
            // Al filtrar, volver siempre al mes activo por defecto
            setDefaultActiveMonth().finally(() => {
                // Recargar todas las vistas con el nuevo empleado
                updateView();
                setTimeout(() => {
                    updateDayWarnings();
                    updateDayRatios();
                    updateMonthSummary();
                    updateWeekDisplay();
                    updateApproveButton(); // Actualizar el estado de los botones de aprobación/rechazo
                    
                    // Re-aplicar el estado de bloqueo después de recargar
                    if (isOtherEmployee) {
                        document.querySelectorAll('.hour-input').forEach(input => input.disabled = true);
                        document.querySelectorAll('.comment-btn').forEach(btn => btn.disabled = true);
                        document.querySelectorAll('.btn-icon').forEach(btn => btn.disabled = true);
                    }
                }, 100);
            });
        }

        // Búsqueda en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            loadEmpleados();

            // Agregar event listeners solo para roles con filtro de empleados
            const empleadoSearch = document.getElementById('empleadoSearch');
            const empleadoDropdown = document.getElementById('empleadoDropdown');

            if (canFilterEmpleado && empleadoSearch && empleadoDropdown) {
                // Mostrar dropdown y filtrar cuando se escribe
                empleadoSearch.addEventListener('input', function(e) {
                    const searchText = e.target.value.trim();
                    const filtered = searchText.length > 0 ? filterEmpleados(searchText) : allEmpleados;
                    renderEmpleadoDropdown(filtered);
                    empleadoDropdown.classList.add('show');
                });
                
                // Mostrar empleados al hacer focus para permitir refiltrar fácilmente
                empleadoSearch.addEventListener('focus', function() {
                    renderEmpleadoDropdown(allEmpleados);
                    empleadoDropdown.classList.add('show');
                    this.select();
                });

                // Seleccionar el texto actual para facilitar una nueva búsqueda
                empleadoSearch.addEventListener('click', function() {
                    this.select();
                });
                
                // Cerrar el dropdown cuando se pierde el focus
                empleadoSearch.addEventListener('blur', function() {
                    setTimeout(() => {
                        empleadoDropdown.classList.remove('show');
                    }, 200); // Delay para permitir clicks en items
                });
            }
            
            const projectSearch = document.getElementById('projectSearch');
            if (projectSearch) {
                projectSearch.addEventListener('input', function() {
                    loadProjects();
                });
            }
            
            // Actualizar estado del botón de aprobación al cargar la página
            updateApproveButton();
            
            // Agregar listener al botón de enviar aprobación
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.addEventListener('click', function() {
                    if (!submitBtn.disabled) {
                        submitApproval();
                    }
                });
            }
            
            // Agregar listener al botón de rechazar aprobación
            const rejectBtn = document.getElementById('rejectBtn');
            if (rejectBtn) {
                rejectBtn.addEventListener('click', function() {
                    if (!rejectBtn.disabled) {
                        submitRejection();
                    }
                });
            }
        });

        function copyPreviousWeek() {
            alert('Copiar semana anterior');
        }

        function submitApproval() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1;
            const submitBtn = document.getElementById('submitBtn');
            
            // Verificar si el botón está deshabilitado (ya enviado)
            if (submitBtn.disabled) {
                showErrorModal('Las horas de este mes ya fueron enviadas para aprobación');
                return;
            }
            
            if (!confirm('¿Enviar las horas de este mes para aprobación?')) {
                return;
            }
            
            fetch('cargue_horas_Ajuste_2.php?action=submit_approval', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    year: year,
                    month: month
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal('Horas enviadas correctamente para aprobación\n\nMes: ' + data.mes + '\nHoras: ' + data.horas_teoricas + ' h');
                    // Opcionalmente, deshabilitar el botón después de enviar
                    setTimeout(() => {
                        updateApproveButton();
                    }, 1500);
                } else {
                    showErrorModal('Error: ' + (data.message || 'No se pudieron enviar las horas'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error al enviar las horas');
            });
        }

        function submitRejection() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1;
            const rejectBtn = document.getElementById('rejectBtn');
            
            // Verificar si el botón está deshabilitado
            if (rejectBtn.disabled) {
                showErrorModal('No hay aprobación que rechazar para este mes');
                return;
            }
            
            if (!confirm('¿Rechazar la aprobación de horas de este mes para ' + selectedMatricula + '?\n\nEl empleado podrá enviar sus horas nuevamente.')) {
                return;
            }
            
            fetch('cargue_horas_Ajuste_2.php?action=reject_approval', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    matricula: selectedMatricula,
                    year: year,
                    month: month
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal('Aprobación rechazada correctamente\n\nEl empleado podrá enviar sus horas nuevamente.');
                    // Opcionalmente, actualizar el estado de los botones después de rechazar
                    setTimeout(() => {
                        updateApproveButton();
                    }, 1500);
                } else {
                    showErrorModal('Error: ' + (data.message || 'No se pudo rechazar la aprobación'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Error al rechazar la aprobación');
            });
        }

        // Inicializar
        setDefaultActiveMonth().finally(() => {
            updateView();
        });
    </script>
</body>
</html>
