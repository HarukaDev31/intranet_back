# Textos para plantillas Meta (copiar en Business Manager)

Documento complementario de `META_WHATSAPP_TEMPLATES.md`.  
Cada bloque es el **cuerpo (BODY)** tal como debe registrarse en Meta.

**Alcance:** solo WABA **consolidado** (`fromNumber` por defecto en `WhatsappTrait`).  
No incluye plantillas de **administraci├│n** (`pb_admin_*`), **ventas** (`pb_ventas_*`) ni **curso** (`pb_curso_*`) ΓÇö ver cat├ílogo en `META_WHATSAPP_TEMPLATES.md` ┬º5.3 y ┬º5.9.

**Al crear en Meta:** categor├¡a **Utilidad**, idioma **Espa├▒ol**, cuenta/n├║mero **consolidado**.  
Formato WhatsApp en cuerpo: `*negrita*` solo en texto fijo (no rodear la variable).

### Reglas de variables (Business Manager)

Meta **no acepta** `{{1}}`, `{{2}}`. Usar nombres en min├║sculas, n├║meros y gui├│n bajo:

| Γ¥î Incorrecto | Γ£à Correcto |
|-------------|------------|
| `{{1}}` | `{{carga}}` |
| `*consolidado #{{1}}*` | `consolidado #{{carga}}` |
| `{{NombreCliente}}` | `{{nombre_cliente}}` |

Adem├ís:

- No poner la variable **dentro** de `*negrita*`.
- Dejar espacio antes/despu├⌐s si hay `#` o signos: `consolidado #{{carga}}`.
- No iniciar ni terminar el mensaje solo con una variable (ni que la ├║ltima l├¡nea del BODY termine en `{{ΓÇª}}` sin texto o emoji despu├⌐s).
- Si el cierre es una variable, a├▒adir **un emoji o texto fijo al final** (ej. `≡ƒôª`, `≡ƒôï`, `Γ£ê∩╕Å`).
- Usar llaves ASCII `{{` `}}` (no comillas tipogr├íficas).
- Al enviar por API, los valores van **en el orden** en que aparecen las variables en el texto.

En tablas de este doc, la columna **Par├ímetro Meta** es el nombre en la plantilla; **Orden API** es la posici├│n al enviar (`1`, `2`, ΓÇª).

**Media (PDF/imagen/video):** en plantillas con encabezado DOCUMENT/IMAGE/VIDEO sube un archivo de ejemplo al registrar; al enviar por API va el archivo real v├¡a **URL HTTPS** (Meta no lee rutas del servidor).

**S3 / backend:** `CoordinacionMediaLink` + `MetaWhatsAppCoordinacionService` suben o resuelven el archivo antes del env├¡o:
- Ruta relativa ya en S3/local ΓåÆ `ObjectStorageConnectorInterface::url()` (URL firmada si aplica).
- Archivo local (`storage/app`, `public/assets`, PDF temporal de rotulado, etc.) ΓåÆ subida a `temp/whatsapp-meta/ΓÇª` en el bucket y enlace en el header.
- Jobs y `CoordinacionWhatsappPayload::documentTemplate` / `imageTemplate` siguen pasando `header.path`; no hace falta subir manualmente en cada caller.

**XLSX/DOCX:** no registrar Office en plantilla; usar **enlace** en BODY (D02, VIN) o **DOCUMENT/PDF** (D04, rotulados W04/W05, C03, E08, etc.).

---

## Leyenda r├ípida

| Columna backend | Uso |
|-----------------|-----|
| `template_id` | ID interno (W01, E01ΓÇª) para mapear en c├│digo cuando tengas el nombre Meta |
| `meta_name` | Nombre exacto de la plantilla en Meta |
| `params` | Valores en orden de aparici├│n en el BODY |

---

## 5.1 Bienvenida y rotulado ┬╖ WABA consolidado

### W01 ΓÇö `pb_welcome_rotulado_v1`

**Tipo:** TEXT (el PDF chino va en flujo `welcomeV2` / DOCUMENT aparte si aplica)  
**WABA:** consolidado

**BODY:**

```
Hola ≡ƒÖï≡ƒÅ╗ΓÇìΓÖÇ, te escribe el ├írea de coordinaci├│n de Probusiness,
yo me encargar├⌐ de ayudarte en tu importaci├│n del consolidado #{{carga}}.

≡ƒôó Preste atenci├│n al siguiente paso:
*Rotulado* ≡ƒæç≡ƒÅ╝
Tienes que indicarle a tu proveedor que las cajas m├íster ≡ƒôª cuenten con un rotulado para identificar tus paquetes y diferenciarlas de los dem├ís cuando llegue a nuestro almac├⌐n.

Γÿæ El documento est├í en idioma chino, solo debes enviarle a tu proveedor ≡ƒôñ

Nota: No cambiar ninguno de los datos, en caso tu proveedor tenga alguna consulta, se puede comunicarse:

≡ƒÖì≡ƒÅ╗ΓÇìΓÖé Almac├⌐n China: Mr. Younus
≡ƒô₧ Wechat: 13185122926
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | N├║mero de carga / consolidado |

---

### W02 ΓÇö `pb_rotulado_nuevo_proveedor_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado

**BODY:**

