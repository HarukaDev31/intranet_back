# Plantillas MARKETING — dónde se llaman, qué texto sale y 2 opciones UTILITY

Complemento de [`META_WHATSAPP_MARKETING_TO_UTILITY.md`](./META_WHATSAPP_MARKETING_TO_UTILITY.md).  
Revisión de código: 2026-06-03.

**Leyenda**

- **Opción A:** cambio mínimo (solo plantilla Meta o caption).
- **Opción B:** cambio recomendado (plantilla + ajuste backend / plantilla dedicada).
- **Quitar:** la plantilla sobra o está mal usada; conviene dejar de usarla o borrarla en Meta.

---

## Resumen ejecutivo

| Plantilla | ¿Conectada en código? | Problema principal |
|-----------|----------------------|-------------------|
| `pb_entrega_conformidad_texto_v1` | Sí | Texto promocional hardcodeado en PHP |
| `pb_entrega_conformidad_fotos_v1` | **No** (código usa `pb_entrega_conformidad_foto_v1`) | Huérfana en Meta; duplicado de E07 |
| `pb_consolidado_cotizacion_final_pdf_v1` | Sí | Caption genérico; `{{carga}}` no se envía en payload |
| `pb_consolidado_pago_preliminar_v1` | **No** | Registrada en Meta; reserva CBM va por `administracion` legacy |
| `pb_inspeccion_imagen_v1` / `_video_v1` | Sí | Caption con emojis; cuerpo plantilla redundante con media |
| `pb_proveedor_inspeccion_manual_v1` | **No** | Solo método en payload; sin callers |
| `pb_entrega_recordatorio_v1` | Sí | **Mal usada** en envío formulario entrega (mensaje enorme en `{{mensaje}}`) |
| `pb_consolidado_pagos_img_v1` | Sí | Body fijo comercial; caption distinto al body |
| `pb_proveedor_llegada_china_v1` | Sí | Pregunta comercial + emoji |

---

## 1. `pb_entrega_conformidad_texto_v1`

### Dónde se llama

| Archivo | Método | Ruta API |
|---------|--------|----------|
| `EntregaController.php` | `uploadConformidad` | `POST /entregas/conformidad` |
| `EntregaController.php` | `uploadConformidadForCotizacion` | `POST /entregas/conformidad/{idCotizacion}` |

Payload: `CoordinacionWhatsappPayload::entregaConformidadTexto($phone, $nombre, $carga, $message)`.

### Texto que sale hoy (runtime)

Armado en `EntregaController` ~3052–3054:

```
Hola {nombre} 👋
Adjunto el sustento de entrega correspondiente a su importación del consolidado #{carga}.

Muchas gracias por confiar en Pro Business. Si tiene una próxima importación, estaremos encantados de ayudarlo nuevamente. No dude en escribirnos ✈️📦
```

Variables Meta: `nombre`, `carga` (el cuerpo de plantilla coincide; el `$message` es para Bitrix/preview).

### Opción A — solo plantilla Meta (UTILITY)

```
Hola {{nombre}},

Adjuntamos el sustento de entrega de su importación consolidado #{{carga}}.
```

Backend: **sin cambios** (quitar emojis del `$message` en PHP para que preview/Bitrix coincida).

### Opción B — plantilla + PHP

Plantilla Meta (UTILITY):

```
Hola {{nombre}},

Le enviamos el sustento de entrega de su importación consolidado #{{carga}}.
Este mensaje confirma la recepción del material de conformidad.
```

PHP (`EntregaController`): reemplazar `$message` por el mismo texto sin promoción; centralizar en constante o método `buildConformidadTexto()`.

---

## 2. `pb_entrega_conformidad_fotos_v1` (Meta) vs `pb_entrega_conformidad_foto_v1` (código)

### Dónde se llama (código real)

| Archivo | Método | Plantilla usada |
|---------|--------|-----------------|
| `EntregaController.php` | `uploadConformidad*` | **`pb_entrega_conformidad_foto_v1`** (IMAGE) |

Caption por foto (~3063, 3076):

```
Sustento de entrega — foto 1. 📷
Sustento de entrega — foto 2. 📷
```

Variable Meta foto: `{{numero}}` = `1` o `2`.

### Problema

- En Meta existe **`pb_entrega_conformidad_fotos_v1`** (MARKETING) con el texto promocional largo de E07 — **no la usa el backend**.
- Posible duplicado / confusión al revisar BM.

### Opción A

