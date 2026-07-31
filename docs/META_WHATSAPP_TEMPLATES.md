# Migraci├│n a plantillas Meta (WhatsApp Business API)

Gu├¡a para reemplazar el env├¡o libre v├¡a `WhatsappTrait` (`sendMessage`, `sendMedia`, rutas dedicadas) por **plantillas aprobadas en Meta Business Manager**.

C├│digo de referencia: `app/Traits/WhatsappTrait.php`

**Textos listos para copiar en Meta Business Manager (solo WABA consolidado / default):** ver [`META_WHATSAPP_TEMPLATES_CUERPO.md`](./META_WHATSAPP_TEMPLATES_CUERPO.md). Administraci├│n, ventas y curso: cat├ílogo en ┬º5.3 y ┬º5.9 de este archivo (sin cuerpo detallado a├║n).

---

## 1. Tipos de plantilla en Meta

| Categor├¡a Meta | Cu├índo usarla en este proyecto |
|----------------|--------------------------------|
| **UTILITY** | Confirmaciones, pagos, entregas, documentaci├│n, recordatorios operativos, inspecci├│n, rotulado. **Usar por defecto.** |
| **MARKETING** | Solo promociones comerciales no solicitadas. Evitar si el cliente ya tiene relaci├│n activa de importaci├│n. |
| **AUTHENTICATION** | OTP / c├│digos de verificaci├│n (no aplica hoy). |

| Formato de plantilla | Cu├índo usarla |
|---------------------|---------------|
| **Solo texto** (`body`) | Mensajes informativos sin archivo adjunto obligatorio. |
| **Texto + encabezado DOCUMENT** | PDFs: cotizaci├│n final, rotulado, constancia, cargo entrega, factura, gu├¡a. |
| **Texto + encabezado IMAGE** | N├║meros de cuenta, mapa almac├⌐n China, fotos conformidad, resumen calculadora. |
| **Texto + encabezado VIDEO** | Solo si el activo ya es video (inspecci├│n). |
| **Texto + bot├│n URL** (opcional) | Links largos (`formulario-entrega`, `formulario-comprobante`, inspecci├│n). Meta limita el bot├│n; el link tambi├⌐n puede ir en `{{link}}` en el cuerpo. |

**Secuencias actuales** (varios mensajes seguidos con `sleep`): en Meta cada paso es **una plantilla distinta** o un mensaje de sesi├│n dentro de la ventana de 24 h. Planificar 1 plantilla por paso.

---

## 2. N├║meros / cuentas WABA (`fromNumber`)

| Clave actual | Uso | Plantillas asociadas |
|--------------|-----|----------------------|
| `consolidado` (default) | Cotizaciones, entregas, rotulado, pagos preliminares, calculadora | Prefijo sugerido: `pb_consolidado_*` |
| `administracion` | Factura, gu├¡a, comprobante, cobranza final, vi├íticos, contabilidad | `pb_admin_*` |
| `ventas` | Cotizaci├│n proveedor PDF | `pb_ventas_*` |
| `/welcomeV2` | Bienvenida + rotulado chino (API propia) | `pb_welcome_rotulado` + DOCUMENT |
| `/message-curso` | Cursos | `pb_curso_*` |
| `/media-inspectionV2` | Inspecci├│n (URL p├║blica) | `pb_inspeccion_*` |

Registrar cada plantilla en la **WABA correcta** (consolidado vs administraci├│n vs ventas).

---

## 3. Convenci├│n de variables

### En Business Manager (registro de plantilla)

Meta **no acepta** `{{1}}`, `{{2}}`. Usar solo **min├║sculas, n├║meros y gui├│n bajo**: `{{carga}}`, `{{nombre_cliente}}`, `{{link_formulario}}`.

| Regla | Detalle |
|-------|---------|
| Formato | `{{nombre_variable}}` ΓÇö ej. `{{order_id}}`, nunca `{{Nombre}}` ni `{{1}}` |
| Negrita | No envolver variables en `*negrita*`; solo texto fijo |
| Posici├│n | No iniciar ni terminar el BODY solo con una variable |
| `#` y signos | Dejar la variable separada: `Consolidado #{{carga}}` |