```
Hola ≡ƒÖï≡ƒÅ╗ΓÇìΓÖÇ, te escribe el ├írea de coordinaci├│n de Probusiness.

≡ƒôó A├▒adiste un nuevo proveedor en el Consolidado #{{carga}}

*Rotulado: ≡ƒæç≡ƒÅ╝*
Tienes que indicarle a tu proveedor que las cajas m├íster ≡ƒôª cuenten con un rotulado para identificar tus paquetes y diferenciarlas de los dem├ís cuando llegue a nuestro almac├⌐n.
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |

---

### W03 ΓÇö `pb_rotulado_datos_proveedor_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado

**BODY:**

```
Tambi├⌐n necesito los datos de tu proveedor para comunicarnos y recibir tu carga.

Γ₧í *Datos del proveedor: (Usted lo llena)*

Γÿæ Nombre del producto:
Γÿæ Nombre del vendedor:
Γÿæ Celular del vendedor:

Te avisar├⌐ apenas tu carga llegue a nuestro almac├⌐n de China, cualquier duda me escribes. ≡ƒ½í
```

Sin variables.

---

### W03b ΓÇö `pb_rotulado_datos_proveedor_link_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ (variante `SendRotuladoJob` con URL)

**BODY:**

```
Tambi├⌐n necesito que ingrese al enlace y coloques los datos de tu proveedor, por favor ≡ƒ½í

Ingresar aqu├¡: {{link_datos_proveedor}}

{{lista_proveedores}}

≡ƒ½í
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{link_datos_proveedor}}` | 1 | URL datos proveedor (`APP_URL_DATOS_PROVEEDOR/{uuid}`) |
| `{{lista_proveedores}}` | 2 | Lista de proveedores pendientes (texto multil├¡nea: vendedor, WeChat, c├│digo) |

> Si `{{lista_proveedores}}` supera l├¡mites Meta, dividir en varios mensajes de sesi├│n o acortar lista en backend.

---

### W04 ΓÇö `pb_rotulado_pdf_producto_v1`

**Tipo:** DOCUMENT (encabezado) + BODY ┬╖ **WABA:** consolidado

**BODY:**

```
Producto: {{nombre_producto}}
C├│digo de proveedor: {{codigo_proveedor}} ≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_producto}}` | 1 | Nombre productos |
| `{{codigo_proveedor}}` | 2 | C├│digo proveedor |

---

### W05a ΓÇö `pb_rotulado_etiqueta_calzado_v1`

**Tipo:** DOCUMENT + BODY

**BODY:**

```
≡ƒæå≡ƒÅ╗ ΓÜá Atenci├│n ΓÜá

Etiqueta especial: Calzado

Seg├║n la regulaci├│n de Aduanas Per├║ todo calzado requiere tener una etiqueta Irremovible (Cosida a la leng├╝eta) de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

Γ¢ö No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.
≡ƒÜ½ El rotulado NO puede estar en Chino deber├í ser en ESPA├æOL.
≡ƒô¥ Aqu├¡ tienes un ejemplo de como debes colocar las etiquetas
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigo_proveedor}}` | 1 | C├│digo proveedor |

---

### W05b ΓÇö `pb_rotulado_etiqueta_ropa_v1`

**BODY:** (mismo patr├│n, texto ropa)

```
≡ƒæå≡ƒÅ╗ ΓÜá Atenci├│n ΓÜá

Etiqueta especial: Prendas de Vestir

Seg├║n la regulaci├│n de Aduanas - Per├║ todo producto textil, requiere tener un etiqueta Cosida o Sublimada de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

Γ¢ö No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.
≡ƒÜ½ El rotulado NO puede estar en Chino deber├í ser en ESPA├æOL.
≡ƒô¥ Aqu├¡ tienes un ejemplo de como tu proveedor debe colocar las etiquetas
```

---

### W05c ΓÇö `pb_rotulado_etiqueta_ropa_interior_v1`

```
≡ƒæå≡ƒÅ╗ ΓÜá Atenci├│n ΓÜá

Etiqueta especial: Ropa interior/ Accesorios de Vestir

Seg├║n la regulaci├│n de Aduanas - Per├║ todo producto textil, requiere tener un etiqueta Cosida o Colgante de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

Γ¢ö No aceptamos cargas sin el etiquetado correcto ya que la aduana lo puede decomisar.
≡ƒÜ½ El rotulado NO puede estar en Chino deber├í ser en ESPA├æOL.
≡ƒô¥ Aqu├¡ tienes un ejemplo de como tu proveedor debe colocar las etiquetas
```

---

### W05d ΓÇö `pb_rotulado_etiqueta_maquinaria_v1`

```
≡ƒæå≡ƒÅ╗ ΓÜá Atenci├│n ΓÜá

Etiqueta especial: Maquinaria

Seg├║n la regulaci├│n de Aduanas - Per├║ todas maquinaria domestico o industrial que contengan un motor el├⌐ctrico, requiere tener una placa Irremovible y visible de manera obligatoria.

Por lo tanto, dile a tu proveedor #{{codigo_proveedor}} que le ponga la etiqueta.

Γ¢ö No aceptamos cargas sin la placa ya que la aduana lo puede observar o decomisar.
≡ƒÜ½ El rotulado del producto NO puede estar en Chino deber├í ser en ESPA├æOL.
≡ƒô¥ Aqu├¡ tienes un ejemplo de como tu proveedor debe colocar la placa
```

---

### W06 ΓÇö `pb_rotulado_almacen_china_img_v1`

**Tipo:** IMAGE (encabezado) + BODY

**BODY:**

```
≡ƒÅ╜ Dile a tu proveedor que env├¡e la carga a nuestro almac├⌐n en China
```

