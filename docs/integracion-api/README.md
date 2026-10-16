# API de integración Probusiness — guía rápida

Con esta API puedes **crear, listar, actualizar, confirmar y eliminar cotizaciones** de tu organización y **actualizar el código de proveedor** desde tu sistema.

| | |
|---|---|
| Base URL (producción) | `https://intranetback.probusiness.pe/api/integration/v1` |
| Base URL (pruebas) | `https://qa-intranetback.probusiness.pe/api/integration/v1` |
| Formato | JSON (la creación usa `multipart/form-data` por el PDF) |
| Colección Postman | `probusiness-integracion.postman_collection.json` (misma carpeta) |

## 1. Autenticación

Te entregamos **un token por organización**. Envíalo en cada request:

```
Authorization: Bearer pbk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

- Es secreto: guárdalo en tu servidor (variable de entorno). **Nunca** lo pongas en un frontend ni en un repositorio.
- Solo ves y modificas **datos de tu organización**.
- Si crees que se filtró, pídenos rotarlo: el anterior deja de funcionar al instante.

## 2. Límites

Cada token tiene un límite por minuto y una cuota diaria. Cada respuesta trae:

`X-RateLimit-Limit` · `X-RateLimit-Remaining` · `X-Quota-Remaining`

Si te pasas recibes **429** con `Retry-After` (segundos). Reintenta pasado ese tiempo.

## 3. Endpoints

Cada endpoint requiere un *scope* (permiso) habilitado en tu token. Sin él: **403**.

| Método y ruta | Scope | Para qué |
|---|---|---|
| `GET /cotizaciones-resumen` | `cotizaciones-resumen:read` | Listar |
| `POST /cotizaciones-resumen` | `cotizaciones-resumen:create` | Crear (JSON + PDF de respaldo) |
| `PUT /cotizaciones-resumen/{id}` | `cotizaciones-resumen:update` | Actualizar cliente, proveedores, costos y PDF (solo si está `COTIZADO`) |
| `PUT /cotizaciones-resumen/{id}/estado` | `cotizaciones-resumen:update-state` | Confirmar o volver a cotizado |
| `DELETE /cotizaciones-resumen/{id}` | `cotizaciones-resumen:delete` | Eliminar (solo si está `COTIZADO`) |
| `PUT /proveedores/{id}/code-supplier` | `proveedores:update-code` | Cambiar el código de proveedor |

### 3.1 Listar — `GET /cotizaciones-resumen`

Filtros (query, todos opcionales): `estado` (`COTIZADO`/`CONFIRMADO`), `fecha_inicio` y `fecha_fin` (`YYYY-MM-DD`), `id_contenedor`, `search` (nombre, documento o teléfono), `page`, `per_page` (máx. 50).

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "$BASE/cotizaciones-resumen?estado=CONFIRMADO&per_page=20"
```

Respuesta `200`: `data[]` con `id`, `estado`, `id_contenedor`, `cliente`, totales y `proveedores[]` (con `id`, `code_supplier`, `productos`, `cbm_total`, `costos[]`), más `pagination`.

### 3.2 Crear — `POST /cotizaciones-resumen`

`multipart/form-data` con dos campos:

- `archivo`: **PDF** de respaldo (obligatorio, máx. 10 MB). Queda asociado a la cotización. Se rechaza si no es un PDF real o si contiene JavaScript/archivos embebidos.
- `payload`: el JSON de abajo, **como texto**.

```json
{
  "id_contenedor": 183,
  "cliente": {
    "nombre": "Juan Pérez",
    "whatsapp": "59171234567",
    "documento": "1234567",
    "tipo_documento": "ID",
    "correo": "juan@correo.com"
  },
  "proveedores": [
    {
      "productos": "Bombas de gasolina",
      "cbm_total": 5.3,
      "cbm_imo": 0,
      "peso_total": 800,
      "qty_cajas": 40,
      "unidades": 5800,
      "incoterm": "FOB",
      "costos": [
        { "concepto": "Valor FOB Mercadería", "valor": 17430 },
        { "concepto": "Transporte marítimo y terrestre", "valor": 1166 }
      ]
    }
  ],
  "descuento": 0
}
```

| Campo | Obligatorio | Notas |
|---|---|---|
| `id_contenedor` | sí | Consolidado de **tu** organización |
| `cliente.nombre` | sí | Máx. 150 caracteres |
| `cliente.whatsapp` / `documento` / `tipo_documento` (`ID`\|`RUC`) / `correo` | no | |
| `proveedores[]` | sí (1–50) | |
| `proveedores[].productos` | sí | Máx. 500 caracteres |
| `proveedores[].cbm_total` | sí | ≥ 0. `cbm_imo` no puede superarlo |
| `proveedores[].peso_total`, `qty_cajas`, `unidades`, `incoterm` | no | |
| `proveedores[].costos[]` | no (máx. 50) | `concepto` + `valor` (USD). Opcionales: `valor_bs`, `tasa_cambio` |

