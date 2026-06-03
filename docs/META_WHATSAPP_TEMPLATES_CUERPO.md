# Textos para plantillas Meta (copiar en Business Manager)

Documento complementario de `META_WHATSAPP_TEMPLATES.md`.  
Cada bloque es el **cuerpo (BODY)** tal como debe registrarse en Meta.

**Alcance:** solo WABA **consolidado** (`fromNumber` por defecto en `WhatsappTrait`).  
No incluye plantillas de **administración** (`pb_admin_*`), **ventas** (`pb_ventas_*`) ni **curso** (`pb_curso_*`) — ver catálogo en `META_WHATSAPP_TEMPLATES.md` §5.3 y §5.9.

**Al crear en Meta:** categoría **Utilidad**, idioma **Español**, cuenta/número **consolidado**.  
Formato WhatsApp en cuerpo: `*negrita*` solo en texto fijo (no rodear la variable).

### Reglas de variables (Business Manager)

Meta **no acepta** `{{1}}`, `{{2}}`. Usar nombres en minúsculas, números y guión bajo:

| ❌ Incorrecto | ✅ Correcto |
|-------------|------------|
| `{{1}}` | `{{carga}}` |
| `*consolidado #{{1}}*` | `consolidado #{{carga}}` |
| `{{NombreCliente}}` | `{{nombre_cliente}}` |

Además:

- No poner la variable **dentro** de `*negrita*`.
- Dejar espacio antes/después si hay `#` o signos: `consolidado #{{carga}}`.
- No iniciar ni terminar el mensaje solo con una variable (ni que la última línea del BODY termine en `{{…}}` sin texto o emoji después).
- Si el cierre es una variable, añadir **un emoji o texto fijo al final** (ej. `📦`, `📋`, `✈️`).
- Usar llaves ASCII `{{` `}}` (no comillas tipográficas).
- Al enviar por API, los valores van **en el orden** en que aparecen las variables en el texto.

En tablas de este doc, la columna **Parámetro Meta** es el nombre en la plantilla; **Orden API** es la posición al enviar (`1`, `2`, …).

**Media (PDF/imagen/video):** en plantillas con encabezado DOCUMENT/IMAGE/VIDEO sube un archivo de ejemplo al registrar; al enviar por API va el archivo real vía **URL HTTPS** (Meta no lee rutas del servidor).

**S3 / backend:** `CoordinacionMediaLink` + `MetaWhatsAppCoordinacionService` suben o resuelven el archivo antes del envío:
- Ruta relativa ya en S3/local → `ObjectStorageConnectorInterface::url()` (URL firmada si aplica).
- Archivo local (`storage/app`, `public/assets`, PDF temporal de rotulado, etc.) → subida a `temp/whatsapp-meta/…` en el bucket y enlace en el header.
- Jobs y `CoordinacionWhatsappPayload::documentTemplate` / `imageTemplate` siguen pasando `header.path`; no hace falta subir manualmente en cada caller.

**XLSX/DOCX:** no registrar Office en plantilla; usar **enlace** en BODY (D02, VIN) o **DOCUMENT/PDF** (D04, rotulados W04/W05, C03, E08, etc.).

---

## Leyenda rápida

| Columna backend | Uso |
|-----------------|-----|
| `template_id` | ID interno (W01, E01…) para mapear en código cuando tengas el nombre Meta |
| `meta_name` | Nombre exacto de la plantilla en Meta |
| `params` | Valores en orden de aparición en el BODY |

---

## 5.1 Bienvenida y rotulado · WABA consolidado

### W01 — `pb_welcome_rotulado_v1`

**Tipo:** TEXT (el PDF chino va en flujo `welcomeV2` / DOCUMENT aparte si aplica)  
**WABA:** consolidado

**BODY:**

```
Hola 🙋🏻‍♀, te escribe el área de coordinación de Probusiness,
yo me encargaré de ayudarte en tu importación del consolidado #{{carga}}.

📢 Preste atención al siguiente paso:
*Rotulado* 👇🏼
Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.

☑ El documento está en idioma chino, solo debes enviarle a tu proveedor 📤

Nota: No cambiar ninguno de los datos, en caso tu proveedor tenga alguna consulta, se puede comunicarse:

🙍🏻‍♂ Almacén China: Mr. Younus
📞 Wechat: 13185122926
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Número de carga / consolidado |

---

### W02 — `pb_rotulado_nuevo_proveedor_v1`

**Tipo:** TEXT · **WABA:** consolidado

**BODY:**

```
Hola 🙋🏻‍♀, te escribe el área de coordinación de Probusiness.

