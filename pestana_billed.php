<?php
/**
 * Hook - Debug: Yeison con integración Billed - PASO 4 CORREGIDO
 * Archivo: includes/hooks/yera_hook.php
 * NUEVO: Mostrar detalle completo de tareas y tiempos - CON CORRECCIONES
 */

// Prevenir acceso directo
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// ================================================================
// CARGAR DEPENDENCIAS (PASO 2)
// ================================================================

/**
 * Cargar las clases necesarias del hook consolidado
 */
function cargarDependenciasYeison() {
    $hookBasePath = __DIR__;
    $dependenciasStatus = [];
    
    // Cargar WhMiConexion
    $whMiConexionPath = $hookBasePath . '/whmcs/WhMiConexion.php';
    if (file_exists($whMiConexionPath)) {
        require_once $whMiConexionPath;
        $dependenciasStatus['WhMiConexion'] = [
            'archivo' => $whMiConexionPath,
            'existe' => true,
            'cargado' => class_exists('WhMiConexion')
        ];
    } else {
        $dependenciasStatus['WhMiConexion'] = [
            'archivo' => $whMiConexionPath,
            'existe' => false,
            'cargado' => false
        ];
        logActivity("Yeison Hook Error: No se encontró WhMiConexion.php en " . $whMiConexionPath);
    }
    
    // Cargar ClockifyClient
    $clockifyClientPath = $hookBasePath . '/clockify/ClockifyClient.php';
    if (file_exists($clockifyClientPath)) {
        require_once $clockifyClientPath;
        $dependenciasStatus['ClockifyClient'] = [
            'archivo' => $clockifyClientPath,
            'existe' => true,
            'cargado' => class_exists('ClockifyClient')
        ];
    } else {
        $dependenciasStatus['ClockifyClient'] = [
            'archivo' => $clockifyClientPath,
            'existe' => false,
            'cargado' => false
        ];
        logActivity("Yeison Hook Error: No se encontró ClockifyClient.php en " . $clockifyClientPath);
    }
    
    return $dependenciasStatus;
}

/**
 * Verificar disponibilidad de las dependencias
 */
if (!function_exists('verificarDependenciasYeison')) {
    function verificarDependenciasYeison() {
        return [
            'WhMiConexion' => class_exists('WhMiConexion'),
            'ClockifyClient' => class_exists('ClockifyClient')
        ];
    }
}

/**
 * Función auxiliar para obtener Service ID (misma lógica del hook consolidado)
 */
if (!function_exists('obtenerServiceIdYeison')) {
    function obtenerServiceIdYeison($vars) {
        
        // MÉTODO 1: Desde las variables del hook
        if (isset($vars['id']) && is_numeric($vars['id']) && $vars['id'] > 0) {
            return (int)$vars['id'];
        }
        
        if (isset($vars['serviceid']) && is_numeric($vars['serviceid']) && $vars['serviceid'] > 0) {
            return (int)$vars['serviceid'];
        }
        
        // MÉTODO 2: Desde variables globales de WHMCS
        if (isset($GLOBALS['serviceid']) && is_numeric($GLOBALS['serviceid']) && $GLOBALS['serviceid'] > 0) {
            return (int)$GLOBALS['serviceid'];
        }
        
        // MÉTODO 3: Desde parámetros GET/POST
        if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) {
            return (int)$_GET['id'];
        }
        
        if (isset($_GET['serviceid']) && is_numeric($_GET['serviceid']) && $_GET['serviceid'] > 0) {
            return (int)$_GET['serviceid'];
        }
        
        // MÉTODO 4: Parsing de la URL actual
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        
        // Buscar parámetro 'id' en la URL
        if (preg_match('/[?&]id=(\d+)/', $currentUrl, $matches)) {
            return (int)$matches[1];
        }
        
        // Si no se encuentra ningún ID válido
        return 0;
    }
}



