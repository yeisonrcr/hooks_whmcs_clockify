<?php
require_once 'config.php';

/**
 * Clase para manejar la conexion y operaciones con la API de WHMCS
 * Archivo corregido con todos los métodos funcionando correctamente
 */
class WhMiConexion {
    
    private $whmcsUrl;
    private $apiIdentifier;
    private $apiSecret;
    
    public function __construct() {
        $this->whmcsUrl = WHMCS_URL;
        $this->apiIdentifier = WHMCS_API_IDENTIFIER;
        $this->apiSecret = WHMCS_API_SECRET;
    }
    
    /**
     * Realiza una llamada a la API de WHMCS
     * @param string $action - Acción de la API a ejecutar
     * @param array $params - Parámetros adicionales
     * @return array - Respuesta de la API
     */
    private function apiCall($action, $params = []) {
        $postData = array_merge([
            'action' => $action,
            'username' => $this->apiIdentifier,
            'password' => $this->apiSecret,
            'responsetype' => WHMCS_RESPONSE_TYPE,
        ], $params);
        
        // Log de debug si esta habilitado - SIN CREDENCIALES
        if (WHMCS_DEBUG) {
            $this->log("API Call: $action", $this->sanitizeDataForLog($postData));
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->whmcsUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, WHMCS_CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, WHMCS_TIMEOUT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, WHMCS_USER_AGENT);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            $error = 'Error cURL: ' . curl_error($ch);
            $this->log($error, [], 'ERROR');
            curl_close($ch);
            throw new Exception($error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = "HTTP Error: $httpCode";
            $this->log($error, [], 'ERROR');
            throw new Exception($error);
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Error decodificando JSON: ' . json_last_error_msg();
            $this->log($error, ['response' => 'JSON_DECODE_ERROR'], 'ERROR');
            throw new Exception($error);
        }
        
        if ($result['result'] === 'error') {
            $error = 'Error API WHMCS: ' . $result['message'];
            $this->log($error, ['error_details' => $result['message']], 'ERROR');
            throw new Exception($error);
        }
        
        if (WHMCS_DEBUG) {
            $this->log("API Response: $action", $this->sanitizeResponseForLog($result), 'SUCCESS');
        }
        
        return $result;
    }
    