📢 Añadiste un nuevo proveedor en el Consolidado #{{carga}}

*Rotulado: 👇🏼*
Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |

---

### W03 — `pb_rotulado_datos_proveedor_v1`

**Tipo:** TEXT · **WABA:** consolidado

**BODY:**

```
También necesito los datos de tu proveedor para comunicarnos y recibir tu carga.

➡ *Datos del proveedor: (Usted lo llena)*

☑ Nombre del producto:
☑ Nombre del vendedor:
☑ Celular del vendedor:

Te avisaré apenas tu carga llegue a nuestro almacén de China, cualquier duda me escribes. 🫡
```

Sin variables.

---

### W03b — `pb_rotulado_datos_proveedor_link_v1`

**Tipo:** TEXT · **WABA:** consolidado · (variante `SendRotuladoJob` con URL)

**BODY:**

```
También necesito que ingrese al enlace y coloques los datos de tu proveedor, por favor 🫡

Ingresar aquí: {{link_datos_proveedor}}

{{lista_proveedores}}

🫡
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{link_datos_proveedor}}` | 1 | URL datos proveedor (`APP_URL_DATOS_PROVEEDOR/{uuid}`) |
| `{{lista_proveedores}}` | 2 | Lista de proveedores pendientes (texto multilínea: vendedor, WeChat, código) |

> Si `{{lista_proveedores}}` supera límites Meta, dividir en varios mensajes de sesión o acortar lista en backend.

---

### W04 — `pb_rotulado_pdf_producto_v1`

**Tipo:** DOCUMENT (encabezado) + BODY · **WABA:** consolidado

**BODY:**

```
Producto: {{nombre_producto}}
Código de proveedor: {{codigo_proveedor}} 📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_producto}}` | 1 | Nombre productos |
| `{{codigo_proveedor}}` | 2 | Código proveedor |

---

### W05a — `pb_rotulado_etiqueta_calzado_v1`

**Tipo:** DOCUMENT + BODY

**BODY:**

```
👆🏻 ⚠ Atención ⚠

Etiqueta especial: Calzado

Según la regulación de Aduanas Perú todo calzado requiere tener una etiqueta Irremovible (Cosida a la lengüeta) de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.
🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.
📝 Aquí tienes un ejemplo de como debes colocar las etiquetas
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigo_proveedor}}` | 1 | Código proveedor |

---

### W05b — `pb_rotulado_etiqueta_ropa_v1`

**BODY:** (mismo patrón, texto ropa)

```
👆🏻 ⚠ Atención ⚠

Etiqueta especial: Prendas de Vestir

Según la regulación de Aduanas - Perú todo producto textil, requiere tener un etiqueta Cosida o Sublimada de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.
🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.
📝 Aquí tienes un ejemplo de como tu proveedor debe colocar las etiquetas
```

---

### W05c — `pb_rotulado_etiqueta_ropa_interior_v1`

```
👆🏻 ⚠ Atención ⚠

Etiqueta especial: Ropa interior/ Accesorios de Vestir

Según la regulación de Aduanas - Perú todo producto textil, requiere tener un etiqueta Cosida o Colgante de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

⛔ No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.
🚫 El rotulado NO puede estar en Chino deberá ser en ESPAÑOL.
📝 Aquí tienes un ejemplo de como tu proveedor debe colocar las etiquetas
```

---

### W05d — `pb_rotulado_etiqueta_maquinaria_v1`

```
👆🏻 ⚠ Atención ⚠

Etiqueta especial: Maquinaria

Según la regulación de Aduanas - Perú todas maquinaria domestico o industrial que contengan un motor eléctrico, requiere tener una placa Irremovible y visible de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

⛔ No aceptamos cargas sin la placa ya que la aduana lo puede observar o decomisar.
🚫 El rotulado del producto NO puede estar en Chino deberá ser en ESPAÑOL.
📝 Aquí tienes un ejemplo de como tu proveedor debe colocar la placa
```