Envía **solo** estos campos; cualquier otro devuelve `422`. El vendedor lo asigna el sistema (no se envía).

**Recomendado:** agrega el header `Idempotency-Key: <texto único de 8–64 caracteres>`. Si reintentas con la misma clave (p. ej. por un timeout) recibes la misma respuesta con `Idempotent-Replay: true` y **no se duplica**.

```bash
curl -X POST "$BASE/cotizaciones-resumen" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: pedido-12345" \
  -F "archivo=@respaldo.pdf;type=application/pdf" \
  -F 'payload={"id_contenedor":183,"cliente":{"nombre":"Juan Pérez"},"proveedores":[{"productos":"Bombas","cbm_total":5.3}]}'
```

Respuesta `201`:

```json
{ "success": true, "message": "Cotización registrada",
  "data": { "id": 10892, "estado": "COTIZADO",
            "proveedores": [ { "id": 5150, "productos": "Bombas" } ],
            "archivo": { "nombre_original": "respaldo.pdf", "sha256": "…", "bytes": 46 } } }
```

La cotización nace en `COTIZADO` y **sin** código de proveedor: el código se asigna al confirmarla.

#### Conceptos de costo válidos (organizaciones de Ecuador)

El `concepto` de cada costo se clasifica por su texto. Usa estos nombres (los que ya maneja Ecuador) para que los totales de la cotización salgan bien:

| Concepto recomendado | Se suma a | Se reconoce si el texto contiene |
|---|---|---|
| `Valor de Mercadería` | **FOB** | "mercader", "mercanc", "fob" o "exw" |
| `Costos de Transferencia e ISD (Aprox)` | **ISD** | "isd" o "salida de divisa" |
| `Servicios de Importación` | **Logística** | "servicio" + "import", o "logist" |
| `Logística Internacional` | **Logística** | "logist" |
| `Tributos e Impuestos Aduaneros` | **Impuestos** | "impuest", "tribut" o "aduana" |

- Cualquier otro concepto (por ejemplo flete, seguro o una transferencia sin ISD) **se guarda pero no suma** a FOB, ISD, Logística ni Impuestos.
- Si un texto encaja en varias reglas, gana este orden: ISD, FOB, Impuestos, Logística.
- `valor` va en **USD**. (`valor_bs` y `tasa_cambio` solo aplican a organizaciones de Bolivia.)
- Los costos de **todos** los proveedores se **suman** en los totales: escribe cada costo una sola vez (los de toda la cotización, como ISD, logística e impuestos, van en el primer proveedor).

#### Ejemplo con varios proveedores (Ecuador)

Tres proveedores; el FOB de cada uno va en su proveedor y los costos de toda la cotización en el primero. Totales resultantes: FOB 3 078,00 · ISD 273,90 · Logística 1 800,00 · Impuestos 542,34.

```json
{
  "id_contenedor": 180,
  "cliente": {
    "nombre": "Gladys Cecilia Flores",
    "whatsapp": "593983939230",
    "documento": "1707878011001",
    "tipo_documento": "RUC",
    "correo": "cliente@correo.com"
  },
  "proveedores": [
    {
      "productos": "Pelador de verduras",
      "cbm_total": 1.2,
      "peso_total": 150,
      "qty_cajas": 12,
      "unidades": 300,
      "incoterm": "FOB",
      "costos": [
        { "concepto": "Valor de Mercadería", "valor": 1500.00 },
        { "concepto": "Costos de Transferencia e ISD (Aprox)", "valor": 273.90 },
        { "concepto": "Servicios de Importación", "valor": 1800.00 },
        { "concepto": "Tributos e Impuestos Aduaneros", "valor": 542.34 }
      ]
    },
    {
      "productos": "Hilos de coser",
      "cbm_total": 0.46,
      "qty_cajas": 6,
      "unidades": 600,
      "incoterm": "FOB",
      "costos": [
        { "concepto": "Valor de Mercadería", "valor": 626.52 }
      ]
    },
    {
      "productos": "Máquina eléctrica portátil",
      "cbm_total": 2.3,
      "peso_total": 420,
      "qty_cajas": 8,
      "unidades": 40,
      "incoterm": "FOB",
      "costos": [
        { "concepto": "Valor de Mercadería", "valor": 951.48 }
      ]
    }
  ],
  "descuento": 0
}
```

La respuesta trae un `id` por proveedor, en el mismo orden en que los enviaste.

### 3.3 Actualizar — `PUT /cotizaciones-resumen/{id}`