Sin variables (imagen fija de direcci├│n).

---

### W07 ΓÇö `pb_rotulado_vin_link_v1`

**Tipo:** TEXT ┬╖ Reemplaza env├¡o de `vin_movilidad.xlsx`

**BODY:**

```
≡ƒæå≡ƒÅ╝ Le adjuntamos la lista de c├│digos VIN que deben ir grabados en los veh├¡culos de movilidad personal.

Desc├írgala aqu├¡: {{link_vin}} ≡ƒôï
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{link_vin}}` | 1 | URL p├║blica HTTPS al PDF/listado (no xlsx) |

---

## 5.2 Entrega Lima / Provincia ┬╖ WABA consolidado

### E01 ΓÇö `pb_entrega_link_lima_v1`

**Tipo:** TEXT ┬╖ Bot├│n URL opcional: ┬½Registrar entrega┬╗ ΓåÆ `{{link_formulario}}` ┬╖ **WABA:** consolidado

**BODY:**

```
# Consolidado {{carga}}

≡ƒÖï≡ƒÅ╗ΓÇìΓÖÇ∩╕Å Hola {{nombre_cliente}}, te saluda ├írea de Coordinaci├│n.

Cliente: Lima

Γ£à *Registrarse*, en el siguiente link.
Γ£à *Reservar su horario* de recojo lo antes posible.
Γ£à *Plazo m├íximo* para el registro: 48 horas
Γ£à Tener los pagos al d├¡a.
Γ£à Formulario: {{link_formulario}}

ΓÜá Enviar movilidad acorde al volumen de su carga (auto, camioneta, furg├│n o cami├│n).
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre_cliente}}` | 2 | Nombre cliente |
| `{{link_formulario}}` | 3 | URL formulario entrega Lima |

---

### E02 ΓÇö `pb_entrega_reglas_lima_v1`

**Tipo:** TEXT ┬╖ Sin variables

**BODY:**

```
Γ¥î Tiempo m├íximo de recojo: *30 minutos* seg├║n horario reservado
Γ¥î La movilidad debe retirar toda la mercader├¡a en un solo viaje.
Γ¥î No se permite recojo parcial ni m├║ltiples viajes.
Γ¥î No est├í permitido seleccionar, separar, armar o desarmar productos dentro del almac├⌐n.
Γ¥î No dejar pallets, etiquetas ni bolsas en el almac├⌐n.

≡ƒôì Agradecemos su apoyo para mantener un proceso de entrega ordenado.
```

---

### E03 ΓÇö `pb_entrega_link_provincia_v1`

**BODY:**

```
# Consolidado {{carga}}

≡ƒÖï≡ƒÅ╗ΓÇìΓÖÇ∩╕Å Hola {{nombre_cliente}}, te saluda ├írea de Coordinaci├│n.

Cliente: Provincia

Γ£à *Registrarse*, en el siguiente link.
Γ£à *Plazo m├íximo* para el registro: 48 horas
Γ£à *Organizaremos los env├¡os* una vez liberado el contenedor.
Γ£à Formulario: {{link_formulario}}

ΓÜá De no llenar el formulario no se programar├í el env├¡o de sus productos.
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre_cliente}}` | 2 | Nombre cliente |
| `{{link_formulario}}` | 3 | URL formulario provincia |

---

### E04a ΓÇö `pb_entrega_reglas_provincia_flete_final_v1`

Usar cuando `intval(carga) >= 5`.

**BODY:**

```
Importante:

Γ₧í La informaci├│n registrada ser├í utilizada para la *emisi├│n de gu├¡as de remisi├│n*.
Γ₧í *Validar* que sus datos est├⌐n correctos y completos.
Γ₧í El *costo de flete* Almac├⌐n ΓÇô Agencia detalla en su cotizaci├│n final.
Γ₧í Los env├¡os se realizan con *Marvisur*.
Γ₧í Si desea trabajar con otra agencia de transporte, se aplicar├í un *costo adicional* y previa coordinaci├│n.
Γ₧í En ese caso, no asumimos responsabilidad por incidencias en la entrega con la agencia elegida.
```

---

### E04b ΓÇö `pb_entrega_reglas_provincia_flete_cotiza_v1`

Usar cuando `intval(carga) < 5`.

**BODY:**

```
Importante:

Γ₧í La informaci├│n registrada ser├í utilizada para la *emisi├│n de gu├¡as de remisi├│n*.
Γ₧í *Validar* que sus datos est├⌐n correctos y completos.
Γ₧í El *costo de flete* Almac├⌐n ΓÇô Agencia se cotizar├í y ser├í informado por interno.
Γ₧í Los env├¡os se realizan con *Marvisur*.
Γ₧í Si desea trabajar con otra agencia de transporte, se aplicar├í un *costo adicional* y previa coordinaci├│n.
Γ₧í En ese caso, no asumimos responsabilidad por incidencias en la entrega con la agencia elegida.
```

---

### E05 ΓÇö `pb_entrega_confirm_lima_v1`

**BODY:**

