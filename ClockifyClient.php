<?php
/**
 * Cliente para integracion con Clockify API
 * Archivo: /modules/addons/yeison_integracion_uno/clockify/ClockifyClient.php
 * 
 */

require_once __DIR__ . '/config.php';

class ClockifyClient {
    
    private $apiKey;
    private $workspaceId;
    private $baseUrl;
    
    public function __construct() {
        $this->apiKey = CLOCKIFY_API_KEY;
        $this->workspaceId = CLOCKIFY_WORKSPACE_ID;
        $this->baseUrl = CLOCKIFY_BASE_URL;
    }
    
    /**
     * Realiza una peticion HTTP a la API de Clockify
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        // Timeouts
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
        // User Agent
        curl_setopt($ch, CURLOPT_USERAGENT, defined('CLOCKIFY_USER_AGENT') ? CLOCKIFY_USER_AGENT : 'ClockifyClient/1.0');
        
        // Headers basicos
        $headers = [
            'X-Api-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // SSL - En produccion considera habilitar la verificacion
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Configurar metodo HTTP
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            default: // GET
                // No se necesita configuracion adicional para GET
                break;
        }
        
        // Debug log (opcional)
        error_log("Clockify API Request: {$method} {$url}" . ($data ? " with data: " . json_encode($data) : ""));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        curl_close($ch);
        
        // Manejo de errores
        if ($curlError) {
            throw new Exception("Error cURL #{$curlErrno}: {$curlError}. URL: {$url}");
        }
        
        if ($response === false) {
            throw new Exception("La peticion cURL fallo sin respuesta. URL: {$url}");
        }
        
        // Log de respuesta para debug
        error_log("Clockify API Response: HTTP {$httpCode}, Length: " . strlen($response));
        
        if ($httpCode >= 400) {
            $errorBody = $response ?: 'Sin contenido de error';
            throw new Exception("Error HTTP {$httpCode} en {$url}: {$errorBody}");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar JSON: " . json_last_error_msg() . ". Respuesta: " . substr($response, 0, 200));
        }
        
        return $decoded;
    }
    
    /**
     * Obtiene informacion del workspace actual
     */
    public function getWorkspaceInfo() {
        return $this->makeRequest("/workspaces/{$this->workspaceId}");
    }
    
    /**
     * Obtiene todos los clientes del workspace
     */
    public function getAllClients() {
        return $this->makeRequest("/workspaces/{$this->workspaceId}/clients");
    }
    
    /**
     * Obtiene un cliente especifico por nombre
     */
    public function getClientByName($clientName) {
        $clients = $this->getAllClients();
        
        foreach ($clients as $client) {
            if (strtolower($client['name']) === strtolower($clientName)) {
                return $client;
            }
        }
        
        return null;
    }
    
    /**
     * Obtiene todos los proyectos de un cliente especifico
     */
    public function getClientProjects($clientId) {
        $endpoint = "/workspaces/{$this->workspaceId}/projects";
        $allProjects = $this->makeRequest($endpoint);
        
        $clientProjects = [];
        foreach ($allProjects as $project) {
            if ($project['clientId'] === $clientId) {
                $clientProjects[] = $project;
            }
        }
        
        return $clientProjects;
    }
    
    /**
     * Obtiene solo los proyectos que empiezan con "Billed:"
     */
    public function getBilledProjects($clientId = null) {
        $endpoint = "/workspaces/{$this->workspaceId}/projects";
        $allProjects = $this->makeRequest($endpoint);
        
        $billedProjects = [];
        foreach ($allProjects as $project) {
            if (strpos($project['name'], BILLED_PROJECT_PREFIX) === 0) {
                if ($clientId === null || $project['clientId'] === $clientId) {
                    $billedProjects[] = $project;
                }
            }
        }
        
        return $billedProjects;
    }
    
    /**
     * Obtiene proyectos de un mes especifico (formato Billed: 25/MM)
     */
    public function getProjectsByMonth($month, $year = null) {
        if ($year === null) {
            $year = date('y'); // Ano actual en formato de 2 digitos
        }
        
        $monthFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
        $searchPattern = "Billed: {$year}/{$monthFormatted}";
        
        $allProjects = $this->makeRequest("/workspaces/{$this->workspaceId}/projects");
        
        $monthProjects = [];
        foreach ($allProjects as $project) {
            if (strpos($project['name'], $searchPattern) === 0) {
                $monthProjects[] = $project;
            }
        }
        
        return $monthProjects;
    }
    