---

### W06 — `pb_rotulado_almacen_china_img_v1`

**Tipo:** IMAGE (encabezado) + BODY

**BODY:**

```
🏽 Dile a tu proveedor que envíe la carga a nuestro almacén en China
```

Sin variables (imagen fija de dirección).

---

### W07 — `pb_rotulado_vin_link_v1`

**Tipo:** TEXT · Reemplaza envío de `vin_movilidad.xlsx`

**BODY:**

```
👆🏼 Le adjuntamos la lista de códigos VIN que deben ir grabados en los vehículos de movilidad personal.

Descárgala aquí: {{link_vin}} 📋
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{link_vin}}` | 1 | URL pública HTTPS al PDF/listado (no xlsx) |

---

## 5.2 Entrega Lima / Provincia · WABA consolidado

### E01 — `pb_entrega_link_lima_v1`

**Tipo:** TEXT · Botón URL opcional: «Registrar entrega» → `{{link_formulario}}` · **WABA:** consolidado

**BODY:**

```
# Consolidado {{carga}}

🙋🏻‍♀️ Hola {{nombre_cliente}}, te saluda área de Coordinación.

Cliente: Lima

✅ *Registrarse*, en el siguiente link.
✅ *Reservar su horario* de recojo lo antes posible.
✅ *Plazo máximo* para el registro: 48 horas
✅ Tener los pagos al día.
✅ Formulario: {{link_formulario}}

⚠ Enviar movilidad acorde al volumen de su carga (auto, camioneta, furgón o camión).
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre_cliente}}` | 2 | Nombre cliente |
| `{{link_formulario}}` | 3 | URL formulario entrega Lima |

---

### E02 — `pb_entrega_reglas_lima_v1`

**Tipo:** TEXT · Sin variables

**BODY:**

```
❌ Tiempo máximo de recojo: *30 minutos* según horario reservado
❌ La movilidad debe retirar toda la mercadería en un solo viaje.
❌ No se permite recojo parcial ni múltiples viajes.
❌ No está permitido seleccionar, separar, armar o desarmar productos dentro del almacén.
❌ No dejar pallets, etiquetas ni bolsas en el almacén.

📍 Agradecemos su apoyo para mantener un proceso de entrega ordenado.
```

---

### E03 — `pb_entrega_link_provincia_v1`

**BODY:**

```
# Consolidado {{carga}}

🙋🏻‍♀️ Hola {{nombre_cliente}}, te saluda área de Coordinación.

Cliente: Provincia

✅ *Registrarse*, en el siguiente link.
✅ *Plazo máximo* para el registro: 48 horas
✅ *Organizaremos los envíos* una vez liberado el contenedor.
✅ Formulario: {{link_formulario}}

⚠ De no llenar el formulario no se programará el envío de sus productos.
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre_cliente}}` | 2 | Nombre cliente |
| `{{link_formulario}}` | 3 | URL formulario provincia |

---

### E04a — `pb_entrega_reglas_provincia_flete_final_v1`

Usar cuando `intval(carga) >= 5`.

**BODY:**

```
Importante:

➡ La información registrada será utilizada para la *emisión de guías de remisión*.
➡ *Validar* que sus datos estén correctos y completos.
➡ El *costo de flete* Almacén – Agencia detalla en su cotización final.
➡ Los envíos se realizan con *Marvisur*.
➡ Si desea trabajar con otra agencia de transporte, se aplicará un *costo adicional* y previa coordinación.
➡ En ese caso, no asumimos responsabilidad por incidencias en la entrega con la agencia elegida.
```

---

### E04b — `pb_entrega_reglas_provincia_flete_cotiza_v1`

Usar cuando `intval(carga) < 5`.

**BODY:**

```
Importante:

➡ La información registrada será utilizada para la *emisión de guías de remisión*.
➡ *Validar* que sus datos estén correctos y completos.
➡ El *costo de flete* Almacén – Agencia se cotizará y será informado por interno.
➡ Los envíos se realizan con *Marvisur*.
➡ Si desea trabajar con otra agencia de transporte, se aplicará un *costo adicional* y previa coordinación.
➡ En ese caso, no asumimos responsabilidad por incidencias en la entrega con la agencia elegida.
```