    /**
     * Sanitiza los datos para el log, ocultando informacion sensible
     */
    private function sanitizeDataForLog($data) {
        $sanitized = $data;
        
        // Campos sensibles que deben ser ocultados
        $sensitiveFields = ['username', 'password', 'api_key', 'token', 'secret', 'apikey'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $value = $sanitized[$field];
                if (strlen($value) > 6) {
                    // Mostrar solo los primeros 3 y ultimos 3 caracteres
                    $sanitized[$field] = substr($value, 0, 3) . '***' . substr($value, -3);
                } else {
                    $sanitized[$field] = '***';
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitiza la respuesta para el log, limitando su tamano
     */
    private function sanitizeResponseForLog($response) {
        // Limitar el tamano de la respuesta en los logs
        $responseString = json_encode($response);
        
        if (strlen($responseString) > 2000) {
            return [
                'result' => $response['result'] ?? 'unknown',
                'totalresults' => $response['totalresults'] ?? 0,
                'message' => 'Response truncated for logging (size: ' . strlen($responseString) . ' chars)',
                'first_item_sample' => $this->getFirstItemSample($response)
            ];
        }
        
        return $response;
    }
    
    /**
     * Obtiene una muestra del primer elemento para el log
     */
    private function getFirstItemSample($response) {
        // Buscar el primer elemento de datos util
        if (isset($response['clients']['client'][0])) {
            return [
                'type' => 'client',
                'id' => $response['clients']['client'][0]['id'] ?? null,
                'email' => $response['clients']['client'][0]['email'] ?? null
            ];
        }
        
        if (isset($response['products']['product'][0])) {
            return [
                'type' => 'product',
                'id' => $response['products']['product'][0]['id'] ?? null,
                'domain' => $response['products']['product'][0]['domain'] ?? null
            ];
        }
        
        return ['type' => 'unknown_structure'];
    }
    
    /**
     * Funcion de logging mejorada y segura
     */
    private function log($message, $data = [], $level = 'INFO') {
        if (!WHMCS_DEBUG) return;
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message";
        
        if (!empty($data)) {
            $logEntry .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        
        $logEntry .= "\n" . str_repeat('-', 80) . "\n";
        
        try {
            file_put_contents(WHMCS_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // Si no se puede escribir al log, continuar silenciosamente
        }
    }
    
    /**
     * Obtiene todos los clientes
     */
    public function obtenerClientes($limite = 100) {
        try {
            $response = $this->apiCall('GetClients', [
                'limitnum' => $limite,
                'stats' => true
            ]);
            
            return [
                'success' => true,
                'data' => $response,
                'total' => $response['totalresults'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene los productos/servicios de un cliente especifico
     */
    public function obtenerProductosCliente($clienteId) {
        try {
            $response = $this->apiCall('GetClientsProducts', [
                'clientid' => $clienteId,
                'stats' => true
            ]);
            
            return [
                'success' => true,
                'data' => $response,
                'total' => $response['totalresults'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene informacion detallada de un cliente incluyendo campos personalizados
     */
    public function obtenerDetalleCliente($clienteId) {
        try {
            $response = $this->apiCall('GetClientsDetails', [
                'clientid' => $clienteId,
                'stats' => true
            ]);
            
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene campos personalizados de un cliente
     */
    public function obtenerCamposPersonalizados($clienteId) {
        try {
            // Primero obtenemos los productos del cliente
            $response = $this->apiCall('GetClientsProducts', [
                'clientid' => $clienteId,
                'stats' => true
            ]);

            // Extraer campos personalizados de todos los productos
            $camposPersonalizados = [];
            $clockifyId = null;
            
            if (isset($response['products']['product']) && is_array($response['products']['product'])) {
                foreach ($response['products']['product'] as $producto) {
                    if (isset($producto['customfields']['customfield']) && is_array($producto['customfields']['customfield'])) {
                        foreach ($producto['customfields']['customfield'] as $campo) {
                            $nombreCampo = $campo['name'] ?? $campo['translated_name'] ?? 'Campo sin nombre';
                            $valorCampo = $campo['value'] ?? '';
                            
                            // Verificar si es el campo de Clockify
                            if (strtolower($nombreCampo) === 'id clockify' || strpos(strtolower($nombreCampo), 'clockify') !== false) {
                                $clockifyId = $valorCampo;
                            }
                            
                            $camposPersonalizados[$nombreCampo] = $valorCampo;
                        }
                    }
                }
            }

            return [
                'success' => true,
                'data' => $camposPersonalizados,
                'clockify_id' => $clockifyId,
                'total' => count($camposPersonalizados)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener campos personalizados: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene el ciclo de facturacion traducido
     */
    public function traducirCicloFacturacion($ciclo) {
        $ciclos = BILLING_CYCLES;
        return $ciclos[$ciclo] ?? $ciclo;
    }
    
    /**
     * Obtiene todos los dominios de un cliente
     */
    public function obtenerDominiosCliente($clienteId) {
        try {
            $response = $this->apiCall('GetClientsDomains', [
                'clientid' => $clienteId
            ]);
            
            return [
                'success' => true,
                'data' => $response,
                'total' => $response['totalresults'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica la conexion con la API
     */
    public function verificarConexion() {
        try {
            $response = $this->apiCall('GetClients', ['limitnum' => 1]);
            return [
                'success' => true,
                'message' => 'Conexion exitosa con WHMCS'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Funcion para limpiar logs antiguos (opcional)
     */
    public function limpiarLogsAntiguos($diasAntiguedad = 7) {
        if (!WHMCS_DEBUG || !file_exists(WHMCS_LOG_FILE)) {
            return false;
        }
        
        $fechaLimite = time() - ($diasAntiguedad * 24 * 60 * 60);
        $archivoLog = WHMCS_LOG_FILE;
        
        if (filemtime($archivoLog) < $fechaLimite) {
            try {
                unlink($archivoLog);
                $this->log("Log file cleaned due to age", [], 'INFO');
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Obtiene informacion completa de un producto/servicio incluyendo campos personalizados
     * @param int $serviceId - ID del servicio en WHMCS
     * @return array - Informacion completa del producto
     * CORREGIDO: Ahora usa apiCall() en lugar de makeRequest()
     */
    public function getProductWithCustomFields($serviceId) {
        try {
            $response = $this->apiCall('GetClientsProducts', [
                'serviceid' => $serviceId,
                'stats' => true
            ]);
            
            if (!isset($response['products']['product'][0])) {
                throw new Exception("Producto con ID {$serviceId} no encontrado");
            }
            
            $product = $response['products']['product'][0];
            
            // Procesar campos personalizados para facil acceso
            $customFieldsProcessed = [];
            if (isset($product['customfields']['customfield'])) {
                foreach ($product['customfields']['customfield'] as $field) {
                    $customFieldsProcessed[$field['name']] = $field['value'];
                }
            }
            
            $product['customfields_processed'] = $customFieldsProcessed;
            
            return [
                'success' => true,
                'data' => $product
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Obtiene el ID Clockify de un producto especifico
     * @param int $serviceId - ID del servicio en WHMCS
     * @return string|null - Valor del campo "ID Clockify" o null si no existe
     */
    public function getClockifyIdFromProduct($serviceId) {
        $productInfo = $this->getProductWithCustomFields($serviceId);
        
        if (!$productInfo['success']) {
            return null;
        }
        
        $customFields = $productInfo['data']['customfields_processed'] ?? [];
        return $customFields['ID Clockify'] ?? null;
    }
    
    /**
     * Convierte el ciclo de facturacion de WHMCS al mes correspondiente para buscar en Clockify
     * @param string $billingCycle - Ciclo de facturacion de WHMCS
     * @param array $productData - Datos completos del producto (opcional, para fechas especificas)
     * @return int - Numero del mes a buscar en Clockify
     */
    public function getBillingCycleMonth($billingCycle, $productData = null) {
        $currentMonth = (int)date('n'); // Mes actual sin ceros a la izquierda
        
        switch (strtolower($billingCycle)) {
            case 'monthly':
                // Para facturacion mensual, usar el mes actual
                return $currentMonth;
                
            case 'quarterly':
                // Para facturacion trimestral, usar el primer mes del trimestre actual
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    return 1; // Enero
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    return 4; // Abril
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    return 7; // Julio
                } else {
                    return 10; // Octubre
                }
                
            case 'semiannually':
                // Para facturacion semestral
                if ($currentMonth >= 1 && $currentMonth <= 6) {
                    return 1; // Enero
                } else {
                    return 7; // Julio
                }
                
            case 'annually':
                // Para facturacion anual, siempre enero
                return 1;
                
            case 'biennially':
                // Para facturacion bianual, siempre enero
                return 1;
                
            case 'triennially':
                // Para facturacion trianual, siempre enero
                return 1;
                
            default:
                // Por defecto, mes actual
                return $currentMonth;
        }
    }
    
    /**
     * Obtiene informacion del cliente
     * @param int $clientId - ID del cliente
     * @return array - Informacion del cliente
     * NUEVO: Método añadido para completar la funcionalidad
     */
    public function getClientInfo($clientId) {
        try {
            $response = $this->apiCall('GetClientsDetails', [
                'clientid' => $clientId,
                'stats' => true
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $response['id'] ?? $clientId,
                    'firstname' => $response['firstname'] ?? '',
                    'lastname' => $response['lastname'] ?? '',
                    'email' => $response['email'] ?? '',
                    'company' => $response['companyname'] ?? '',
                    'fullname' => trim(($response['firstname'] ?? '') . ' ' . ($response['lastname'] ?? ''))
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Obtiene informacion completa para generar el reporte de Clockify
     * @param int $serviceId - ID del servicio en WHMCS
     * @return array - Informacion completa necesaria para el reporte
     */
    public function getReportData($serviceId) {
        try {
            // Obtener informacion del producto
            $productResult = $this->getProductWithCustomFields($serviceId);
            
            if (!$productResult['success']) {
                throw new Exception("No se pudo obtener informacion del producto: " . $productResult['message']);
            }
            
            $product = $productResult['data'];
            
            // Verificar que existe el campo ID Clockify
            $clockifyId = $this->getClockifyIdFromProduct($serviceId);
            if (empty($clockifyId)) {
                throw new Exception("El producto no tiene configurado el campo 'ID Clockify'");
            }
            
            // Determinar el mes segun el ciclo de facturacion
            $billingCycle = $product['billingcycle'] ?? 'Monthly';
            $targetMonth = $this->getBillingCycleMonth($billingCycle, $product);
            
            // Obtener informacion del cliente
            $clientInfo = $this->getClientInfo($product['clientid']);
            
            return [
                'success' => true,
                'data' => [
                    'service' => [
                        'id' => $serviceId,
                        'name' => $product['name'] ?? 'Producto sin nombre',
                        'domain' => $product['domain'] ?? '',
                        'status' => $product['status'] ?? 'Unknown',
                        'billingcycle' => $billingCycle,
                        'nextduedate' => $product['nextduedate'] ?? '',
                        'regdate' => $product['regdate'] ?? ''
                    ],
                    'client' => $clientInfo['success'] ? $clientInfo['data'] : null,
                    'clockify' => [
                        'client_name' => $clockifyId,
                        'target_month' => $targetMonth,
                        'target_month_name' => $this->getMonthName($targetMonth),
                        'current_year' => date('Y')
                    ],
                    'generated_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
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
?>