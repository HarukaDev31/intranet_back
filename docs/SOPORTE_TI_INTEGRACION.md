# Soporte TI — Backend (probusiness_intranetv2_back)

Módulo para el front `probusiness_intranetv3` con `NUXT_PUBLIC_SOPORTE_TI_USE_API=true`.

## Instalación

```bash
php artisan migrate
php artisan storage:link
```

Migración: `database/migrations/2026_05_15_100000_create_soporte_ti_tables.php`

## Rutas

Registradas en `routes/modules/soporte-ti.php` (incluidas desde `routes/api.php`).

Middleware: **`jwt.auth`** (mismo patrón que calculadora de importación).

Prefijo API: `/api/soporte-ti/...`

## Broadcasting

Canal en `routes/channels.php`:

- `private-soporte-ti.chat.{chatUuid}`

Eventos:

| Evento | Clase |
|--------|--------|
| `SoporteTiMensajeCreado` | `App\Events\SoporteTi\SoporteTiMensajeCreado` |
| `SoporteTiEstadoActualizado` | `App\Events\SoporteTi\SoporteTiEstadoActualizado` |

## Usuario autenticado

El módulo usa `App\Models\Usuario` (`ID_Usuario`, `No_Nombres_Apellidos`).

## Endpoints

Ver contrato en el front: `probusiness_intranetv3/services/soporteTiService.ts`.