```
Hola, {{primer_nombre}} ≡ƒæï

Tu recojo del Consolidado #{{carga}} ha sido registrado. Aqu├¡ el resumen:

≡ƒæñ *PERSONA QUE RECOGE*
{{pick_name}}
*DNI:* {{pick_dni}}
*Cel.:* {{pick_phone}}

≡ƒôà *FECHA Y HORA DE RECOJO*
{{fecha_hora_recojo}}

≡ƒôì *DIRECCI├ôN DE RECOJO*
{{direccion}}
{{referencia}}
{{maps_url}}

Gracias por confiar en *Pro Business* ≡ƒÖî
Donde conectamos tu negocio con los mejores productos y servicios.
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{primer_nombre}}` | 1 | Primer nombre |
| `{{carga}}` | 2 | Carga |
| `{{pick_name}}` | 3 | Nombre quien recoge |
| `{{pick_dni}}` | 4 | DNI |
| `{{pick_phone}}` | 5 | Celular |
| `{{fecha_hora_recojo}}` | 6 | Fecha textual ┬╖ hora |
| `{{direccion}}` | 7 | Direcci├│n |
| `{{referencia}}` | 8 | Referencia |
| `{{maps_url}}` | 9 | URL Google Maps |

---

### E06 ΓÇö `pb_entrega_confirm_provincia_v1`

**BODY:**

```
Γ£à *Env├¡o registrado*

Hola, {{primer_nombre}} ≡ƒæï

Tu solicitud de env├¡o para el Consolidado #{{carga}} fue registrada correctamente.

≡ƒôª *DESTINATARIO*
*Nombre:* {{destinatario}}
*{{doc_label}}:* {{doc_numero}}
*Celular:* {{celular}}

≡ƒÜÜ *TRANSPORTE*
*Agencia:* {{agencia}}
*RUC:* {{ruc_agencia}}
*Destino:* {{destino}}
*Entrega en:* {{entrega_en}}
*Direcci├│n:* {{direccion}}

Γ£à
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{primer_nombre}}` | 1 | Primer nombre |
| `{{carga}}` | 2 | Carga |
| `{{destinatario}}` | 3 | Nombre destinatario |
| `{{doc_label}}` | 4 | DNI o RUC (etiqueta) |
| `{{doc_numero}}` | 5 | N├║mero documento |
| `{{celular}}` | 6 | Celular |
| `{{agencia}}` | 7 | Agencia |
| `{{ruc_agencia}}` | 8 | RUC agencia |
| `{{destino}}` | 9 | Destino ubigeo |
| `{{entrega_en}}` | 10 | Agencia o Domicilio |
| `{{direccion}}` | 11 | Direcci├│n domicilio; si entrega en agencia, enviar `ΓÇö` o `No aplica` |

---

### E07 ΓÇö `pb_entrega_conformidad_texto_v1`

**Tipo:** TEXT (mensaje principal)

**BODY:**

```
Hola {{nombre}} ≡ƒæï
Adjunto el sustento de entrega correspondiente a su importaci├│n del consolidado #{{carga}}.

Muchas gracias por confiar en Pro Business. Si tiene una pr├│xima importaci├│n, estaremos encantados de ayudarlo nuevamente. No dude en escribirnos Γ£ê∩╕Å≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre}}` | 1 | Nombre |
| `{{carga}}` | 2 | Carga |

**Flujo Meta (2 fotos):** enviar primero **E07** (texto) y despu├⌐s **una o dos veces** **E07-img** (cada foto = un env├¡o de plantilla; `{{numero}}` = `1` o `2`). Meta no permite 2 im├ígenes en una sola plantilla.

---

### E07-img ΓÇö `pb_entrega_conformidad_foto_v1`

**Tipo:** IMAGE (header) + TEXT (body obligatorio en Meta)

**Registro en BM:** categor├¡a **Utilidad**, encabezado **Imagen** (sube un JPG de ejemplo; al enviar por API va la foto real de conformidad).

**BODY:**

```
Sustento de entrega ΓÇö foto {{numero}}. ≡ƒô╖
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{numero}}` | 1 | `1` para `photo_1`, `2` para `photo_2` (misma plantilla, dos env├¡os si hay dos fotos) |

---

### E08 ΓÇö `pb_entrega_cargo_firmado_v1`

**Tipo:** DOCUMENT + BODY

**BODY:**

```
Hola {{nombre}} ≡ƒæï
Adjunto el documento de cargo de entrega firmado correspondiente a su importaci├│n del consolidado #{{carga}}.

Muchas gracias por confiar en Pro Business. Γ£ê∩╕Å≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre}}` | 1 | Nombre cliente |
| `{{carga}}` | 2 | Carga |

---

### E09 ΓÇö `pb_entrega_cobro_servicios_v1`

**Tipo:** TEXT (+ IMAGE cuentas despu├⌐s con C04, mismo n├║mero consolidado)
**WABA:** consolidado

**BODY:**

```
Consolidado #{{carga}}
Hola {{nombre}}, por favor proceder con el pago de lo siguiente:

{{bloque_servicios}}

≡ƒÆ│
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre}}` | 2 | Nombre |
| `{{bloque_servicios}}` | 3 | Bloque(s) servicio (DELIVERY/MONTACARGA armado en backend) |

**Ejemplo de `{{bloque_servicios}}` (DELIVERY):**

```
ΓÇö DELIVERY
Se env├¡a el costo del flete interno (Almac├⌐n-agencia)
Costo: S/ 150.00
Por favor nos compartes el comprobante de pago para poder gestionar tu env├¡o
```

---

### E10 ΓÇö `pb_entrega_recordatorio_v1`

Solo si Meta aprueba cuerpo variable; si no, mensaje libre en ventana 24h.

**BODY:**