**Quitar** `pb_entrega_conformidad_fotos_v1` de Meta (si no se usa en otro sistema).  
Mantener solo `pb_entrega_conformidad_foto_v1` con body UTILITY:

```
Sustento fotográfico de entrega — foto {{numero}}.
```

Caption PHP: `Sustento de entrega — foto {n}.` (sin 📷).

### Opción B

Unificar nombre en Meta y código (`foto_v1` o `fotos_v1`) y **no enviar** plantilla de texto largo antes de las fotos si el texto UTILITY de §1 ya se envió; las fotos solo llevan caption corto transaccional.

---

## 3. `pb_consolidado_cotizacion_final_pdf_v1`

### Dónde se llama

| Archivo | Método | Cuándo |
|---------|--------|--------|
| `CotizacionFinalController.php` | `updateEstadoCotizacionFinal` (~1188) | Al pasar cotización final a estado enviado |

Payload: `consolidadoCotizacionFinalPdf($phone, $pathPdf, $pdfCaption, 3)` — **sin** `carga` en array de parámetros (body Meta tiene `{{carga}}`).

Caption / preview hoy:

```
Cotización final de importación
```

Secuencia en el mismo flujo: mensaje largo `pb_consolidado_cotizacion_final_v1` (texto) → PDF → resumen pago → imagen cuentas.

### Opción A — plantilla Meta

Body UTILITY:

```
Adjuntamos la cotización final de su importación, consolidado #{{carga}}.
```

Backend: pasar `carga` en payload (añadir a `consolidadoCotizacionFinalPdf()`).

### Opción B — acortar flujo

- Quitar emoji del mensaje previo `consolidado_cotizacion_final_v1` (“un gusto saludarte”, “Pronto le aviso…”) — es otro candidato MARKETING.
- PDF solo con body UTILITY + `{{carga}}`; el detalle de montos queda en el PDF, no repetir en WhatsApp previo.

---

## 4. `pb_consolidado_pago_preliminar_v1`

### Dónde se llama

**No conectada.** Existe en Meta; el código de reserva CBM usa `sendMessage` legacy:

| Archivo | Método | Instancia |
|---------|--------|-----------|
| `CotizacionProveedorController.php` | `sendReservationMessage` (~2378) | `administracion` |
| `CotizacionProveedorController.php` | `procesarEstadoCobrando` (~1265) | `administracion` (teléfono mal pasado) |

### Texto que sale hoy (sin plantilla Meta)

```
Hola {nombre}, te escribe el área de contabilidad de Probusiness.

Reserva de espacio:
*Consolidado #{carga}-2025*

Ahora tienes que hacer el pago del CBM preliminar para poder subir su carga en nuestro contenedor.

☑ CBM Preliminar: {volumen} cbm
☑ Costo CBM: ${monto}
☑ Pendiente de pago CBM: ${pendiente}   (si aplica)
📅 Fecha Limite de pago: {fecha}

⚠ Nota: Realizar el pago antes del llenado del contenedor.

📦 En caso hubiera variaciones en el cubicaje se cobrará la diferencia en la cotización final.

Apenas haga el pago, envíe por este medio para hacer la reserva.
```

### Opción A

**Quitar** plantilla Meta `pb_consolidado_pago_preliminar_v1` si no van a cablearla, o dejarla pendiente.

Acortar mensaje legacy (mientras tanto):

```
Consolidado #{carga}. Pago preliminar CBM: {volumen} cbm — ${monto}. Fecha límite: {fecha}. Envíe comprobante por este chat.
```

### Opción B

Cablear `CoordinacionWhatsappPayload::consolidadoPagoPreliminar()` (crear método) con plantilla UTILITY + variables fijas:

- `{{carga}}`, `{{cbm}}`, `{{monto}}`, `{{fecha_limite}}`

Quitar del mensaje: “te escribe el área…”, emojis, párrafo de variaciones (mover a PDF/email si hace falta).

---

## 5. `pb_inspeccion_imagen_v1` / `pb_inspeccion_video_v1`

### Dónde se llaman

| Archivo | Método / Job |
|---------|----------------|
| `CotizacionProveedorController.php` | `sendSingleInspectionFile` |
| `SendInspectionMediaJob.php` | loop imágenes / videos |

Mensaje principal previo usa **`pb_inspeccion_llegada_v1`** (no MARKETING), no estas plantillas.

Caption / body hoy (~2298, job ~215):

```
📦 Inspección — proveedor {codigo_proveedor} 📦
```