    /**
     * Obtiene todas las tareas de un proyecto especifico
     */
    public function getProjectTasks($projectId) {
        return $this->makeRequest("/workspaces/{$this->workspaceId}/projects/{$projectId}/tasks");
    }
    




















    
    /**
     * Obtiene entradas de tiempo de un proyecto (usa POST con reports API)
     */
    public function getProjectTimeEntries($projectId, $startDate = null, $endDate = null) {
        $endpoint = "/workspaces/{$this->workspaceId}/reports/detailed";
        
        // Datos para el POST request
        $postData = [
            'dateRangeStart' => $startDate ?: '2024-01-01T00:00:00.000Z',
            'dateRangeEnd' => $endDate ?: date('Y-m-d\TH:i:s.000\Z'),
            'detailedFilter' => [
                'page' => 1,
                'pageSize' => 1000,
                'options' => [
                    'totals' => 'CALCULATE'
                ]
            ],
            'projects' => [
                'ids' => [$projectId],
                'contains' => 'CONTAINS',
                'status' => 'ALL'
            ]
        ];
        
        return $this->makeRequest($endpoint, 'POST', $postData);
    }

    /**
     * Metodo alternativo mas simple usando el endpoint correcto
     */
    public function getProjectTimeEntriesSimple($projectId, $userId = null, $startDate = null, $endDate = null) {
        // Si no hay userId, obtener el usuario actual
        if (!$userId) {
            $user = $this->getCurrentUser();
            $userId = $user['id'];
        }
        
        $endpoint = "/workspaces/{$this->workspaceId}/user/{$userId}/time-entries";
        
        // Parametros de consulta
        $params = [
            'project' => $projectId,
            'page-size' => 1000
        ];
        
        if ($startDate) {
            $params['start'] = $startDate;
        }
        if ($endDate) {
            $params['end'] = $endDate;
        }
        
        $queryString = http_build_query($params);
        return $this->makeRequest($endpoint . '?' . $queryString);
    }

    /**
     * Obtiene informacion del usuario actual
     */
    public function getCurrentUser() {
        return $this->makeRequest("/user");
    }

    /**
     * Metodo mejorado que combina ambos enfoques
     */
    public function getProjectTimeEntriesImproved($projectId, $startDate = null, $endDate = null) {
        try {
            // Intentar primero con el metodo del reporte (mas completo)
            return $this->getProjectTimeEntries($projectId, $startDate, $endDate);
        } catch (Exception $e) {
            // Si falla, usar el metodo simple
            error_log("Metodo de reporte fallo, usando metodo simple: " . $e->getMessage());
            return $this->getProjectTimeEntriesSimple($projectId, null, $startDate, $endDate);
        }
    }

    /**
     * Convierte duracion ISO 8601 a formato legible (dias, horas, minutos)
     */
    public function formatDuration($duration) {
        if (empty($duration) || $duration === 'PT0S') {
            return '0 minutos';
        }
        
        // Parsear duracion ISO 8601 (PT1H30M15S)
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
        
        $hours = isset($matches[1]) ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
        
        // Convertir todo a minutos
        $totalMinutes = ($hours * 60) + $minutes + ($seconds > 30 ? 1 : 0);
        
        if ($totalMinutes < 60) {
            return $totalMinutes . ' minutos';
        }
        
        $days = floor($totalMinutes / (24 * 60));
        $remainingHours = floor(($totalMinutes % (24 * 60)) / 60);
        $remainingMinutes = $totalMinutes % 60;
        
        $result = [];
        if ($days > 0) $result[] = $days . ' dia' . ($days > 1 ? 's' : '');
        if ($remainingHours > 0) $result[] = $remainingHours . ' hora' . ($remainingHours > 1 ? 's' : '');
        if ($remainingMinutes > 0) $result[] = $remainingMinutes . ' minuto' . ($remainingMinutes > 1 ? 's' : '');
        
        return implode(', ', $result);
    }
    
