# Laravel Horizon - Instalación y Uso

## Instalación Completada ✅

Laravel Horizon ha sido instalado exitosamente en el proyecto. Horizon es un dashboard y supervisor para las colas de Redis de Laravel.

## Configuración

### Archivos Creados
- `config/horizon.php` - Configuración de Horizon
- `app/Providers/HorizonServiceProvider.php` - Proveedor de servicios de Horizon
- `HORIZON_README.md` - Este archivo de documentación

### Configuración del Proveedor
El proveedor de servicios de Horizon ha sido registrado en `config/app.php` y configurado para permitir acceso en entorno local.

## Uso

### Comandos Principales

1. **Iniciar Horizon Dashboard:**
   ```bash
   php artisan horizon
   ```

2. **Iniciar Horizon en modo producción:**
   ```bash
   php artisan horizon --environment=production
   ```

3. **Pausar Horizon:**
   ```bash
   php artisan horizon:pause
   ```

4. **Continuar Horizon:**
   ```bash
   php artisan horizon:continue
   ```

5. **Terminar Horizon:**
   ```bash
   php artisan horizon:terminate
   ```

6. **Limpiar trabajos fallidos:**
   ```bash
   php artisan horizon:clear
   ```

### Acceso al Dashboard

Una vez que Horizon esté ejecutándose, puedes acceder al dashboard en:
```
http://tu-dominio.com/horizon
```

### Configuración de Redis

Asegúrate de que Redis esté configurado correctamente en tu archivo `.env`:

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Configuración de Colas

En `config/queue.php`, asegúrate de que Redis esté configurado como driver:

```php
'default' => env('QUEUE_CONNECTION', 'redis'),

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

## Monitoreo

Horizon proporciona:
- Dashboard en tiempo real
- Métricas de rendimiento
- Monitoreo de trabajos fallidos
- Estadísticas de colas
- Supervisión de workers

## Notas Importantes

1. **Entorno de Desarrollo:** Horizon está configurado para permitir acceso sin autenticación en entorno local.
2. **Producción:** Para entornos de producción, configura la autenticación en `HorizonServiceProvider.php`.
3. **Redis:** Asegúrate de que Redis esté instalado y ejecutándose.
4. **Workers:** Horizon gestiona automáticamente los workers de las colas.

## Troubleshooting

Si encuentras problemas:

1. Verifica que Redis esté ejecutándose
2. Limpia la caché: `php artisan config:clear`
3. Verifica la configuración en `config/horizon.php`
4. Revisa los logs en `storage/logs/horizon.log`

## Comandos Útiles

```bash
# Ver estado de Horizon
php artisan horizon:status

# Ver métricas
php artisan horizon:metrics

# Limpiar caché
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```
