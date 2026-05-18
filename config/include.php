<?php
// include.php - Conexión a la base de datos
// NO debe tener espacios ni BOM antes de <?php

// Desactivar caché del navegador (evita problemas entre localhost y producción)
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Incluir configuración global
require_once 'config.php';

// Usar la variable global $ENVIRONMENT definida en config.php
global $ENVIRONMENT;

// $ENVIRONMENT y get_environment() se definen en includes/db_connection.php

if ($ENVIRONMENT === 'LOCAL') {
    // ===== CREDENCIALES LOCALHOST =====
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    $host = 'localhost';
    $dbname = ($_SERVER['HTTP_HOST'] === 'localhost') ? 'control_presupuestal_horas' : 'appdocumental';
    $user = '';
    $password = '';
    $conn = null;
    try {
        $conn = @new mysqli($host, $user, $password, $dbname);
        if ($conn && $conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
    } catch (Exception $e) {
        // Intentar con usuario root
        $user = 'root';
        $password = '';
        try {
            $conn = @new mysqli($host, $user, $password, $dbname);
            if ($conn && $conn->connect_error) {
                throw new Exception($conn->connect_error);
            }
        } catch (Exception $e2) {
            $conn = null;
            error_log('MySQL Connection Error: ' . $e2->getMessage());
        }
    }
    // Nombres de tablas en LOCALHOST
    $tabla_acum_real_proyectos = 'acum_real_proyectos';
    $tabla_app_reporte_inputhh = 'app_reporte_inputhh';
    $tabla_app_reporte_inputhh_detalle = 'app_reporte_inputhh_detalle';
    $tabla_aprobaciones_horas = 'aprobaciones_horas';
    $tabla_asignacion = 'asignación';
    $tabla_avance_fisico_ejecutado_programado = 'avance_fisico_ejecutado_programado';
    $tabla_avance_pdt_proyecto = 'avance_pdt_proyecto';
    $tabla_cargue_horas = 'cargue_horas';
    $tabla_compartir_presupuesto = 'compartir_presupuesto';
    $tabla_correos_empleados = 'correos_empleados';
    $tabla_costo_valorizado = 'costo_valorizado';
    $tabla_empleados = 'empleados';
    $tabla_gastos_personal = 'gastos_personal';
    $tabla_horas_calendario = 'horas_calendario';
    $tabla_horas_dia = 'horas_dia';
    $tabla_horas_habiles_calendario = 'horas_habiles_calendario';
    $tabla_horas_valorizadas = 'horas_valorizadas';
    $tabla_login_usuarios = 'login_usuarios';
    $tabla_mes_activo = 'mes_activo';
    $tabla_proyectos = 'proyectos';
    $tabla_temp_gastos = 'temp_gastos';
    $tabla_sub_centros_costos = 'sub_centros_costos';
    // Nombres de vistas en LOCALHOST
    $vista_aprobacion_area_funcional_proyecto = 'aprobacion_area_funcional_proyecto';
    $vista_capacidad_instalada_empleados = 'capacidad_instalada_empleados';
    $vista_capacidad_instalada_por_cada_empleado = 'capacidad_instalada_por_cada_empleado';
    $vista_costo_asignado = 'costo_asignado';
    $vista_costo_asignado_resumen = 'costo_asignado_resumen';
    $vista_gastos_personal_valorizado = 'gastos_personal_valorizado';
    $vista_grafica_capacidad_instalada = 'grafica_capacidad_instalada';
    $vista_real_ejecutado = 'real_ejecutado';
    $vista_asignacion_base = 'vista_asignacion_base';
    $vista_empleados = 'vista_empleados';
    $vista_vw_estado_aprobacion = 'vw_estado_aprobacion';
    $vista_vw_estado_aprobacion_user = 'vw_estado_aprobacion_user';
    $vista_vw_max_version_pto = 'vw_max_version_pto';
    $vista_vw_porcentaje_cargue = 'vw_porcentaje_cargue';
    $vista_vw_presupuesto_total_proyectos = 'vw_presupuesto_total_proyectos';
    $vista_vw_validar_presupuesto = 'vw_validar_presupuesto';
    // Vistas adicionales solicitadas
    $vista_costo_mensual_por_area = 'vista_costo_mensual_por_area';
    $vista_costo_mensual_por_area_proyecto_cc = 'vista_costo_mensual_por_area_proyecto_cc';
    $vista_detalle_mes_activo = 'vista_detalle_mes_activo';
    $vista_horas_mes_detalle_capacidad_instalada = 'vista_horas_mes_detalle_capacidad_instalada';
} else {
    // ===== CREDENCIALES PRODUCCIÓN =====
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php_errors.log');
    $host = 'localhost';
    $user = 'untiembzufpqh';
    $password = '{1c#1@@:13~u';
    $dbname = 'dbugqgfvwmknci';
    // Nombres de tablas en PRODUCCIÓN (minúsculas)
    $tabla_acum_real_proyectos = 'acum_real_proyectos';
    $tabla_app_reporte_inputhh = 'app_reporte_inputhh';
    $tabla_app_reporte_inputhh_detalle = 'app_reporte_inputhh_detalle';
    $tabla_aprobaciones_horas = 'aprobaciones_horas';
    $tabla_asignacion = 'asignación';
    $tabla_avance_fisico_ejecutado_programado = 'avance_fisico_ejecutado_programado';
    $tabla_avance_pdt_proyecto = 'avance_pdt_proyecto';
    $tabla_cargue_horas = 'cargue_horas';
    $tabla_compartir_presupuesto = 'compartir_presupuesto';
    $tabla_correos_empleados = 'correos_empleados';
    $tabla_costo_valorizado = 'costo_valorizado';
    $tabla_empleados = 'empleados';
    $tabla_gastos_personal = 'gastos_personal';
    $tabla_horas_calendario = 'horas_calendario';
    $tabla_horas_dia = 'horas_dia';
    $tabla_horas_habiles_calendario = 'horas_habiles_calendario';
    $tabla_horas_valorizadas = 'horas_valorizadas';
    $tabla_login_usuarios = 'login_usuarios';
    $tabla_mes_activo = 'mes_activo';
    $tabla_proyectos = 'proyectos';
    $tabla_temp_gastos = 'temp_gastos';
    $tabla_emisiones = 'emisiones';
    $tabla_usuarios_login = 'usuarios_login';
    $tabla_lista_entregables = 'lista_entregables';
    $tabla_transmittals = 'transmittals';
    $tabla_clientes_proyecto = 'clientes_proyecto';
    $tabla_sub_centros_costos = 'sub_centros_costos';
    // Nombres de vistas en PRODUCCIÓN
    $vista_aprobacion_area_funcional_proyecto = 'aprobacion_area_funcional_proyecto';
    $vista_capacidad_instalada_empleados = 'capacidad_instalada_empleados';
    $vista_capacidad_instalada_por_cada_empleado = 'capacidad_instalada_por_cada_empleado';
    $vista_costo_asignado = 'costo_asignado';
    $vista_costo_asignado_resumen = 'costo_asignado_resumen';
    $vista_gastos_personal_valorizado = 'gastos_personal_valorizado';
    $vista_grafica_capacidad_instalada = 'grafica_capacidad_instalada';
    $vista_real_ejecutado = 'real_ejecutado';
    $vista_asignacion_base = 'vista_asignacion_base';
    $vista_empleados = 'vista_empleados';
    $vista_vw_estado_aprobacion = 'vw_estado_aprobacion';
    $vista_vw_estado_aprobacion_user = 'vw_estado_aprobacion_user';
    $vista_vw_max_version_pto = 'vw_max_version_pto';
    $vista_vw_porcentaje_cargue = 'vw_porcentaje_cargue';
    $vista_vw_presupuesto_total_proyectos = 'vw_presupuesto_total_proyectos';
    $vista_vw_validar_presupuesto = 'vw_validar_presupuesto';
    $vista_emisiones_version_max = 'vista_emisiones_version_max';
    // Vistas adicionales solicitadas
    $vista_costo_mensual_por_area = 'vista_costo_mensual_por_area';
    $vista_costo_mensual_por_area_proyecto_cc = 'vista_costo_mensual_por_area_proyecto_cc';
    $vista_detalle_mes_activo = 'vista_detalle_mes_activo';
    $vista_horas_mes_detalle_capacidad_instalada = 'vista_horas_mes_detalle_capacidad_instalada';
}

// Establecer timeout para conexión
ini_set('mysqli.connect_timeout', 3);
ini_set('default_socket_timeout', 3);

// Intentar conexión primero sin usuario ni contraseña, y si falla, intentar con usuario root y contraseña vacía
try {
    $conn = @new mysqli($host, $user, $password, $dbname);
} catch (Exception $e) {
    error_log('MySQL Exception: ' . $e->getMessage());
    $conn = null;
}

if ($conn && $conn->connect_error) {
    error_log('MySQL Connection Error: ' . $conn->connect_error);
    // Mostrar error en pantalla en todos los entornos para diagnóstico
    echo '<div style="background:#ff0000;color:white;padding:20px;margin:20px;border-radius:8px;"><strong>Error de conexión a la base de datos:</strong><br>' . htmlspecialchars($conn->connect_error) . '</div>';
    // Establecer $conn a null para que otros archivos lo detecten
    $conn = null;
} elseif ($conn && !$conn->connect_error) {
    // Establecer charset UTF-8 solo si la conexión fue exitosa
    @$conn->set_charset("utf8mb4");
}

if (!function_exists('get_logged_user_context')) {
    function get_logged_user_context($mysqli, $usuario, $matricula = '') {
        $context = [
            'usuario' => trim((string)$usuario),
            'matricula' => trim((string)$matricula),
            'nombre_usuario' => '',
            'area_funcional' => '',
            'rol' => '',
            'found' => false,
        ];

        if (!($mysqli instanceof mysqli) || $mysqli->connect_error) {
            return $context;
        }

        $userQueries = [];
        if ($context['usuario'] !== '') {
            $userQueries[] = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE Usuario = ? LIMIT 1";
            $userQueries[] = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE TRIM(Usuario) = TRIM(?) LIMIT 1";
            $userQueries[] = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE UPPER(TRIM(Usuario)) = UPPER(TRIM(?)) LIMIT 1";
        }

        foreach ($userQueries as $sqlUser) {
            $stmtUser = $mysqli->prepare($sqlUser);
            if (!$stmtUser) {
                continue;
            }

            $stmtUser->bind_param('s', $context['usuario']);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $rowUser = $resultUser ? $resultUser->fetch_assoc() : null;
            $stmtUser->close();

            if (!$rowUser) {
                continue;
            }

            $context['found'] = true;
            $context['usuario'] = isset($rowUser['Usuario']) ? trim((string)$rowUser['Usuario']) : $context['usuario'];
            if ($context['matricula'] === '' && isset($rowUser['Matricula'])) {
                $context['matricula'] = trim((string)$rowUser['Matricula']);
            }
            $context['nombre_usuario'] = isset($rowUser['Nombre_Usuario']) ? trim((string)$rowUser['Nombre_Usuario']) : '';
            $context['area_funcional'] = isset($rowUser['area_funcional']) ? trim((string)$rowUser['area_funcional']) : '';
            $context['rol'] = isset($rowUser['ROL']) ? trim((string)$rowUser['ROL']) : '';
            break;
        }

        if (!$context['found'] && $context['matricula'] !== '') {
            $sqlMatricula = "SELECT Usuario, Matricula, Nombre_Usuario, `Área_Funcional` AS area_funcional, ROL FROM login_usuarios WHERE TRIM(Matricula) = TRIM(?) LIMIT 1";
            $stmtMatricula = $mysqli->prepare($sqlMatricula);
            if ($stmtMatricula) {
                $stmtMatricula->bind_param('s', $context['matricula']);
                $stmtMatricula->execute();
                $resultMatricula = $stmtMatricula->get_result();
                $rowMatricula = $resultMatricula ? $resultMatricula->fetch_assoc() : null;
                $stmtMatricula->close();

                if ($rowMatricula) {
                    $context['found'] = true;
                    $context['usuario'] = isset($rowMatricula['Usuario']) ? trim((string)$rowMatricula['Usuario']) : $context['usuario'];
                    $context['matricula'] = isset($rowMatricula['Matricula']) ? trim((string)$rowMatricula['Matricula']) : $context['matricula'];
                    $context['nombre_usuario'] = isset($rowMatricula['Nombre_Usuario']) ? trim((string)$rowMatricula['Nombre_Usuario']) : '';
                    $context['area_funcional'] = isset($rowMatricula['area_funcional']) ? trim((string)$rowMatricula['area_funcional']) : '';
                    $context['rol'] = isset($rowMatricula['ROL']) ? trim((string)$rowMatricula['ROL']) : '';
                }
            }
        }

        return $context;
    }
}

// Puedes usar $conn en otros archivos incluyendo este include.php