---

### E05 — `pb_entrega_confirm_lima_v1`

**BODY:**

```
Hola, {{primer_nombre}} 👋

Tu recojo del Consolidado #{{carga}} ha sido registrado. Aquí el resumen:

👤 *PERSONA QUE RECOGE*
{{pick_name}}
*DNI:* {{pick_dni}}
*Cel.:* {{pick_phone}}

📅 *FECHA Y HORA DE RECOJO*
{{fecha_hora_recojo}}

📍 *DIRECCIÓN DE RECOJO*
{{direccion}}
{{referencia}}
{{maps_url}}

Gracias por confiar en *Pro Business* 🙌
Donde conectamos tu negocio con los mejores productos y servicios.
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{primer_nombre}}` | 1 | Primer nombre |
| `{{carga}}` | 2 | Carga |
| `{{pick_name}}` | 3 | Nombre quien recoge |
| `{{pick_dni}}` | 4 | DNI |
| `{{pick_phone}}` | 5 | Celular |
| `{{fecha_hora_recojo}}` | 6 | Fecha textual · hora |
| `{{direccion}}` | 7 | Dirección |
| `{{referencia}}` | 8 | Referencia |
| `{{maps_url}}` | 9 | URL Google Maps |

---

### E06 — `pb_entrega_confirm_provincia_v1`

**BODY:**

```
✅ *Envío registrado*

Hola, {{primer_nombre}} 👋

Tu solicitud de envío para el Consolidado #{{carga}} fue registrada correctamente.

📦 *DESTINATARIO*
*Nombre:* {{destinatario}}
*{{doc_label}}:* {{doc_numero}}
*Celular:* {{celular}}

🚚 *TRANSPORTE*
*Agencia:* {{agencia}}
*RUC:* {{ruc_agencia}}
*Destino:* {{destino}}
*Entrega en:* {{entrega_en}}
*Dirección:* {{direccion}}

✅
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{primer_nombre}}` | 1 | Primer nombre |
| `{{carga}}` | 2 | Carga |
| `{{destinatario}}` | 3 | Nombre destinatario |
| `{{doc_label}}` | 4 | DNI o RUC (etiqueta) |
| `{{doc_numero}}` | 5 | Número documento |
| `{{celular}}` | 6 | Celular |
| `{{agencia}}` | 7 | Agencia |
| `{{ruc_agencia}}` | 8 | RUC agencia |
| `{{destino}}` | 9 | Destino ubigeo |
| `{{entrega_en}}` | 10 | Agencia o Domicilio |
| `{{direccion}}` | 11 | Dirección domicilio; si entrega en agencia, enviar `—` o `No aplica` |

---

### E07 — `pb_entrega_conformidad_texto_v1`

**Tipo:** TEXT (mensaje principal)

**BODY:**

```
Hola {{nombre}} 👋
Adjunto el sustento de entrega correspondiente a su importación del consolidado #{{carga}}.

Muchas gracias por confiar en Pro Business. Si tiene una próxima importación, estaremos encantados de ayudarlo nuevamente. No dude en escribirnos ✈️📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre}}` | 1 | Nombre |
| `{{carga}}` | 2 | Carga |

**Flujo Meta (2 fotos):** enviar primero **E07** (texto) y después **una o dos veces** **E07-img** (cada foto = un envío de plantilla; `{{numero}}` = `1` o `2`). Meta no permite 2 imágenes en una sola plantilla.

---

### E07-img — `pb_entrega_conformidad_foto_v1`

**Tipo:** IMAGE (header) + TEXT (body obligatorio en Meta)

**Registro en BM:** categoría **Utilidad**, encabezado **Imagen** (sube un JPG de ejemplo; al enviar por API va la foto real de conformidad).

**BODY:**

```
Sustento de entrega — foto {{numero}}. 📷
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{numero}}` | 1 | `1` para `photo_1`, `2` para `photo_2` (misma plantilla, dos envíos si hay dos fotos) |

---

### E08 — `pb_entrega_cargo_firmado_v1`

**Tipo:** DOCUMENT + BODY

**BODY:**

