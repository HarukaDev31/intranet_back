# Importación de Productos con Jobs

## Descripción

Se ha refactorizado el sistema de importación de productos para usar Jobs de Laravel, lo que permite procesar archivos Excel grandes en segundo plano sin bloquear la aplicación.

## Cambios Implementados

### 1. Nuevo Job: `ImportProductosExcelJob`

**Ubicación:** `app/Jobs/ImportProductosExcelJob.php`

**Funcionalidades:**
- Procesa archivos Excel en segundo plano
- Extrae imágenes de manera optimizada
- Maneja errores y actualiza estadísticas
- Limpia archivos temporales automáticamente

### 2. Controlador Simplificado

**Método `importExcel`:**
- Solo valida el archivo y crea el registro inicial
- Dispara el Job para procesamiento en segundo plano
- Retorna inmediatamente con estado "processing"

**Nuevo método `checkImportStatus`:**
- Permite verificar el progreso de la importación
- Retorna estadísticas actualizadas

## Configuración Requerida

### 1. Configurar Colas

Asegúrate de que las colas estén configuradas en `config/queue.php`:

```php
'default' => env('QUEUE_CONNECTION', 'database'),
```

### 2. Crear Tabla de Jobs (si no existe)

```bash
php artisan queue:table
php artisan migrate
```

### 3. Ejecutar Worker de Colas

```bash
php artisan queue:work
```

Para producción, se recomienda usar Supervisor para mantener el worker activo.

## Uso del Sistema

### 1. Iniciar Importación

```javascript
// Frontend
const formData = new FormData();
formData.append('excel_file', file);
formData.append('idContenedor', contenedorId);

fetch('/api/productos/import', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Importación iniciada:', data.data.import_id);
        // Guardar el ID para verificar progreso
        localStorage.setItem('importId', data.data.import_id);
    }
});
```

### 2. Verificar Progreso

```javascript
// Verificar estado de la importación
function checkImportProgress(importId) {
    fetch(`/api/productos/import/status/${importId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const stats = data.data.estadisticas;
            
            if (stats.status === 'processing') {
                console.log('Procesando...', stats.productos_importados, '/', stats.total_productos);
                // Verificar nuevamente en 5 segundos
                setTimeout(() => checkImportProgress(importId), 5000);
            } else if (stats.status === 'completed') {
                console.log('Importación completada:', stats.productos_importados, 'productos');
            } else if (stats.status === 'failed') {
                console.error('Error en importación:', stats.error);
            }
        }
    });
}

// Usar
const importId = localStorage.getItem('importId');
if (importId) {
    checkImportProgress(importId);
}
```

## Estados de Importación

### 1. `processing`
- La importación está en progreso
- Se puede verificar el progreso con `checkImportStatus`

### 2. `completed`
- La importación se completó exitosamente
- Incluye estadísticas finales

### 3. `failed`
- Ocurrió un error durante la importación
- Incluye mensaje de error

## Optimizaciones Implementadas

### 1. Optimización de `getDrawingCollection()`
- Se llama una sola vez por archivo en lugar de por producto
- Reduce significativamente el tiempo de procesamiento

### 2. Mapa de Coordenadas
- Búsqueda O(1) de dibujos por coordenada
- Elimina iteraciones innecesarias

### 3. Procesamiento en Segundo Plano
- No bloquea la aplicación web
- Permite procesar archivos grandes sin timeouts

## Monitoreo y Logs

### Logs Importantes

```bash
# Ver logs de importación
tail -f storage/logs/laravel.log | grep "ImportProductosExcelJob"

# Ver jobs en cola
php artisan queue:monitor

# Ver jobs fallidos
php artisan queue:failed
```

### Comandos Útiles

```bash
# Limpiar jobs fallidos
php artisan queue:flush

# Reintentar jobs fallidos
php artisan queue:retry all

# Ver estadísticas de colas
php artisan queue:work --verbose
```

## Consideraciones de Producción

### 1. Configuración de Supervisor

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

### 2. Configuración de Redis (Recomendado)

```php
// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for' => null,
],
```

### 3. Monitoreo de Recursos

- Monitorear uso de memoria durante importaciones grandes
- Configurar timeouts apropiados
- Implementar alertas para jobs fallidos

## Migración desde el Sistema Anterior

### Cambios en el Frontend

1. **Cambiar manejo de respuesta:**
   - Antes: Esperar respuesta completa
   - Ahora: Recibir ID y verificar progreso

2. **Implementar polling:**
   - Verificar estado cada 5-10 segundos
   - Mostrar progreso al usuario

3. **Manejo de errores:**
   - Verificar estado `failed`
   - Mostrar mensajes de error apropiados

### Beneficios de la Migración

1. **Mejor UX:** Respuesta inmediata al usuario
2. **Escalabilidad:** Procesa archivos grandes sin problemas
3. **Confiabilidad:** Reintentos automáticos y manejo de errores
4. **Monitoreo:** Mejor visibilidad del progreso
5. **Rendimiento:** Optimizaciones significativas en el procesamiento
