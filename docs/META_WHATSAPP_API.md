# Meta WhatsApp + Bitrix (coordinación / consolidado)

Flujo implementado en `MetaWhatsAppCoordinacionService` y `SendCoordinacionWhatsAppJob`.

## Bitrix línea 39 (obligatorio para coordinación)

Flujo por mensaje (con `META_WHATSAPP_BITRIX_REGISTER_OPENLINE_MESSAGE=true`, valor por defecto):

1. **Meta Cloud API** — plantilla al cliente primero (no espera Bitrix).
2. Se inserta fila en `whatsapp_coordinacion_bitrix_registros` y se encola `ProcessWhatsAppCoordinacionBitrixRegistroJob`.
3. El job (hasta `META_WHATSAPP_BITRIX_REGISTER_MAX_ATTEMPTS` reintentos, por defecto 3):
   - Contacto + `imopenlines.crm.chat.get` (canal coordinación, no ventas).
   - `imopenlines.session.intercept` si hay `CHAT_ID`.
   - **`imopenlines.crm.message.add`** con `bitrix_message`.
4. Tras 3 fallos: `status=failed` y **no** se vuelve a encolar ese registro.

La API **no** acepta `LINE_ID` en message.add; solo `CHAT_ID` del canal correcto.

## Batch en Horizon (rotulado)

Con `META_WHATSAPP_COORDINACION_ENABLED=true`, `SendRotuladoJob` agrupa todos los `SendCoordinacionWhatsAppJob` en un **batch de Laravel** (`Bus::batch`).

- En Horizon: menú **Batches** → nombre tipo `Rotulado · carga 10 · cot. #9438 · Cliente`.
- Cada job del batch muestra su etiqueta (`displayName`: “PDF rotulado — ROBA6”, “Bienvenida rotulado”, etc.).
- Requiere migración `job_batches` (`php artisan migrate`).
- Los jobs de Bitrix (`ProcessWhatsAppCoordinacionBitrixRegistroJob`) van aparte, no bloquean el batch de envío Meta.

Si el **cliente** recibe dos WhatsApp iguales, el conector de la línea 39 en Bitrix está reenviando además de Meta; hay que ajustar el canal en Bitrix24, no quitar el paso 4 (sin eso el chat 39 queda vacío para el equipo).

`META_WHATSAPP_BITRIX_REGISTER_OPENLINE_MESSAGE=false` solo para pruebas sin historial en open line.

## Activar

```env
META_WHATSAPP_COORDINACION_ENABLED=true
META_WHATSAPP_ACCESS_TOKEN=tu_token_meta
META_WHATSAPP_PHONE_NUMBER_ID=1062249786981832
BITRIX_WEBHOOK_URL=https://probusiness.bitrix24.es/rest/181/...
BITRIX_WEBHOOK_INTERCEPT=https://probusiness.bitrix24.es/rest/181/.../
AWS_DEFAULT_REGION=us-east-2
```

`META_WHATSAPP_LEGACY_FALLBACK=true` mantiene redis.probusiness.pe si un `sendMessage` no pasa plantilla Meta.

## Uso en Jobs

```php
use App\Support\WhatsApp\CoordinacionWhatsappPayload;

$this->queueCoordinacionWhatsApp(
    CoordinacionWhatsappPayload::entregaLinkLima(
        $telefono . '@c.us',
        $carga,
        $nombreCliente,
        $linkFormulario,
        $textoParaBitrix  // MESSAGE con variables ya reemplazadas
    )
);
```

Documento + header:

```php
$this->queueCoordinacionWhatsApp(
    CoordinacionWhatsappPayload::documentTemplate(
        $phone,
        'pb_rotulado_pdf_producto_v1',
        ['nombre_producto' => '...', 'codigo_proveedor' => '...'],
        $rutaPdfLocal,
        $captionParaBitrix
    )
);
```

## `sendMessage` / `sendMedia` (fromNumber `consolidado`)

Con `META_WHATSAPP_COORDINACION_ENABLED=true`:

- Si pasas `$meta` con `template` → cola job Meta.
- Si no pasas `$meta` y `legacy_fallback=true` → cola redis legacy en el job.
- Si `legacy_fallback=false` → solo plantillas explícitas.

```php
$this->sendMessage($texto, $phone, 0, 'consolidado', [
    'template' => 'pb_entrega_recordatorio_v1',
    'body_parameters' => ['mensaje' => $texto],
    'bitrix_message' => $texto,
]);
```

## Jobs ya migrados a plantillas

- `SendDeliveryFormBulkJob` (E01/E03 + E02/E04)
- `SendDeliveryConfirmationWhatsAppLimaJob` (E05)
- `SendDeliveryConfirmationWhatsAppProvinceJob` (E06)
- `SendRotuladoJob` (W01, W02 parcial)

Pendiente: resto de `sendMessage`/`sendMedia` en rotulado, documentación, entregas, calculadora (ver `META_WHATSAPP_TEMPLATES_CUERPO.md`).

## Cola

```bash
php artisan queue:work --queue=notificaciones
```