Variable Meta: `codigo_proveedor`.

### Opción A — plantilla Meta

Body UTILITY:

```
Inspección en almacén — proveedor {{codigo_proveedor}}. Imagen adjunta.
```

(video: “Video adjunto.”)

Caption PHP: igual sin emojis.

### Opción B — acortar / quitar redundancia

Caption PHP vacío o solo `{codigo_proveedor}`; todo el contexto va en el body UTILITY de la plantilla (una línea).  
El mensaje `inspeccion_llegada_v1` ya dice que llegó a Yiwu — evitar repetir “inspección” tres veces en la secuencia.

---

## 6. `pb_proveedor_inspeccion_manual_v1`

### Dónde se llama

**No conectada.** Solo definida en `CoordinacionWhatsappPayload::proveedorInspeccionManual()`.

Inspección real usa `inspeccionLlegada` + imagen/video, no esta plantilla.

### Opción A

**Quitar** de Meta si no hay plan de uso manual desde intranet.

### Opción B

Si se usará para mensajes manuales del coordinador: body UTILITY fijo + `{{mensaje}}` acotado a **una línea factual**, p. ej.:

```
Actualización inspección proveedor {{codigo_proveedor}}: {{mensaje}}
```

(añadir variable `codigo_proveedor` en plantilla y payload).

---

## 7. `pb_entrega_recordatorio_v1` ⚠️ uso incorrecto

### Dónde se llama

| Archivo | Método | Ruta | Qué mete en `{{mensaje}}` |
|---------|--------|------|---------------------------|
| `EntregaController.php` | `sendMessageDelivery` | `POST /delivery/send-message/{id}` | **`buildDeliveryFormsMessage()` completo** (~400+ caracteres) |
| `EntregaController.php` | `sendRecordatorioFormularioDelivery` | `POST /delivery/recordatorio-formulario/{id}` | Texto libre del request |
| `EntregaController.php` | `sendCobroCotizacionFinalDelivery` | `POST /delivery/cobro-cotizacion-final/{id}` | Texto libre del request (cobro) |

### Texto típico en `sendMessageDelivery` (hoy entra entero en `{{mensaje}}`)

```
MENSAJE CLIENTES LIMA:   (o PROVINCIA / genérico)

# Consolidado {carga}
Logística: Cliente Lima

✅ *Registrarse*, en el siguiente link.
✅ *Plazo máximo* para el registro: 48 horas
✅ *Organizaremos los envíos* una vez liberado el contenedor.
✅ *FORMS:* {url}

⚠  De no llenar el formulario no se programará el envío de sus productos.

-------------------------
msj2:
Importante:

➡ La información registrada será utilizada para la *emisión de guías de remisión*.
➡ *Validar* que sus datos estén correctos y completos.
...
➡ Los envíos se realizan con *Marvisur*.
...
```

**Problema:** plantilla genérica “Recordatorio” + bloque enorme con emojis y reglas comerciales → Meta MARKETING seguro.  
**Además:** el bulk (`SendDeliveryFormBulkJob`) ya usa **`entregaLinkLima` / `entregaLinkProvincia` + reglas** — el envío individual está desalineado.

### Opción A — arreglar solo `sendMessageDelivery`

Dejar de usar `entregaRecordatorio`. Copiar lógica del bulk:

- `entregaLinkLima` o `entregaLinkProvincia` (mensaje 1)
- `entregaReglasLima` / reglas provincia (mensaje 2)

**Quitar** `pb_entrega_recordatorio_v1` de este flujo.

### Opción B — recordatorio real UTILITY

Para `sendRecordatorioFormularioDelivery`, fijar plantilla con variables:

```
Recordatorio registro de entrega — consolidado #{{carga}}.

Complete el formulario: {{link_formulario}}

Plazo: 48 horas desde este aviso.
```

Backend: dejar de aceptar `message` libre; construir desde `carga` + URL.  
Para cobro cotización final: usar **`entregaCobroServicios`** (ya existe), no recordatorio.

---

## 8. `pb_consolidado_pagos_img_v1`

### Dónde se llama

| Archivo | Método | Contexto |
|---------|--------|----------|
| `CotizacionFinalController.php` | `updateEstadoCotizacionFinal` | Tras cotización final |
| `CotizacionProveedorController.php` | `validateToSendInspectionMessage` | Tras 1ª inspección |
| `EntregaController.php` | `sendCobroDeliveryDelivery` | Cobro delivery/montacarga |

