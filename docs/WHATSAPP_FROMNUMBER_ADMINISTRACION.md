# Inventario WhatsApp — `fromNumber = 'administracion'`

Listado de llamadas a `sendMessage` / `sendMedia` del `WhatsappTrait` que pasan **`'administracion'`** como instancia (número/WABA de contabilidad y facturación).

**Relacionado:** plantillas Meta sugeridas en [`META_WHATSAPP_TEMPLATES.md`](./META_WHATSAPP_TEMPLATES.md) § 5.3 (`pb_admin_*`) y textos en [`META_WHATSAPP_TEMPLATES_CUERPO.md`](./META_WHATSAPP_TEMPLATES_CUERPO.md) § 5.3.

**Generado:** revisión estática del código en `app/`.

---

## Resumen

| Métrica | Valor |
|---------|--------|
| Archivos PHP con envíos activos | **8** |
| Puntos de envío (llamadas `sendMessage` / `sendMedia`) | **~22** activas + **1 comentada** |
| Jobs dedicados | **6** (3 sin `dispatch` en el repo — ver nota) |
| Controladores | **3** |
| Plantillas Meta § A (admin) aplicables | **~13** (A01–A13) |

---

## Por archivo

### 1. `FacturaGuiaController` — 9 envíos (síncronos, API intranet)

| # | Método | Tipo | Destinatario | Descripción | Ruta HTTP (prefijo factura-guía) |
|---|--------|------|--------------|-------------|----------------------------------|
| 1 | `sendFactura` | `sendMedia` | Cliente | PDF factura comercial + mensaje crédito fiscal | `POST …/send-factura/{idCotizacion}` |
| 2 | `sendGuia` | `sendMedia` | Cliente | PDF guía remisión (legacy `guia_remision_url`) | `POST …/send-guia/{idCotizacion}` |
| 3 | `enviarFormulario` | `sendMessage` | Cliente(s) | Link formulario comprobante (bulk por contenedor) | `POST …/contabilidad/enviar-formulario/{idContenedor}` |
| 4 | `sendComprobantesContabilidad` | `sendMedia` × N | Cliente | Un PDF por comprobante de pago | `POST …/contabilidad/send-comprobantes/{idCotizacion}` |
| 5 | `sendGuiasContabilidad` | `sendMedia` × N | Cliente | Guías remisión (tabla `guia_remision` o legacy) | `POST …/contabilidad/send-guias/{idCotizacion}` |
| 6 | `sendDetraccionesContabilidad` | `sendMedia` × N | Cliente | Constancias de detracción | `POST …/contabilidad/send-detracciones/{idCotizacion}` |
| 7 | `sendFormularioContabilidad` | `sendMessage` | Cliente | Formulario comprobante (una cotización) | `POST …/contabilidad/send-formulario/{idCotizacion}` |

**Builders de texto:** `buildMensajeFormularioNuevo`, `buildMensajeFormularioAntiguo` (mismos mensajes que jobs A01/A02).

**Plantillas Meta sugeridas:** A01, A02, A03, A04, A05, A06, A11, A12, A13.

---

### 2. `PagosController` — 2 envíos

| # | Método | Tipo | Cuándo | Descripción |
|---|--------|------|--------|-------------|
| 8 | `actualizarPagoCoordinacion` | `sendMessage` | `status === CONFIRMADO` | Confirmación de pago coordinación (monto, concepto, consolidado) |
| 9 | `updateStatusCurso` | `sendMessage` | `status === CONFIRMADO` | Confirmación de pago de curso |

**Plantillas Meta:** no hay plantilla fija en CUERPO; mensaje armado en runtime (UTILITY genérica o sesión 24h).

---

### 3. `CotizacionProveedorController` — 4 envíos

| # | Método | Tipo | Cuándo | Descripción |
|---|--------|------|--------|-------------|
| 10 | `procesarEstadoCobrando` | `sendMessage` + `sendMedia` | Estado cobrando proveedor | Cobro CBM preliminar + imagen `pagos-full.jpg` |
| 11 | `sendReservationMessage` | `sendMessage` + `sendMedia` | Tras inspección / reserva | Mismo flujo cobro reserva CBM + imagen pagos |

**Plantilla Meta sugerida:** A09 (`pb_admin_cobro_reserva_cbm_v1`) + A08 (`pb_admin_pagos_imagen_v1`).

**⚠ Bug en `procesarEstadoCobrando` (L1224–1228):** los argumentos no coinciden con la firma del trait:

```php
// Actual (incorrecto): 'administracion' se interpreta como teléfono
$this->sendMessage($message, 'administracion');
$this->sendMedia($pagosUrl, 'image/jpg', 'administracion');

// Debería ser como sendReservationMessage / ForceSendCobrandoJob:
$this->sendMessage($message, $phoneNumberId, 0, 'administracion');
$this->sendMedia($pagosUrl, 'image/jpg', null, $phoneNumberId, 10, 'administracion');
```

