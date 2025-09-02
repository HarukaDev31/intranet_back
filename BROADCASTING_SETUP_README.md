# Configuración de Broadcasting - Laravel

## Descripción

Este documento explica la configuración de broadcasting en Laravel para notificaciones en tiempo real usando Pusher.

## Configuración Actual

### 1. Canales Configurados

Los siguientes canales están configurados en `routes/channels.php`:

- **`private-Documentacion-notifications`**: Solo usuarios con rol "Documentacion"
- **`private-Cotizador-notifications`**: Solo usuarios con rol "Cotizador"
- **`private-Coordinacion-notifications`**: Solo usuarios con rol "Coordinacion"
- **`private-Administracion-notifications`**: Solo usuarios con rol "Administracion"
- **`private-ContenedorConsolidado-notifications`**: Múltiples roles permitidos
- **`private-User-notifications`**: Todos los usuarios autenticados

### 2. Roles Definidos

En `app/Models/Usuario.php`:

```php
const ROL_DOCUMENTACION = 'Documentacion';
const ROL_COTIZADOR = 'Cotizador';
const ROL_COORDINACION = 'Coordinacion';
const ROL_ADMINISTRACION = 'Administracion';
const ROL_ALMACEN_CHINA = 'Almacen China';
```

## Problema Resuelto

### Error Original
```
Broadcasting auth error: verifyUserCanAccessChannel()
```

### Causa
El `BroadcastController` estaba manejando manualmente la autenticación para algunos canales, lo que causaba conflictos con el sistema estándar de Laravel.

### Solución
1. **Simplificado el controlador**: Ahora usa `Broadcast::auth($request)` para todos los canales
2. **Habilitado BroadcastServiceProvider**: Descomentado en `config/app.php`
3. **Configuración estándar**: Los canales se manejan a través de `routes/channels.php`

## Configuración Requerida

### 1. Variables de Entorno

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_ID=your-app-id
PUSHER_APP_CLUSTER=mt1
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
```

### 2. Dependencias

```bash
composer require pusher/pusher-php-server
```

### 3. Configuración de Laravel Echo (Frontend)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: false,
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
    disableStats: true,
});
```

## Uso de Canales

### 1. Suscribirse a un Canal

```javascript
// Canal privado para documentación
Echo.private('Documentacion-notifications')
    .listen('DocumentacionEvent', (e) => {
        console.log('Evento de documentación:', e);
    });

// Canal privado para cotizador
Echo.private('Cotizador-notifications')
    .listen('CotizacionEvent', (e) => {
        console.log('Evento de cotización:', e);
    });
```

### 2. Emitir Eventos

```php
// En un controlador o job
event(new DocumentacionEvent($data));

// O usando broadcasting
broadcast(new DocumentacionEvent($data));
```

## Monitoreo y Debugging

### 1. Logs de Autenticación

Los logs de autenticación se guardan en `storage/logs/laravel.log`:

```bash
# Ver logs de broadcasting
tail -f storage/logs/laravel.log | grep "Broadcasting"

# Ver logs de autenticación específica
tail -f storage/logs/laravel.log | grep "Broadcasting auth"
```

### 2. Verificar Estado de Broadcasting

```bash
# Verificar configuración
php artisan config:show broadcasting

# Verificar rutas de broadcasting
php artisan route:list | grep broadcast
```

### 3. Testing de Canales

```php
// En un test
$this->actingAs($user)
     ->post('/broadcasting/auth', [
         'socket_id' => '123.456',
         'channel_name' => 'private-Documentacion-notifications'
     ])
     ->assertStatus(200);
```

## Troubleshooting

### 1. Error de Autenticación

**Síntoma**: `403 Unauthorized` en autenticación de broadcasting

**Solución**:
- Verificar que el usuario esté autenticado
- Verificar que el usuario tenga el rol correcto
- Verificar que el canal esté definido en `routes/channels.php`

### 2. Error de Conexión

**Síntoma**: No se reciben eventos en el frontend

**Solución**:
- Verificar configuración de Pusher
- Verificar que Laravel Echo esté configurado correctamente
- Verificar que el worker de websockets esté ejecutándose

### 3. Error de Permisos

**Síntoma**: Usuario no puede acceder a un canal específico

**Solución**:
- Verificar la lógica en `routes/channels.php`
- Verificar que el usuario tenga el grupo/rol correcto
- Verificar la relación `grupo` en el modelo `Usuario`

## Comandos Útiles

```bash
# Limpiar cache de configuración
php artisan config:clear

# Limpiar cache de rutas
php artisan route:clear

# Verificar estado de broadcasting
php artisan queue:work --verbose

# Reiniciar servicios
php artisan optimize:clear
```

## Consideraciones de Seguridad

1. **Autenticación**: Todos los canales privados requieren autenticación JWT
2. **Autorización**: Los canales verifican roles específicos del usuario
3. **Validación**: Los eventos se validan antes de ser emitidos
4. **Logging**: Todas las autenticaciones se registran para auditoría

## Próximos Pasos

1. **Implementar eventos específicos** para cada canal
2. **Agregar notificaciones push** para eventos críticos
3. **Implementar rate limiting** para autenticaciones
4. **Agregar métricas** de uso de broadcasting
