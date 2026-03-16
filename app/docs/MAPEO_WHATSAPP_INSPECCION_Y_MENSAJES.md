# Mapeo: envío de inspección y otros mensajes por WhatsApp

Ruta base del backend: `probusiness_intranetv2_back/app`.

---

## 1. INSPECCIÓN POR WHATSAPP

### 1.1 Job: `SendInspectionMediaJob`  
**Archivo:** `app/Jobs/SendInspectionMediaJob.php`

- **Disparo:** Se encola desde `CotizacionProveedorController::forceSendInspection()` (y desde `validateToSendInspectionMessage` vía lógica de envío al guardar inspección).
- **Qué hace:**
  - Actualiza estado del proveedor a `INSPECTION` / `INSPECCIONADO` y tracking.
  - Obtiene imágenes (`image/jpeg`, `image/png`, `image/jpg`) y videos (`video/mp4`) de `AlmacenInspection` del proveedor.
  - Envía **un mensaje de texto** al teléfono de la cotización y luego **cada imagen/video** con caption = código del proveedor.

**Mensaje de texto enviado (inspección):**
```text
{nombre_cliente}----{code_supplier}----{qty_box} boxes.

📦 Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.
```

- **Cada imagen/video:** se envía con mensaje/caption = `{code_supplier}`.
- **API usada:** `WhatsappTrait::sendMessage()` para el texto y `sendMediaInspectionToController()` para cada archivo (envía URL pública al endpoint `/media-inspectionV2`).

---

### 1.2 Controller: `CotizacionProveedorController`  
**Archivo:** `app/Http/Controllers/CargaConsolidada/CotizacionProveedorController.php`

| Método | Uso inspección | Mensajes |
|--------|----------------|----------|
| `saveInspection` | Guarda archivos de inspección y luego llama a `validateToSendInspectionMessage`. | No envía mensaje directo; el envío lo hace `validateToSendInspectionMessage`. |
| `validateToSendInspectionMessage` | Valida archivos, construye mensaje, envía por WhatsApp y dispara evento/notificaciones. | Mismo texto que el Job (ver abajo). |
| `buildInspectionMessage` (privado) | Construye el texto del mensaje de inspección. | `{cliente}----{code_supplier}----{qtyBox} boxes.\n\n📦 Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.\n\n` |
| `sendInspectionFiles` / `sendSingleInspectionFile` | Envían cada imagen/video con caption = `code_supplier`. | Texto inicial igual que arriba; cada media con caption = código proveedor. |
| `forceSendInspection` | Encola `SendInspectionMediaJob` para reenviar inspección. | Los mensajes los envía el Job (mismo texto y medias). |
| `testSendMediaInspection` | Prueba envío de medias de inspección. | Usa `sendMediaInspection()` (base64) a `/media-inspectionV2`. |

---

### 1.3 Trait: `WhatsappTrait`  
**Archivo:** `app/Traits/WhatsappTrait.php`

| Método | Uso |
|--------|-----|
| `sendMediaInspection` | Envía un archivo de inspección en base64 al API `/media-inspectionV2` (ruta dedicada sin `fromNumber`). |
| `sendMediaInspectionToController` | Envía **URL pública** del archivo al mismo endpoint `/media-inspectionV2`; el controlador externo encola el envío real. Requiere `inspection_id`. |

Inspecciones no usan `fromNumber` de consolidado/administración/ventas; usan estas rutas dedicadas.

---

### 1.4 Evento (no envía WhatsApp)  
**Archivo:** `app/Events/CotizacionChinaInspected.php`

- Se dispara cuando un proveedor es inspeccionado (ej. desde `CotizacionProveedorController::dispararEventoYNotificacionProveedorInspeccionado`).
- Hace **broadcast** a canales Pusher (`Coordinacion-notifications`, `Cotizador-notifications`). **No envía WhatsApp.**

---

## 2. OTROS MENSAJES WHATSAPP (SIN INSPECCIÓN)

### 2.1 Controllers