---

### 4. Jobs en cola

| Job | Cola | Envíos | Disparado desde | Plantilla Meta |
|-----|------|--------|-----------------|----------------|
| `SendComprobanteFormNotificationJob` | `notificaciones` | `sendMessage` → **cliente** | `ComprobanteFormController` al guardar formulario | A03 / A04 |
| | | `// sendMessage` → admin **comentado** (L76) | | — |
| `SendReminderPagoWhatsAppJob` | `importaciones` | `sendMessage` + `sendMedia` (imagen pagos) | `CotizacionFinalController` recordatorio pago | A07 + A08 |
| `ForceSendCobrandoJob` | `importaciones` | `sendMessage` + `sendMedia` (imagen pagos) | `CotizacionProveedorController` ~L3244 | A09 + A08 |
| `SendViaticoWhatsappNotificationJob` | `notificaciones` | `sendMessage` + `sendMedia` (comprobante) | `ViaticoController` retribución | A10 |
| `SendContabilidadComprobantesJob` | `notificaciones` | `sendMedia` × N | **Sin `::dispatch` en el repo** | A11 |
| `SendContabilidadGuiasJob` | `notificaciones` | `sendMedia` × N | **Sin `::dispatch` en el repo** | A12 |
| `SendContabilidadDetraccionesJob` | `notificaciones` | `sendMedia` × N | **Sin `::dispatch` en el repo** | A13 |

Los tres jobs `SendContabilidad*` duplican la lógica ya expuesta en **`FacturaGuiaController::send*Contabilidad`** (síncrono). Hoy el front usa las rutas del controlador; los jobs quedan como candidatos a unificar o eliminar.

---

## Mapa rápido: flujo → plantilla Meta (WABA administracion)

| Flujo de negocio | ID doc | Nombre Meta |
|------------------|--------|-------------|
| Formulario comprobante (link nuevo) | A01 | `pb_admin_comprobante_form_link_nuevo_v1` |
| Formulario comprobante (confirmar datos antiguos) | A02 | `pb_admin_comprobante_form_confirm_antiguo_v1` |
| Cliente completó formulario FACTURA | A03 | `pb_admin_comprobante_cliente_factura_v1` |
| Cliente completó formulario BOLETA | A04 | `pb_admin_comprobante_cliente_boleta_v1` |
| Envío factura comercial PDF | A05 | `pb_admin_factura_comercial_v1` |
| Envío guía remisión PDF | A06 | `pb_admin_guia_remision_v1` |
| Recordatorio pago cotización final | A07 | `pb_admin_recordatorio_pago_v1` |
| Imagen números de cuenta | A08 | `pb_admin_pagos_imagen_v1` |
| Cobro reserva CBM preliminar | A09 | `pb_admin_cobro_reserva_cbm_v1` |
| Viático / retribución | A10 | `pb_admin_viatico_adjunto_v1` |
| Comprobante pago (contabilidad) | A11 | `pb_admin_contabilidad_comprobante_v1` |
| Guía remisión (contabilidad) | A12 | `pb_admin_contabilidad_guia_v1` |
| Detracción (contabilidad) | A13 | `pb_admin_contabilidad_detraccion_v1` |

---

## Secuencias habituales (varios mensajes seguidos)

| Secuencia | Pasos | `fromNumber` |
|-----------|-------|--------------|
| Recordatorio pago final | 1) Texto A07 → 2) Imagen A08 | administracion |
| Cobro CBM / Force cobrando | 1) Texto A09 → 2) Imagen A08 | administracion |
| Comprobantes contabilidad | 1) Texto intro + N × PDF | administracion |
| Guías contabilidad | 1) Texto intro + N × PDF | administracion |
| Detracciones | 1) Texto intro + N × PDF | administracion |

En Meta cada paso con texto distinto = **plantilla distinta** (o mensaje dentro de ventana 24h).

---

## Lo que NO usa `administracion` pero está cerca

- **`SendDeliveryFormBulkJob`**, entregas Lima/Provincia → `consolidado` (E01–E04).
- **`CotizacionFinalController`** envío cotización final masivo → `consolidado` (C01–C04); solo el **recordatorio** usa administracion vía job.
- Campo BD `note_administracion` → no es `fromNumber`.

---

## Checklist migración Meta (solo administracion)

- [ ] Registrar plantillas A01–A13 en la WABA **administracion**.
- [ ] Priorizar: A01, A02, A03, A04, A07, A09, A05, A06 (mayor volumen).
- [ ] Corregir `procesarEstadoCobrando` antes de migrar A09 en ese path.
- [ ] Decidir si `SendContabilidad*Job` se usan o se eliminan a favor del controlador.
- [ ] Variables de entorno / config: token y phone_id de WABA administracion.

---

*Actualizar este archivo si se agregan envíos con `'administracion'` o se cambian rutas en `routes/modules/carga-consolidada.php`.*