if (!function_exists('procesarDetalleTareas')) {
    function procesarDetalleTareas($clockifyData) {
        $detalleTareas = [];
        
        // Debug: Agregar información sobre la estructura de datos
        logActivity("Yeison Debug: Procesando estructura de clockifyData - keys: " . json_encode(array_keys($clockifyData)));
        
        // NUEVA LÓGICA: Buscar tareas en diferentes ubicaciones
        $tareas = [];
        $timeEntries = [];
        
        // Opción 1: Tareas directamente en la raíz (como muestra tu JSON)
        if (isset($clockifyData['tasks']) && is_array($clockifyData['tasks'])) {
            $tareas = $clockifyData['tasks'];
            logActivity("Yeison Debug: Encontradas " . count($tareas) . " tareas en la raíz");
        }
        
        // Opción 2: timeEntries directamente en la raíz
        if (isset($clockifyData['timeEntries']) && is_array($clockifyData['timeEntries'])) {
            $timeEntries = $clockifyData['timeEntries'];
            logActivity("Yeison Debug: Encontradas " . count($timeEntries) . " entradas de tiempo en la raíz");
        }
        
        // Opción 3: Buscar dentro de proyectos (lógica original como respaldo)
        if (empty($tareas) && empty($timeEntries) && isset($clockifyData['projects']) && is_array($clockifyData['projects'])) {
            logActivity("Yeison Debug: Buscando tareas dentro de proyectos");
            foreach ($clockifyData['projects'] as $proyecto) {
                if (isset($proyecto['tasks']) && is_array($proyecto['tasks'])) {
                    $tareas = array_merge($tareas, $proyecto['tasks']);
                }
                if (isset($proyecto['timeEntries']) && is_array($proyecto['timeEntries'])) {
                    $timeEntries = array_merge($timeEntries, $proyecto['timeEntries']);
                }
            }
        }
        
        // Procesar las tareas encontradas
        foreach ($tareas as $indiceTarea => $tarea) {
            $detalleTarea = [
                'proyecto' => 'Proyecto Principal', // Valor por defecto
                'proyecto_id' => isset($tarea['projectId']) ? $tarea['projectId'] : 'N/A',
                'tarea_nombre' => isset($tarea['name']) ? $tarea['name'] : 'Tarea sin nombre',
                'tarea_id' => isset($tarea['id']) ? $tarea['id'] : 'N/A',
                'descripcion' => isset($tarea['description']) ? $tarea['description'] : '',
                'tiempo_segundos' => 0, // Se calculará desde timeEntries
                'tiempo_formateado' => '',
                'es_facturable' => isset($tarea['billable']) ? (bool)$tarea['billable'] : true,
                'tarifa_horaria' => 0,
                'monto_total' => 0,
                'cantidad_registros' => 0,
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'tags' => [],
                'usuario_asignado' => 'No asignado',
                'tipo' => 'tarea'
            ];
            
            // Convertir duration si existe
            if (isset($tarea['duration']) && !empty($tarea['duration'])) {
                $duracionSegundos = convertirDuracionASegundos($tarea['duration']);
                $detalleTarea['tiempo_segundos'] = $duracionSegundos;
                $detalleTarea['tiempo_formateado'] = formatearTiempo($duracionSegundos);
            } else if (isset($tarea['totalTime']) && !empty($tarea['totalTime'])) {
                $duracionSegundos = convertirDuracionASegundos($tarea['totalTime']);
                $detalleTarea['tiempo_segundos'] = $duracionSegundos;
                $detalleTarea['tiempo_formateado'] = formatearTiempo($duracionSegundos);
            } else if (isset($tarea['formattedTime'])) {
                $detalleTarea['tiempo_formateado'] = $tarea['formattedTime'];
            }
            
            $detalleTareas[] = $detalleTarea;
            logActivity("Yeison Debug: Tarea {$indiceTarea} procesada: {$detalleTarea['tarea_nombre']} - Tiempo: {$detalleTarea['tiempo_formateado']}");
        }
        
        // Procesar timeEntries como tareas individuales
        foreach ($timeEntries as $indiceEntry => $entry) {
            $detalleTarea = [
                'proyecto' => 'Proyecto Principal',
                'proyecto_id' => isset($entry['projectId']) ? $entry['projectId'] : 'N/A',
                'tarea_nombre' => isset($entry['description']) ? $entry['description'] : 'Entrada de tiempo sin descripción',
                'tarea_id' => isset($entry['taskId']) ? $entry['taskId'] : (isset($entry['id']) ? $entry['id'] : 'N/A'),
                'descripcion' => isset($entry['description']) ? $entry['description'] : '',
                'tiempo_segundos' => 0,
                'tiempo_formateado' => '',
                'es_facturable' => isset($entry['billable']) ? (bool)$entry['billable'] : true,
                'tarifa_horaria' => 0,
                'monto_total' => 0,
                'cantidad_registros' => 1,
                'fecha_inicio' => isset($entry['timeInterval']['start']) ? $entry['timeInterval']['start'] : null,
                'fecha_fin' => isset($entry['timeInterval']['end']) ? $entry['timeInterval']['end'] : null,
                'tags' => isset($entry['tagIds']) ? $entry['tagIds'] : [],
                'usuario_asignado' => isset($entry['userId']) ? $entry['userId'] : 'No asignado',
                'tipo' => 'time_entry'
            ];
            
            // Procesar duración del timeInterval
            if (isset($entry['timeInterval']['duration']) && !empty($entry['timeInterval']['duration'])) {
                $duracionSegundos = convertirDuracionASegundos($entry['timeInterval']['duration']);
                $detalleTarea['tiempo_segundos'] = $duracionSegundos;
                $detalleTarea['tiempo_formateado'] = formatearTiempo($duracionSegundos);
            }
            
            // Procesar tarifas si existen
            if (isset($entry['hourlyRate']['amount'])) {
                $detalleTarea['tarifa_horaria'] = (float)$entry['hourlyRate']['amount'];
            }
            
            $detalleTareas[] = $detalleTarea;
            logActivity("Yeison Debug: TimeEntry {$indiceEntry} procesada: {$detalleTarea['tarea_nombre']} - Tiempo: {$detalleTarea['tiempo_formateado']}");
        }
        
        // Procesar la tarea virtual si existe
        if (isset($clockifyData['virtualTask'])) {
            $virtualTask = $clockifyData['virtualTask'];
            $detalleTarea = [
                'proyecto' => 'Proyecto Principal',
                'proyecto_id' => 'virtual',
                'tarea_nombre' => isset($virtualTask['name']) ? $virtualTask['name'] : 'Tarea Virtual',
                'tarea_id' => isset($virtualTask['id']) ? $virtualTask['id'] : 'virtual',
                'descripcion' => 'Tiempo registrado sin tarea específica asignada',
                'tiempo_segundos' => 0,
                'tiempo_formateado' => isset($virtualTask['formattedTime']) ? $virtualTask['formattedTime'] : '0 min',
                'es_facturable' => true,
                'tarifa_horaria' => 0,
                'monto_total' => 0,
                'cantidad_registros' => 1,
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'tags' => [],
                'usuario_asignado' => 'No asignado',
                'tipo' => 'virtual_task'
            ];
            
            if (isset($virtualTask['totalTime']) && !empty($virtualTask['totalTime'])) {
                $duracionSegundos = convertirDuracionASegundos($virtualTask['totalTime']);
                $detalleTarea['tiempo_segundos'] = $duracionSegundos;
                if (empty($detalleTarea['tiempo_formateado']) || $detalleTarea['tiempo_formateado'] === '0 min') {
                    $detalleTarea['tiempo_formateado'] = formatearTiempo($duracionSegundos);
                }
            }
            
            $detalleTareas[] = $detalleTarea;
            logActivity("Yeison Debug: Tarea virtual procesada: {$detalleTarea['tarea_nombre']} - Tiempo: {$detalleTarea['tiempo_formateado']}");
        }
        
        // Ordenar por tipo y luego por nombre
        usort($detalleTareas, function($a, $b) {
            $tipoCompare = strcmp($a['tipo'], $b['tipo']);
            if ($tipoCompare !== 0) {
                return $tipoCompare;
            }
            return strcmp($a['tarea_nombre'], $b['tarea_nombre']);
        });
        
        logActivity("Yeison Debug: Total de elementos procesados: " . count($detalleTareas));
        
        return $detalleTareas;
    }
}




if (!function_exists('convertirDuracionASegundos')) {
    function convertirDuracionASegundos($duracion) {
        if (empty($duracion) || !is_string($duracion)) {
            return 0;
        }
        
        // Si ya es un número (segundos), devolverlo
        if (is_numeric($duracion)) {
            return (int)$duracion;
        }
        
        $segundos = 0;
        
        // Formato ISO 8601 Duration (PT5H50M)
        if (strpos($duracion, 'PT') === 0) {
            // Extraer horas
            if (preg_match('/(\d+)H/', $duracion, $matches)) {
                $segundos += (int)$matches[1] * 3600;
            }
            
            // Extraer minutos
            if (preg_match('/(\d+)M/', $duracion, $matches)) {
                $segundos += (int)$matches[1] * 60;
            }
            
            // Extraer segundos
            if (preg_match('/(\d+)S/', $duracion, $matches)) {
                $segundos += (int)$matches[1];
            }
        }
        
        return $segundos;
    }
}




if (!function_exists('formatearTiempo')) {
    function formatearTiempo($segundos) {
        // Convertir a número y validar
        $segundos = is_numeric($segundos) ? (int)$segundos : 0;
        
        if ($segundos <= 0) {
            return '0 min';
        }
        
        $horas = intval($segundos / 3600);
        $minutos = intval(($segundos % 3600) / 60);
        $segs = $segundos % 60;
        
        $resultado = [];
        if ($horas > 0) {
            $resultado[] = $horas . 'h';
        }
        if ($minutos > 0) {
            $resultado[] = $minutos . 'm';
        }
        if ($segs > 0 && $horas == 0 && $minutos < 5) {
            $resultado[] = $segs . 's';
        }
        
        return !empty($resultado) ? implode(' ', $resultado) : '0 min';
    }
}


