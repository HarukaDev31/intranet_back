# Migración a plantillas Meta (WhatsApp Business API)

Guía para reemplazar el envío libre vía `WhatsappTrait` (`sendMessage`, `sendMedia`, rutas dedicadas) por **plantillas aprobadas en Meta Business Manager**.

Código de referencia: `app/Traits/WhatsappTrait.php`

**Textos listos para copiar en Meta Business Manager (solo WABA consolidado / default):** ver [`META_WHATSAPP_TEMPLATES_CUERPO.md`](./META_WHATSAPP_TEMPLATES_CUERPO.md). Administración, ventas y curso: catálogo en §5.3 y §5.9 de este archivo (sin cuerpo detallado aún).

---

## 1. Tipos de plantilla en Meta

| Categoría Meta | Cuándo usarla en este proyecto |
|----------------|--------------------------------|
| **UTILITY** | Confirmaciones, pagos, entregas, documentación, recordatorios operativos, inspección, rotulado. **Usar por defecto.** |
| **MARKETING** | Solo promociones comerciales no solicitadas. Evitar si el cliente ya tiene relación activa de importación. |
| **AUTHENTICATION** | OTP / códigos de verificación (no aplica hoy). |

| Formato de plantilla | Cuándo usarla |
|---------------------|---------------|
| **Solo texto** (`body`) | Mensajes informativos sin archivo adjunto obligatorio. |
| **Texto + encabezado DOCUMENT** | PDFs: cotización final, rotulado, constancia, cargo entrega, factura, guía. |
| **Texto + encabezado IMAGE** | Números de cuenta, mapa almacén China, fotos conformidad, resumen calculadora. |
| **Texto + encabezado VIDEO** | Solo si el activo ya es video (inspección). |
| **Texto + botón URL** (opcional) | Links largos (`formulario-entrega`, `formulario-comprobante`, inspección). Meta limita el botón; el link también puede ir en `{{link}}` en el cuerpo. |

**Secuencias actuales** (varios mensajes seguidos con `sleep`): en Meta cada paso es **una plantilla distinta** o un mensaje de sesión dentro de la ventana de 24 h. Planificar 1 plantilla por paso.

---

## 2. Números / cuentas WABA (`fromNumber`)

| Clave actual | Uso | Plantillas asociadas |
|--------------|-----|----------------------|
| `consolidado` (default) | Cotizaciones, entregas, rotulado, pagos preliminares, calculadora | Prefijo sugerido: `pb_consolidado_*` |
| `administracion` | Factura, guía, comprobante, cobranza final, viáticos, contabilidad | `pb_admin_*` |
| `ventas` | Cotización proveedor PDF | `pb_ventas_*` |
| `/welcomeV2` | Bienvenida + rotulado chino (API propia) | `pb_welcome_rotulado` + DOCUMENT |
| `/message-curso` | Cursos | `pb_curso_*` |
| `/media-inspectionV2` | Inspección (URL pública) | `pb_inspeccion_*` |

Registrar cada plantilla en la **WABA correcta** (consolidado vs administración vs ventas).

---

## 3. Convención de variables

### En Business Manager (registro de plantilla)

Meta **no acepta** `{{1}}`, `{{2}}`. Usar solo **minúsculas, números y guión bajo**: `{{carga}}`, `{{nombre_cliente}}`, `{{link_formulario}}`.

| Regla | Detalle |
|-------|---------|
| Formato | `{{nombre_variable}}` — ej. `{{order_id}}`, nunca `{{Nombre}}` ni `{{1}}` |
| Negrita | No envolver variables en `*negrita*`; solo texto fijo |
| Posición | No iniciar ni terminar el BODY solo con una variable |
| `#` y signos | Dejar la variable separada: `Consolidado #{{carga}}` |

Los textos listos para pegar en Meta están en [`META_WHATSAPP_TEMPLATES_CUERPO.md`](./META_WHATSAPP_TEMPLATES_CUERPO.md).

### Al enviar por Cloud API

Los valores se envían como **array en el orden** en que aparecen las variables en el cuerpo de la plantilla (posición 1, 2, 3…), aunque en Meta estén nombradas.

### Glosario de nombres (reutilizar entre plantillas)