```
≡ƒô⌐ Recordatorio:

{{mensaje}}

≡ƒÖî
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{mensaje}}` | 1 | Texto completo del recordatorio (desde intranet) |

---

## 5.3 Cotizaci├│n final y pagos ┬╖ WABA consolidado

### C01 ΓÇö `pb_consolidado_cotizacion_final_v1`

**WABA:** consolidado

**BODY:**

```
≡ƒôª Consolidado #{{carga}}
Hola {{nombre}} ≡ƒÿü un gusto saludarte!
A continuaci├│n te envio la cotizaci├│n final de tu importaci├│n≡ƒôï≡ƒôª.

≡ƒÖïΓÇìΓÖé∩╕ÅPAGO PENDIENTE:
Γÿæ∩╕ÅCosto CBM: ${{costo_cbm}}
Γÿæ∩╕ÅImpuestos: ${{impuestos}}
{{servicios_extras}}
Γ£àTotal: ${{total}}

Pronto le aviso nuevos avances, que tengan buen dia
├Ültimo d├¡a de pago: {{fecha_limite}} ≡ƒôà
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | Carga |
| `{{nombre}}` | 2 | Nombre |
| `{{costo_cbm}}` | 3 | Costo CBM |
| `{{impuestos}}` | 4 | Impuestos |
| `{{servicios_extras}}` | 5 | L├¡nea servicios extras o vac├¡o |
| `{{total}}` | 6 | Total |
| `{{fecha_limite}}` | 7 | ├Ültimo d├¡a de pago |

---

### C02 ΓÇö `pb_consolidado_resumen_pago_v1`

**BODY:**

```
≡ƒÆ░*Resumen de Pago*
Γ£àCotizaci├│n final: ${{total_cotizacion}}
Γ£àAdelanto: ${{adelanto}}
Γ£à Pendiente de pago: ${{pendiente}} ≡ƒÆ│
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{total_cotizacion}}` | 1 | Total cotizaci├│n |
| `{{adelanto}}` | 2 | Adelanto |
| `{{pendiente}}` | 3 | Pendiente |

---

### C03 ΓÇö `pb_consolidado_cotizacion_final_pdf_v1`

**Tipo:** DOCUMENT

**BODY:**

```
Cotizaci├│n final ΓÇö Consolidado #{{carga}}. ≡ƒôä
```

---

### C04 ΓÇö `pb_consolidado_pagos_img_v1`

**Tipo:** IMAGE

**BODY:**

```
Medios de pago Pro Business.
```

---

### C05 ΓÇö `pb_consolidado_pago_preliminar_v1`

Mensaje gen├⌐rico si el controlador arma texto variable ΓÇö usar `{{mensaje}}` con cuerpo completo o definir plantilla por cada flujo de `PagosController`.

**BODY sugerido (ajustar seg├║n mensaje real en c├│digo):**

```
≡ƒô⌐ Pago preliminar:

{{mensaje}}

≡ƒÆ│
```

---

## 5.4 Documentaci├│n importaci├│n ┬╖ WABA consolidado

### D01 ΓÇö `pb_docs_paso1_excel_video_v1`

**BODY:**

```
ΓÜá∩╕ÅIMPORTANTEΓÜá∩╕Å

El siguiente paso es la recopilaci├│n de tus documentos para la declaraci├│n en Aduanas. Para ello, te solicitar├⌐ los siguientes documento.

Documentaci├│n: CONSOLIDADO #{{carga}}

Γÿæ PASO 1: Llenar el Excel de confirmaci├│n con las caracter├¡sticas de los productos que est├ís importando para poder declarar correctamente tus productos ≡ƒôä y evitar multas o p├⌐rdidas en aduanas.

≡ƒôó IMPORTANTE: Ver el video sobre el Excel de confirmaci├│n. ≡ƒôï

Video: https://youtu.be/rvhwblBEbXQ
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{carga}}` | 1 | C├│digo carga (ej. 05) |

---

### D02 ΓÇö `pb_docs_excel_link_v1`

**Reemplaza adjunto XLSX** ΓÇö un mensaje por cotizaci├│n (Excel general con una hoja por proveedor).

**BODY (legacy):**

```
Documentaci├│n: CONSOLIDADO #{{carga}}

Excel de confirmaci├│n ΓÇö Proveedor {{codigo_proveedor}}

Desc├írgalo aqu├¡: {{link_excel}} ≡ƒôä
```

QA / main (`pb_docs_excel_link_v1_qa`) ΓÇö plantilla que usa el backend:

**BODY:**

```
Tienes 2 opciones para llenar la informaci├│n
1.	≡ƒô▒ Desde tu celular:
{{link_intranet}}   .
2.	≡ƒôäDescargando el Excel
{{link_excel}}Γ£à.
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{link_intranet}}` | 1 | URL formulario web (sin `?proveedor=`) |
| `{{link_excel}}` | 2 | URL Google Drive |

> Nota: el backend env├¡a `pb_docs_excel_link_v1_qa` (misma plantilla Meta que en QA).

---

### D02b ΓÇö `pb_docs_excel_conf_recibido_v1`

**Tipo:** TEXT ┬╖ **Categor├¡a:** UTILITY ┬╖ **WABA:** consolidado  
**Origen:** `ExcelConfirmacionController` (web p├║blica) tras `saveConfirmation` exitoso del cliente.

**BODY:**

```
Gracias por llenar la informaci├│n de tu importaci├│n del consolidado #{{consolidado}}
Si aun tienes informaci├│n pendiente de llenar, vuelve a ingresar al enlace
{{enlace}}.
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{consolidado}}` | 1 | C├│digo carga (ej. 05) |
| `{{enlace}}` | 2 | URL formulario web (`buildExcelConfirmacionUrl`, sin `?proveedor=`) |

---

### D03 ΓÇö `pb_docs_paso2_word_v1`

**Sin fecha m├íxima:**

**BODY:**

```
Γÿæ PASO 2: Solicita a tu proveedor los documentos finales:
ΓÇó Commercial Invoice ≡ƒôä.
ΓÇó Packing List ≡ƒôª.

