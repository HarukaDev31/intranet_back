# Soporte TI — Integración en intranet_back

Módulo Laravel alineado con el front `probusiness_intranetv3` (`NUXT_PUBLIC_SOPORTE_TI_USE_API=true`).

## 1. Migración

```bash
php artisan migrate
```

Archivo: `database/migrations/2026_05_15_100000_create_soporte_ti_tables.php`

Enlace simbólico de storage (imágenes de chat y maquetas):

```bash
php artisan storage:link
```

## 2. Rutas API

En `routes/api.php` (dentro del grupo con prefijo `api` y middleware de auth que ya uses):

```php
require __DIR__ . '/modules/soporte-ti.php';
```

Si tu proyecto usa namespace en `RouteServiceProvider`:

```php
Route::prefix('api')->middleware('api')->group(function () {
    Route::namespace('App\Http\Controllers')->group(function () {
        require base_path('routes/modules/soporte-ti.php');
    });
});
```

**Nota:** Si el middleware de auth no es `auth:api`, edita `routes/modules/soporte-ti.php` (por ejemplo `jwt.auth` o `auth:sanctum`).

## 3. Broadcasting (WebSocket)

Añadir en `routes/channels.php`:

```php
Broadcast::channel('soporte-ti.chat.{chatUuid}', function ($user, $chatUuid) {
    if (!$user) {
        return false;
    }
    $sala = \App\Models\SoporteTi\SoporteTiChatSala::where('chat_uuid', $chatUuid)->first();
    if (!$sala) {
        return false;
    }
    // Opcional: restringir a miembros de la sala
    // return \App\Models\SoporteTi\SoporteTiChatMiembro::where('sala_id', $sala->id)
    //     ->where('usuario_id', $user->id)->exists();
    return true;
});
```

Eventos (canal privado `private-soporte-ti.chat.{uuid}`):

| Evento | Clase |
|--------|--------|
| `SoporteTiMensajeCreado` | `App\Events\SoporteTi\SoporteTiMensajeCreado` |
| `SoporteTiEstadoActualizado` | `App\Events\SoporteTi\SoporteTiEstadoActualizado` |

En desarrollo, usa `QUEUE_CONNECTION=sync` para que los broadcasts se envíen sin worker.

## 4. Modelo User

Los modelos referencian `App\User`. Si tu proyecto usa `App\Models\User`, cambia el `use` en:

- `app/Models/SoporteTi/SoporteTiSolicitudEstado.php`
- `app/Models/SoporteTi/SoporteTiChatMiembro.php`
- `app/Models/SoporteTi/SoporteTiMensaje.php`

## 5. Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/soporte-ti/solicitudes` | Listado (sin mensajes) |
| POST | `/api/soporte-ti/solicitudes` | Crear ticket + sala + mensaje sistema |
| GET | `/api/soporte-ti/solicitudes/{id}` | Detalle |
| PUT | `/api/soporte-ti/solicitudes/{id}` | Actualizar (estado, maqueta, fase…) |
| POST | `/api/soporte-ti/solicitudes/{id}/mensajes` | Enviar mensaje (+ imágenes multipart) |
| POST | `/api/soporte-ti/solicitudes/{id}/maqueta` | Subir maqueta (`archivo`) |
| POST | `/api/soporte-ti/solicitudes/{id}/estado` | Cambiar estado (`estado_id`, `comentario`) |
| GET | `/api/soporte-ti/solicitudes/{id}/estados/historial` | Historial de estados |
| GET | `/api/soporte-ti/estados` | Catálogo de estados |
| GET | `/api/soporte-ti/chats/{uuid}/mensajes` | Chat paginado (`limit`, `before_id`) |

### Paginación de mensajes

```
GET /api/soporte-ti/chats/{uuid}/mensajes?limit=25&before_id=120
```

```json
{
  "success": true,
  "data": [ /* mensajes ASC */ ],
  "pagination": {
    "has_more": true,
    "oldest_id": 95,
    "newest_id": 119,
    "per_page": 25,
    "total": null
  }
}
```

## 6. Front Nuxt

En `.env`:

```
NUXT_PUBLIC_SOPORTE_TI_USE_API=true
```

Reinicia el dev server tras cambiar variables públicas.