Caption PHP hoy:

```
Números de cuenta para pago
Números de cuenta          (CotizacionFinal)
```

Body plantilla Meta:

```
Medios de pago Pro Business.
```

Archivo: `public/assets/images/pagos-full.jpg` o `pagos-full-soles.jpeg`.

### Opción A — plantilla Meta

Body UTILITY:

```
Datos bancarios para el pago de su importación. Imagen adjunta.
```

Unificar caption PHP: `Datos bancarios para pago`.

### Opción B — variable `{{carga}}`

Body:

```
Datos bancarios — consolidado #{{carga}}.
```

Actualizar `consolidadoPagosImg()` para recibir `$carga` en los 3 call sites.

---

## 9. `pb_proveedor_llegada_china_v1`

### Dónde se llama

| Archivo | Método | Trigger |
|---------|--------|---------|
| `NotifyArriveDateToday.php` | `handle` | Cron `notify:arrive-date-today` |

Payload: `proveedorLlegadaChina($phone, $nombre, $codigo, $mensaje)`.

### Texto que sale hoy

Hardcode en comando ~71 (igual que plantilla Meta):

```
Hola 👋 {nombre_cliente} la carga de tu proveedor {codigo_proveedor} aun no llega a nuestro almacen de China, ¿tienes alguna noticia por parte de tu proveedor?
```

### Opción A — plantilla + comando

Meta UTILITY:

```
Hola {{nombre_cliente}},

Seguimiento logístico: la carga del proveedor {{codigo_proveedor}} aún no consta en almacén China. Indíquenos fecha de despacho o guía si la tiene.
```

Comando: mismo texto sin emoji ni signo de pregunta comercial.

### Opción B — acortar

```
{{nombre_cliente}} — proveedor {{codigo_proveedor}}: sin ingreso a almacén China a la fecha. Envíe actualización de despacho.
```

Considerar **no enviar** si `arrive_date_china` es hoy pero el cron filtra “sin inspección” — el mensaje dice “aún no llega” (puede ser confuso). Revisar regla de negocio del comando.

---

## Plantillas MARKETING sin uso en runtime (candidatas a quitar en Meta)

1. `pb_entrega_conformidad_fotos_v1` — duplicado; código usa `foto_v1`.
2. `pb_consolidado_pago_preliminar_v1` — reserva va por `administracion` sin Meta.
3. `pb_proveedor_inspeccion_manual_v1` — sin callers.

---

## Cambios backend prioritarios (impacto UTILITY)

| Prioridad | Cambio | Archivo |
|-----------|--------|---------|
| 1 | `sendMessageDelivery` → usar `entregaLink*` + reglas, **no** `entregaRecordatorio` | `EntregaController.php` |
| 2 | Quitar cierre promocional de conformidad | `EntregaController.php` ~3052 |
| 3 | `sendCobroCotizacionFinalDelivery` → `entregaCobroServicios` | `EntregaController.php` |
| 4 | Pasar `carga` a `consolidadoCotizacionFinalPdf` | `CoordinacionWhatsappPayload.php` + `CotizacionFinalController.php` |
| 5 | Acortar texto `NotifyArriveDateToday` | `NotifyArriveDateToday.php` |
| 6 | Unificar captions inspección sin emojis | `CotizacionProveedorController.php`, `SendInspectionMediaJob.php` |

---

## Referencia rápida call sites

```
uploadConformidad          → pb_entrega_conformidad_texto_v1 + pb_entrega_conformidad_foto_v1
sendMessageDelivery        → pb_entrega_recordatorio_v1  ❌ mal planteado
sendRecordatorioFormulario → pb_entrega_recordatorio_v1  (texto libre)
sendCobroCotizacionFinal   → pb_entrega_recordatorio_v1  ❌ debería ser entregaCobroServicios
sendCobroDeliveryDelivery  → entregaCobroServicios + pb_consolidado_pagos_img_v1
updateEstadoCotizacionFinal→ pb_consolidado_cotizacion_final* + pb_consolidado_pagos_img_v1
validateToSendInspection   → pb_inspeccion_llegada_v1 + img/video + pb_consolidado_pagos_img_v1
SendInspectionMediaJob     → pb_inspeccion_llegada_v1 + img/video
notify:arrive-date-today   → pb_proveedor_llegada_china_v1
(sin uso)                  → pb_proveedor_inspeccion_manual_v1, pb_consolidado_pago_preliminar_v1, pb_entrega_conformidad_fotos_v1
```
