# 🚀 WHMCS + Clockify Integration

## 📋 Descripción

**WHMCS + Clockify Integration** es una solución completa que integra automáticamente tu sistema de facturación WHMCS con la plataforma de seguimiento de tiempo Clockify. Esta integración permite generar reportes detallados de horas trabajadas para cada cliente, mostrando la información directamente en el área de cliente de WHMCS.

## ✨ Características Principales

### 🔗 Integración Bidireccional
- **Conexión automática** entre servicios WHMCS y proyectos Clockify
- **Sincronización de datos** en tiempo real
- **Mapeo inteligente** de clientes y proyectos

### 📊 Reportes Visuales
- **Gráficos interactivos** tipo donut para distribución de tareas
- **Detalles granulares** de tiempo por tarea
- **Resúmenes ejecutivos** con totales y porcentajes
- **Información de facturación** integrada

### 📄 Exportación a PDF
- **Generación automática** de reportes en PDF
- **Captura visual completa** de los datos
- **Nomenclatura inteligente** de archivos
- **Optimización para múltiples páginas**

### 🎨 Interfaz de Usuario
- **Pestaña personalizada** en el área de cliente
- **Diseño responsive** compatible con Bootstrap
- **Tooltips informativos** en gráficos
- **Indicadores de estado** en tiempo real

### ⚙️ Configuración Avanzada
- **Múltiples ciclos de facturación** (mensual, trimestral, anual)
- **Campos personalizados** para ID Clockify
- **Manejo de errores robusto**
- **Sistema de logging detallado**

## 🏗️ Arquitectura del Proyecto

```
📁 whmcs-clockify-integration/
├── 📁 clockify/
│   ├── 🔧 ClockifyClient.php      # Cliente API Clockify
│   └── ⚙️ config.php              # Configuración Clockify
├── 📁 whmcs/
│   ├── 🔧 WhMiConexion.php        # Cliente API WHMCS
│   └── ⚙️ config.php              # Configuración WHMCS
├── 🎨 pestana_billed.php          # Hook principal WHMCS
├── 📋 README.md                   # Documentación
└── 🛡️ LICENSE                     # Licencia MIT
```

## 🛠️ Componentes Técnicos

### 🔌 ClockifyClient.php
**Cliente robusto para la API de Clockify con capacidades avanzadas:**

```php
// Características principales
✅ Autenticación segura con API Key
✅ Manejo de múltiples workspaces
✅ Búsqueda inteligente de proyectos "Billed:"
✅ Cálculo automático de duraciones ISO 8601
✅ Agrupación de tareas por proyecto
✅ Soporte para tareas virtuales (tiempo sin asignar)
✅ Sistema de cache y optimización
```

### 🔌 WhMiConexion.php
**Integrador avanzado con la API de WHMCS:**

```php
// Funcionalidades clave
✅ Conexión segura con credenciales API
✅ Obtención de datos de clientes y servicios
✅ Manejo de campos personalizados
✅ Mapeo de ciclos de facturación
✅ Logging seguro (sin credenciales)
✅ Validación de datos robusta
```

### 🎨 pestana_billed.php
**Hook principal con interfaz avanzada:**

```javascript
// Características de la interfaz
✅ Integración jQuery para manipulación DOM
✅ Carga asíncrona de librerías (jsPDF, html2canvas)
✅ Gráficos SVG nativos optimizados
✅ Sistema de tooltips informativos
✅ Exportación PDF con captura visual
✅ Manejo de errores elegante
```

## 📦 Instalación

### 1️⃣ Prerrequisitos

```bash
# Versiones requeridas
PHP >= 7.4
WHMCS >= 8.0
Extensiones PHP: curl, json, mbstring
Acceso API: Clockify + WHMCS
```

### 2️⃣ Clonación del Repositorio

```bash
git clone https://github.com/tu-usuario/whmcs-clockify-integration.git
cd whmcs-clockify-integration
```

### 3️⃣ Configuración de APIs

**📋 Configurar Clockify:**
```php
// clockify/config.php
define('CLOCKIFY_API_KEY', 'tu_api_key_clockify');
define('CLOCKIFY_WORKSPACE_ID', 'tu_workspace_id');
define('CLOCKIFY_BASE_URL', 'https://api.clockify.me/api/v1');
define('BILLED_PROJECT_PREFIX', 'Billed:');
```

**📋 Configurar WHMCS:**
```php
// whmcs/config.php
define('WHMCS_URL', 'https://tu-whmcs.com/includes/api.php');
define('WHMCS_API_IDENTIFIER', 'tu_api_identifier');
define('WHMCS_API_SECRET', 'tu_api_secret');
define('WHMCS_RESPONSE_TYPE', 'json');
```

### 4️⃣ Instalación en WHMCS

```bash
# Copiar archivos al directorio de WHMCS
cp -r clockify/ /path/to/whmcs/modules/addons/tu_addon/
cp -r whmcs/ /path/to/whmcs/modules/addons/tu_addon/
cp pestana_billed.php /path/to/whmcs/includes/hooks/
```

### 5️⃣ Configurar Campos Personalizados

En WHMCS, crear un campo personalizado llamado **"ID Clockify"** en los productos que contenga el nombre del cliente en Clockify.

## 🎯 Uso

### 👨‍💼 Para Administradores

1. **Configurar APIs** en los archivos de configuración
2. **Crear campos personalizados** "ID Clockify" en productos
3. **Asignar nombres de clientes** Clockify a cada servicio
4. **Verificar logs** para troubleshooting

### 👤 Para Clientes

1. **Acceder al área de cliente** en WHMCS
2. **Navegar a detalles del producto/servicio**
3. **Hacer clic en "Gurux Informe Horas"**
4. **Visualizar reportes interactivos**
5. **Descargar PDF** del reporte