    /**
     * Obtiene informacion completa de un proyecto con sus tareas y tiempos
     * CORREGIDO: Ahora incluye tiempo sin tareas asignadas
     */
    public function getCompleteProjectInfo($projectId) {
        $project = $this->makeRequest("/workspaces/{$this->workspaceId}/projects/{$projectId}");
        $tasks = $this->getProjectTasks($projectId);
        
        // Usar el metodo mejorado para obtener time entries
        $timeEntries = $this->getProjectTimeEntriesImproved($projectId);
        
        // Calcular tiempo total del proyecto
        $totalDuration = 'PT0S';
        $timeWithoutTask = 'PT0S'; // Nuevo: tiempo sin tarea asignada
        
        // Agrupar tiempo por tarea
        $taskTimes = [];
        
        foreach ($timeEntries as $entry) {
            $duration = $entry['timeInterval']['duration'] ?? 'PT0S';
            
            // Sumar al tiempo total
            $totalDuration = $this->addDurations($totalDuration, $duration);
            
            // Verificar si la entrada tiene tarea asignada
            $taskId = $entry['taskId'] ?? null;
            
            if ($taskId) {
                // Tiempo con tarea asignada
                if (!isset($taskTimes[$taskId])) {
                    $taskTimes[$taskId] = 'PT0S';
                }
                $taskTimes[$taskId] = $this->addDurations($taskTimes[$taskId], $duration);
            } else {
                // Tiempo sin tarea asignada
                $timeWithoutTask = $this->addDurations($timeWithoutTask, $duration);
            }
        }
        
        // Agregar informacion de tiempo a cada tarea
        foreach ($tasks as &$task) {
            $task['totalTime'] = $taskTimes[$task['id']] ?? 'PT0S';
            $task['formattedTime'] = $this->formatDuration($task['totalTime']);
        }
        
        // Crear una "tarea virtual" para el tiempo sin tarea si existe
        $virtualTask = null;
        if ($timeWithoutTask !== 'PT0S') {
            $virtualTask = [
                'id' => 'sin-tarea',
                'name' => 'Tiempo sin tarea asignada',
                'totalTime' => $timeWithoutTask,
                'formattedTime' => $this->formatDuration($timeWithoutTask),
                'isVirtual' => true // Marca para identificar que es una tarea virtual
            ];
        }
        
        return [
            'project' => $project,
            'tasks' => $tasks,
            'timeEntries' => $timeEntries,
            'totalTime' => $totalDuration,
            'formattedTotalTime' => $this->formatDuration($totalDuration),
            'timeWithoutTask' => $timeWithoutTask, // Nuevo campo
            'formattedTimeWithoutTask' => $this->formatDuration($timeWithoutTask), // Nuevo campo
            'virtualTask' => $virtualTask // Nuevo campo
        ];
    }
    
    /**
     * Suma dos duraciones ISO 8601
     */
    private function addDurations($duration1, $duration2) {
        $total1 = $this->durationToSeconds($duration1);
        $total2 = $this->durationToSeconds($duration2);
        return $this->secondsToDuration($total1 + $total2);
    }
    
    /**
     * Convierte duracion ISO 8601 a segundos
     */
    private function durationToSeconds($duration) {
        if (empty($duration) || $duration === 'PT0S') return 0;
        
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
        
        $hours = isset($matches[1]) ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
        
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
    
    /**
     * Convierte segundos a duracion ISO 8601
     */
    private function secondsToDuration($seconds) {
        if ($seconds == 0) return 'PT0S';
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $duration = 'PT';
        if ($hours > 0) $duration .= $hours . 'H';
        if ($minutes > 0) $duration .= $minutes . 'M';
        if ($secs > 0) $duration .= $secs . 'S';
        
        return $duration;
    }


    /**
     * Busca proyectos "Billed:" de un cliente especifico para un mes dado
     * @param string $clientName - Nombre del cliente en Clockify
     * @param int $month - Mes (1-12)
     * @param int $year - Ano (opcional, por defecto ano actual)
     * @return array - Array de proyectos que coinciden
     */
    public function getProjectsByClientAndMonth($clientName, $month, $year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        
        // Formato del mes con 2 digitos
        $monthFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);
        
        // Buscar el cliente por nombre
        $client = $this->getClientByName($clientName);
        if (!$client) {
            throw new Exception("Cliente '{$clientName}' no encontrado en Clockify");
        }
        
        // Obtener todos los proyectos del cliente
        $clientProjects = $this->getClientProjects($client['id']);
        
        // Filtrar proyectos que contengan "Billed:" y el mes especifico
        $billedProjects = [];
        foreach ($clientProjects as $project) {
            $projectName = $project['name'];
            
            // Verificar que contenga "Billed:" y el mes
            if (strpos($projectName, 'Billed:') === 0) {
                // Extraer el mes del nombre del proyecto usando regex
                if (preg_match('/Billed:\s*\d+\/(\d{2})/', $projectName, $matches)) {
                    $projectMonth = $matches[1];
                    
                    if ($projectMonth === $monthFormatted) {
                        $billedProjects[] = $project;
                    }
                }
            }
        }
        
        return $billedProjects;
    }
    