Corrige una cotización **sin borrarla**: cliente, CBM, peso, unidades, costos y (opcional) el PDF. Solo si está en `COTIZADO`; si ya está confirmada responde `422` (vuélvela a `COTIZADO` con 3.4 y reintenta).

Es un **reemplazo completo** del contenido: envía cliente, **todos** los proveedores y **todos** sus costos tal como deben quedar (mismo formato que al crear).

- Para **conservar** un proveedor (y su código) incluye su `id` (el que te devolvió el POST). Un proveedor sin `id` se crea nuevo.
- Un proveedor existente que **no** envíes se elimina, salvo que ya tenga código asignado (esos se conservan).
- El `id` debe ser de esa cotización; si no, `422`.
- `id_contenedor` es opcional y debe ser el mismo de la cotización (el consolidado no se puede cambiar). El vendedor tampoco.
- El PDF es **opcional**: si no lo envías se conserva el actual; si lo envías, reemplaza al anterior (mismas validaciones que al crear).

**Sin PDF** — JSON directo:

```bash
curl -X PUT "$BASE/cotizaciones-resumen/10892" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"cliente":{"nombre":"Juan Pérez"},
       "proveedores":[{"id":5150,"productos":"Bombas","cbm_total":5.5,
         "costos":[{"concepto":"Valor de Mercadería","valor":18000}]}]}'
```

**Con PDF** — `multipart/form-data` enviado como **POST con el campo `_method=PUT`** (PHP no lee multipart en un PUT real), con `payload` (texto) y `archivo`:

```bash
curl -X POST "$BASE/cotizaciones-resumen/10892" \
  -H "Authorization: Bearer $TOKEN" \
  -F "_method=PUT" \
  -F "archivo=@respaldo-corregido.pdf;type=application/pdf" \
  -F 'payload={"cliente":{"nombre":"Juan Pérez"},"proveedores":[{"id":5150,"productos":"Bombas","cbm_total":5.5}]}'
```

Respuesta `200`: `data` con `id`, `estado` y `proveedores[]` (`id`, `productos`, `code_supplier`); si enviaste PDF, también `archivo` (`nombre_original`, `sha256`, `bytes`).

### 3.4 Cambiar estado — `PUT /cotizaciones-resumen/{id}/estado`

```bash
curl -X PUT "$BASE/cotizaciones-resumen/10892/estado" \
  -H "Authorization: Bearer $TOKEN" -d "estado=CONFIRMADO"
```

- `estado`: `CONFIRMADO` o `COTIZADO`.
- Al **confirmar** se asignan los códigos de proveedor y se devuelven en `data.proveedores[].code_supplier`.
- Volver a `COTIZADO` mantiene los códigos ya asignados.

### 3.5 Eliminar — `DELETE /cotizaciones-resumen/{id}`

Solo cotizaciones en `COTIZADO`. Una confirmada responde `422` (primero devuélvela a `COTIZADO`). Es una baja lógica: deja de aparecer en el listado.

### 3.6 Actualizar código de proveedor — `PUT /proveedores/{id}/code-supplier`

```bash
curl -X PUT "$BASE/proveedores/5150/code-supplier" \
  -H "Authorization: Bearer $TOKEN" -d "code_supplier=BOL2-JUPE1-1"
```

Reglas del código: letras **mayúsculas**, números y guiones (`ABC1-XYZ2-1`), 3–60 caracteres. No puede existir en **ningún** otro proveedor del sistema; si lo usa una cotización confirmada no se puede reutilizar (`409`).

## 4. Errores

Todas las respuestas de error traen `{ "success": false, "message": "…" }` (y `errors` en validaciones).

| HTTP | Significado |
|---|---|
| `401` | Token ausente, inválido o revocado |
| `403` | El token no tiene el scope, o la integración está deshabilitada |
| `404` | El recurso no existe **para tu organización** (también si es de otra) |
| `409` | Conflicto (p. ej. código de proveedor ya en uso) |
| `422` | Datos inválidos o regla de negocio (campo extra, PDF no válido, eliminar una confirmada…) |
| `429` | Límite por minuto o cuota diaria excedidos (`Retry-After`) |
| `500` | Error nuestro: reintenta con la misma `Idempotency-Key` y avísanos si persiste |

## 5. Flujo típico

1. `POST /cotizaciones-resumen` → guarda el `id` (estado `COTIZADO`).
   Si corriges algo, `PUT /cotizaciones-resumen/{id}` mientras siga en `COTIZADO`.
2. `PUT /cotizaciones-resumen/{id}/estado` con `CONFIRMADO` → recibes los `code_supplier`.
3. (Opcional) `PUT /proveedores/{id}/code-supplier` si necesitas otro código (debe ser único).
4. `GET /cotizaciones-resumen` cuando quieras consultar el estado.
