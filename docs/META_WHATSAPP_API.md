# Meta WhatsApp (coordinación / consolidado)

Historial operativo en **WhatsApp Inbox** (`wa_inbox_*`). Envíos vía `SendCoordinacionWhatsAppJob` → `WhatsappInboxCoordinacionOutboundService`.

`MetaWhatsAppCoordinacionService` solo expone **Graph API** (plantillas y media de encabezado).

## Dos batches en Horizon

1. **Programático** — `SendCoordinacionWhatsAppJob` (`Programático · …`). Crea/actualiza filas en `wa_inbox_*`.
2. **Envío inbox** — `SendWaInboxOutboundJob` en **cadena secuencial** (`Bus::chain`, orden `sort_order`, pausa `META_WHATSAPP_INBOX_OUTBOUND_STEP_DELAY`). Solo mensajes `pending` hacia Meta.

Chat inbox: media S3 con `OBJECT_STORAGE_INBOX_DISPLAY_PRESIGNED=true` (evita CDN 403 en `templates/`).

Helpers: `runWhatsAppCoordinacionBatch()` o `setWhatsAppCoordinacionBatchId` + `dispatchWhatsAppCoordinacionBatch`.

Tablas: `whatsapp_coordinacion_batches` (`laravel_batch_id`, `outbound_laravel_batch_id`, `job_domain`), `whatsapp_coordinacion_batch_items`.

## Activar

```env
META_WHATSAPP_COORDINACION_ENABLED=true
META_WHATSAPP_ACCESS_TOKEN=tu_token_meta
META_WHATSAPP_PHONE_NUMBER_ID=1062249786981832
META_WHATSAPP_QUEUE=notificaciones
META_WHATSAPP_INBOX_QUEUE=notificaciones
```

Payload de plantilla: `body_parameters` obligatorios; `chat_preview` opcional (por defecto se resuelve desde Graph/Meta vía `WhatsappInboxTemplateService::resolvePreviewText`, config `META_WHATSAPP_COORDINACION_PREVIEW_FROM_TEMPLATE=true`). Ver `docs/META_WHATSAPP_TEMPLATES_CUERPO.md`.