```
Hola {{nombre}} 👋
Adjunto el documento de cargo de entrega firmado correspondiente a su importación del consolidado #{{carga}}.

Muchas gracias por confiar en Pro Business. ✈️📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre}}` | 1 | Nombre cliente |
| `{{carga}}` | 2 | Carga |

---

### E09 — `pb_entrega_cobro_servicios_v1`

**Tipo:** TEXT (+ IMAGE cuentas después con C04, mismo número consolidado)
**WABA:** consolidado

**BODY:**

```
Consolidado #{{carga}}
Hola {{nombre}}, por favor proceder con el pago de lo siguiente:

{{bloque_servicios}}

💳
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre}}` | 2 | Nombre |
| `{{bloque_servicios}}` | 3 | Bloque(s) servicio (DELIVERY/MONTACARGA armado en backend) |

**Ejemplo de `{{bloque_servicios}}` (DELIVERY):**

```
— DELIVERY
Se envía el costo del flete interno (Almacén-agencia)
Costo: S/ 150.00
Por favor nos compartes el comprobante de pago para poder gestionar tu envío
```

---

### E10 — `pb_entrega_recordatorio_v1`

Solo si Meta aprueba cuerpo variable; si no, mensaje libre en ventana 24h.

**BODY:**

```
📩 Recordatorio:

{{mensaje}}

🙌
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{mensaje}}` | 1 | Texto completo del recordatorio (desde intranet) |

---

## 5.3 Cotización final y pagos · WABA consolidado

### C01 — `pb_consolidado_cotizacion_final_v1`

**WABA:** consolidado

**BODY:**

```
📦 Consolidado #{{carga}}
Hola {{nombre}} 😁 un gusto saludarte!
A continuación te envio la cotización final de tu importación📋📦.

🙋‍♂️PAGO PENDIENTE:
☑️Costo CBM: ${{costo_cbm}}
☑️Impuestos: ${{impuestos}}
{{servicios_extras}}
✅Total: ${{total}}

Pronto le aviso nuevos avances, que tengan buen dia
Último día de pago: {{fecha_limite}} 📅
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre}}` | 2 | Nombre |
| `{{costo_cbm}}` | 3 | Costo CBM |
| `{{impuestos}}` | 4 | Impuestos |
| `{{servicios_extras}}` | 5 | Línea servicios extras o vacío |
| `{{total}}` | 6 | Total |
| `{{fecha_limite}}` | 7 | Último día de pago |

---

### C02 — `pb_consolidado_resumen_pago_v1`

**BODY:**

```
💰*Resumen de Pago*
✅Cotización final: ${{total_cotizacion}}
✅Adelanto: ${{adelanto}}
✅ Pendiente de pago: ${{pendiente}} 💳
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{total_cotizacion}}` | 1 | Total cotización |
| `{{adelanto}}` | 2 | Adelanto |
| `{{pendiente}}` | 3 | Pendiente |

---

### C03 — `pb_consolidado_cotizacion_final_pdf_v1`

**Tipo:** DOCUMENT

**BODY:**

```
Cotización final — Consolidado #{{carga}}. 📄
```

---

### C04 — `pb_consolidado_pagos_img_v1`

**Tipo:** IMAGE

**BODY:**

```
Medios de pago Pro Business.
```

---

### C05 — `pb_consolidado_pago_preliminar_v1`

Mensaje genérico si el controlador arma texto variable — usar `{{mensaje}}` con cuerpo completo o definir plantilla por cada flujo de `PagosController`.

**BODY sugerido (ajustar según mensaje real en código):**

```
📩 Pago preliminar:

{{mensaje}}

💳
```

---

## 5.4 Documentación importación · WABA consolidado

### D01 — `pb_docs_paso1_excel_video_v1`

**BODY:**

```
⚠️IMPORTANTE⚠️

El siguiente paso es la recopilación de tus documentos para la declaración en Aduanas. Para ello, te solicitaré los siguientes documento.

Documentación: CONSOLIDADO #{{carga}}

☑ PASO 1: Llenar el Excel de confirmación con las características de los productos que estás importando para poder declarar correctamente tus productos 📄 y evitar multas o pérdidas en aduanas.

📢 IMPORTANTE: Ver el video sobre el Excel de confirmación. 📋

Video: https://youtu.be/rvhwblBEbXQ
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Código carga (ej. 05) |

---

### D02 — `pb_docs_excel_link_v1`

**Reemplaza adjunto XLSX** — un mensaje por proveedor.

**BODY:**

```
Documentación: CONSOLIDADO #{{carga}}