    /**
     * Obtiene informacion completa de multiples proyectos con calculo de porcentajes
     * @param array $projects - Array de proyectos
     * @return array - Informacion detallada con porcentajes por tarea
     */
    public function getMultipleProjectsWithPercentages($projects) {
        $result = [
            'projects' => [],
            'totalTime' => 'PT0S',
            'formattedTotalTime' => '0 minutos',
            'allTasks' => [],
            'summary' => []
        ];
        
        $totalSeconds = 0;
        $allTasksData = [];
        
        foreach ($projects as $project) {
            // Obtener informacion completa de cada proyecto
            $projectInfo = $this->getCompleteProjectInfo($project['id']);
            
            // Sumar al tiempo total
            $projectSeconds = $this->durationToSeconds($projectInfo['totalTime']);
            $totalSeconds += $projectSeconds;
            
            // Agregar informacion del proyecto
            $result['projects'][] = [
                'info' => $project,
                'details' => $projectInfo
            ];
            
            // Procesar tareas de este proyecto
            foreach ($projectInfo['tasks'] as $task) {
                $taskSeconds = $this->durationToSeconds($task['totalTime']);
                if ($taskSeconds > 0) {
                    $taskKey = $task['name'];
                    
                    if (!isset($allTasksData[$taskKey])) {
                        $allTasksData[$taskKey] = [
                            'name' => $task['name'],
                            'totalSeconds' => 0,
                            'projects' => []
                        ];
                    }
                    
                    $allTasksData[$taskKey]['totalSeconds'] += $taskSeconds;
                    $allTasksData[$taskKey]['projects'][] = [
                        'projectName' => $project['name'],
                        'time' => $task['totalTime'],
                        'formattedTime' => $task['formattedTime']
                    ];
                }
            }
            
            // Agregar tiempo sin tarea si existe
            if ($projectInfo['virtualTask']) {
                $virtualTaskSeconds = $this->durationToSeconds($projectInfo['virtualTask']['totalTime']);
                if ($virtualTaskSeconds > 0) {
                    $virtualKey = 'Sin tarea asignada - ' . $project['name'];
                    
                    $allTasksData[$virtualKey] = [
                        'name' => $virtualKey,
                        'totalSeconds' => $virtualTaskSeconds,
                        'projects' => [
                            [
                                'projectName' => $project['name'],
                                'time' => $projectInfo['virtualTask']['totalTime'],
                                'formattedTime' => $projectInfo['virtualTask']['formattedTime']
                            ]
                        ]
                    ];
                }
            }
        }
        
        // Calcular porcentajes y formatear
        foreach ($allTasksData as $taskKey => $taskData) {
            $percentage = $totalSeconds > 0 ? ($taskData['totalSeconds'] / $totalSeconds) * 100 : 0;
            
            $result['allTasks'][] = [
                'name' => $taskData['name'],
                'totalTime' => $this->secondsToDuration($taskData['totalSeconds']),
                'formattedTime' => $this->formatDuration($this->secondsToDuration($taskData['totalSeconds'])),
                'percentage' => round($percentage, 2),
                'projects' => $taskData['projects']
            ];
        }
        
        // Ordenar tareas por tiempo (mayor a menor)
        usort($result['allTasks'], function($a, $b) {
            return $this->durationToSeconds($b['totalTime']) - $this->durationToSeconds($a['totalTime']);
        });
        
        // Establecer tiempo total
        $result['totalTime'] = $this->secondsToDuration($totalSeconds);
        $result['formattedTotalTime'] = $this->formatDuration($result['totalTime']);
        
        // Crear resumen
        $result['summary'] = [
            'totalProjects' => count($projects),
            'totalTasks' => count($result['allTasks']),
            'totalTime' => $result['totalTime'],
            'formattedTotalTime' => $result['formattedTotalTime']
        ];
        
        return $result;
    }
    
    /**
     * Obtiene el resumen de trabajo de un cliente para un mes especifico
     * @param string $clientName - Nombre del cliente en Clockify
     * @param int $month - Mes (1-12)
     * @param int $year - Ano (opcional)
     * @return array - Resumen completo del trabajo del mes
     */
    public function getClientMonthlyBilledSummary($clientName, $month, $year = null) {
        try {
            // Buscar proyectos del cliente para el mes
            $projects = $this->getProjectsByClientAndMonth($clientName, $month, $year);
            
            if (empty($projects)) {
                return [
                    'success' => false,
                    'message' => "No se encontraron proyectos 'Billed:' para el cliente '{$clientName}' en el mes {$month}",
                    'data' => null
                ];
            }
            
            // Obtener informacion detallada con porcentajes
            $detailedInfo = $this->getMultipleProjectsWithPercentages($projects);
            
            return [
                'success' => true,
                'message' => "Datos obtenidos correctamente",
                'data' => [
                    'client' => $clientName,
                    'month' => $month,
                    'year' => $year ?: date('Y'),
                    'monthName' => $this->getMonthName($month),
                    'projects' => $detailedInfo['projects'],
                    'tasks' => $detailedInfo['allTasks'],
                    'summary' => $detailedInfo['summary'],
                    'generatedAt' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error al obtener datos: " . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Convierte numero de mes a nombre en espanol
     * @param int $month - Numero del mes (1-12)
     * @return string - Nombre del mes
     */
    private function getMonthName($month) {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        return $months[$month] ?? 'Mes desconocido';
    }



}