≡ƒôï Adjuntamos un Word con indicaciones para un correcto llenado.
≡ƒô⌐ El documento est├í en idioma chino, solo enviarlo a su proveedor.
≡ƒÜ½ Indicar a tu proveedor, que no se rellena encima del Word. ESTE WORD ES SOLO UNA GUIA.
```

**Con fecha m├íxima** ΓÇö `pb_docs_paso2_word_fecha_v1`:

```
Γÿæ PASO 2: Solicita a tu proveedor los documentos finales:
ΓÇó Commercial Invoice ≡ƒôä.
ΓÇó Packing List ≡ƒôª.

≡ƒôï Adjuntamos un Word con indicaciones para un correcto llenado.
≡ƒô⌐ El documento est├í en idioma chino, solo enviarlo a su proveedor.
≡ƒÜ½ Indicar a tu proveedor, que no se rellena encima del Word. ESTE WORD ES SOLO UNA GUIA.

Fecha maxima de entrega: {{fecha_maxima}} ≡ƒôà
```

---

### D04 ΓÇö `pb_docs_consideraciones_doc_v1`

**Tipo:** DOCUMENT (encabezado) + TEXT ┬╖ **WABA:** consolidado  
**Origen:** `SolicitarDocumentosWhatsAppJob` ΓÇö hoy env├¡a `CONSIDERATIONS.docx` con `sendMedia`; en Meta usar **PDF** (`CONSIDERATIONS.pdf`) en el header DOCUMENT.

**Registro en BM:** sube un PDF de ejemplo en el encabezado; al enviar por API va el archivo real (mismo flujo que el job, paso final tras D03).

**BODY:**

```
Consideraciones para la documentaci├│n de tu importaci├│n. ≡ƒôï
```

Sin variables (el archivo va en el encabezado DOCUMENT, no como `{{link}}` en el texto).

**Secuencia:** D01 ΓåÆ D02 (por proveedor) ΓåÆ D03 ΓåÆ **D04** (documento adjunto).

---

### D05 ΓÇö `pb_docs_recordatorio_intro_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ **Origen:** `GeneralController::recordatoriosDocumentos` (paso 1 de la secuencia).

**Idioma en BM:** **Spanish (Peru)** / `es_PE`.

**BODY:**

```
Hola {{nombre_cliente}}, estamos esperando que nos env├¡es los documentos de tu importaci├│n del consolidado #{{carga}}. A continuaci├│n detallo los que faltan:
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{carga}}` | 2 | C├│digo carga (ej. 05) |

---

### D06 ΓÇö `pb_docs_recordatorio_proveedor_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ **Origen:** `GeneralController::recordatoriosDocumentos` ΓÇö **un solo mensaje agregado** con todos los documentos/proveedores pendientes (ya no uno por proveedor).

**Idioma en BM:** **Spanish (Peru)** / `es_PE` (debe coincidir con la API; no registrar en English).

**BODY (sin Excel):**

```
Recordatorio de documentaci├│n de importaci├│n ≡ƒôï

Proveedor: {{codigo_proveedor}}

A├║n estamos esperando los siguientes documentos:
{{documentos_faltantes}}

Por favor env├¡alos lo antes posible para continuar con la declaraci├│n aduanera. Gracias.
```

| Par├ímetro Meta | Orden API | Campo backend | Sample BM |
|----------------|-----------|---------------|-----------|
| `{{codigo_proveedor}}` | 1 | C├│digos unidos (`JASO6-1 Y JASO6-2`) | `JASO6-1 Y JASO6-2` |
| `{{documentos_faltantes}}` | 2 | Docs con c├│digos por l├¡nea | `Packing List ≡ƒôª (JASO6-1 Y JASO6-2)` |

---

### D06b ΓÇö `pb_docs_recordatorio_proveedor_v1_qa` ΓÜá∩╕Å ACTUALIZAR EN META

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ **Origen:** mismo endpoint cuando el recordatorio incluye `excel_confirmacion`.

**Usar cuando:** hay Excel pendiente (cualquier proveedor). **Un solo env├¡o** (agrega todos los proveedores). El aviso de aduana va **dentro** de esta plantilla (no se env├¡a D07).

**Idioma en BM:** **Spanish (Peru)** / `es_PE`.

**BODY (copiar tal cual en Meta Business Manager):**

```
Recordatorio de documentaci├│n de importaci├│n ≡ƒôï

A├║n estamos esperando los siguientes documentos:

Excel de confirmaci├│n ({{codigos_excel}}) ≡ƒôä
Tienes 2 opciones para llenar la informaci├│n
1.≡ƒô▒ Desde tu celular:
{{link_web}}
2.≡ƒôäDescargando el Excel
{{link_drive}}

{{documentos_otros}}

Por favor env├¡alos lo antes posible para continuar con la declaraci├│n aduanera. Gracias

Probusiness Coordinaci├│n: Si no tenemos tus documentos a tiempo, aduana puede aplicarte multas o inmovilizaci├│n de tus productos.
```

