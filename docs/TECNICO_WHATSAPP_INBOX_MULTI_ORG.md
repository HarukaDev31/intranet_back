# Técnico — WhatsApp Inbox por organización

**Estado:** listo para QA (rama `qa`).  
**Fecha:** 2026-09-11  
**Repos:** `probusiness_intranetv3` (front) + `probusiness_intranetv2_back` (API)

Relacionado (pendiente, no va en este lote): [PENDIENTE_PORTAL_CLIENTES_POR_ORG.md](./PENDIENTE_PORTAL_CLIENTES_POR_ORG.md).

---

## 1. Qué se resolvió

Las credenciales Meta del inbox (token, `phone_number_id`, WABA, verify token, app secret) **dejan de vivir solo en `.env`**. Cada organización tiene su fila en `wa_inbox_organizacion_config`. Un socio configura su número; Probusiness (org 1) el suyo.

El webhook Meta sigue siendo **uno** (`/webhooks/meta/whatsapp-inbox`). Se rutea por `phone_number_id` a la org dueña. No hace falta un backend por socio.

---

## 2. Modelo de datos

### `wa_inbox_organizacion_config`

| Columna | Notas |
|---|---|
| `organizacion_id` | Unique. Org 1 se seedéa desde `.env` en la migración. |
| `enabled` | Si está off, esa org no envía ni recibe. |
| `access_token`, `app_secret`, `webhook_verify_token` | Cifrados en reposo (`Crypt` / `APP_KEY`). La API admin **no** los devuelve: flags `*_set`. Campo vacío en el PUT = no cambiar. |
| `phone_number_id`, `waba_id`, `graph_api_version`, `default_language`, `display_number` | Identidad Graph. |
| `legacy_fallback`, `preview_from_template`, `session_when_window_open` | Flags de comportamiento. |

### `wa_inbox_sessions.organizacion_id`

Sesiones existentes se backfillean a org 1. Listado / envío / templates filtran por la org del JWT (nunca por `organizacion_id` del request).

Migraciones (correr en QA):

1. `2026_09_11_160000_create_wa_inbox_organizacion_config`
2. `2026_09_11_160100_seed_wa_inbox_config_menu` → menú `admin/whatsapp`
3. `2026_09_11_170000_encrypt_wa_inbox_organizacion_secrets`

```bash
php artisan migrate
```

**No rotar `APP_KEY`** sin re-cifrar las filas. El helper `WaInboxSecretCrypt` detecta payload Laravel vs plaintext viejo para no doble-cifrar.

---

## 3. Quién configura

| Org | Quién |
|---|---|
| 1 (Probusiness) | Rol `GERENCIA` / `GERENTE GENERAL`, o usuario `root` |
| ≠ 1 | Rol `Socio` |

Método: `Usuario::puedeConfigurarWhatsappInbox()`.  
Pantalla: `/admin/whatsapp`. Middleware front `whatsapp-inbox-config`. Acceso por menú del back (seed) + bypass de `menu-access` si el rol/org califica.

El inbox de chats se abre por **menú**, no por lista hardcodeada de roles. API: solo `jwt.auth`.

---

## 4. Flujo técnico

```text
Meta Cloud API
    │  GET verify / POST firma HMAC
    ▼
/webhooks/meta/whatsapp-inbox
    │  compara verify token y app_secret contra TODAS las orgs
    │  (hash_equals post-decrypt; no hay WHERE token =)
    ▼
phone_number_id → wa_inbox_organizacion_config
    │
    ▼
wa_inbox_sessions (organizacion_id) → conversaciones / envío Graph
    │
    ▼
WS private-whatsapp-inbox.org.{id}
    └─ legado private-whatsapp-inbox.coordinacion solo org 1
```

- Envío Graph usa las creds de la **org de la sesión/conversación**.
- Jobs de coordinación (rotulado, delivery) siguen org 1 vía `ensureDefaultSession()`.
- Front: `resolveWaInboxWsChannel()` en `constants/whatsappInboxWs.ts`. Echo + plugin websocket se suscriben al canal de la org del usuario.
- Header del inbox: nombre de org + engranaje a `/admin/whatsapp` si `can_configure`.

### API admin (JWT)

| Método | Ruta | Body / respuesta |
|---|---|---|
| GET | `/api/whatsapp-inbox/config` | Payload sin secretos + flags `*_set` |
| PUT | `/api/whatsapp-inbox/config` | Campos de form. Secretos vacíos = no tocar |

`organizacion_id` sale del usuario autenticado.

### Qué queda en `.env` del servidor

ffmpeg, colas, límites de media, broadcast. Las vars Meta de org 1 se copian **una vez** al seed; después manda la tabla. Fallback `.env` solo para org 1 si la fila no está.