Excel de confirmación — Proveedor {{codigo_proveedor}}

Descárgalo aquí: {{link_excel}} 📄
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{codigo_proveedor}}` | 2 | Código proveedor |
| `{{link_excel}}` | 3 | URL Google Drive (`excel_confirmacion_drive_link` en BD) |

---

### D03 — `pb_docs_paso2_word_v1`

**Sin fecha máxima:**

**BODY:**

```
☑ PASO 2: Solicita a tu proveedor los documentos finales:
• Commercial Invoice 📄.
• Packing List 📦.

📋 Adjuntamos un Word con indicaciones para un correcto llenado.
📩 El documento está en idioma chino, solo enviarlo a su proveedor.
🚫 Indicar a tu proveedor, que no se rellena encima del Word. ESTE WORD ES SOLO UNA GUIA.
```

**Con fecha máxima** — `pb_docs_paso2_word_fecha_v1`:

```
☑ PASO 2: Solicita a tu proveedor los documentos finales:
• Commercial Invoice 📄.
• Packing List 📦.

📋 Adjuntamos un Word con indicaciones para un correcto llenado.
📩 El documento está en idioma chino, solo enviarlo a su proveedor.
🚫 Indicar a tu proveedor, que no se rellena encima del Word. ESTE WORD ES SOLO UNA GUIA.

Fecha maxima de entrega: {{fecha_maxima}} 📅
```

---

### D04 — `pb_docs_consideraciones_doc_v1`

**Tipo:** DOCUMENT (encabezado) + TEXT · **WABA:** consolidado  
**Origen:** `SolicitarDocumentosWhatsAppJob` — hoy envía `CONSIDERATIONS.docx` con `sendMedia`; en Meta usar **PDF** (`CONSIDERATIONS.pdf`) en el header DOCUMENT.

**Registro en BM:** sube un PDF de ejemplo en el encabezado; al enviar por API va el archivo real (mismo flujo que el job, paso final tras D03).

**BODY:**

```
Consideraciones para la documentación de tu importación. 📋
```

Sin variables (el archivo va en el encabezado DOCUMENT, no como `{{link}}` en el texto).

**Secuencia:** D01 → D02 (por proveedor) → D03 → **D04** (documento adjunto).

---

### D05 — `pb_docs_recordatorio_intro_v1`

**Tipo:** TEXT · **WABA:** consolidado · **Origen:** `GeneralController::recordatoriosDocumentos` (paso 1 de la secuencia).

**Idioma en BM:** **Spanish (Peru)** / `es_PE`.

**BODY:**

```
Hola {{nombre_cliente}}, estamos esperando que nos envíes los documentos de tu importación del consolidado #{{carga}}. A continuación detallo los que faltan:
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{carga}}` | 2 | Código carga (ej. 05) |

---

### D06 — `pb_docs_recordatorio_proveedor_v1`

**Tipo:** TEXT · **WABA:** consolidado · **Origen:** `GeneralController::recordatoriosDocumentos` — **un mensaje por proveedor** con documentos pendientes.

**Idioma en BM:** **Spanish (Peru)** / `es_PE` (debe coincidir con la API; no registrar en English).

**BODY:**

```
Recordatorio de documentación de importación 📋

Proveedor: {{codigo_proveedor}}

Aún estamos esperando los siguientes documentos: {{documentos_faltantes}}

