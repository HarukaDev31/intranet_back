# Plantillas MARKETING → reemplazos UTILITY (WABA consolidado)

**Fuente:** Graph API · WABA `27140133382305301` · categoría `MARKETING` · estado `APPROVED`  
**Fecha consulta:** 2026-06-03  
**Objetivo:** textos alternativos para recategorizar como **UTILITY** sin cambiar el nombre de plantilla ni romper el backend (mismas variables).

---

## Reglas Meta para que no vuelvan a MARKETING

| Evitar | Usar en su lugar |
|--------|------------------|
| Emojis decorativos (👋 ✈️ 📦 🙌) | Texto plano o sin emojis |
| “Gracias por confiar”, “próxima importación”, “escríbanos” | Solo hecho operativo |
| Preguntas comerciales (“¿tienes noticias…?”) | Solicitud de dato con contexto (`consolidado`, `proveedor`, `fecha`) |
| Títulos genéricos con emoji (`📩 Recordatorio:`) | Asunto transaccional (“Recordatorio de entrega — consolidado #…”) |
| “Medios de pago Pro Business” (marca + genérico) | “Datos bancarios para el pago de su importación” |
| Idioma distinto al contenido (`en` con texto en español) | Registrar en `es_PE` |

**Proceso:** WhatsApp Manager → Editar plantilla → categoría **Utility** → pegar BODY sugerido → re-aprobación (horas–48 h).  
**API:** `POST /{template_id}` con `"category": "UTILITY"` y `components` actualizados.

Documentación Meta: [Template categorization](https://developers.facebook.com/docs/whatsapp/updates-to-pricing/new-template-guidelines/)

---

## Resumen rápido

| # | Plantilla | ID Meta | Prioridad UTILITY | Esfuerzo |
|---|-----------|---------|-------------------|----------|
| 1 | `pb_entrega_conformidad_texto_v1` | `1946109989431342` | Alta | Medio — quitar cierre promocional |
| 2 | `pb_entrega_conformidad_fotos_v1` | `971202642414846` | Alta | Medio — mismo texto que E07 |
| 3 | `pb_consolidado_cotizacion_final_pdf_v1` | `1011536098480296` | Alta | Bajo |
| 4 | `pb_consolidado_pago_preliminar_v1` | `1525206729148007` | Alta | Bajo + cambiar idioma a `es_PE` |
| 5 | `pb_inspeccion_imagen_v1` | `1998799414059157` | Alta | Bajo |
| 6 | `pb_inspeccion_video_v1` | `1311834643772722` | Alta | Bajo |
| 7 | `pb_proveedor_inspeccion_manual_v1` | `1513863057193258` | Media | Bajo |
| 8 | `pb_entrega_recordatorio_v1` | `1663157671585077` | Media | Medio — acotar `{{mensaje}}` en backend |
| 9 | `pb_consolidado_pagos_img_v1` | `1643364683602668` | Media | Medio |
| 10 | `pb_proveedor_llegada_china_v1` | `1534244464952047` | Baja | Alto — redactar como seguimiento logístico |

> **Nota:** En código se usa `pb_entrega_conformidad_foto_v1` (singular); en Meta existe `pb_entrega_conformidad_fotos_v1` (plural). Verificar cuál está en producción antes de editar.

---

## 1. `pb_entrega_conformidad_texto_v1`

**Backend:** `CoordinacionWhatsappPayload::entregaConformidadTexto` · `EntregaController::uploadConformidad`  
**Variables:** `{{nombre}}`, `{{carga}}`

### Texto actual (MARKETING)

```
Hola {{nombre}} 👋
Adjunto el sustento de entrega correspondiente a su importación del consolidado #{{carga}}.

Muchas gracias por confiar en Pro Business. Si tiene una próxima importación, estaremos encantados de ayudarlo nuevamente. No dude en escribirnos ✈️📦
```

### Por qué Meta la marca MARKETING

- Cierre promocional (“próxima importación”, “escríbanos”).
- Emojis de engagement.

### BODY sugerido (UTILITY)

```
Hola {{nombre}},

Le enviamos el sustento de entrega de su importación del consolidado #{{carga}}.

Este mensaje confirma la recepción del material de conformidad asociado a su entrega.
```

**Alternativa corta:**

```
Hola {{nombre}},

Adjuntamos el sustento de entrega de su importación consolidado #{{carga}}.
```

---

## 2. `pb_entrega_conformidad_fotos_v1`

**Backend:** posible duplicado de E07; en código está `pb_entrega_conformidad_foto_v1` (foto por foto).  
**Variables:** `{{nombre}}`, `{{carga}}`

### Texto actual

Igual que `pb_entrega_conformidad_texto_v1` (ver §1).

### BODY sugerido (UTILITY)

Usar el **mismo texto UTILITY del §1**. Si esta plantilla es solo para el flujo con fotos adjuntas en mensajes separados, puede acortarse:

```
Hola {{nombre}},

Registramos el sustento fotográfico de entrega de su importación consolidado #{{carga}}.
```

---

## 3. `pb_consolidado_cotizacion_final_pdf_v1`

**Backend:** `CoordinacionWhatsappPayload::consolidadoCotizacionFinalPdf`  
**Tipo:** DOCUMENT (header) + BODY  
**Variables:** `{{carga}}`

### Texto actual (MARKETING)

```
Cotización final - Consolidado #{{carga}}. 📄
```

### Por qué puede quedar MARKETING

- Emoji decorativo.
- Texto muy corto sin contexto transaccional.

### BODY sugerido (UTILITY)

```
Adjuntamos la cotización final de su importación, consolidado #{{carga}}.

Revise el documento PDF para confirmar montos y fecha límite de pago indicados.
```

**Alternativa mínima (cambio pequeño):**

```
Cotización final de su importación — consolidado #{{carga}}. Documento adjunto en PDF.
```

---

## 4. `pb_consolidado_pago_preliminar_v1`

**Backend:** `PagosController`, `CotizacionController` (texto en `{{mensaje}}`)  
**Variables:** `{{mensaje}}`  
**Idioma en Meta:** `en` ← **corregir a `es_PE`** al editar (idioma debe coincidir con el contenido).

### Texto actual (MARKETING)

```
📩 Pago preliminar:

{{mensaje}}

💳
```

### Por qué Meta la marca MARKETING

- Plantilla “contenedor” genérica; si `{{mensaje}}` incluye promoción, Meta la clasifica marketing.
- Emojis en marco fijo.
- Idioma `en` con contenido en español.

### BODY sugerido (UTILITY)

```
Notificación de pago preliminar — importación consolidada.

{{mensaje}}

Responda con su comprobante para continuar la gestión de embarque.
```

**Requisito backend:** sanitizar `{{mensaje}}` para que sea solo datos (monto, cotización, banco) — sin “un gusto saludarte”, emojis ni CTA comercial. Ver `normalizeTemplateParameterText`.

---

## 5. `pb_inspeccion_imagen_v1`

**Backend:** `CoordinacionWhatsappPayload::inspeccionImagen`  
**Tipo:** IMAGE (header) + BODY  
**Variables:** `{{codigo_proveedor}}`

### Texto actual (MARKETING)

```
📦 Inspección - proveedor {{codigo_proveedor}} 📦
```

### BODY sugerido (UTILITY)

```
Imagen de inspección de mercadería recibida en almacén — proveedor {{codigo_proveedor}}.

Corresponde a su carga en proceso de consolidado.
```

**Alternativa mínima:**

```
Inspección en almacén — proveedor {{codigo_proveedor}}. Imagen adjunta.
```

---

## 6. `pb_inspeccion_video_v1`

**Backend:** `CoordinacionWhatsappPayload::inspeccionVideo`  
**Tipo:** VIDEO (header) + BODY  
**Variables:** `{{codigo_proveedor}}`

### Texto actual

Igual que imagen (§5).

### BODY sugerido (UTILITY)

```
Video de inspección de mercadería recibida en almacén — proveedor {{codigo_proveedor}}.

Corresponde a su carga en proceso de consolidado.
```

**Alternativa mínima:**

```
Inspección en almacén — proveedor {{codigo_proveedor}}. Video adjunto.
```

---

## 7. `pb_proveedor_inspeccion_manual_v1`

**Backend:** `CoordinacionWhatsappPayload::proveedorInspeccionManual` · `CotizacionProveedorController`  
**Variables:** `{{mensaje}}`

### Texto actual (MARKETING)

```
📩 Inspección:

{{mensaje}}

📦
```

### BODY sugerido (UTILITY)

```
Actualización de inspección de proveedor:

{{mensaje}}

Mensaje generado desde el seguimiento de su importación.
```

**Backend:** el contenido de `{{mensaje}}` debe ser factual (estado, cajas, observación). Una sola línea, sin `\n` en parámetros.

---

## 8. `pb_entrega_recordatorio_v1`

**Backend:** `CoordinacionWhatsappPayload::entregaRecordatorio` · `sendRecordatorioFormularioDelivery`  
**Variables:** `{{mensaje}}`

### Texto actual (MARKETING)

```
📩 Recordatorio:

{{mensaje}}

🙌
```

### Por qué Meta la marca MARKETING

- Marco genérico “Recordatorio” + cuerpo libre; si el intranet manda texto comercial, Meta lo clasifica marketing.
- Emojis.

### BODY sugerido (UTILITY)

Opción A — marco transaccional (recomendado):

```
Recordatorio de entrega — importación consolidada.

{{mensaje}}

Complete el formulario indicado para confirmar fecha y modalidad de entrega.
```

Opción B — más específico (requiere fijar texto en backend, no libre):

```
Recordatorio: debe completar el registro de entrega de su consolidado.

{{mensaje}}
```

**Backend:** en `sendRecordatorioFormularioDelivery`, armar `{{mensaje}}` solo con datos (link formulario, fecha, dirección). Sin saludos largos ni promociones.

---

## 9. `pb_consolidado_pagos_img_v1`

**Backend:** `CoordinacionWhatsappPayload::consolidadoPagosImg`  
**Tipo:** IMAGE (header) + BODY  
**Variables:** ninguna (body fijo)

### Texto actual (MARKETING)

```
Medios de pago Pro Business.
```

### Por qué Meta la marca MARKETING

- Suena a material comercial / promoción de marca, no a notificación de pago de un pedido concreto.

### BODY sugerido (UTILITY)

Opción A — sin variables (mínimo cambio):

```
Datos bancarios para el pago de su importación. Imagen adjunta con cuentas habilitadas.
```

Opción B — con variable (requiere cambio backend + nueva plantilla o editar components):

```
Datos bancarios para el pago de su importación — consolidado #{{carga}}.
```

Si se elige opción B, agregar `carga` en `consolidadoPagosImg()` en `CoordinacionWhatsappPayload.php`.

---

## 10. `pb_proveedor_llegada_china_v1`

**Backend:** `CoordinacionWhatsappPayload::proveedorLlegadaChina` · `NotifyArriveDateToday`  
**Variables:** `{{nombre_cliente}}`, `{{codigo_proveedor}}`

### Texto actual (MARKETING)

```
Hola 👋 {{nombre_cliente}} la carga de tu proveedor {{codigo_proveedor}} aun no llega a nuestro almacen de China, ¿tienes alguna noticia por parte de tu proveedor?
```

### Por qué Meta la marca MARKETING

- Tono conversacional + pregunta abierta (“¿tienes noticias?”) = seguimiento comercial, no aviso transaccional.
- “Hola 👋” informal.

### BODY sugerido (UTILITY)

```
Hola {{nombre_cliente}},

Seguimiento logístico: la carga del proveedor {{codigo_proveedor}} aún no consta en nuestro almacén en China.

Indíquenos, si lo tiene, la fecha estimada de despacho o número de guía del proveedor.
```

**Alternativa más corta:**

```
{{nombre_cliente}}, informamos que la carga del proveedor {{codigo_proveedor}} no ha ingresado aún al almacén en China. Envíenos actualización de despacho si la tiene.
```

---

## Checklist post-edición

- [ ] Editar cada plantilla en Meta → categoría **Utility** + BODY de este doc.
- [ ] Corregir `pb_consolidado_pago_preliminar_v1` a idioma **`es_PE`**.
- [ ] Alinear `docs/META_WHATSAPP_TEMPLATES_CUERPO.md` con los textos aprobados.
- [ ] Revisar que parámetros dinámicos no incluyan emojis ni promoción (`normalizeTemplateParameterText`).
- [ ] Tras aprobación, verificar categoría:  
  `GET /27140133382305301/message_templates?fields=name,category,status`
- [ ] Si Meta rechaza, apelar en WhatsApp Manager → **Template Category Updates** (60 días).

---

## Orden sugerido de migración

1. `pb_consolidado_cotizacion_final_pdf_v1` — cambio pequeño, alto impacto en costo.
2. `pb_inspeccion_imagen_v1` / `pb_inspeccion_video_v1` — quitar emojis del body.
3. `pb_entrega_conformidad_texto_v1` + `pb_entrega_conformidad_fotos_v1` — reescritura clara.
4. `pb_consolidado_pago_preliminar_v1` — idioma + marco UTILITY.
5. `pb_proveedor_inspeccion_manual_v1` / `pb_entrega_recordatorio_v1` — dependen del contenido de `{{mensaje}}`.
6. `pb_consolidado_pagos_img_v1` — redacción transaccional.
7. `pb_proveedor_llegada_china_v1` — la más sensible; probar primero en una plantilla de prueba si hay dudas.