| Par├ímetro Meta | Orden API | Campo backend | Sample BM |
|----------------|-----------|---------------|-----------|
| `{{codigos_excel}}` | 1 | C├│digos con Excel pendiente | `JASO6-1 Y JASO6-2` |
| `{{link_web}}` | 2 | URL formulario web | `https://confirmacion.probusiness.pe/{uuid}` |
| `{{link_drive}}` | 3 | Link Drive (o `ΓÇö`) | `https://drive.google.com/...` |
| `{{documentos_otros}}` | 4 | Packing / Invoice con c├│digos (o `ΓÇö`) | `Packing List ≡ƒôª (JASO6-1 Y JASO6-2)\nCOMERCIAL INVOICE (JASO6-1)` |

**Notas Meta:**
- Si la plantilla actual `pb_docs_recordatorio_proveedor_v1_qa` ya est├í aprobada con variables viejas (`codigo_proveedor`, `documentos_faltantes`), crear una **nueva versi├│n** (ej. `pb_docs_recordatorio_proveedor_v2`) o editar y reenviar a revisi├│n con los 4 par├ímetros de arriba.
- Tras aprobar, el backend ya env├¡a: `codigos_excel`, `link_web`, `link_drive`, `documentos_otros`.

### D07 ΓÇö `pb_docs_recordatorio_aviso_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ **Origen:** `GeneralController::recordatoriosDocumentos` (cierre **solo si no hay Excel**).

**Idioma en BM:** **Spanish (Peru)** / `es_PE`.

**BODY:**

```
Si no tenemos tus documentos a tiempo, aduana puede aplicarte multas o inmovilizaci├│n de tus productos.
```

Sin variables.

**Secuencia recordatorio:**
- Con Excel: D05 ΓåÆ D06b (`_qa`, 1 mensaje) 
- Sin Excel: D05 ΓåÆ D06 (`v1`, 1 mensaje) ΓåÆ D07

---

## 5.5 Inspecci├│n ┬╖ WABA consolidado

Mismo n├║mero; env├¡o v├¡a API `/media-inspectionV2`.

### I01 ΓÇö `pb_inspeccion_llegada_v1`

**BODY:**

```
≡ƒôª Cliente: {{nombre_cliente}} ΓÇö Proveedor {{codigo_proveedor}} ΓÇö {{cantidad_cajas}} boxes.

Tu carga lleg├│ a nuestro almac├⌐n de Yiwu, te comparto las fotos y videos.

≡ƒöù Ver inspecci├│n: {{link_inspeccion}} ≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{codigo_proveedor}}` | 2 | C├│digo proveedor |
| `{{cantidad_cajas}}` | 3 | Cantidad cajas |
| `{{link_inspeccion}}` | 4 | URL inspecci├│n |

---

### I02 ΓÇö `pb_inspeccion_imagen_v1`

**Tipo:** IMAGE ┬╖ Caption:

**BODY:**

```
≡ƒôª Inspecci├│n ΓÇö proveedor {{codigo_proveedor}} ≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigo_proveedor}}` | 1 | C├│digo proveedor |

---

### I03 ΓÇö `pb_inspeccion_video_v1`

**Tipo:** VIDEO ┬╖ Caption:

**BODY:**

```
≡ƒôª Inspecci├│n ΓÇö proveedor {{codigo_proveedor}} ≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigo_proveedor}}` | 1 | C├│digo proveedor |

---

## 5.6 Calculadora importaci├│n ┬╖ WABA consolidado

### CAL01 ΓÇö `pb_calc_intro_v1`

**BODY:**

```
Bien, Te env├¡o la cotizaci├│n de tu importaci├│n, en el documento podr├ís ver el detalle de los costos.

ΓÜá∩╕Å Nota: Leer T├⌐rminos y Condiciones.

≡ƒÄÑ Video Explicativo:
Γû╢∩╕Å https://youtu.be/H7U-_5wCWd4
```

---

### CAL02 ΓÇö `pb_calc_pdf_v1`

**Tipo:** DOCUMENT

**BODY:**

```
Cotizaci├│n de importaci├│n ΓÇö Calculadora Pro Business.
```

---

### CAL03 ΓÇö `pb_calc_resumen_texto_v1`

**BODY:**

```
≡ƒôè Aqu├¡ te paso el resumen de cu├ínto te saldr├¡a cada modelo y el total de inversi├│n

≡ƒÆ░ El primer pago es el SERVICIO DE IMPORTACI├ôN y se realiza antes del zarpe de buque ≡ƒÜó
```

---

### CAL04 ΓÇö `pb_calc_resumen_img_v1`

**Tipo:** IMAGE

**BODY:**

```
≡ƒôè Resumen detallado de costos y pagos
```

---

## 5.7 Proveedores y operaciones (consolidado)

### P01 ΓÇö `pb_proveedor_llegada_china_v1`

**BODY:**

```
Hola ≡ƒæï {{nombre_cliente}} la carga de tu proveedor {{codigo_proveedor}} aun no llega a nuestro almacen de China, ┬┐tienes alguna noticia por parte de tu proveedor?
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{codigo_proveedor}}` | 2 | C├│digo proveedor |

---

### P02 ΓÇö `pb_proveedor_datos_link_v1`

**BODY:**