Por favor envíalos lo antes posible para continuar con la declaración aduanera. Gracias.
```

| Parámetro Meta | Orden API | Campo backend | Sample BM |
|----------------|-----------|---------------|-----------|
| `{{codigo_proveedor}}` | 1 | Código proveedor (ej. ANHA10-1) | `ANHA10-1` |
| `{{documentos_faltantes}}` | 2 | Lista compacta sin `\n` (`formatDocumentosFaltantesForMeta`) | `Commercial Invoice 📄 · Packing List 📦.` |

> Meta rechaza plantillas con **demasiadas variables para la longitud del texto**. El cuerpo anterior incluye suficiente texto fijo para 2 variables. No acortar a una sola línea.

---

### D07 — `pb_docs_recordatorio_aviso_v1`

**Tipo:** TEXT · **WABA:** consolidado · **Origen:** `GeneralController::recordatoriosDocumentos` (cierre de la secuencia).

**Idioma en BM:** **Spanish (Peru)** / `es_PE`.

**BODY:**

```
Si no tenemos tus documentos a tiempo, aduana puede aplicarte multas o inmovilización de tus productos.
```

Sin variables.

**Secuencia recordatorio:** D05 → D06 (por proveedor) → D07.

---

## 5.5 Inspección · WABA consolidado

Mismo número; envío vía API `/media-inspectionV2`.

### I01 — `pb_inspeccion_llegada_v1`

**BODY:**

```
📦 Cliente: {{nombre_cliente}} — Proveedor {{codigo_proveedor}} — {{cantidad_cajas}} boxes.

Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.

🔗 Ver inspección: {{link_inspeccion}} 📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{codigo_proveedor}}` | 2 | Código proveedor |
| `{{cantidad_cajas}}` | 3 | Cantidad cajas |
| `{{link_inspeccion}}` | 4 | URL inspección |

---

### I02 — `pb_inspeccion_imagen_v1`

**Tipo:** IMAGE · Caption:

**BODY:**

```
📦 Inspección — proveedor {{codigo_proveedor}} 📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigo_proveedor}}` | 1 | Código proveedor |

---

### I03 — `pb_inspeccion_video_v1`

**Tipo:** VIDEO · Caption:

**BODY:**

```
📦 Inspección — proveedor {{codigo_proveedor}} 📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigo_proveedor}}` | 1 | Código proveedor |

---

## 5.6 Calculadora importación · WABA consolidado

### CAL01 — `pb_calc_intro_v1`

**BODY:**

```
Bien, Te envío la cotización de tu importación, en el documento podrás ver el detalle de los costos.

⚠️ Nota: Leer Términos y Condiciones.

🎥 Video Explicativo:
▶️ https://youtu.be/H7U-_5wCWd4
```

---

### CAL02 — `pb_calc_pdf_v1`

**Tipo:** DOCUMENT

**BODY:**

```
Cotización de importación — Calculadora Pro Business.
```

---

### CAL03 — `pb_calc_resumen_texto_v1`

**BODY:**

```
📊 Aquí te paso el resumen de cuánto te saldría cada modelo y el total de inversión

💰 El primer pago es el SERVICIO DE IMPORTACIÓN y se realiza antes del zarpe de buque 🚢
```

---

### CAL04 — `pb_calc_resumen_img_v1`

**Tipo:** IMAGE

**BODY:**

```
📊 Resumen detallado de costos y pagos
```

---

## 5.7 Proveedores y operaciones (consolidado)

### P01 — `pb_proveedor_llegada_china_v1`

**BODY:**

```
Hola 👋 {{nombre_cliente}} la carga de tu proveedor {{codigo_proveedor}} aun no llega a nuestro almacen de China, ¿tienes alguna noticia por parte de tu proveedor?
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{codigo_proveedor}}` | 2 | Código proveedor |

---

### P02 — `pb_proveedor_datos_link_v1`

**BODY:**

```
Hola {{nombre_cliente}} necesitamos los datos de tu proveedor para que nuestro equipo de China se encarge de recibir tu carga.

Por favor ingresa al enlace y coloca los datos del proveedor.

Ingresar aquí: {{link_datos_proveedor}}

{{lista_proveedores}}

Quedo atenta.
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{link_datos_proveedor}}` | 2 | URL formulario datos proveedor |
| `{{lista_proveedores}}` | 3 | Lista compacta sin saltos de línea, ej. `Proveedores pendientes: JOLI11-1 · JOLI11-2` (`formatListaProveedoresForMeta`) |

---

### P03 — `pb_proveedor_inspeccion_manual_v1`

Mensaje armado en `CotizacionProveedorController` — usar `{{mensaje}}` cuerpo completo o desglosar cuando congeles el texto.

**BODY:**

```
📩 Inspección:

{{mensaje}}

📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{mensaje}}` | 1 | Texto inspección (sin saltos de línea; una sola línea o acortar) |

> Meta rechaza `\n` y `\t` en parámetros. Sanitizar en backend (`normalizeTemplateParameterText`).

---

### P04 — `pb_general_cliente_v1`

**Tipo:** TEXT · **WABA:** consolidado · **Origen:** usos puntuales con texto corto (no `recordatoriosDocumentos` → usar **D05–D07**).

**BODY:**

```
{{mensaje}}

📋
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{mensaje}}` | 1 | Texto libre **en una sola línea** (máx. ~1024 caracteres prácticos) |

> **No usar** para flujos con listas multilínea, URLs + listas de proveedores ni textos largos (`CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` → usar **P06** / **P07**).

---

### P05 — `pb_delivery_whatsapp_v1`

**Tipo:** TEXT · **Origen:** `DeliveryController::sendInitialDeliveryFormMessage` · Confirmación tras registrar formulario de delivery Lima.

**BODY:**

```
Hola {{nombre}}.

Gracias por llenar nuestro formulario del consolidado #{{carga}}, le estaremos avisando de nuevos avances.

📦
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre}}` | 1 | `Cotizacion::nombre` |
| `{{carga}}` | 2 | `Contenedor::carga` |

> Texto fijo en plantilla Meta; backend solo envía `nombre` y `carga` vía `CoordinacionWhatsappPayload::deliveryWhatsapp()`.

---

### P06 — `pb_proveedor_datos_guardado_pendiente_v1`

**Tipo:** TEXT · **WABA:** consolidado · **Origen:** `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`tipo_mensaje` = `guardar1`).

Cliente completó datos de al menos un proveedor pero **aún quedan pendientes**.

**BODY:**

```
Se registró exitosamente los datos de tu proveedor.

Queda pendiente completar los datos de: {{codigos_pendientes}}

Contacta al vendedor y sube los datos faltantes en el siguiente enlace:

{{link_datos_proveedor}}

✅
```

| Parámetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigos_pendientes}}` | 1 | Códigos pendientes compactos, ej. `JOLI11-2 · JOLI11-3` (`formatCodigosProveedoresPendientesForMeta`) |
| `{{link_datos_proveedor}}` | 2 | URL formulario (`CoordinacionWhatsappPayload::buildDatosProveedorUrl`) |

**Bitrix / popup:** conservar el `$mensaje` multilínea actual (con viñetas y guiones) en `bitrix_message`; solo el template Meta usa formato compacto.

---

### P07 — `pb_proveedor_datos_guardado_completo_v1`

**Tipo:** TEXT · **WABA:** consolidado · **Origen:** `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`tipo_mensaje` = `guardar2`).

Todos los proveedores de la cotización ya tienen datos completos.

**BODY:**

```
Se registró exitosamente los datos de tu proveedor.

Gracias por ayudarnos a hacer mejor nuestro trabajo, el equipo de China se contactará pronto con tu proveedor.

🫡
```

Sin variables.

---

## Tabla resumen para implementación en Laravel

Cuando tengas el **nombre Meta** y el **ID de plantilla** (o nombre), mapear así:

```php
// Ejemplo futuro en config/meta_whatsapp_templates.php
return [
    'E01' => [
        'name' => 'pb_entrega_link_lima_v1',
        'language' => 'es',
        'waba' => 'consolidado',
        'params' => ['carga', 'nombre_cliente', 'link_formulario'],
    ],
    // ...
];
```

---

## Notas finales para aprobación Meta

1. No iniciar ni terminar el body solo con una variable; cerrar con emoji o texto fijo si la última línea termina en `{{…}}`.
2. Evitar más de ~10 variables por plantilla (límite práctico).
3. URLs siempre `https://`.
4. Montos sin símbolos raros; usar `1234.56` o `1,234.56` consistente.
5. Plantillas duplicadas (E04a/E04b, D03 con/sin fecha) se eligen en PHP según regla de negocio.
6. Secuencias (E01→E02, C01→C03→C02→C04): respetar orden y delay en cola de jobs.

---

*Al registrar en Meta, copia el BODY sin los fences de markdown. Solo cuenta **consolidado**. Actualizar cuando cambie el texto en código fuente.*