if (!function_exists('obtenerDatosCompletosYeisonPaso4')) {
    function obtenerDatosCompletosYeisonPaso4($serviceId) {
        $resultado = [
            'service_id' => $serviceId,
            'timestamp' => date('Y-m-d H:i:s'),
            'success' => false,
            'datos_whmcs' => null,
            'datos_clockify' => null,
            'detalle_tareas' => [], // NUEVO EN PASO 4
            'error_message' => '',
            'debug_info' => []
        ];
        
        try {
            logActivity("Yeison Debug: Iniciando obtenerDatosCompletosYeisonPaso4 para service ID: {$serviceId}");
            
            // Verificar dependencias
            $dependenciasOk = verificarDependenciasYeison();
            
            if (!$dependenciasOk['WhMiConexion'] || !$dependenciasOk['ClockifyClient'] || $serviceId <= 0) {
                $error = 'Dependencias no disponibles o Service ID inválido';
                $resultado['error_message'] = $error;
                logActivity("Yeison Debug Error: {$error}");
                return $resultado;
            }
            
            // PASO 4A: Obtener datos de WHMCS
            logActivity("Yeison Debug: Obteniendo datos de WHMCS...");
            $whmcsClient = new WhMiConexion();
            $whmcsResult = $whmcsClient->getReportData($serviceId);
            
            if (!$whmcsResult || !isset($whmcsResult['success']) || !$whmcsResult['success']) {
                $error = 'No se pudieron obtener datos de WHMCS';
                $resultado['error_message'] = $error;
                logActivity("Yeison Debug Error: {$error}");
                return $resultado;
            }
            
            $resultado['datos_whmcs'] = $whmcsResult['data'];
            $resultado['debug_info']['whmcs_data_keys'] = array_keys($whmcsResult['data']);
            logActivity("Yeison Debug: Datos WHMCS obtenidos correctamente");
            
            // PASO 4B: Obtener datos de Clockify con detalle
            $clockifyInfo = $whmcsResult['data']['clockify'] ?? null;
            
            if (!$clockifyInfo || !isset($clockifyInfo['client_name']) || !isset($clockifyInfo['target_month'])) {
                $error = 'Configuración de Clockify incompleta en WHMCS';
                $resultado['error_message'] = $error;
                logActivity("Yeison Debug Error: {$error}");
                return $resultado;
            }
            
            $clientName = $clockifyInfo['client_name'];
            $targetMonth = $clockifyInfo['target_month'];
            
            logActivity("Yeison Debug: Obteniendo datos de Clockify para cliente: {$clientName}, mes: {$targetMonth}");
            
            $clockifyClient = new ClockifyClient();
            $clockifyResult = $clockifyClient->getClientMonthlyBilledSummary($clientName, $targetMonth);
            
            if (!$clockifyResult || !isset($clockifyResult['success']) || !$clockifyResult['success']) {
                $error = 'No se pudieron obtener datos de Clockify para cliente: ' . $clientName;
                $resultado['error_message'] = $error;
                logActivity("Yeison Debug Error: {$error}");
                return $resultado;
            }
            
            $resultado['datos_clockify'] = $clockifyResult['data'];
            logActivity("Yeison Debug: Datos Clockify obtenidos correctamente");
            
            // PASO 4C: NUEVO - Procesar detalle de tareas
            logActivity("Yeison Debug: Iniciando procesamiento de detalle de tareas...");
            $resultado['detalle_tareas'] = procesarDetalleTareas($clockifyResult['data']);
            
            $resultado['success'] = true;
            $resultado['debug_info']['clockify_client'] = $clientName;
            $resultado['debug_info']['clockify_month'] = $targetMonth;
            $resultado['debug_info']['total_tareas_procesadas'] = count($resultado['detalle_tareas']);
            
            logActivity("Yeison Debug: Procesamiento completo exitoso - Total tareas: " . count($resultado['detalle_tareas']));
            
        } catch (Exception $e) {
            $error = 'Excepción: ' . $e->getMessage();
            $resultado['error_message'] = $error;
            logActivity("Yeison Hook Exception Paso 4: {$error}");
        }
        
        return $resultado;
    }
}



