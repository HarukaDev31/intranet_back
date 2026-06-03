# Meta WhatsApp (coordinación / consolidado)

Historial operativo en **WhatsApp Inbox** (`wa_inbox_*`). Envíos vía `SendCoordinacionWhatsAppJob` → `WhatsappInboxCoordinacionOutboundService`.

`MetaWhatsAppCoordinacionService` solo expone **Graph API** (plantillas y media de encabezado).

## Dos batches en Horizon

1. **Programático** — `SendCoordinacionWhatsAppJob` (`Programático · …`). Crea/actualiza filas en `wa_inbox_*`.
2. **Envío inbox** — `SendWaInboxOutboundJob` (`Inbox envío · …`), despachado al finalizar el batch 1. Solo mensajes `pending` hacia Meta.

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

Payload de plantilla: `chat_preview` (texto en el inbox). Ver `docs/META_WHATSAPP_TEMPLATES_CUERPO.md` y `probusiness_intranetv3/docs/WHATSAPP_INBOX_ENVIOS_PROGRAMATICOS.md`.