| Variable | Descripción |
|----------|-------------|
| `{{nombre_cliente}}` / `{{nombre}}` | Nombre o primer nombre |
| `{{primer_nombre}}` | Primer nombre (confirmaciones entrega) |
| `{{carga}}` | Número de consolidado |
| `{{carga_anio}}` | Ej. `05-2026` |
| `{{consolidado_label}}` | Etiqueta consolidado en comprobantes (ej. #05-2026) |
| `{{link_formulario}}` | URL formulario entrega Lima/Provincia |
| `{{link_comprobante}}` | URL formulario comprobante |
| `{{link_datos_proveedor}}` | URL datos proveedor |
| `{{link_excel}}` / `{{link_vin}}` | Enlaces documentación |
| `{{link_inspeccion}}` | URL inspección |
| `{{codigo_proveedor}}` | Código proveedor |
| `{{fecha_limite}}` / `{{fecha_maxima}}` | Fechas dd/mm/yyyy |
| `{{mensaje}}` | Cuerpo dinámico (solo si Meta aprueba plantilla genérica) |

**Reglas Meta:** sin saltos de línea excesivos en una sola variable; montos sin símbolos raros; URLs HTTPS públicas.

---

## 4. Archivos Office (XLSX / DOCX) — NO enviar nativos

> **Migración Meta:** WhatsApp Cloud API **no admite** enviar `.xlsx` / `.docx` como documento de plantilla de forma fiable para el cliente final.

| Archivo actual | Origen | Acción recomendada |
|----------------|--------|-------------------|
| `EXCEL_DE_CONFIRMACION_*.xlsx` | `SolicitarDocumentosWhatsAppJob` | **Opción A:** generar PDF por proveedor y plantilla HEADER DOCUMENT. **Opción B:** subir a storage y plantilla de texto con `{{link_descarga_excel}}`. |
| `CONSIDERATIONS.docx` | `SolicitarDocumentosWhatsAppJob` | Convertir a **PDF** y plantilla **D04** DOCUMENT (header); no enlace en texto. |
| `vin_movilidad.xlsx` | `SendRotuladoJob` ~L818 | Generar **PDF** del listado VIN o enlace de descarga; no plantilla DOCUMENT xlsx. |

```php
// TODO Meta: reemplazar sendMedia($excelPath, 'application/vnd...sheet', ...)
// por: Storage::url + template pb_consolidado_doc_link con {{link}}
// o: Pdf::loadView(...)->save() + template DOCUMENT pdf
```

Los **PDF** e **imágenes** (jpg/png) sí van en plantillas con encabezado DOCUMENT / IMAGE.

---

## 5. Catálogo de plantillas por flujo

### 5.1 Bienvenida y rotulado (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Categoría | Origen | Variables |
|----|----------------------|------|-----------|--------|-----------|
| W01 | `pb_welcome_rotulado_v1` | TEXT + DOCUMENT (PDF chino vía welcome API) | UTILITY | `sendWelcome`, `WhatsappTrait::buildWelcomeRotuladoMessageText` | `{{carga}}` (consolidado #) |
| W02 | `pb_rotulado_nuevo_proveedor_v1` | TEXT | UTILITY | `SendRotuladoJob`, `ForceSendRotuladoJob`, `CotizacionProveedorController` | `{{carga}}` |
| W03 | `pb_rotulado_datos_proveedor_v1` | TEXT | UTILITY | Mismo flujo, mensaje “datos de tu proveedor…” | — |
| W04 | `pb_rotulado_pdf_producto_v1` | DOCUMENT | UTILITY | `SendRotuladoJob::sendMedia` rotulado PDF | `{{producto}}`, `{{codigo_proveedor}}` (caption → body) |
| W05 | `pb_rotulado_tipo_calzado_v1` | DOCUMENT | UTILITY | PDFs por tipo (calzado, ropa, etc.) | `{{tipo}}`, `{{codigo}}` |
| W06 | `pb_rotulado_almacen_china_img_v1` | IMAGE | UTILITY | Imagen dirección almacén | — |
| W07 | `pb_rotulado_vin_link_v1` | TEXT | UTILITY | **Reemplaza xlsx** — ver §4 | `{{link_vin}}` |

---

### 5.2 Formulario de entrega Lima / Provincia (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| E01 | `pb_entrega_link_lima_v1` | TEXT (+ botón URL opcional) | `SendDeliveryFormBulkJob` msg principal Lima | `{{nombre_cliente}}`, `{{carga}}`, `{{link_formulario}}` |
| E02 | `pb_entrega_reglas_lima_v1` | TEXT | `SendDeliveryFormBulkJob` msg secundario Lima | — (texto fijo) |
| E03 | `pb_entrega_link_provincia_v1` | TEXT | Bulk / provincia principal | `{{nombre_cliente}}`, `{{carga}}`, `{{link_formulario}}` |
| E04 | `pb_entrega_reglas_provincia_v1` | TEXT | Bulk secundario; variante si `carga < 5` | `{{texto_flete}}` (cotizado vs cotización final) |
| E05 | `pb_entrega_confirm_lima_v1` | TEXT | `LimaRecojoNotificacionService` | `{{primer_nombre}}`, `{{carga}}`, `{{pick_name}}`, `{{pick_dni}}`, `{{pick_phone}}`, `{{fecha_texto}}`, `{{hora_recojo}}`, `{{direccion}}`, `{{referencia}}`, `{{maps_url}}` |
| E06 | `pb_entrega_confirm_provincia_v1` | TEXT | `ProvinciaEntregaNotificacionService` | `{{primer_nombre}}`, `{{carga}}`, `{{destinatario}}`, `{{doc_label}}`, `{{doc}}`, `{{celular}}`, `{{agencia}}`, `{{ruc_agencia}}`, `{{destino}}`, `{{entrega_en}}`, `{{direccion}}` |
| E07 | `pb_entrega_conformidad_texto_v1` | TEXT | `EntregaController::uploadConformidad` | `{{nombre}}`, `{{carga}}` |
| E07-img | `pb_entrega_conformidad_foto_v1` | IMAGE + TEXT | `EntregaController::uploadConformidad` (por cada foto) | `{{numero}}` (`1` / `2`) + media header |
| E08 | `pb_entrega_cargo_firmado_v1` | TEXT + DOCUMENT | `EntregaController::signCargoEntrega` | `{{nombre}}`, `{{carga}}` + PDF |
| E09 | `pb_entrega_cobro_servicios_v1` | TEXT + IMAGE | `sendCobroDeliveryDelivery` | `{{carga}}`, `{{nombre}}`, bloques servicio |
| E10 | `pb_entrega_recordatorio_v1` | TEXT | `sendRecordatorioFormularioDelivery` (mensaje libre request) | Definir plantilla genérica o mantener sesión 24h |

`link_formulario` = `{APP_URL_CLIENTES}/formulario-entrega/{idContenedor}?destino=lima|provincia`

---

### 5.3 Comprobante y contabilidad (`administracion`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| A01 | `pb_admin_comprobante_form_link_nuevo_v1` | TEXT | `FacturaGuiaController::buildMensajeFormularioNuevo` | `{{nombre}}`, `{{carga}}`, `{{link_comprobante}}` |
| A02 | `pb_admin_comprobante_form_confirm_antiguo_v1` | TEXT | `buildMensajeFormularioAntiguo` | `{{nombre}}`, `{{carga}}`, tipo, RUC, razón, domicilio, destino |
| A03 | `pb_admin_comprobante_cliente_factura_v1` | TEXT | `SendComprobanteFormNotificationJob` FACTURA | `{{consolidado_label}}`, `{{ruc}}`, `{{razon_social}}` |
| A04 | `pb_admin_comprobante_cliente_boleta_v1` | TEXT | Mismo job BOLETA | `{{consolidado_label}}`, `{{dni}}`, `{{nombre_completo}}` |
| A05 | `pb_admin_factura_comercial_v1` | DOCUMENT | `FacturaGuiaController::sendMedia` factura | `{{carga}}` + PDF |
| A06 | `pb_admin_guia_remision_v1` | DOCUMENT | Envío guías (varios archivos = varias plantillas o 1 por archivo) | `{{carga}}`, nombre archivo |
| A07 | `pb_admin_recordatorio_pago_v1` | TEXT | `SendReminderPagoWhatsAppJob` | `{{carga}}`, `{{descripcion}}`, `{{total}}`, `{{adelanto}}`, `{{pendiente}}`, `{{fecha_limite}}` |
| A08 | `pb_admin_pagos_imagen_v1` | IMAGE | Recordatorio / cobranza / cotización final (imagen cuentas) | — |
| A09 | `pb_admin_cobro_reserva_cbm_v1` | TEXT | `ForceSendCobrandoJob`, `CotizacionProveedorController::procesarEstadoCobrando` | `{{nombre}}`, `{{carga_anio}}`, `{{cbm}}`, `{{costo}}`, `{{fecha_limite}}` |
| A10 | `pb_admin_viatico_adjunto_v1` | DOCUMENT | `SendViaticoWhatsappNotificationJob` | según mensaje dinámico |
| A11 | `pb_admin_contabilidad_comprobante_v1` | DOCUMENT | `SendContabilidadComprobantesJob` | `{{carga}}` |
| A12 | `pb_admin_contabilidad_guia_v1` | DOCUMENT | `SendContabilidadGuiasJob` | `{{carga}}` |
| A13 | `pb_admin_contabilidad_detraccion_v1` | DOCUMENT | `SendContabilidadDetraccionesJob` | `{{carga}}` |

---

### 5.4 Cotización final y pagos (`consolidado` / `administracion`)

| ID | Nombre Meta sugerido | Tipo | `fromNumber` | Origen | Variables |
|----|----------------------|------|--------------|--------|-----------|
| C01 | `pb_consolidado_cotizacion_final_v1` | TEXT | consolidado | `CotizacionFinalController` | `{{nombre}}`, `{{carga}}`, montos logística/impuestos/total, `{{fecha_limite}}` |
| C02 | `pb_consolidado_resumen_pago_v1` | TEXT | consolidado | Mismo flujo paso 2 | `{{total}}`, `{{adelanto}}`, `{{pendiente}}` |
| C03 | `pb_consolidado_cotizacion_final_pdf_v1` | DOCUMENT | consolidado | `sendMedia` PDF cotización | PDF adjunto |
| C04 | `pb_consolidado_pagos_img_v1` | IMAGE | consolidado | Imagen números cuenta | — |
| C05 | `pb_consolidado_pago_preliminar_v1` | TEXT | consolidado | `PagosController`, `CotizacionController` | según mensaje armado en controlador |

---

### 5.5 Documentación importación (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| D01 | `pb_docs_paso1_excel_video_v1` | TEXT | `SolicitarDocumentosWhatsAppJob` paso 1 | `{{carga}}` |
| D02 | `pb_docs_excel_link_v1` | TEXT | **Reemplaza xlsx** — un envío por proveedor o zip | `{{carga}}`, `{{codigo_proveedor}}`, `{{link_excel}}` |
| D03 | `pb_docs_paso2_word_v1` | TEXT | Paso 2 texto | `{{carga}}`, `{{fecha_maxima}}` (opcional) |
| D04 | `pb_docs_consideraciones_doc_v1` | DOCUMENT + TEXT | `SolicitarDocumentosWhatsAppJob` (media PDF) | — (PDF en header; body fijo) |
| D05 | `pb_docs_recordatorio_intro_v1` | TEXT | `GeneralController::recordatoriosDocumentos` (intro) | `{{nombre_cliente}}`, `{{carga}}` |
| D06 | `pb_docs_recordatorio_proveedor_v1` | TEXT | `recordatoriosDocumentos` (por proveedor) | `{{codigo_proveedor}}`, `{{documentos_faltantes}}` |
| D07 | `pb_docs_recordatorio_aviso_v1` | TEXT | `recordatoriosDocumentos` (cierre) | — (texto fijo) |

---

### 5.6 Inspección (`/media-inspectionV2`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| I01 | `pb_inspeccion_llegada_v1` | TEXT | `SendInspectionMediaJob` | `{{cliente}}`, `{{code_supplier}}`, `{{qty_box}}`, `{{link_inspeccion}}` |
| I02 | `pb_inspeccion_imagen_v1` | IMAGE | Media por URL | `{{code_supplier}}` (caption) |
| I03 | `pb_inspeccion_video_v1` | VIDEO | Media inspección | `{{code_supplier}}` |

`link_inspeccion` = `{APP_URL_CLIENTES}/inspeccion/{uuid}?id_proveedor={id}`

---

### 5.7 Calculadora importación (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| CAL01 | `pb_calc_intro_v1` | TEXT | `CalculadoraImportacionWhatsappService` msg 1 | — (texto fijo + URL video) |
| CAL02 | `pb_calc_pdf_v1` | DOCUMENT | PDF cotización | PDF |
| CAL03 | `pb_calc_resumen_texto_v1` | TEXT | Mensaje 3 | — |
| CAL04 | `pb_calc_resumen_img_v1` | IMAGE | Imagen resumen costos | caption fijo |

---

### 5.8 Proveedores y operaciones varias (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| P01 | `pb_proveedor_llegada_china_v1` | TEXT | `NotifyArriveDateToday` | `{{nombre_cliente}}`, `{{code_supplier}}` |
| P02 | `pb_proveedor_datos_link_v1` | TEXT | `SendRecordatorioDatosProveedorJob` | `{{nombre_cliente}}`, `{{link_datos_proveedor}}`, `{{lista_proveedores}}` (compacta, sin `\n`) |
| P03 | `pb_proveedor_inspeccion_manual_v1` | TEXT | `CotizacionProveedorController` | `{{mensaje}}` (una línea) |
| P04 | `pb_general_cliente_v1` | TEXT | Usos puntuales con texto corto (no recordatorios documentos) | `{{mensaje}}` (texto corto, una línea) |
| P06 | `pb_proveedor_datos_guardado_pendiente_v1` | TEXT | `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`guardar1`) | `{{codigos_pendientes}}`, `{{link_datos_proveedor}}` |
| P07 | `pb_proveedor_datos_guardado_completo_v1` | TEXT | `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`guardar2`) | — (texto fijo) |
| P05 | `pb_delivery_whatsapp_v1` | TEXT | `DeliveryController` | `{{mensaje}}` (una línea) |

---

### 5.9 Ventas y cursos

| ID | Nombre Meta sugerido | Tipo | Canal | Origen | Variables |
|----|----------------------|------|-------|--------|-----------|
| V01 | `pb_ventas_cotizacion_pdf_v1` | DOCUMENT | ventas | `CotizacionProveedorController` ~3722 | PDF + `{{nombre}}` |
| V02 | `pb_ventas_curso_inicio_v1` | TEXT | ventas | `CursoController::sendMessageVentas` | según curso |
| CU01 | `pb_curso_constancia_v1` | DOCUMENT | curso | `SendConstanciaCurso` | PDF constancia |
| CU02 | `pb_curso_mensaje_v1` | TEXT | curso | `sendMessageCurso` | dinámico |

---

## 6. Mensajes dinámicos / libres (sin plantilla fija hoy)

Estos usan texto armado en runtime o vienen del request. Para Meta:

| Origen | Estrategia |
|--------|------------|
| `EntregaController::sendRecordatorioFormularioDelivery` | Plantilla UTILITY genérica + variable `{{mensaje}}` **solo si Meta aprueba**; si no, respuesta dentro de ventana 24h. |
| `EntregaController::sendCobroCotizacionFinalDelivery` | Plantillas C01–C04 |
| `CotizacionProveedorController` inspección / masivos | Plantillas I01–I03 o sesión |

---

## 7. Checklist de implementación

- [ ] Crear plantillas en Business Manager (es_PE).
- [ ] Asociar cada plantilla a la WABA (`consolidado`, `administracion`, `ventas`).
- [ ] Mapear `template_name` + `language` + array de parámetros en un servicio `MetaWhatsAppService`.
- [ ] Sustituir `_callApi('/messageV2')` por `messages` template endpoint.
- [ ] **XLSX/DOCX:** implementar PDF o link antes de registrar plantillas DOCUMENT.
- [ ] Secuencias: cola de jobs con delay (como hoy `sleep`) respetando límites Meta.
- [ ] Variables de entorno: `META_WABA_CONSOLIDADO`, `META_WABA_ADMIN`, tokens por número.
- [ ] Probar en número de prueba Meta antes de producción.

---

## 8. Referencia rápida: llamadas con `fromNumber = consolidado` (default)

Archivos que hoy usan `sendMessage` / `sendMedia` **sin** pasar `administracion` ni `ventas` (lista para priorizar migración en número consolidado):

- `SendDeliveryFormBulkJob`, `SendDeliveryConfirmationWhatsApp*Job`
- `SendRotuladoJob`, `ForceSendRotuladoJob` (parcial)
- `SolicitarDocumentosWhatsAppJob` (texto; archivos → §4)
- `CotizacionFinalController`, `CalculadoraImportacionWhatsappService`
- `EntregaController` (entrega, conformidad, cargo firmado)
- `SendInspectionMediaJob` (ruta inspección)
- `NotifyArriveDateToday`, `SendRecordatorioDatosProveedorJob`
- Y otros listados en inventario interno `docs/inventario_whatsapp.json`

---

## 9. Ejemplo de cuerpo de plantilla (Meta)

**`pb_entrega_link_lima_v1`** (UTILITY, español) — ver texto completo en [`META_WHATSAPP_TEMPLATES_CUERPO.md`](./META_WHATSAPP_TEMPLATES_CUERPO.md) § E01.

```
# Consolidado {{carga}}

🙋🏻‍♀️ Hola {{nombre_cliente}}, te saluda área de Coordinación.

Cliente: Lima

✅ Regístrate en el siguiente enlace.
✅ Reserva tu horario de recojo lo antes posible.
✅ Plazo máximo: 48 horas.
✅ Tener los pagos al día.

Formulario: {{link_formulario}}

⚠ Enviar movilidad acorde al volumen de su carga.
```

Orden al enviar por API: `carga` → `nombre_cliente` → `link_formulario`.

---

*Documento generado para migración desde `WhatsappTrait`. Actualizar al agregar nuevos flujos.*