if (!function_exists('probarConexionBasicaYeisonPaso4')) {
    function probarConexionBasicaYeisonPaso4($serviceId) {
        $resultado = [
            'service_id' => $serviceId,
            'timestamp' => date('Y-m-d H:i:s'),
            'dependencias' => [],
            'conexion_whmcs' => false,
            'conexion_clockify' => false,
            'error_message' => '',
            'datos_disponibles' => false,
            'datos_completos' => null
        ];
        
        try {
            logActivity("Yeison Debug: Iniciando prueba de conexión básica para service ID: {$serviceId}");
            
            // Cargar dependencias
            $dependenciasStatus = cargarDependenciasYeison();
            $resultado['dependencias'] = $dependenciasStatus;
            
            // Verificar si las clases están disponibles
            $dependenciasOk = verificarDependenciasYeison();
            
            if ($dependenciasOk['WhMiConexion'] && $dependenciasOk['ClockifyClient'] && $serviceId > 0) {
                
                // Probar conexión WHMCS
                try {
                    $whmcsClient = new WhMiConexion();
                    $resultado['conexion_whmcs'] = true;
                    logActivity("Yeison Debug: Conexión WHMCS exitosa");
                    
                    // PASO 4: Obtener datos completos con detalle de tareas
                    $datosCompletos = obtenerDatosCompletosYeisonPaso4($serviceId);

                    $resultado['datos_completos'] = $datosCompletos;
                    
                    if ($datosCompletos['success']) {
                        $resultado['datos_disponibles'] = true;
                        $resultado['preview_data'] = [
                            'cliente_clockify' => $datosCompletos['datos_clockify']['client'] ?? 'No encontrado',
                            'mes_objetivo' => ($datosCompletos['datos_clockify']['monthName'] ?? 'Mes') . ' ' . ($datosCompletos['datos_clockify']['year'] ?? 'Año'),
                            'total_proyectos' => $datosCompletos['datos_clockify']['summary']['totalProjects'] ?? 0,
                            'total_tareas' => $datosCompletos['datos_clockify']['summary']['totalTasks'] ?? 0,
                            'tiempo_total' => $datosCompletos['datos_clockify']['summary']['formattedTotalTime'] ?? '0 horas',
                            'detalle_tareas' => $datosCompletos['detalle_tareas'], // NUEVO EN PASO 4
                            'total_tareas_procesadas' => count($datosCompletos['detalle_tareas'])
                        ];
                        logActivity("Yeison Debug: Datos disponibles correctamente - Total tareas: " . count($datosCompletos['detalle_tareas']));
                    } else {
                        $resultado['error_message'] = $datosCompletos['error_message'];
                        logActivity("Yeison Debug Error: " . $datosCompletos['error_message']);
                    }
                    
                } catch (Exception $e) {
                    $error = 'Error WHMCS: ' . $e->getMessage();
                    $resultado['error_message'] .= $error . '; ';
                    logActivity("Yeison Debug Exception WHMCS: {$error}");
                }
                
                // Probar conexión Clockify
                try {
                    $clockifyClient = new ClockifyClient();
                    $resultado['conexion_clockify'] = true;
                    logActivity("Yeison Debug: Conexión Clockify exitosa");
                } catch (Exception $e) {
                    $error = 'Error Clockify: ' . $e->getMessage();
                    $resultado['error_message'] .= $error . '; ';
                    logActivity("Yeison Debug Exception Clockify: {$error}");
                }
                
            } else {
                $faltantes = [];
                foreach ($dependenciasOk as $dep => $disponible) {
                    if (!$disponible) {
                        $faltantes[] = $dep;
                    }
                }
                if (!empty($faltantes)) {
                    $resultado['error_message'] = 'Dependencias faltantes: ' . implode(', ', $faltantes);
                }
                if ($serviceId <= 0) {
                    $resultado['error_message'] .= ($resultado['error_message'] ? '; ' : '') . 'Service ID inválido';
                }
                logActivity("Yeison Debug: " . $resultado['error_message']);
            }
            
        } catch (Exception $e) {
            $error = 'Error general: ' . $e->getMessage();
            $resultado['error_message'] = $error;
            logActivity("Yeison Hook Exception Paso 4: {$error}");
        }
        
        return $resultado;
    }
}

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    // Verificar condiciones
    $is_clientarea = strpos($_SERVER['REQUEST_URI'], 'clientarea.php') !== false;
    $is_productdetails = isset($_GET['action']) && $_GET['action'] == 'productdetails';
    
    // Solo ejecutar en la página correcta
    if (!($is_clientarea && $is_productdetails)) {
        return '';
    }
    
    // Obtener Service ID
    $serviceId = obtenerServiceIdYeison($vars);
    
    // Probar conexión y obtener datos (PASO 4)
    $pruebaConexion = probarConexionBasicaYeisonPaso4($serviceId);
    
    // Debug info expandido
    $debug_info = [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'GET_action' => isset($_GET['action']) ? $_GET['action'] : 'no_action',
        'GET_id' => isset($_GET['id']) ? $_GET['id'] : 'no_id',
        'service_id_detected' => $serviceId,
        'vars_keys' => array_keys($vars),
        'current_page' => basename($_SERVER['PHP_SELF']),
        'prueba_conexion' => [
            'success' => $pruebaConexion['datos_disponibles'],
            'total_tareas_encontradas' => isset($pruebaConexion['preview_data']['detalle_tareas']) ? count($pruebaConexion['preview_data']['detalle_tareas']) : 0
        ]
    ];
        





    return '
        <script>

            $(document).ready(function() {
                
                // TIMEOUT para asegurar que el DOM esté completamente cargado
                setTimeout(function() {
                    
                    // =============================================================
                    // PASO 1: VERIFICAR Y AGREGAR LA PESTAÑA DE DEBUG
                    // =============================================================
                    // Buscar si existe algún elemento nav-tabs en la página
                    if ($(".nav-tabs").length > 0) {
                        
                        // Crear el HTML de la nueva pestaña con icono y versión
                        var yeisonTab = \'<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#yeison-debug-tab"><i class="fas fa-tools"></i> Gurux Informe Horas</a></li>\';
                        // Agregar la pestaña al primer nav-tabs encontrado
                        $(".nav-tabs").first().append(yeisonTab);
                        
                        // =============================================================
                        // PASO 2: CARGAR LIBRERÍAS NECESARIAS PARA PDF CON CAPTURA
                        // =============================================================
                        // Variables para controlar el estado de carga
                        window.jsPDFLoaded = false;
                        window.html2canvasLoaded = false;
                        
                        // Cargar jsPDF
                        var jsPDFScript = document.createElement(\'script\');
                        jsPDFScript.src = \'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js\';
                        
                        jsPDFScript.onload = function() {
                            window.jsPDFLoaded = true;

                            updateLibraryStatus();
                        };
                        
                        jsPDFScript.onerror = function() {
                            console.error(\'❌ Error al cargar jsPDF\');
                            window.jsPDFLoaded = false;
                            updateLibraryStatus();
                        };
                        
                        document.head.appendChild(jsPDFScript);
                        // Cargar html2canvas
                        var html2canvasScript = document.createElement(\'script\');
                        html2canvasScript.src = \'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js\';
                        
                        html2canvasScript.onload = function() {
                            window.html2canvasLoaded = true;
                            console.log(\'✅ html2canvas cargado correctamente\');
                            updateLibraryStatus();
                        };
                        
                        html2canvasScript.onerror = function() {
                            console.error(\'❌ Error al cargar html2canvas\');
                            window.html2canvasLoaded = false;
                            updateLibraryStatus();
                        };
                        
                        document.head.appendChild(html2canvasScript);
                        // Función para actualizar el indicador de estado de las librerías
                        function updateLibraryStatus() {
                            setTimeout(function() {
                                var status = \'\';
                                var color = \'#ffc107\'; // amarillo por defecto
                                
                                if (window.jsPDFLoaded && window.html2canvasLoaded) {
                                    status = \'✅ Todas las librerías listas\';
                                    color = \'#28a745\';
                                } else if (!window.jsPDFLoaded && !window.html2canvasLoaded) {
                                    status = \'⏳ Cargando librerías...\';
                                    color = \'#ffc107\';
                                } else if (window.jsPDFLoaded && !window.html2canvasLoaded) {
                                    status = \'⏳ Cargando html2canvas...\';
                                    color = \'#ffc107\';
                                } else if (!window.jsPDFLoaded && window.html2canvasLoaded) {
                                    status = \'⏳ Cargando jsPDF...\';
                                    color = \'#ffc107\';
                                }
                                
                                $(\'#jspdf-indicator\').html(status).css(\'color\', color);
                            }, 100);
                        }
                            

                        // Convertir datos PHP a JavaScript usando json_encode
                        var conexionStatus = ' . json_encode($pruebaConexion) . ';
                        
                        // *** GUARDAR EN WINDOW PARA ACCESO GLOBAL ***
                        window.yeisonDebugData = conexionStatus;
                        
                        // Determinar iconos y colores según el estado de la conexión
                        var statusIcon = conexionStatus.datos_disponibles ? "✅" : (conexionStatus.error_message ? "❌" : "⚠️");
                        var statusColor = conexionStatus.datos_disponibles ? "#28a745" : (conexionStatus.error_message ? "#dc3545" : "#ffc107");
                        

                        // Iniciar construcción del contenido de la pestaña
                        var yeisonContent = \'<div class="tab-pane fade" id="yeison-debug-tab">\' +
                            \'<div class="container-fluid mt-3">\' +
                            \'<h3 class="text-center mb-4">📊 Informe de horas trabajadas</h3>\' +
                            

                            \'<div class="text-center mb-4">\' +
                            \'<button id="generar-pdf-yeison" class="btn btn-primary btn-lg" onclick="generarPDFYeison()">\' +
                            \'📄 Descargar PDF\' +
                            \'<span id="pdf-loading" style="display:none; margin-left:10px;">⏳</span>\' +
                            \'</button>\'



                            
                            // INFORMACIÓN BÁSICA
                            \'<div class="alert alert-success text-center"><strong>✅ Versión corregida con validaciones mejoradas!</strong></div>\' +
                            \'<div class="alert alert-info text-center"><strong>📊 Service ID: ' . (isset($serviceId) ? $serviceId : 'No definido') . '</strong></div>\' +
                            
                            // =============================================================
                            // PASO 5: SECCIÓN DE ESTADO DE CONEXIONES
                            // =============================================================
                            \'<div class="card mb-4">\' +
                            \'<div class="card-header" style="background-color: \' + statusColor + \'; color: white;"><h5>\' + statusIcon + \' PASO 4: Datos de Clockify</h5></div>\' +
                            \'<div class="card-body"><p><strong>🔗 Conexión WHMCS: </strong>\' + (conexionStatus.conexion_whmcs ? "✅ OK" : "❌ Error") + \'</p>\' +
                            \'<p><strong>⏰ Conexión Clockify: </strong>\' + (conexionStatus.conexion_clockify ? "✅ OK" : "❌ Error") + \'</p>\' +
                            \'<p><strong>📊 Datos disponibles: </strong>\' + (conexionStatus.datos_disponibles ? "✅ SÍ" : "❌ NO") + \'</p></div></div>\';
                        

                        // =============================================================
                        // GENERACIÓN DE GRÁFICO DONUT PARA DISTRIBUCIÓN DE TAREAS CLOCKIFY
                        // =============================================================

                        // Verificar si existen tareas detalladas en los datos
                        if (conexionStatus.preview_data.detalle_tareas && conexionStatus.preview_data.detalle_tareas.length > 0) {
                            
                            // =============================================================
                            // PASO 1: INICIALIZAR CONTENEDOR PRINCIPAL
                            // =============================================================
                            yeisonContent += \'<div class="card mb-4" style="border: 0 !important; background-color: transparent !important; box-shadow: none !important;">\' +
                                            \'<div class="card-body">\';
                            
                            // =============================================================
                            // PASO 2: ITERAR SOBRE CADA TAREA INDIVIDUAL (PLACEHOLDER)
                            // =============================================================
                            // Nota: Este forEach parece estar incompleto en el código original
                            conexionStatus.preview_data.detalle_tareas.forEach(function(tarea, index) {
                                // TODO: Agregar lógica específica para procesar cada tarea aquí
                                yeisonContent += \'</div>\'; // Cierre temporal - revisar lógica
                            });
                            
                            // =============================================================
                            // PASO 3: CALCULAR TIEMPO TOTAL EN SEGUNDOS
                            // =============================================================
                            var tiempoTotalSegundos = 0;
                            conexionStatus.preview_data.detalle_tareas.forEach(function(tarea) {
                                tiempoTotalSegundos += parseInt(tarea.tiempo_segundos) || 0;
                            });
                            
                            // =============================================================
                            // PASO 4: DEFINIR PALETA DE COLORES PARA EL GRÁFICO
                            // =============================================================
                            var coloresGrafico = [
                                \'#FF6B6B\', // Rojo coral
                                \'#4ECDC4\', // Turquesa
                                \'#45B7D1\', // Azul claro
                                \'#96CEB4\', // Verde menta
                                \'#FFEAA7\', // Amarillo suave
                                \'#DDA0DD\', // Violeta
                                \'#98D8C8\', // Verde agua
                                \'#F7DC6F\'  // Amarillo dorado
                            ];
                            
                            // =============================================================
                            // PASO 5: PREPARAR DATOS PARA EL GRÁFICO DONUT
                            // =============================================================
                            var datosDonut = [];
                            conexionStatus.preview_data.detalle_tareas.forEach(function(tarea, index) {
                                var segundos = parseInt(tarea.tiempo_segundos) || 0;
                                var porcentaje = tiempoTotalSegundos > 0 ? ((segundos / tiempoTotalSegundos) * 100) : 0;
                                
                                datosDonut.push({
                                    nombre: tarea.tarea_nombre || \'Sin nombre\',
                                    tiempo: tarea.tiempo_formateado || \'0:00:00\',
                                    segundos: segundos,
                                    porcentaje: porcentaje.toFixed(1),
                                    color: coloresGrafico[index % coloresGrafico.length]
                                });
                            });
                            
                            // =============================================================
                            // PASO 6: GENERAR ESTRUCTURA HTML DEL GRÁFICO
                            // =============================================================
                            yeisonContent += \'<div class="mt-4">\' +
                                            \'<div class="row">\' +
                                            
                                            // Contenedor del gráfico SVG
                                            \'<div class="col-md-6 text-center">\' +
                                            \'<div style="position: relative; display: inline-block;">\' +
                                            \'<svg width="300" height="300" viewBox="0 0 300 300">\';
                            
                            // =============================================================
                            // PASO 7: CONFIGURACIÓN DEL GRÁFICO DONUT
                            // =============================================================
                            var acumuladoAngulo = 0;        // Ángulo acumulado para posicionar segmentos
                            var radio = 120;               // Radio exterior del donut
                            var grosor = 40;               // Grosor del anillo
                            var radioInterno = radio - grosor; // Radio interior del donut
                            var centroX = 150;             // Centro X del SVG
                            var centroY = 150;             // Centro Y del SVG
                            
                            // =============================================================
                            // PASO 8: GENERAR SEGMENTOS DEL DONUT
                            // =============================================================
                            datosDonut.forEach(function(dato, index) {
                                // Calcular ángulo del segmento (360° = 2π radianes)
                                var anguloSegmento = (dato.porcentaje / 100) * 2 * Math.PI;
                                
                                // ==============================================
                                // Calcular coordenadas del arco exterior
                                // ==============================================
                                var x1 = centroX + radio * Math.cos(acumuladoAngulo);
                                var y1 = centroY + radio * Math.sin(acumuladoAngulo);
                                var x2 = centroX + radio * Math.cos(acumuladoAngulo + anguloSegmento);
                                var y2 = centroY + radio * Math.sin(acumuladoAngulo + anguloSegmento);
                                
                                // ==============================================
                                // Calcular coordenadas del arco interior
                                // ==============================================
                                var x1Inner = centroX + radioInterno * Math.cos(acumuladoAngulo);
                                var y1Inner = centroY + radioInterno * Math.sin(acumuladoAngulo);
                                var x2Inner = centroX + radioInterno * Math.cos(acumuladoAngulo + anguloSegmento);
                                var y2Inner = centroY + radioInterno * Math.sin(acumuladoAngulo + anguloSegmento);
                                
                                // Determinar si es un arco grande (más de 180°)
                                var largeArcFlag = anguloSegmento > Math.PI ? 1 : 0;
                                
                                // ==============================================
                                // Crear el path SVG del segmento donut
                                // ==============================================
                                var pathData = \'M \' + x1 + \' \' + y1 + \' \' +           // Mover al punto inicial exterior
                                            \'A \' + radio + \' \' + radio + \' 0 \' + largeArcFlag + \' 1 \' + x2 + \' \' + y2 + \' \' + // Arco exterior
                                            \'L \' + x2Inner + \' \' + y2Inner + \' \' +   // Línea al punto interior
                                            \'A \' + radioInterno + \' \' + radioInterno + \' 0 \' + largeArcFlag + \' 0 \' + x1Inner + \' \' + y1Inner + \' \' + // Arco interior
                                            \'Z\'; // Cerrar path
                                
                                // Agregar el segmento al SVG con tooltip Controla el mouse encima los nombres 
                                yeisonContent += \'<path d="\' + pathData + \'" \' +
                                                \'fill="\' + dato.color + \'" \' +
                                                \'stroke="#fff" \' +
                                                \'stroke-width="2" \' +
                                                \'data-toggle="tooltip" \' +
                                                \'title="\' + dato.nombre + \': \' + dato.porcentaje + \'% (\' + dato.tiempo + \')"></path>\';
                                
                                // Acumular ángulo para el siguiente segmento
                                acumuladoAngulo += anguloSegmento;
                            });
                            
                            // =============================================================
                            // PASO 9: CERRAR SVG Y AGREGAR TEXTO CENTRAL
                            // =============================================================
                            yeisonContent += \'</svg>\' +
                                            
                                            // Texto central con información resumida
                                            \'<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">\' +
                                            \'<div style="font-size: 14px; font-weight: bold; color: #666;">TOTAL</div>\' +
                                            \'<div style="font-size: 16px; font-weight: bold; color: #333;">\' + 
                                            (conexionStatus.preview_data.tiempo_total || \'0:00:00\') + \'</div>\' +
                                            \'<div style="font-size: 12px; color: #999;">\' + datosDonut.length + \' tareas</div>\' +
                                            \'</div>\' +
                                            \'</div></div>\' +
                                            
                                            // Contenedor de la leyenda
                                            \'<div class="col-md-6">\' +
                                            \'<div class="legend-container">\';
                            
                            // =============================================================
                            // PASO 10: GENERAR LEYENDA CON COLORES Y PORCENTAJES
                            // =============================================================
                            datosDonut.forEach(function(dato) {
                                yeisonContent += \'<div class="legend-item mb-2">\' +
                                                \'<div class="d-flex align-items-center">\' +
                                                \'<div style="width: 20px; height: 20px; background-color: \' + dato.color + \'; margin-right: 10px; border-radius: 3px;"></div>\' +
                                                \'<span style="font-size: 13px;">\' +
                                                \'<strong>\' + dato.nombre + \'</strong> - \' + dato.porcentaje + \'% (\' + dato.tiempo + \')\' +
                                                \'</span>\' +
                                                \'</div>\' +
                                                \'</div>\';
                            });
                            
                            // =============================================================
                            // PASO 11: CERRAR CONTENEDORES
                            // =============================================================
                            yeisonContent += \'</div></div>\' +     // Cerrar leyenda y columna
                                            \'</div></div>\' +      // Cerrar fila y contenedor del gráfico
                                            \'</div></div>\';       // Cerrar card-body y card principal
                            
                        } else {
                            // =============================================================
                            // PASO 12: MENSAJE CUANDO NO HAY TAREAS DISPONIBLES
                            // =============================================================
                            yeisonContent += \'<div class="card mb-4">\' +
                                            \'<div class="card-header bg-warning text-dark">\' +
                                            \'<h5>⚠️ NO SE ENCONTRARON TAREAS DETALLADAS</h5>\' +
                                            \'</div>\' +
                                            \'<div class="card-body">\' +
                                            \'<p><strong>Posibles causas:</strong></p>\' +
                                            \'<ul>\' +
                                            \'<li>No hay datos de tareas en la respuesta de Clockify</li>\' +
                                            \'<li>La estructura de datos no coincide con lo esperado</li>\' +
                                            \'<li>Error en el procesamiento de las tareas</li>\' +
                                            \'</ul>\' +
                                            \'</div>\' +
                                            \'</div>\';
                        }
                        
































                        // Cerrar la sección principal de datos
                        yeisonContent += \'</div>\';
                        
                        // Verificar si existen las dependencias antes de iterarlas
                        if (conexionStatus.dependencias && typeof conexionStatus.dependencias === \'object\') {
                            // Iterar sobre cada dependencia y mostrar su estado
                            Object.keys(conexionStatus.dependencias).forEach(function(dep) {
                                var depInfo = conexionStatus.dependencias[dep];
                                var depIcon = depInfo.cargado ? "✅" : (depInfo.existe ? "⚠️" : "❌");
                            });
                        } 
                        
                        // Cerrar sección de dependencias y contenedor principal
                        yeisonContent += \'</div></div></div></div>\';
                        
                        // =============================================================
                        // PASO 12: AGREGAR TODO EL CONTENIDO AL DOM
                        // =============================================================
                        $(".tab-content").append(yeisonContent);
                        
                        // Log de confirmación en consola
                        console.log("✅ Pestaña Yeison Debug v4.1 agregada correctamente");
                        console.log("📊 Datos cargados:", conexionStatus);
                        
                        // Inicializar estado de librerías
                        updateLibraryStatus();
                        
                    } else {
                        // Error: No se encontraron pestañas nav-tabs en la página
                        console.log("❌ No se encontraron pestañas nav-tabs en la página");
                    }
                }, 1000); // Esperar 1 segundo para asegurar carga completa del DOM
            });


            $(document).on(\'click\', \'#generar-pdf-yeison\', function() {
                
                // *** OBTENER DATOS DESDE WINDOW ***
                var conexionStatus = window.yeisonDebugData;
                
                if (!conexionStatus) {
                    alert(\'❌ Error: No se encontraron los datos de conexión.\\n\\nPor favor recarga la página e intenta de nuevo.\');
                    return;
                }
                
                // =============================================================
                // VERIFICACIÓN CORREGIDA DE LIBRERÍAS CARGADAS
                // =============================================================
                function checkLibrariesLoaded() {
                    // Verificación más robusta de las librerías
                    var jsPDFOk = false;
                    var html2canvasOk = false;
                    
                    // Verificar jsPDF - múltiples formas de acceso
                    if (typeof window.jsPDF !== \'undefined\') {
                        jsPDFOk = true;
                    } else if (typeof jsPDF !== \'undefined\') {
                        jsPDFOk = true;
                    } else if (window.jspdf && window.jspdf.jsPDF) {
                        jsPDFOk = true;
                    }
                    
                    // Verificar html2canvas
                    if (typeof html2canvas !== \'undefined\') {
                        html2canvasOk = true;
                    }
                    
                    console.log(\'🔍 Estado librerías:\', {
                        jsPDF: jsPDFOk,
                        html2canvas: html2canvasOk,
                        windowJsPDF: typeof window.jsPDF,
                        globalJsPDF: typeof jsPDF,
                        windowJspdf: typeof window.jspdf,
                        globalHtml2canvas: typeof html2canvas
                    });
                    
                    return jsPDFOk && html2canvasOk;
                }
                
                if (!checkLibrariesLoaded()) {
                    alert(\'❌ Las librerías no están completamente cargadas.\\n\\nPor favor espera unos segundos y vuelve a intentar.\');
                    return;
                }
                
                // =============================================================
                // MOSTRAR ESTADO DE PROCESAMIENTO - CORREGIDO
                // =============================================================
                var $button = $(\'#generar-pdf-yeison\');
                var $loading = $(\'#pdf-loading\');
                
                // Deshabilitar botón y mostrar loading
                $button.prop(\'disabled\', true);
                $loading.show();
                
                // Función para restaurar el botón
                function restoreButton() {
                    $button.prop(\'disabled\', false);
                    $loading.hide();
                }
                

                try {
                    // =============================================================
                    // CAPTURAR EL CONTENIDO VISUAL - ELEMENTO CORREGIDO
                    // =============================================================
                    var elementToCapture = document.getElementById(\'yeison-debug-tab\');
                    
                    if (!elementToCapture) {
                        throw new Error(\'No se encontró el elemento a capturar (#yeison-debug-tab)\');
                    }
                    
                    // Configuración de html2canvas para mejor calidad
                    var html2canvasOptions = {
                        useCORS: true,
                        allowTaint: false,
                        scale: 1.5, // Reducir escala para evitar problemas de memoria
                        scrollX: 0,
                        scrollY: 0,
                        width: elementToCapture.scrollWidth,
                        height: elementToCapture.scrollHeight,
                        backgroundColor: \'#ffffff\',
                        logging: true // Activar logging para debug
                    };
                    
                    console.log(\'🔄 Iniciando captura con html2canvas...\');
                    console.log(\'📏 Dimensiones elemento:\', elementToCapture.scrollWidth + \'x\' + elementToCapture.scrollHeight);
                    
                    // =============================================================
                    // OCULTAR BOTÓN ANTES DE CAPTURAR PARA QUE NO APAREZCA EN EL PDF
                    // =============================================================
                    $(\'#generar-pdf-yeison\').hide();




                    html2canvas(elementToCapture, html2canvasOptions).then(function(canvas) {
                        
                        console.log(\'✅ Captura completada, generando PDF...\');
                        console.log(\'📏 Dimensiones canvas:\', canvas.width + \'x\' + canvas.height);
                        
                        // =============================================================
                        // GENERAR PDF CON LA IMAGEN CAPTURADA - LÓGICA CORREGIDA
                        // =============================================================
                        
                        // Obtener jsPDF de manera más robusta
                        var jsPDFConstructor;
                        if (typeof window.jsPDF !== \'undefined\') {
                            jsPDFConstructor = window.jsPDF;
                        } else if (typeof jsPDF !== \'undefined\') {
                            jsPDFConstructor = jsPDF;
                        } else if (window.jspdf && window.jspdf.jsPDF) {
                            jsPDFConstructor = window.jspdf.jsPDF;
                        }
                        
                        if (!jsPDFConstructor) {
                            throw new Error(\'No se pudo acceder a jsPDF\');
                        }
                        
                        // Crear el documento PDF
                        var doc = new jsPDFConstructor(\'p\', \'mm\', \'a4\');
                        
                        // Obtener dimensiones del canvas
                        var imgWidth = canvas.width;
                        var imgHeight = canvas.height;
                        
                        // Dimensiones de página A4 en mm
                        var pageWidth = 210;
                        var pageHeight = 297;
                        var margin = 10;
                        var usableWidth = pageWidth - (margin * 2);
                        var usableHeight = pageHeight - (margin * 2);
                        
                        // Calcular escala para que quepa en la página
                        var ratio = Math.min(usableWidth / (imgWidth * 0.264583), usableHeight / (imgHeight * 0.264583));
                        var scaledWidth = (imgWidth * 0.264583) * ratio;
                        var scaledHeight = (imgHeight * 0.264583) * ratio;
                        
                        console.log(\'📐 Cálculos PDF:\', {
                            originalSize: imgWidth + \'x\' + imgHeight + \'px\',
                            ratio: ratio,
                            scaledSize: scaledWidth.toFixed(2) + \'x\' + scaledHeight.toFixed(2) + \'mm\'
                        });
                        
                        // Convertir canvas a imagen
                        var imgData = canvas.toDataURL(\'image/png\', 0.8); // Reducir calidad para optimizar
                        
                        // Centrar imagen en la página
                        var xPos = (pageWidth - scaledWidth) / 2;
                        var yPos = margin;
                        
                        // Verificar si necesita múltiples páginas
                        if (scaledHeight > usableHeight) {
                            // Múltiples páginas
                            var pagesNeeded = Math.ceil(scaledHeight / usableHeight);
                            console.log(\'📄 Se necesitan \' + pagesNeeded + \' páginas\');
                            
                            for (var i = 0; i < pagesNeeded; i++) {
                                if (i > 0) {
                                    doc.addPage();
                                }
                                
                                // Calcular la porción de imagen para esta página
                                var sourceY = (imgHeight / pagesNeeded) * i;
                                var sourceHeight = imgHeight / pagesNeeded;
                                
                                // Crear canvas temporal para esta página
                                var tempCanvas = document.createElement(\'canvas\');
                                var tempCtx = tempCanvas.getContext(\'2d\');
                                tempCanvas.width = imgWidth;
                                tempCanvas.height = sourceHeight;
                                
                                // Dibujar la porción correspondiente
                                tempCtx.drawImage(canvas, 0, sourceY, imgWidth, sourceHeight, 0, 0, imgWidth, sourceHeight);
                                
                                var tempImgData = tempCanvas.toDataURL(\'image/png\', 0.8);
                                doc.addImage(tempImgData, \'PNG\', xPos, yPos, scaledWidth, usableHeight);
                            }
                        } else {
                            // Una sola página
                            doc.addImage(imgData, \'PNG\', xPos, yPos, scaledWidth, scaledHeight);
                        }














                        // =============================================================
                        // DESCARGAR EL PDF CON NOMBRE PERSONALIZADO
                        // =============================================================

                        // Obtener la fecha actual
                        var fecha = new Date();
                        var año = fecha.getFullYear();
                        var mes = String(fecha.getMonth() + 1).padStart(2, \'0\'); // Mes con 2 dígitos



                        // Obtener el nombre del cliente desde los datos de conexión - VERSIÓN MEJORADA
                        var nombreCliente = \'cliente\'; // Valor por defecto

                        // Intentar obtener el nombre del cliente de diferentes fuentes posibles (orden de prioridad)
                        if (conexionStatus.preview_data && conexionStatus.preview_data.cliente_clockify) {
                            // Primera prioridad: cliente_clockify desde preview_data
                            nombreCliente = conexionStatus.preview_data.cliente_clockify;
                        } else if (conexionStatus.cliente_clockify) {
                            // Segunda prioridad: cliente_clockify directo
                            nombreCliente = conexionStatus.cliente_clockify;
                        } else if (conexionStatus.preview_data && conexionStatus.preview_data.cliente_nombre) {
                            // Tercera prioridad: cliente_nombre desde preview_data
                            nombreCliente = conexionStatus.preview_data.cliente_nombre;
                        } else if (conexionStatus.cliente_nombre) {
                            // Cuarta prioridad: cliente_nombre directo
                            nombreCliente = conexionStatus.cliente_nombre;
                        } else if (conexionStatus.client_name) {
                            // Quinta prioridad: client_name directo
                            nombreCliente = conexionStatus.client_name;
                        } else if (conexionStatus.preview_data && conexionStatus.preview_data.client_name) {
                            // Sexta prioridad: client_name desde preview_data
                            nombreCliente = conexionStatus.preview_data.client_name;
                        } else if (conexionStatus.clockify_client_name) {
                            // Séptima prioridad: clockify_client_name directo
                            nombreCliente = conexionStatus.clockify_client_name;
                        } else if (conexionStatus.preview_data && conexionStatus.preview_data.clockify_client_name) {
                            // Octava prioridad: clockify_client_name desde preview_data
                            nombreCliente = conexionStatus.preview_data.clockify_client_name;
                        }


                        // Limpiar el nombre del cliente (quitar espacios, caracteres especiales, etc.)
                        nombreCliente = nombreCliente.toString()
                            .toLowerCase()
                            .replace(/\\s+/g, \'-\')           // Espacios por guiones
                            .replace(/[áàäâ]/g, \'a\')        // Acentos
                            .replace(/[éèëê]/g, \'e\')
                            .replace(/[íìïî]/g, \'i\')
                            .replace(/[óòöô]/g, \'o\')
                            .replace(/[úùüû]/g, \'u\')
                            .replace(/ñ/g, \'n\')
                            .replace(/[^a-z0-9\\-]/g, \'\')    // Solo letras, números y guiones
                            .replace(/\\-+/g, \'-\')           // Múltiples guiones por uno solo
                            .replace(/^\\-|\\-$/g, \'\');       // Quitar guiones al inicio y final

                        // Si después de limpiar queda vacío, usar valor por defecto
                        if (nombreCliente === \'\' || nombreCliente === \'cliente\') {
                            nombreCliente = \'cliente-sin-nombre\';
                        }


                        // Generar el nombre del archivo
                        var filename = \'reporte-\' + nombreCliente + \'-\' + año.toString().slice(-2) + \'_\' + mes + \'.pdf\';
                        

                        doc.save(filename);
                        
                        // =============================================================
                        // MOSTRAR BOTÓN NUEVAMENTE DESPUÉS DE GENERAR EL PDF
                        // =============================================================
                        $(\'#generar-pdf-yeison\').show();
                        
                        // Restaurar botón
                        restoreButton();
                        
                    }).catch(function(error) {
                        // Error en la captura con html2canvas
                        console.error(\'❌ Error en html2canvas:\', error);
                        alert(\'❌ Error al capturar la imagen:\\n\\n\' + error.message + \'\\n\\nIntenta de nuevo en unos segundos.\');
                        
                        // =============================================================
                        // MOSTRAR BOTÓN EN CASO DE ERROR
                        // =============================================================
                        $(\'#generar-pdf-yeison\').show();
                        restoreButton();
                    });






















                    
                } catch (error) {
                    // Error general en el proceso
                    console.error(\'❌ Error general en captura PDF:\', error);
                    alert(\'❌ Error en el proceso de captura:\\n\\n\' + error.message);
                    
                    // =============================================================
                    // MOSTRAR BOTÓN EN CASO DE ERROR GENERAL
                    // =============================================================
                    $(\'#generar-pdf-yeison\').show();
                    restoreButton();
                }
















            });

            window.generarPDFYeison = function() {
                $(\'#generar-pdf-yeison\').trigger(\'click\');
            };

        </script>';
    
    
    });



if (!function_exists('debugEstructuraClockify')) {
    function debugEstructuraClockify($serviceId) {
        try {
            logActivity("=== YEISON DEBUG ESTRUCTURA CLOCKIFY ===");
            logActivity("Service ID: {$serviceId}");
            
            $dependenciasOk = verificarDependenciasYeison();
            if (!$dependenciasOk['WhMiConexion'] || !$dependenciasOk['ClockifyClient']) {
                logActivity("ERROR: Dependencias no disponibles");
                return false;
            }
            
            $whmcsClient = new WhMiConexion();
            $whmcsResult = $whmcsClient->getReportData($serviceId);
            
            if (!$whmcsResult || !$whmcsResult['success']) {
                logActivity("ERROR: No se pudieron obtener datos de WHMCS");
                return false;
            }
            
            $clockifyInfo = $whmcsResult['data']['clockify'] ?? null;
            if (!$clockifyInfo) {
                logActivity("ERROR: No hay configuración de Clockify en WHMCS");
                return false;
            }
            
            $clientName = $clockifyInfo['client_name'];
            $targetMonth = $clockifyInfo['target_month'];
            
            logActivity("Cliente Clockify: {$clientName}");
            logActivity("Mes objetivo: {$targetMonth}");
            
            $clockifyClient = new ClockifyClient();
            $clockifyResult = $clockifyClient->getClientMonthlyBilledSummary($clientName, $targetMonth);
            
            if (!$clockifyResult || !$clockifyResult['success']) {
                logActivity("ERROR: No se pudieron obtener datos de Clockify");
                return false;
            }
            
            $data = $clockifyResult['data'];
            
            logActivity("=== ESTRUCTURA DE DATOS CLOCKIFY ===");
            logActivity("Keys principales: " . json_encode(array_keys($data)));
            
            if (isset($data['projects'])) {
                logActivity("Número de proyectos: " . count($data['projects']));
                
                foreach ($data['projects'] as $index => $project) {
                    logActivity("--- Proyecto {$index} ---");
                    logActivity("Keys del proyecto: " . json_encode(array_keys($project)));
                    logActivity("Nombre: " . ($project['name'] ?? 'Sin nombre'));
                    
                    if (isset($project['tasks'])) {
                        logActivity("Número de tareas: " . count($project['tasks']));
                        
                        foreach ($project['tasks'] as $taskIndex => $task) {
                            logActivity("  -- Tarea {$taskIndex} --");
                            logActivity("  Keys de la tarea: " . json_encode(array_keys($task)));
                            logActivity("  Nombre: " . ($task['name'] ?? 'Sin nombre'));
                            logActivity("  Duración: " . ($task['duration'] ?? 'No definida') . " (tipo: " . gettype($task['duration'] ?? null) . ")");
                            
                            if ($taskIndex >= 2) { // Solo mostrar las primeras 3 tareas por proyecto
                                logActivity("  ... (más tareas omitidas para el debug)");
                                break;
                            }
                        }
                    } else {
                        logActivity("No hay tareas en este proyecto");
                    }
                    
                    if ($index >= 1) { // Solo mostrar los primeros 2 proyectos
                        logActivity("... (más proyectos omitidos para el debug)");
                        break;
                    }
                }
            } else {
                logActivity("ERROR: No se encontró la key 'projects' en los datos");
            }
            
            logActivity("=== FIN DEBUG ESTRUCTURA CLOCKIFY ===");
            return true;
            
        } catch (Exception $e) {
            logActivity("EXCEPCIÓN en debugEstructuraClockify: " . $e->getMessage());
            return false;
        }
    }
}

// Hook adicional para ejecutar debug automático cuando sea necesario
add_hook('ClientAreaFooterOutput', 10, function ($vars) {
    $is_clientarea = strpos($_SERVER['REQUEST_URI'], 'clientarea.php') !== false;
    $is_productdetails = isset($_GET['action']) && $_GET['action'] == 'productdetails';
    $debug_enabled = isset($_GET['yeison_debug']) && $_GET['yeison_debug'] == '1';
    
    if ($is_clientarea && $is_productdetails && $debug_enabled) {
        $serviceId = obtenerServiceIdYeison($vars);
        if ($serviceId > 0) {
            debugEstructuraClockify($serviceId);
        }
    }
    
    return '';
});

?>