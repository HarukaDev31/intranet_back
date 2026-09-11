# Pendiente — Portal de clientes por organización

**Estado:** PENDIENTE. No implementar en el lote QA del inbox.  
**Fecha:** 2026-09-11  
**Dueño conceptual:** el core es el **portal clientes**. WhatsApp, Drive y datos proveedor se cuelgan de la misma config, no al revés.

Hecho en paralelo (ya en QA): [TECNICO_WHATSAPP_INBOX_MULTI_ORG.md](./TECNICO_WHATSAPP_INBOX_MULTI_ORG.md).

---

## 1. Problema

Hoy casi todos los links públicos nacen de un solo `.env`:

- `APP_URL_CLIENTES` → `clientes.probusiness.pe` (firma, inspección, formularios, reset).
- `APP_URL_EXCEL_CONFIRMACION` (o la misma base).
- `APP_URL_DATOS_PROVEEDOR` (ni siquiera está en `config/app.php`).

El registro público (`AuthController::register` / `loginCliente`) **no asigna org**. Un cliente que se anota en un “front 2” cae como si fuera de Probusiness (org 1).

En el front intranet hay URLs **fijas**:

- `components/cargaconsolidada/cotizaciones/CotizacionesView/index.vue` → `https://clientes.probusiness.pe/firma-acuerdo-servicio/{uuid}`
- `components/cargaconsolidada/cotizaciones/DocumentacionCotizacionesView/index.vue` → igual
- `pages/basedatos/clientes/[id].vue` → `/recuperar-contrasena`
- Sidebar / home / favicon / notificaciones → logos de `intranet.probusiness.pe`

---

## 2. Decisión (acordada)

1. Montar **otro front** del portal clientes (mismo codebase, otro deploy o dominio).
2. Cada front lleva un **key de organización** (no el `organizacion_id` numérico).
3. Si alguien se registra en el front 2, el back resuelve el key → org y el cliente nace ahí.
4. Drive y datos proveedor son otro producto, pero se arreglan con **la misma fila en BD**.

### Por qué un key y no `organizacion_id` en el body

La regla de alcance: la org **nunca** se toma del request salvo usuario intranet org 1. Un form público que mande `organizacion_id=1` es forjable.

El front manda `X-Org-Key` (o el back resuelve por `Host`). El servidor traduce key → `organizacion_id`.

---

## 3. Diseño a implementar

Tabla hermana (no mezclar con tokens Meta de `wa_inbox_organizacion_config`):

`organizacion_portales` (nombre tentativo)

| Campo | Uso |
|---|---|
| `organizacion_id` | Dueño |
| `public_key` | Lo que lleva el front al registrar / login (UUID / string random) |
| `url_clientes` | Base de firma, inspección, formularios, reset |
| `url_excel_confirmacion` | Si es otro host; si vacío, `url_clientes` |
| `url_datos_proveedor` | Form proveedor |
| `drive_folder_id` | Opcional; Drive no es el portal |
| `logo_url` / `nombre_publico` | Branding del portal |

Seed org 1 con el `.env` actual. El socio tiene su key + `https://clientes.elsocio…`.

### Flujo

```text
Front clientes org 2  (.env NUXT_PUBLIC_ORG_KEY o Host)
        │  register / login  + header X-Org-Key
        ▼
Back resuelve key → organizacion_id
        │
        ▼
users / clientes nacen con esa org

Intranet (JWT socio)
        │  arma link de firma / inspección
        ▼
url_clientes de la org de la COTIZACIÓN (padre), no APP_URL_CLIENTES global
```

Un solo API. No duplicar webhook Meta, Graph, CDN ni Gemini.

### Qué no duplicar (sigue global o solo org 1)

| Host | Destino |
|---|---|
| Este API + webhook Meta | Global (un back) |
| `graph.facebook.com` | Ya por org (tabla WhatsApp) |
| CDN | Puede quedar un host |
| Bitrix, Moodle, Evolution, redis.probusiness.pe | Solo org 1 |

---

## 4. Checklist de implementación

- [ ] Migración `organizacion_portales` + seed org 1
- [ ] Resolver org por `X-Org-Key` / `Host` en `register` y `loginCliente`
- [ ] Helper `urlClientesForOrganizacion($orgId)` y usarlo en:
  - `CotizacionController` (firma)
  - `CotizacionProveedorController` + `SendInspectionMediaJob`
  - `FacturaGuiaController`
  - `EntregaController` + `SendDeliveryFormBulkJob`
  - `CoordinacionWhatsappPayload` (excel + datos proveedor)
  - `ClientesController` / `AuthController` (reset)
- [ ] Quitar hardcodes del front intranet (composable, no service en pages)
- [ ] CORS: origen del front 2
- [ ] Deploy del portal clientes #2 (mismo repo, env distinto) **o** un build que resuelva por dominio
- [ ] Prueba: registro en front 2 → cliente org 2; link de firma abre el front 2

---

## 5. Tiempo restante

Estimado **3–5,5 días (24–43 h)**. Detalle en la sección 8 de [TECNICO_WHATSAPP_INBOX_MULTI_ORG.md](./TECNICO_WHATSAPP_INBOX_MULTI_ORG.md).

Invertido hasta ahora en el análisis de URLs + acuerdo de diseño: **~2 h** (dentro de las ~10,5 h del lote inbox).
