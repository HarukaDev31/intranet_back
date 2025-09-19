# SendInspectionMediaJob - Documentación

## Descripción General
El `SendInspectionMediaJob` es un job asíncrono que maneja el envío de medios de inspección (imágenes y videos) por WhatsApp para los proveedores de carga consolidada. Este job procesa archivos tanto locales como de URLs externas.

## Características Principales

### 🚀 **Procesamiento Asíncrono**
- **Queue**: Procesa en segundo plano sin bloquear la respuesta HTTP
- **Reintentos**: 3 intentos automáticos en caso de fallo
- **Timeout**: 5 minutos máximo por job
- **Logging**: Registro detallado de todo el proceso

### 📁 **Manejo de Archivos**
- **URLs Externas**: Descarga automática con cURL
- **Rutas Locales**: Búsqueda en múltiples ubicaciones
- **Archivos Temporales**: Limpieza automática después del envío
- **Validación**: Verificación de existencia y tipos de archivo

### 📱 **Integración WhatsApp**
- **Mensajes**: Envío de mensaje principal cuando corresponde
- **Medios**: Envío de imágenes y videos
- **Estados**: Actualización automática del estado del proveedor

## Estructura del Job

### **Constructor**
```php
public function __construct($idProveedor, $idCotizacion, $idsProveedores, $userId = null)
```

**Parámetros:**
- `$idProveedor`: ID del proveedor a procesar
- `$idCotizacion`: ID de la cotización
- `$idsProveedores`: Array con todos los IDs de proveedores del proceso
- `$userId`: ID del usuario que inició el proceso (opcional)

### **Método Principal - handle()**
Ejecuta el procesamiento completo:

1. **Obtención de datos**:
   - Imágenes del proveedor
   - Videos del proveedor
   - Datos del proveedor
   - Datos de la cotización
   - Información del contenedor

2. **Actualización de estado**:
   - Cambia el estado a 'INSPECTION'
   - Actualiza estados a 'INSPECCIONADO'

3. **Envío de mensajes**:
   - Mensaje principal (si corresponde)
   - Imágenes individuales
   - Videos individuales

4. **Limpieza**:
   - Eliminación de archivos temporales
   - Logging de resultados

### **Métodos de Soporte**

#### `resolveMediaPath($filePath)`
Resuelve rutas de archivos, manejando:
- URLs externas (descarga con cURL)
- Múltiples ubicaciones locales
- Validación de existencia

#### `downloadExternalMedia($url)`
Descarga archivos externos:
- Configuración robusta de cURL
- Validación HTTP (código 200)
- Manejo de Content-Type
- Creación de archivos temporales

#### `getFileExtensionFromUrl($url, $contentType)`
Determina la extensión correcta:
- Extracción desde URL
- Fallback a Content-Type
- Extensiones soportadas: jpg, png, gif, webp, mp4, avi, mov, wmv, pdf

## Uso desde el Controlador

### **Despacho del Job**
```php
SendInspectionMediaJob::dispatch(
    $idProveedor,
    $idCotizacion, 
    $idsProveedores,
    $user->ID_Usuario
);
```

### **Respuesta Inmediata**
```json
{
    "success": true,
    "message": "Proceso de inspección iniciado correctamente",
    "data": {
        "proveedores_procesados": 3,
        "jobs_despachados": 3,
        "nota": "Los archivos se están procesando en segundo plano"
    }
}
```

## Configuración de Queue

### **Requisitos**
- Queue driver configurado (database, redis, etc.)
- Worker ejecutándose: `php artisan queue:work`
- Supervisor (recomendado para producción)

### **Comando para Procesar**
```bash
# Procesamiento manual
php artisan queue:work

# Con supervisor (recomendado)
php artisan queue:work --daemon --tries=3 --timeout=300
```

## Logging y Monitoreo

### **Logs Generados**
- Inicio del job con parámetros
- Resolución de rutas de archivos
- Descarga de archivos externos
- Envío de mensajes y medios
- Actualización de estados
- Limpieza de archivos temporales
- Errores y excepciones

### **Ejemplo de Logs**
```
[INFO] Iniciando job de envío de inspección {"id_proveedor":123,"id_cotizacion":456}
[INFO] Resolviendo ruta de archivo: https://example.com/image.jpg
[INFO] URL externa detectada, descargando: https://example.com/image.jpg
[INFO] Archivo descargado exitosamente {"url":"...","temp_file":"...","size":156789}
[INFO] Estado del proveedor actualizado {"id_proveedor":123,"nuevo_estado":"INSPECCIONADO"}
[INFO] Job de inspección completado exitosamente
```

## Manejo de Errores

### **Reintentos Automáticos**
- **3 intentos** antes de marcar como fallido
- **Backoff exponencial** entre reintentos
- **Logging detallado** de cada intento

### **Método failed()**
Se ejecuta cuando todos los intentos fallan:
- Log del error final
- Posibilidad de notificar administradores
- Reversión de cambios si es necesario

### **Errores Comunes**
- **cURL no disponible**: Verificar instalación de cURL
- **Timeout de descarga**: Archivos muy grandes o conexión lenta
- **Archivo no encontrado**: URL inválida o archivo eliminado
- **Permisos**: Problemas de escritura en directorio temporal

## Ventajas del Diseño

### ✅ **Performance**
- **No bloquea** la respuesta HTTP
- **Procesamiento paralelo** de múltiples proveedores
- **Manejo eficiente** de archivos grandes

### ✅ **Confiabilidad**
- **Reintentos automáticos** en caso de fallo
- **Logging completo** para debugging
- **Limpieza automática** de archivos temporales

### ✅ **Escalabilidad**
- **Queue workers** pueden ejecutarse en múltiples servidores
- **Procesamiento distribuido** de cargas pesadas
- **Monitoreo** con herramientas como Horizon

### ✅ **Mantenibilidad**
- **Separación de responsabilidades** clara
- **Código reutilizable** para otros procesos
- **Testing** más fácil de jobs individuales

## Consideraciones de Producción

### **Supervisor Configuration**
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/path/to/project
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/logs/worker.log
stopwaitsecs=3600
```

### **Monitoreo**
- **Laravel Horizon** para queues Redis
- **Logs de aplicación** para debugging
- **Métricas de sistema** para performance

### **Backup y Recovery**
- **Jobs fallidos** se almacenan en `failed_jobs` table
- **Retry manual** con `php artisan queue:retry all`
- **Limpieza periódica** de jobs antiguos

