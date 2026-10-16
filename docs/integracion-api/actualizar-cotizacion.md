# Actualizar una cotización — `PUT /cotizaciones-resumen/{id}`

Corrige una cotización **sin borrarla**: cliente, CBM, peso, unidades, costos y (opcional) el PDF de respaldo.

| | |
|---|---|
| Base URL (producción) | `https://intranetback.probusiness.pe/api/integration/v1` |
| Base URL (pruebas) | `https://qa-intranetback.probusiness.pe/api/integration/v1` |
| Autenticación | `Authorization: Bearer pbk_...` |
| Scope requerido | `cotizaciones-resumen:update` (sin él: `403`) |
| Colección Postman | `actualizar-cotizacion.postman_collection.json` |

## Reglas

- Solo si la cotización está en **`COTIZADO`**. Si ya está confirmada responde `422`: vuélvela a `COTIZADO` (`PUT /cotizaciones-resumen/{id}/estado`) y reintenta.
- Es un **reemplazo completo**: envía el cliente, **todos** los proveedores y **todos** sus costos tal como deben quedar (mismo formato que al crear).
- Para **conservar** un proveedor (y su código) incluye su `id` (el que devolvió el POST de creación). Un proveedor sin `id` se crea nuevo.
- Un proveedor existente que **no** envíes se elimina, salvo que ya tenga código asignado (esos se conservan).
- El `id` de un proveedor debe ser de esa cotización; si no, `422`.
- `id_contenedor` es opcional y debe ser el mismo de la cotización: el consolidado no se puede cambiar. El vendedor tampoco.
- El **PDF es opcional**: si no lo envías se conserva el actual; si lo envías reemplaza al anterior (mismas validaciones que al crear: PDF real, máx. 10 MB, sin JavaScript ni archivos embebidos).
- Solo se aceptan los campos documentados; cualquier otro devuelve `422`.
- Los conceptos de costo se clasifican igual que al crear (ver README, "Conceptos de costo válidos").

## Opción A — sin PDF (JSON directo)

`PUT /cotizaciones-resumen/{id}` con `Content-Type: application/json`:

```bash
curl -X PUT "$BASE/cotizaciones-resumen/10892" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cliente": { "nombre": "Juan Pérez", "whatsapp": "59171234567" },
    "proveedores": [
      {
        "id": 5150,
        "productos": "Bombas de gasolina",
        "cbm_total": 5.5,
        "peso_total": 800,
        "qty_cajas": 40,
        "unidades": 5800,
        "incoterm": "FOB",
        "costos": [
          { "concepto": "Valor FOB Mercadería", "valor": 18000 }
        ]
      }
    ],
    "descuento": 0
  }'
```

## Opción B — con PDF nuevo (multipart)

PHP no lee `multipart/form-data` en un PUT real, así que se envía como **`POST` con el campo `_method=PUT`**. Campos: `payload` (el mismo JSON, **como texto**) y `archivo` (PDF).

```bash
curl -X POST "$BASE/cotizaciones-resumen/10892" \
  -H "Authorization: Bearer $TOKEN" \
  -F "_method=PUT" \
  -F "archivo=@respaldo-corregido.pdf;type=application/pdf" \
  -F 'payload={"cliente":{"nombre":"Juan Pérez"},"proveedores":[{"id":5150,"productos":"Bombas de gasolina","cbm_total":5.5}]}'
```

## Campos del payload

| Campo | Obligatorio | Notas |
|---|---|---|
| `cliente.nombre` | sí | Máx. 150 caracteres |
| `cliente.whatsapp` / `documento` / `tipo_documento` (`ID`\|`RUC`) / `correo` | no | |
| `proveedores[]` | sí (1–50) | Lista completa tal como debe quedar |
| `proveedores[].id` | no | Id del proveedor a conservar. Sin `id` se crea uno nuevo |
| `proveedores[].productos` | sí | Máx. 500 caracteres |
| `proveedores[].cbm_total` | sí | ≥ 0. `cbm_imo` no puede superarlo |
| `proveedores[].cbm_imo`, `peso_total`, `qty_cajas`, `unidades`, `incoterm` | no | |
| `proveedores[].costos[]` | no (máx. 50) | `concepto` + `valor` (USD) obligatorios. Opcionales: `valor_bs`, `tasa_cambio` (solo Bolivia) |
| `descuento` | no | ≥ 0 |
| `id_contenedor` | no | Si se envía, debe ser el actual |

## Respuesta `200`

```json
{
  "success": true,
  "message": "Cotización actualizada",
  "data": {
    "id": 10892,
    "estado": "COTIZADO",
    "proveedores": [
      { "id": 5150, "productos": "Bombas de gasolina", "code_supplier": null }
    ],
    "archivo": { "nombre_original": "respaldo-corregido.pdf", "sha256": "…", "bytes": 46210 }
  }
}
```

`archivo` solo aparece si enviaste un PDF.

## Errores

| HTTP | Cuándo |
|---|---|
| `401` | Token ausente, inválido o revocado |
| `403` | El token no tiene el scope `cotizaciones-resumen:update` |
| `404` | La cotización no existe **para tu organización** (también si es de otra) |
| `422` | Datos inválidos: campo no permitido, proveedor ajeno, costo sin `concepto`/`valor`, `id_contenedor` distinto, PDF no válido, o la cotización no está en `COTIZADO` |
| `429` | Límite por minuto o cuota diaria excedidos (`Retry-After`) |
| `500` | Error nuestro: reintenta y avísanos si persiste |
