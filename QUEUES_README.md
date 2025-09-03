# Configuración de Colas Múltiples en Laravel

## Resumen

Este proyecto está configurado para usar múltiples colas de trabajo para optimizar el rendimiento y la organización de los jobs. Cada tipo de job se ejecuta en su cola específica.

## Colas Configuradas

### 1. **Cola `default`**
- **Propósito**: Jobs generales y por defecto
- **Supervisor**: `supervisor-1`
- **Procesos**: 3 (local) / 10 (producción)
- **Intentos**: 1

### 2. **Cola `importaciones`**
- **Propósito**: Jobs de importación de datos (Excel, CSV, etc.)
- **Supervisor**: `supervisor-importaciones`
- **Procesos**: 2 (local) / 5 (producción)
- **Intentos**: 3
- **Jobs**: `ImportProductosExcelJob`

### 3. **Cola `emails`**
- **Propósito**: Envío de emails y notificaciones por correo
- **Supervisor**: `supervisor-emails`
- **Procesos**: 1 (local) / 3 (producción)
- **Intentos**: 3
- **Timeout**: 60 segundos
- **Jobs**: `SendEmailJob`

### 4. **Cola `notificaciones`**
- **Propósito**: Notificaciones push, broadcast, etc.
- **Supervisor**: `supervisor-notificaciones`
- **Procesos**: 1 (local) / 2 (producción)
- **Intentos**: 2
- **Timeout**: 30 segundos
- **Jobs**: `SendNotificationJob`

## Cómo Usar las Colas

### 1. **Especificar la cola en el Job**

```php
class MiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El nombre de la cola en la que debe ejecutarse el job.
     */
    public $queue = 'nombre_de_la_cola';

    // ... resto del código
}
```

### 2. **Especificar la cola al despachar**

```php
// Despachar a una cola específica
MiJob::dispatch($data)->onQueue('importaciones');

// O usar el método onQueue()
dispatch(new MiJob($data))->onQueue('emails');
```

### 3. **Configurar propiedades adicionales**

```php
class MiJob implements ShouldQueue
{
    public $queue = 'importaciones';
    public $tries = 3;           // Número de intentos
    public $timeout = 120;       // Timeout en segundos
    public $delay = 60;          // Retraso antes de ejecutar
    
    // ... resto del código
}
```

## Comandos Útiles

### **Iniciar Horizon**
```bash
php artisan horizon
```

### **Reiniciar Horizon**
```bash
php artisan horizon:terminate
php artisan horizon
```

### **Ver estado de las colas**
```bash
php artisan queue:work --queue=importaciones,emails,notificaciones,default
```

### **Procesar una cola específica**
```bash
php artisan queue:work --queue=importaciones
```

### **Ver jobs fallidos**
```bash
php artisan queue:failed
```

### **Reintentar jobs fallidos**
```bash
php artisan queue:retry all
```

## Monitoreo con Horizon

Accede al dashboard de Horizon en: `http://tu-dominio.com/horizon`

Desde ahí puedes:
- Ver el estado de todas las colas
- Monitorear jobs en tiempo real
- Ver métricas de rendimiento
- Reintentar jobs fallidos
- Pausar/reanudar supervisores

## Mejores Prácticas

### 1. **Organización por Tipo**
- Agrupa jobs similares en la misma cola
- Usa nombres descriptivos para las colas
- Considera la prioridad de cada tipo de job

### 2. **Configuración de Recursos**
- Ajusta `maxProcesses` según los recursos del servidor
- Configura `timeout` apropiado para cada tipo de job
- Establece `tries` según la criticidad del job

### 3. **Monitoreo**
- Revisa regularmente el dashboard de Horizon
- Configura alertas para jobs fallidos
- Monitorea el uso de memoria y CPU

### 4. **Escalabilidad**
- En producción, considera usar más supervisores
- Ajusta la configuración según la carga
- Usa balanceo automático cuando sea posible

## Ejemplos de Uso

### **Job de Importación**
```php
// En un controller
ImportProductosExcelJob::dispatch($filePath, $idImportProducto);
// Se ejecutará automáticamente en la cola 'importaciones'
```

### **Job de Email**
```php
// En un controller
SendEmailJob::dispatch($to, $subject, $content);
// Se ejecutará automáticamente en la cola 'emails'
```

### **Job de Notificación**
```php
// En un controller
SendNotificationJob::dispatch($userId, $message);
// Se ejecutará automáticamente en la cola 'notificaciones'
```

## Troubleshooting

### **Jobs no se ejecutan**
1. Verifica que Horizon esté corriendo
2. Revisa los logs en `storage/logs/laravel.log`
3. Verifica la configuración de Redis
4. Asegúrate de que los supervisores estén activos

### **Jobs fallan repetidamente**
1. Revisa el método `failed()` del job
2. Verifica la configuración de `tries`
3. Revisa los logs para errores específicos
4. Considera aumentar el `timeout`

### **Problemas de memoria**
1. Reduce `maxProcesses` en la configuración
2. Aumenta `memory_limit` en `config/horizon.php`
3. Optimiza el código de los jobs
4. Considera usar jobs más pequeños