Los textos listos para pegar en Meta est├ín en [`META_WHATSAPP_TEMPLATES_CUERPO.md`](./META_WHATSAPP_TEMPLATES_CUERPO.md).

### Al enviar por Cloud API

Los valores se env├¡an como **array en el orden** en que aparecen las variables en el cuerpo de la plantilla (posici├│n 1, 2, 3ΓÇª), aunque en Meta est├⌐n nombradas.

### Glosario de nombres (reutilizar entre plantillas)

| Variable | Descripci├│n |
|----------|-------------|
| `{{nombre_cliente}}` / `{{nombre}}` | Nombre o primer nombre |
| `{{primer_nombre}}` | Primer nombre (confirmaciones entrega) |
| `{{carga}}` | N├║mero de consolidado |
| `{{carga_anio}}` | Ej. `05-2026` |
| `{{consolidado_label}}` | Etiqueta consolidado en comprobantes (ej. #05-2026) |
| `{{link_formulario}}` | URL formulario entrega Lima/Provincia |
| `{{link_comprobante}}` | URL formulario comprobante |
| `{{link_datos_proveedor}}` | URL datos proveedor |
| `{{link_excel}}` / `{{link_vin}}` | Enlaces documentaci├│n |
| `{{link_inspeccion}}` | URL inspecci├│n |
| `{{codigo_proveedor}}` | C├│digo proveedor |
| `{{fecha_limite}}` / `{{fecha_maxima}}` | Fechas dd/mm/yyyy |
| `{{mensaje}}` | Cuerpo din├ímico (solo si Meta aprueba plantilla gen├⌐rica) |

**Reglas Meta:** sin saltos de l├¡nea excesivos en una sola variable; montos sin s├¡mbolos raros; URLs HTTPS p├║blicas.

---

## 4. Archivos Office (XLSX / DOCX) ΓÇö NO enviar nativos

> **Migraci├│n Meta:** WhatsApp Cloud API **no admite** enviar `.xlsx` / `.docx` como documento de plantilla de forma fiable para el cliente final.

| Archivo actual | Origen | Acci├│n recomendada |
|----------------|--------|-------------------|
| `EXCEL_DE_CONFIRMACION_*.xlsx` | `SolicitarDocumentosWhatsAppJob` | **Opci├│n A:** generar PDF por proveedor y plantilla HEADER DOCUMENT. **Opci├│n B:** subir a storage y plantilla de texto con `{{link_descarga_excel}}`. |
| `CONSIDERATIONS.docx` | `SolicitarDocumentosWhatsAppJob` | Convertir a **PDF** y plantilla **D04** DOCUMENT (header); no enlace en texto. |
| `vin_movilidad.xlsx` | `SendRotuladoJob` ~L818 | Generar **PDF** del listado VIN o enlace de descarga; no plantilla DOCUMENT xlsx. |

```php
// TODO Meta: reemplazar sendMedia($excelPath, 'application/vnd...sheet', ...)
// por: Storage::url + template pb_consolidado_doc_link con {{link}}
// o: Pdf::loadView(...)->save() + template DOCUMENT pdf
```

Los **PDF** e **im├ígenes** (jpg/png) s├¡ van en plantillas con encabezado DOCUMENT / IMAGE.

---

## 5. Cat├ílogo de plantillas por flujo

### 5.1 Bienvenida y rotulado (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Categor├¡a | Origen | Variables |
|----|----------------------|------|-----------|--------|-----------|
| W01 | `pb_welcome_rotulado_v1` | TEXT + DOCUMENT (PDF chino v├¡a welcome API) | UTILITY | `sendWelcome`, `WhatsappTrait::buildWelcomeRotuladoMessageText` | `{{carga}}` (consolidado #) |
| W02 | `pb_rotulado_nuevo_proveedor_v1` | TEXT | UTILITY | `SendRotuladoJob`, `ForceSendRotuladoJob`, `CotizacionProveedorController` | `{{carga}}` |
| W03 | `pb_rotulado_datos_proveedor_v1` | TEXT | UTILITY | Mismo flujo, mensaje ΓÇ£datos de tu proveedorΓÇªΓÇ¥ | ΓÇö |
| W04 | `pb_rotulado_pdf_producto_v1` | DOCUMENT | UTILITY | `SendRotuladoJob::sendMedia` rotulado PDF | `{{producto}}`, `{{codigo_proveedor}}` (caption ΓåÆ body) |
| W05 | `pb_rotulado_tipo_calzado_v1` | DOCUMENT | UTILITY | PDFs por tipo (calzado, ropa, etc.) | `{{tipo}}`, `{{codigo}}` |
| W06 | `pb_rotulado_almacen_china_img_v1` | IMAGE | UTILITY | Imagen direcci├│n almac├⌐n | ΓÇö |
| W07 | `pb_rotulado_vin_link_v1` | TEXT | UTILITY | **Reemplaza xlsx** ΓÇö ver ┬º4 | `{{link_vin}}` |

---

### 5.2 Formulario de entrega Lima / Provincia (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| E01 | `pb_entrega_link_lima_v1` | TEXT (+ bot├│n URL opcional) | `SendDeliveryFormBulkJob` msg principal Lima | `{{nombre_cliente}}`, `{{carga}}`, `{{link_formulario}}` |
| E02 | `pb_entrega_reglas_lima_v1` | TEXT | `SendDeliveryFormBulkJob` msg secundario Lima | ΓÇö (texto fijo) |
| E03 | `pb_entrega_link_provincia_v1` | TEXT | Bulk / provincia principal | `{{nombre_cliente}}`, `{{carga}}`, `{{link_formulario}}` |
| E04 | `pb_entrega_reglas_provincia_v1` | TEXT | Bulk secundario; variante si `carga < 5` | `{{texto_flete}}` (cotizado vs cotizaci├│n final) |
| E05 | `pb_entrega_confirm_lima_v1` | TEXT | `LimaRecojoNotificacionService` | `{{primer_nombre}}`, `{{carga}}`, `{{pick_name}}`, `{{pick_dni}}`, `{{pick_phone}}`, `{{fecha_texto}}`, `{{hora_recojo}}`, `{{direccion}}`, `{{referencia}}`, `{{maps_url}}` |
| E06 | `pb_entrega_confirm_provincia_v1` | TEXT | `ProvinciaEntregaNotificacionService` | `{{primer_nombre}}`, `{{carga}}`, `{{destinatario}}`, `{{doc_label}}`, `{{doc}}`, `{{celular}}`, `{{agencia}}`, `{{ruc_agencia}}`, `{{destino}}`, `{{entrega_en}}`, `{{direccion}}` |
| E07 | `pb_entrega_conformidad_texto_v1` | TEXT | `EntregaController::uploadConformidad` | `{{nombre}}`, `{{carga}}` |
| E07-img | `pb_entrega_conformidad_foto_v1` | IMAGE + TEXT | `EntregaController::uploadConformidad` (por cada foto) | `{{numero}}` (`1` / `2`) + media header |
| E08 | `pb_entrega_cargo_firmado_v1` | TEXT + DOCUMENT | `EntregaController::signCargoEntrega` | `{{nombre}}`, `{{carga}}` + PDF |
| E09 | `pb_entrega_cobro_servicios_v1` | TEXT + IMAGE | `sendCobroDeliveryDelivery` | `{{carga}}`, `{{nombre}}`, bloques servicio |
| E10 | `pb_entrega_recordatorio_v1` | TEXT | `sendRecordatorioFormularioDelivery` (mensaje libre request) | Definir plantilla gen├⌐rica o mantener sesi├│n 24h |

`link_formulario` = `{APP_URL_CLIENTES}/formulario-entrega/{idContenedor}?destino=lima|provincia`

---

### 5.3 Comprobante y contabilidad (`administracion`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| A01 | `pb_admin_comprobante_form_link_nuevo_v1` | TEXT | `FacturaGuiaController::buildMensajeFormularioNuevo` | `{{nombre}}`, `{{carga}}`, `{{link_comprobante}}` |
| A02 | `pb_admin_comprobante_form_confirm_antiguo_v1` | TEXT | `buildMensajeFormularioAntiguo` | `{{nombre}}`, `{{carga}}`, tipo, RUC, raz├│n, domicilio, destino |
| A03 | `pb_admin_comprobante_cliente_factura_v1` | TEXT | `SendComprobanteFormNotificationJob` FACTURA | `{{consolidado_label}}`, `{{ruc}}`, `{{razon_social}}` |
| A04 | `pb_admin_comprobante_cliente_boleta_v1` | TEXT | Mismo job BOLETA | `{{consolidado_label}}`, `{{dni}}`, `{{nombre_completo}}` |
| A05 | `pb_admin_factura_comercial_v1` | DOCUMENT | `FacturaGuiaController::sendMedia` factura | `{{carga}}` + PDF |
| A06 | `pb_admin_guia_remision_v1` | DOCUMENT | Env├¡o gu├¡as (varios archivos = varias plantillas o 1 por archivo) | `{{carga}}`, nombre archivo |
| A07 | `pb_admin_recordatorio_pago_v1` | TEXT | `SendReminderPagoWhatsAppJob` | `{{carga}}`, `{{descripcion}}`, `{{total}}`, `{{adelanto}}`, `{{pendiente}}`, `{{fecha_limite}}` |
| A08 | `pb_admin_pagos_imagen_v1` | IMAGE | Recordatorio / cobranza / cotizaci├│n final (imagen cuentas) | ΓÇö |
| A09 | `pb_admin_cobro_reserva_cbm_v1` | TEXT | `ForceSendCobrandoJob`, `CotizacionProveedorController::procesarEstadoCobrando` | `{{nombre}}`, `{{carga_anio}}`, `{{cbm}}`, `{{costo}}`, `{{fecha_limite}}` |
| A10 | `pb_admin_viatico_adjunto_v1` | DOCUMENT | `SendViaticoWhatsappNotificationJob` | seg├║n mensaje din├ímico |
| A11 | `pb_admin_contabilidad_comprobante_v1` | DOCUMENT | `SendContabilidadComprobantesJob` | `{{carga}}` |
| A12 | `pb_admin_contabilidad_guia_v1` | DOCUMENT | `SendContabilidadGuiasJob` | `{{carga}}` |
| A13 | `pb_admin_contabilidad_detraccion_v1` | DOCUMENT | `SendContabilidadDetraccionesJob` | `{{carga}}` |

---

### 5.4 Cotizaci├│n final y pagos (`consolidado` / `administracion`)

| ID | Nombre Meta sugerido | Tipo | `fromNumber` | Origen | Variables |
|----|----------------------|------|--------------|--------|-----------|
| C01 | `pb_consolidado_cotizacion_final_v1` | TEXT | consolidado | `CotizacionFinalController` | `{{nombre}}`, `{{carga}}`, montos log├¡stica/impuestos/total, `{{fecha_limite}}` |
| C02 | `pb_consolidado_resumen_pago_v1` | TEXT | consolidado | Mismo flujo paso 2 | `{{total}}`, `{{adelanto}}`, `{{pendiente}}` |
| C03 | `pb_consolidado_cotizacion_final_pdf_v1` | DOCUMENT | consolidado | `sendMedia` PDF cotizaci├│n | PDF adjunto |
| C04 | `pb_consolidado_pagos_img_v1` | IMAGE | consolidado | Imagen n├║meros cuenta | ΓÇö |
| C05 | `pb_consolidado_pago_preliminar_v1` | TEXT | consolidado | `PagosController`, `CotizacionController` | seg├║n mensaje armado en controlador |

---

### 5.5 Documentaci├│n importaci├│n (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| D01 | `pb_docs_paso1_excel_video_v1` | TEXT | `SolicitarDocumentosWhatsAppJob` paso 1 | `{{carga}}` |
| D02 | `pb_docs_excel_link_v1` / `_qa` | TEXT | Excel confirmaci├│n (web + Drive) | QA: `{{link_intranet}}`, `{{link_excel}}` |
| D02b | `pb_docs_excel_conf_recibido_v1` | TEXT | Cliente guard├│ Excel confirmaci├│n (web) | `{{consolidado}}`, `{{enlace}}` |
| D03 | `pb_docs_paso2_word_v1` | TEXT | Paso 2 texto | `{{carga}}`, `{{fecha_maxima}}` (opcional) |
| D04 | `pb_docs_consideraciones_doc_v1` | DOCUMENT + TEXT | `SolicitarDocumentosWhatsAppJob` (media PDF) | ΓÇö (PDF en header; body fijo) |
| D05 | `pb_docs_recordatorio_intro_v1` | TEXT | `GeneralController::recordatoriosDocumentos` (intro) | `{{nombre_cliente}}`, `{{carga}}` |
| D06 | `pb_docs_recordatorio_proveedor_v1` / `_qa` | TEXT | `recordatoriosDocumentos` (**un mensaje agregado**) | sin Excel: `{{codigo_proveedor}}`, `{{documentos_faltantes}}` ┬╖ con Excel: `{{codigos_excel}}`, `{{link_web}}`, `{{link_drive}}`, `{{documentos_otros}}` |
| D07 | `pb_docs_recordatorio_aviso_v1` | TEXT | `recordatoriosDocumentos` (cierre; solo si **no** hay Excel) | ΓÇö (texto fijo) |

---

### 5.6 Inspecci├│n (`/media-inspectionV2`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| I01 | `pb_inspeccion_llegada_v1` | TEXT | `SendInspectionMediaJob` | `{{cliente}}`, `{{code_supplier}}`, `{{qty_box}}`, `{{link_inspeccion}}` |
| I02 | `pb_inspeccion_imagen_v1` | IMAGE | Media por URL | `{{code_supplier}}` (caption) |
| I03 | `pb_inspeccion_video_v1` | VIDEO | Media inspecci├│n | `{{code_supplier}}` |

`link_inspeccion` = `{APP_URL_CLIENTES}/inspeccion/{uuid}?id_proveedor={id}`

---

### 5.7 Calculadora importaci├│n (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| CAL01 | `pb_calc_intro_v1` | TEXT | `CalculadoraImportacionWhatsappService` msg 1 | ΓÇö (texto fijo + URL video) |
| CAL02 | `pb_calc_pdf_v1` | DOCUMENT | PDF cotizaci├│n | PDF |
| CAL03 | `pb_calc_resumen_texto_v1` | TEXT | Mensaje 3 | ΓÇö |
| CAL04 | `pb_calc_resumen_img_v1` | IMAGE | Imagen resumen costos | caption fijo |

---

### 5.8 Proveedores y operaciones varias (`consolidado`)

| ID | Nombre Meta sugerido | Tipo | Origen | Variables |
|----|----------------------|------|--------|-----------|
| P01 | `pb_proveedor_llegada_china_v1` | TEXT | `NotifyArriveDateToday` | `{{nombre_cliente}}`, `{{code_supplier}}` |
| P02 | `pb_proveedor_datos_link_v1` | TEXT | `SendRecordatorioDatosProveedorJob` | `{{nombre_cliente}}`, `{{link_datos_proveedor}}`, `{{lista_proveedores}}` (compacta, sin `\n`) |
| P03 | `pb_proveedor_inspeccion_manual_v1` | TEXT | `CotizacionProveedorController` | `{{mensaje}}` (una l├¡nea) |
| P04 | `pb_general_cliente_v1` | TEXT | Usos puntuales con texto corto (no recordatorios documentos) | `{{mensaje}}` (texto corto, una l├¡nea) |
| P06 | `pb_proveedor_datos_guardado_pendiente_v1` | TEXT | `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`guardar1`) | `{{codigos_pendientes}}`, `{{link_datos_proveedor}}` |
| P07 | `pb_proveedor_datos_guardado_completo_v1` | TEXT | `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`guardar2`) | ΓÇö (texto fijo) |
| P05 | `pb_delivery_whatsapp_v1` | TEXT | `DeliveryController::sendInitialDeliveryFormMessage` | `{{nombre}}`, `{{carga}}` |

---

### 5.9 Ventas y cursos

| ID | Nombre Meta sugerido | Tipo | Canal | Origen | Variables |
|----|----------------------|------|-------|--------|-----------|
| V01 | `pb_ventas_cotizacion_pdf_v1` | DOCUMENT | ventas | `CotizacionProveedorController` ~3722 | PDF + `{{nombre}}` |
| V02 | `pb_ventas_curso_inicio_v1` | TEXT | ventas | `CursoController::sendMessageVentas` | seg├║n curso |
| CU01 | `pb_curso_constancia_v1` | DOCUMENT | curso | `SendConstanciaCurso` | PDF constancia |
| CU02 | `pb_curso_mensaje_v1` | TEXT | curso | `sendMessageCurso` | din├ímico |

---

## 6. Mensajes din├ímicos / libres (sin plantilla fija hoy)

Estos usan texto armado en runtime o vienen del request. Para Meta:

| Origen | Estrategia |
|--------|------------|
| `EntregaController::sendRecordatorioFormularioDelivery` | Plantilla UTILITY gen├⌐rica + variable `{{mensaje}}` **solo si Meta aprueba**; si no, respuesta dentro de ventana 24h. |
| `EntregaController::sendCobroCotizacionFinalDelivery` | Plantillas C01ΓÇôC04 |
| `CotizacionProveedorController` inspecci├│n / masivos | Plantillas I01ΓÇôI03 o sesi├│n |

---

## 7. Checklist de implementaci├│n

- [ ] Crear plantillas en Business Manager (es_PE).
- [ ] Asociar cada plantilla a la WABA (`consolidado`, `administracion`, `ventas`).
- [ ] Mapear `template_name` + `language` + array de par├ímetros en un servicio `MetaWhatsAppService`.
- [ ] Sustituir `_callApi('/messageV2')` por `messages` template endpoint.
- [ ] **XLSX/DOCX:** implementar PDF o link antes de registrar plantillas DOCUMENT.
- [ ] Secuencias: cola de jobs con delay (como hoy `sleep`) respetando l├¡mites Meta.
- [ ] Variables de entorno: `META_WABA_CONSOLIDADO`, `META_WABA_ADMIN`, tokens por n├║mero.
- [ ] Probar en n├║mero de prueba Meta antes de producci├│n.

---

## 8. Referencia r├ípida: llamadas con `fromNumber = consolidado` (default)

Archivos que hoy usan `sendMessage` / `sendMedia` **sin** pasar `administracion` ni `ventas` (lista para priorizar migraci├│n en n├║mero consolidado):

- `SendDeliveryFormBulkJob`, `SendDeliveryConfirmationWhatsApp*Job`
- `SendRotuladoJob`, `ForceSendRotuladoJob` (parcial)
- `SolicitarDocumentosWhatsAppJob` (texto; archivos ΓåÆ ┬º4)
- `CotizacionFinalController`, `CalculadoraImportacionWhatsappService`
- `EntregaController` (entrega, conformidad, cargo firmado)
- `SendInspectionMediaJob` (ruta inspecci├│n)
- `NotifyArriveDateToday`, `SendRecordatorioDatosProveedorJob`
- Y otros listados en inventario interno `docs/inventario_whatsapp.json`

---

## 9. Ejemplo de cuerpo de plantilla (Meta)

**`pb_entrega_link_lima_v1`** (UTILITY, espa├▒ol) ΓÇö ver texto completo en [`META_WHATSAPP_TEMPLATES_CUERPO.md`](./META_WHATSAPP_TEMPLATES_CUERPO.md) ┬º E01.

```
# Consolidado {{carga}}

≡ƒÖï≡ƒÅ╗ΓÇìΓÖÇ∩╕Å Hola {{nombre_cliente}}, te saluda ├írea de Coordinaci├│n.

Cliente: Lima

Γ£à Reg├¡strate en el siguiente enlace.
Γ£à Reserva tu horario de recojo lo antes posible.
Γ£à Plazo m├íximo: 48 horas.
Γ£à Tener los pagos al d├¡a.

Formulario: {{link_formulario}}

ΓÜá Enviar movilidad acorde al volumen de su carga.
```

Orden al enviar por API: `carga` ΓåÆ `nombre_cliente` ΓåÆ `link_formulario`.

---

*Documento generado para migraci├│n desde `WhatsappTrait`. Actualizar al agregar nuevos flujos.*
