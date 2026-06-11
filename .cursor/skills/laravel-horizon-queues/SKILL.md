---
name: laravel-horizon-queues
description: >-
  Colas Redis y Laravel Horizon en intranet_back. Usar al crear jobs ShouldQueue,
  dispatch desde controladores/servicios, o cuando jobs quedan en pending/failed
  sin ejecutarse (p. ej. cola default sin supervisor).
---

# Laravel Horizon y colas (intranet_back)

## Regla principal

**Nunca despachar jobs a la cola `default` sin un supervisor en `config/horizon.php`.**

En este proyecto Horizon **no** tiene worker para `default`. Los jobs que no llamen `onQueue(...)` van a `default` y **nunca se ejecutan**.

## Colas con supervisor activo

| Cola | Supervisor | Uso típico |
|------|------------|------------|
| `importaciones` | supervisor-importaciones | WhatsApp importaciones, recordatorios |
| `emails` | supervisor-emails | Correos |
| `notificaciones` | supervisor-notificaciones | WhatsApp, viáticos, broadcasts encolados |
| `importaciones_facturacion` | supervisor-importaciones-facturacion | Import Excel facturación |
| `plantillas_finales` | supervisor-plantillas-finales | Plantillas finales masivas |
| `soporte_ti` | supervisor-soporte-ti | Chat Soporte TI |
| `carga_consolidada` | supervisor-carga-consolidada | Excel seguimiento Drive, cortes 20:00, sync consolidado |

Config centralizada: `config/carga_consolidada.php` → `CARGA_CONSOLIDADA_QUEUE` (default: `carga_consolidada`).

## Checklist al crear un job nuevo

1. **Elegir cola existente** o crear una nueva con supervisor en `horizon.php` (`defaults`, `production`, `local`).
2. En el constructor del job:
   ```php
   $this->onQueue((string) config('mi_modulo.queue', 'nombre_cola'));
   ```
   o, si no hay config:
   ```php
   $this->onQueue('notificaciones');
   ```
3. **Registrar supervisor** en `config/horizon.php`:
   - `defaults` → definición base
   - `environments.production` → overrides prod
   - `environments.local` → overrides local
4. Añadir umbral en `waits` si el job es largo (ej. `'redis:carga_consolidada' => 600`).
5. Documentar env en `.env.example` si la cola es configurable.
6. Tras cambios en `horizon.php`: `php artisan horizon:terminate` y volver a levantar Horizon.

## Jobs de carga consolidada / seguimiento Drive

- `VincularSeguimientoConsolidadoExcelJob`
- `SyncSeguimientoConsolidadoExcelJob`
- `ProcesarCorteSeguimientoDatosProveedorJob`

Todos usan `config('carga_consolidada.queue')` → cola `carga_consolidada`.

Estado de vinculación: enum `App\Enums\CargaConsolidada\ExcelSeguimientoLinkStatus` (`queued`, `processing`, `completed`, `failed`). No hay endpoint GET de estado; el frontend lo recibe en headers de cotizaciones y vía WebSocket al terminar el job.

## Verificación rápida

```bash
# Jobs pendientes en Redis (desde WSL)
php artisan queue:monitor carga_consolidada

# Reiniciar workers tras cambiar horizon.php
php artisan horizon:terminate
php artisan horizon
```

En el dashboard Horizon (`/horizon`), confirmar que aparece el supervisor `supervisor-carga-consolidada` escuchando `carga_consolidada`.

## PHP 7

- Sin promoted properties en constructores.
- `onQueue()` en el constructor del job, no en propiedades tipadas PHP 8.

## Anti-patrones

- `dispatch(new MiJob())` sin `onQueue` → queda en `default` → no corre.
- Añadir supervisor solo en `defaults` y olvidar `production` / `local`.
- Timeout del supervisor menor que `$timeout` del job (Excel/Drive: 600–900 s).