| Controller | Método / contexto | Mensaje / contenido |
|------------|-------------------|----------------------|
| **CotizacionController** | `updateEstadoCotizacion` (estado INTERESADO) | Mensaje para crons: "Hola {nombre}, sabemos que está interesad@ en el consolidado #{carga}..." (no se envía directo si no es prod). |
| **CotizacionController** | `updateEstadoCotizacion` (estado CONFIRMADO) | Genera PDF contrato y lo guarda; envío WhatsApp comentado. Mensaje (comentado): recordatorio contrato, puntos 1–6. |
| **CotizacionController** | `sendRecordatorioFirmaContrato` | "Hola {nombreCliente} porfavor firmar su contrato del consolidado #{carga} {signUrl}". Envío actualmente comentado (sendMessageVentas). |
| **EntregaController** | Subida conformidad (fotos entrega) | Texto: "Hola {nombre} 👋 Adjunto el sustento de entrega correspondiente a su importación del consolidado #{carga}...". Luego envía 2 fotos (photo1, photo2). |
| **EntregaController** | `sendMessageDelivery` | Mensaje libre desde request. |
| **EntregaController** | `sendRecordatorioFormularioDelivery` | Mensaje desde request (recordatorio formulario entrega). |
| **EntregaController** | `sendCobroCotizacionFinalDelivery` | Mensaje desde request (cobro cotización final). |
| **EntregaController** | `sendCobroDeliveryDelivery` | DELIVERY: "Consolidado #X, Hola {nombre}, por favor proceder con el pago del DELIVERY... Costo: S/ {total_pago_delivery}...". MONTACARGA: texto con costo montacarga y nota estiba. Luego envía imagen de números de cuenta. |
| **EntregaController** | `signCargoEntrega` | "Hola {cliente} 👋 Adjunto el documento de cargo de entrega firmado correspondiente a su importación del consolidado #{carga}...". Envía PDF firmado. |
| **CalculadoraImportacionController** | Estado COTIZADO (sendWhatsAppMessage) | 1) Primer mensaje texto; 2) PDF cotización; 3) Tercer mensaje texto; 4) Imagen resumen costos. |
| **CotizacionProveedorController** | Varios (rotulado, cobranza, etc.) | sendWelcome(carga), mensajes de coordinación, dirección almacén China, números de cuenta, etc. |
| **FacturaGuiaController** | Envío factura / guía / comprobantes | sendMedia con fromNumber 'administracion'. |
| **PagosController** | Notificaciones pago | sendMessage(..., 'administracion'). |
| **CursoController** | Mensajes curso | sendMessageCurso(...). |
| **Clientes/DeliveryController** | Mensaje delivery | sendMessage($message, $phoneNumber). |
| **Clientes/GeneralController** | Confirmación / consideraciones | Mensajes y envío de Excel/Word. |
| **CotizacionFinalController** | Cotización final + números de cuenta | Mensaje + PDF + mensaje + imagen pagos. |

### 2.2 Jobs

| Job | Contenido |
|-----|-----------|
| **SendInspectionMediaJob** | Inspección: mensaje texto + imágenes/videos (ver arriba). |
| **SendViaticoWhatsappNotificationJob** | Mensaje + comprobante retribución (administracion). |
| **SendRotuladoJob** | sendWelcome, mensajes coordinación, dirección China, PDFs rotulado (calzado, ropa, etc.), MOVILIDAD/VIM. |
| **SendContabilidadGuiasJob** | Guías de remisión (administracion). |
| **SendContabilidadComprobantesJob** | Comprobantes PDF (administracion). |
| **SendContabilidadDetraccionesJob** | Constancias detracción (administracion). |
| **SendConstanciaCurso** | Constancia de curso por WhatsApp. |
| **SendComprobanteFormNotificationJob** | Mensaje a administración + mensaje confirmación al cliente (administracion). |
| **ForceSendCobrandoJob** | Mensaje cobranza + imagen números de cuenta. |
| **SendRecordatorioDatosProveedorJob** | Recordatorio datos proveedor. |
| **SendDeliveryConfirmationWhatsAppLimaJob** | Confirmación entrega Lima. |
| **SendDeliveryConfirmationWhatsAppProvinceJob** | Confirmación entrega provincia. |
| **ForceSendRotuladoJob** | sendWelcome + mensajes coordinación + dirección China, etc. |

### 2.3 Servicios

- **CalculadoraImportacionService:** usa WhatsApp para tipo cliente y exportación; no envía mensajes directos de inspección.
- **ClienteService / ClienteExportService:** búsqueda/export por WhatsApp; no envío de inspección.

---

## 3. RESUMEN INSPECCIÓN

| Dónde | Qué se envía |
|-------|----------------|
| **SendInspectionMediaJob** | 1 mensaje: `{cliente}----{code_supplier}----{qtyBox} boxes.` + "📦 Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos."; luego cada imagen/video con caption = code_supplier. |
| **CotizacionProveedorController** | Mismo mensaje de texto + cada archivo con caption = code_supplier (vía sendMediaInspectionToController). |
| **WhatsappTrait** | sendMediaInspection (base64) o sendMediaInspectionToController (URL) → endpoint `/media-inspectionV2`. |

**Tabla/Modelo:** `AlmacenInspection` (`contenedor_consolidado_almacen_inspection`). Relaciones: `Cotizacion::inspeccionAlmacen`, `CotizacionProveedor::inspectionAlmacen`.

---

## 4. ENDPOINTS API WHATSAPP (Trait)

- `_callApi('/messageV2', ...)` — mensaje texto (consolidado/administracion/ventas).
- `_callApi('/media-inspectionV2', ...)` — media de inspección (URL o base64, inspectionId).
- `_callApi('/message-ventas', ...)` — mensaje desde ventas.
- `_callApi('/message-curso', ...)` — mensaje curso.
- sendWelcome, sendMedia con fromNumber: consolidado, administracion, ventas.

Base URL del API: `https://redis.probusiness.pe/api/whatsapp` (en WhatsappTrait).