---

## 5. Archivos clave

**Back**

- `app/Models/WhatsappInbox/WaInboxOrganizacionConfig.php`
- `app/Services/WhatsappInbox/WhatsappInboxOrgConfigService.php`
- `app/Http/Controllers/WhatsappInbox/WhatsappInboxConfigController.php`
- `app/Support/WhatsApp/WaInboxSecretCrypt.php`
- `app/Support/WhatsApp/WaInboxBroadcastChannel.php`
- Webhook / send / session / template / conversation services (scope por org)
- `routes/modules/whatsapp-inbox.php`, `routes/channels.php`

**Front**

- `pages/admin/whatsapp.vue`
- `composables/whatsapp-inbox/useWhatsappInboxConfig.ts`
- `services/whatsappInbox/whatsappInboxConfigService.ts`
- `middleware/whatsapp-inbox-config.ts`
- `constants/whatsappInboxWs.ts`, `ensureWaInboxEchoChannel.ts`, `plugins/websocket.client.ts`

---

## 6. Cómo probar en QA

1. Migrar.
2. Cerrar sesión y volver a entrar (menú `WhatsApp`).
3. Org 1 (Gerencia / root): `/admin/whatsapp` — debe verse la fila seededa desde `.env`. Completar secretos si el seed no los trajo.
4. Socio: misma pantalla, **su** número. No debe ver chats de org 1.
5. Webhook Meta: el `phone_number_id` del socio tiene que estar en la fila y el verify token / app secret coincidir. Un solo callback URL.
6. Enviar un template y un texto; confirmar WS en el canal `whatsapp-inbox.org.{id}`.
7. PUT con token vacío: el token anterior no se borra.

---

## 7. Lote extra que viaja en el mismo push a `qa`

No es inbox, pero estaba en working tree y se sube junto:

- Home operativa / stats de carga consolidada.
- Customers: KPI + listado filtran juntos.
- Documentación / abiertos / completados de consolidado (pasos socio, packing list china).
- Cotización resumen (wizard crear).
- BD clientes (alcance org).
- Helpers: `ClienteLookupHelper`, `CargaLabel`, `CountryPhoneHelper`, `phone_code` en `pais_flags`.

---

## 8. Tiempos (commits `qa` desde 2026-09-09)

Fuente: log de `qa` en ambos repos, 9 sep 00:46 → 11 sep 09:24. No es clock de sesión; es estimado por densidad de commits + tamaño.

**Calendario:** ~2,5 días. **Efectivo:** **~38–48 h** (unas **5–6 jornadas**). El inbox de hoy es solo ~8–10 h de ese total.

| Día | Ventana (commits) | Qué | Est. |
|---|---|---|---|
| 9 | 00:46–08:00 | Soporte TI push + logout JWT (ruido, no multi-org) | 3–4 h |
| 9 | 11:55–14:53 | Merge QA + **fase 1/2 multi-org**: scope, `organizacion_id` en pipeline, middleware, tapar fugas | 7–9 h |
| 9–10 | 20:14–01:17 | BD clientes por org + panel-acceso (orgs, cargos, usuarios) | 5–7 h |
| 10 | 09:47–18:36 | **Cotización resumen IA** (tablas, Gemini, wizard, listado, guardado) + menús por org + socio crea consolidado / 2 de 3 pasos | 10–13 h |
| 11 | 00:43 | Customers + cierre a BD clientes + wizard/listado socio + i18n almacén | 6–8 h |
| 11 | 07:22–07:28 | phpstan modelos | 0,5–1 h |
| 11 | ~mañana → 09:24 | Inbox WhatsApp por org + cifrado + docs portal (un commit gordo) | 8–10 h |
| | | **Invertido 9–11 sep** | **~38–48 h** |

Conteos (sin merges): front ~11 commits / +6,0k −0,6k; back ~27 commits / +10,2k −0,9k.

### Qué falta — portal clientes por org (ver doc pendiente)

| Bloque | Estimado restante |
|---|---|
| Tabla `organizacion_portales` + seed org 1 + resolver key en register/login | 4–6 h |
| Armar links (`url_clientes`, excel, datos proveedor) desde la org del padre | 4–6 h |
| Sacar hardcodes `clientes.probusiness.pe` / branding intranet | 2–3 h |
| Segundo front clientes (mismo código, otro deploy o host) + CORS | 8–16 h |
| Drive / datos proveedor en la misma fila | 2–4 h |
| Prueba con org socio real (registro + firma + inspección) | 4–8 h |
| **Total restante** | **~24–43 h (3–5,5 días)** |

El cuello de botella no es la tabla: es montar el front 2 y no romper links que hoy salen del `.env` global.