## 📊 Ejemplos de Reportes

### 🎨 Gráfico Donut Interactivo
```
📊 Distribución de Tareas
┌─────────────────────────────┐
│  🎨 Diseño Web     45.2%    │
│  💻 Desarrollo    32.1%     │
│  🐛 Bug Fixes     12.7%     │
│  📞 Soporte       10.0%     │
└─────────────────────────────┘
Total: 127h 30m | 15 tareas
```

### 📋 Detalle de Tareas
| Tarea | Tiempo | % |
|-------|--------|---|
| 🎨 Diseño de Landing Page | 25h 30m | 20.0% |
| 💻 Implementación Backend | 41h 15m | 32.4% |
| 🐛 Corrección de Bugs | 16h 20m | 12.8% |
| 📞 Soporte Técnico | 12h 45m | 10.0% |

## 🔧 Configuración Avanzada

### ⚙️ Ciclos de Facturación Soportados

| Ciclo WHMCS | Mes Objetivo | Descripción |
|-------------|--------------|-------------|
| `monthly` | Mes actual | Facturación mensual |
| `quarterly` | Primer mes del trimestre | Facturación trimestral |
| `semiannually` | Enero o Julio | Facturación semestral |
| `annually` | Enero | Facturación anual |

### 🎨 Personalización de Colores

```javascript
// Paleta de colores para gráficos
var coloresGrafico = [
    '#FF6B6B', // Rojo coral
    '#4ECDC4', // Turquesa
    '#45B7D1', // Azul claro
    '#96CEB4', // Verde menta
    '#FFEAA7', // Amarillo suave
    '#DDA0DD', // Violeta
    '#98D8C8', // Verde agua
    '#F7DC6F'  // Amarillo dorado
];
```

### 🔍 Sistema de Logging

```php
// Habilitar logging detallado
define('WHMCS_DEBUG', true);
define('WHMCS_LOG_FILE', '/path/to/logs/whmcs_integration.log');

// Niveles de log disponibles
logActivity("INFO: Conexión exitosa");
logActivity("WARNING: Datos incompletos");
logActivity("ERROR: Falló la autenticación");
```

## 🚀 Tecnologías Utilizadas

### 🔧 Backend
- ! **PHP 7.4+** - Lógica principal
- ! **cURL** - Comunicación con APIs
- ! **JSON** - Intercambio de datos

### 🎨 Frontend
- ! **JavaScript ES6+** - Interactividad
- ! **jQuery** - Manipulación DOM
- ! **Bootstrap** - Framework CSS
- ! **SVG** - Gráficos vectoriales

### 📄 Generación PDF
- ! **jsPDF** - Generación de PDFs
- ! **html2canvas** - Captura de pantalla

### 🔗 APIs Integradas
- ! **Clockify API v1** - Gestión de tiempo
- ! **WHMCS API** - Sistema de facturación

## 🔒 Seguridad

### 🛡️ Medidas Implementadas

- ✅ **Validación de entrada** en todos los endpoints
- ✅ **Sanitización de logs** (credenciales ocultas)
- ✅ **Verificación SSL** en conexiones API
- ✅ **Timeouts configurables** para prevenir ataques
- ✅ **Manejo seguro de errores** sin exposición de datos
- ✅ **Autenticación robusta** con APIs

## 🐛 Troubleshooting

### ❓ Problemas Comunes

**🔴 "No se encontraron datos de Clockify"**
```bash
# Verificar configuración
1. Revisar API Key de Clockify
2. Confirmar Workspace ID correcto
3. Verificar formato de nombres "Billed:"
4. Comprobar permisos de API
```

**🔴 "Error de conexión WHMCS"**
```bash
# Pasos de diagnóstico
1. Verificar credenciales API WHMCS
2. Confirmar URL de API correcta
3. Revisar campo personalizado "ID Clockify"
4. Validar permisos de usuario API
```

**🔴 "PDF no se genera"**
```bash
# Soluciones
1. Verificar carga de librerías JavaScript
2. Comprobar consola del navegador
3. Revisar tamaño del contenido
4. Validar permisos de descarga
```

## 📈 Roadmap

### 🎯 Próximas Características

- [ ] 📊 **Dashboard analítico** con métricas avanzadas
- [ ] 🔔 **Notificaciones automáticas** por email
- [ ] 📱 **API REST propia** para integraciones externas
- [ ] 🎨 **Temas personalizables** para reportes
- [ ] 📋 **Plantillas de reporte** configurables
- [ ] 🔄 **Sincronización bidireccional** WHMCS ↔ Clockify
- [ ] 📈 **Análisis predictivo** de tiempo vs. presupuesto
- [ ] 🌐 **Soporte multi-idioma** completo

## 👥 Contribución

### 🤝 Cómo Contribuir

1. **Fork** el repositorio
2. **Crea** una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. **Commit** tus cambios (`git commit -m 'Add: Amazing Feature'`)
4. **Push** a la rama (`git push origin feature/AmazingFeature`)
5. **Abre** un Pull Request

### 📝 Estándares de Código

- ✅ **PSR-4** para autoloading de clases
- ✅ **PSR-12** para estilo de código
- ✅ **PHPDoc** para documentación
- ✅ **Camel Case** para métodos y variables
- ✅ **Snake Case** para constantes

## 📄 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## 🙏 Agradecimientos

- 💙 **WHMCS Team** por su robusta API
- ⏰ **Clockify** por su excelente plataforma
- 🎨 **Bootstrap** por el framework CSS
- 📄 **jsPDF Team** por la librería de PDFs
- 👥 **Comunidad Open Source** por el apoyo constante

---