```
Hola {{nombre_cliente}} necesitamos los datos de tu proveedor para que nuestro equipo de China se encarge de recibir tu carga.

Por favor ingresa al enlace y coloca los datos del proveedor.

Ingresar aqu├¡: {{link_datos_proveedor}}

{{lista_proveedores}}

Quedo atenta.
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre_cliente}}` | 1 | Nombre cliente |
| `{{link_datos_proveedor}}` | 2 | URL formulario datos proveedor |
| `{{lista_proveedores}}` | 3 | Lista compacta sin saltos de l├¡nea, ej. `Proveedores pendientes: JOLI11-1 ┬╖ JOLI11-2` (`formatListaProveedoresForMeta`) |

---

### P03 ΓÇö `pb_proveedor_inspeccion_manual_v1`

Mensaje armado en `CotizacionProveedorController` ΓÇö usar `{{mensaje}}` cuerpo completo o desglosar cuando congeles el texto.

**BODY:**

```
≡ƒô⌐ Inspecci├│n:

{{mensaje}}

≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{mensaje}}` | 1 | Texto inspecci├│n (sin saltos de l├¡nea; una sola l├¡nea o acortar) |

> Meta rechaza `\n` y `\t` en par├ímetros. Sanitizar en backend (`normalizeTemplateParameterText`).

---

### P04 ΓÇö `pb_general_cliente_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ **Origen:** usos puntuales con texto corto (no `recordatoriosDocumentos` ΓåÆ usar **D05ΓÇôD07**).

**BODY:**

```
{{mensaje}}

≡ƒôï
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{mensaje}}` | 1 | Texto libre **en una sola l├¡nea** (m├íx. ~1024 caracteres pr├ícticos) |

> **No usar** para flujos con listas multil├¡nea, URLs + listas de proveedores ni textos largos (`CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` ΓåÆ usar **P06** / **P07**).

---

### P05 ΓÇö `pb_delivery_whatsapp_v1`

**Tipo:** TEXT ┬╖ **Origen:** `DeliveryController::sendInitialDeliveryFormMessage` ┬╖ Confirmaci├│n tras registrar formulario de delivery Lima.

**BODY:**

```
Hola {{nombre}}.

Gracias por llenar nuestro formulario del consolidado #{{carga}}, le estaremos avisando de nuevos avances.

≡ƒôª
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{nombre}}` | 1 | `Cotizacion::nombre` |
| `{{carga}}` | 2 | `Contenedor::carga` |

> Texto fijo en plantilla Meta; backend solo env├¡a `nombre` y `carga` v├¡a `CoordinacionWhatsappPayload::deliveryWhatsapp()`.

---

### P06 ΓÇö `pb_proveedor_datos_guardado_pendiente_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ **Origen:** `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`tipo_mensaje` = `guardar1`).

Cliente complet├│ datos de al menos un proveedor pero **a├║n quedan pendientes**.

**BODY:**

```
Se registr├│ exitosamente los datos de tu proveedor.

Queda pendiente completar los datos de: {{codigos_pendientes}}

Contacta al vendedor y sube los datos faltantes en el siguiente enlace:

{{link_datos_proveedor}}

Γ£à
```

| Par├ímetro Meta | Orden API | Campo backend |
|----------------|-----------|---------------|
| `{{codigos_pendientes}}` | 1 | C├│digos pendientes compactos, ej. `JOLI11-2 ┬╖ JOLI11-3` (`formatCodigosProveedoresPendientesForMeta`) |
| `{{link_datos_proveedor}}` | 2 | URL formulario (`CoordinacionWhatsappPayload::buildDatosProveedorUrl`) |

**Bitrix / popup:** conservar el `$mensaje` multil├¡nea actual (con vi├▒etas y guiones) en `bitrix_message`; solo el template Meta usa formato compacto.

---

### P07 ΓÇö `pb_proveedor_datos_guardado_completo_v1`

**Tipo:** TEXT ┬╖ **WABA:** consolidado ┬╖ **Origen:** `CotizacionProveedorController::updateContenedorCotizacionProveedoresByUuid` (`tipo_mensaje` = `guardar2`).

Todos los proveedores de la cotizaci├│n ya tienen datos completos.

**BODY:**

```
Se registr├│ exitosamente los datos de tu proveedor.

Gracias por ayudarnos a hacer mejor nuestro trabajo, el equipo de China se contactar├í pronto con tu proveedor.

≡ƒ½í
```

Sin variables.

---

## Tabla resumen para implementaci├│n en Laravel

Cuando tengas el **nombre Meta** y el **ID de plantilla** (o nombre), mapear as├¡:

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

## Notas finales para aprobaci├│n Meta

1. No iniciar ni terminar el body solo con una variable; cerrar con emoji o texto fijo si la ├║ltima l├¡nea termina en `{{ΓÇª}}`.
2. Evitar m├ís de ~10 variables por plantilla (l├¡mite pr├íctico).
3. URLs siempre `https://`.
4. Montos sin s├¡mbolos raros; usar `1234.56` o `1,234.56` consistente.
5. Plantillas duplicadas (E04a/E04b, D03 con/sin fecha) se eligen en PHP seg├║n regla de negocio.
6. Secuencias (E01ΓåÆE02, C01ΓåÆC03ΓåÆC02ΓåÆC04): respetar orden y delay en cola de jobs.

---

*Al registrar en Meta, copia el BODY sin los fences de markdown. Solo cuenta **consolidado**. Actualizar cuando cambie el texto en c├│digo fuente.*
