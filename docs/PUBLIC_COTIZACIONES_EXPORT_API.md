# Cotizaciones por contenedor (JSON)

API de **solo lectura** para consultar cotizaciones de un contenedor. Respuesta en JSON.

---

## Inicio rápido

### 1. Postman (recomendado)

Importa la colección incluida:

```
docs/postman/Public_Cotizaciones_Export.postman_collection.json
```

En Postman: **File → Import** → selecciona el archivo.

La colección ya viene configurada con:

| Variable | Valor |
|----------|-------|
| `base_url` | `https://intranetback.probusiness.pe/api` |
| `token` | Token de acceso (incluido en la colección) |
| `idContenedor` | `1` (cámbialo por el ID que necesites) |

Solo ajusta `idContenedor` si aplica y ejecuta **Exportar cotizaciones (JSON)**.

### 2. cURL

```bash
curl -X GET "https://intranetback.probusiness.pe/api/public/carga-consolidada/contenedor/cotizaciones/1/exportar" \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Accept: application/json"
```

> El token está en la variable `token` de la colección Postman.

---

## Request

```
GET https://intranetback.probusiness.pe/api/public/carga-consolidada/contenedor/cotizaciones/{idContenedor}/exportar
```

**Header obligatorio**

```
Authorization: Bearer {token}
Accept: application/json
```

### Parámetros

**Path**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `idContenedor` | number | ID del contenedor |

**Query** (opcionales)

| Parámetro | Default | Descripción |
|-----------|---------|-------------|
| `sort_by` | `id` | Campo para ordenar |
| `sort_order` | `asc` | `asc` o `desc` |
| `estado_coordinacion` | — | Filtra por estado en coordinación. `todos` = sin filtro |
| `estado_china` | — | Filtra por estado en China. `todos` = sin filtro |
| `tipo_cliente` | — | ID del tipo de cliente. `todos` = sin filtro |

---

## Response

### 200 OK

```json
{
  "success": true,
  "total": 1,
  "data": [
    {
      "n": 1,
      "carga": "CARGA-2024-001",
      "fecha_cierre": "15/03/2024",
      "asesor": "Juan Pérez",
      "cod": "COD-001",
      "created_at": "2024-03-01 05:30:00",
      "fecha_de_confirmacion": "2024-03-05 09:00:00",
      "fecha_de_baja": null,
      "razon_de_baja": "",
      "updated_at": "2024-03-10 09:15:00",
      "nombre_cliente": "Empresa ABC S.A.C.",
      "dni_ruc": "20123456789",
      "correo": "cliente@ejemplo.com",
      "whatsapp": "+51987654321",
      "tipo_cliente": "Importador recurrente",
      "volumen": "2.5",
      "volumen_china": "2.3",
      "qty_item": "15",
      "fob": "5000.00",
      "logistica": "1200.00",
      "impuesto": "800.00",
      "tarifa": "450.00",
      "cotizacion": "https://...",
      "estado": "CONFIRMADO"
    }
  ]
}
```

| Campo raíz | Descripción |
|------------|-------------|
| `success` | `true` si la consulta fue exitosa |
| `total` | Cantidad de registros en `data` |
| `data` | Array de cotizaciones |

**Campos de cada item en `data`**

| Campo | Descripción |
|-------|-------------|
| `n` | Número de fila |
| `carga` | Código de la carga |
| `fecha_cierre` | Cierre del contenedor (`dd/mm/yyyy`) |
| `asesor` | Vendedor asignado |
| `cod` | Código de cotización |
| `created_at` | Fecha de creación (`America/Lima`, `Y-m-d H:i:s`) |
| `fecha_de_confirmacion` | Fecha de confirmación (`America/Lima`; `null` si no está confirmada) |
| `fecha_de_baja` | Fecha de baja (`America/Lima`; `null` si activa) |
| `razon_de_baja` | Motivo de baja |
| `updated_at` | Última actualización (`America/Lima`) |
| `nombre_cliente` | Nombre del cliente |
| `dni_ruc` | Documento |
| `correo` | Email |
| `whatsapp` | Teléfono |
| `tipo_cliente` | Tipo de cliente |
| `volumen` | CBM |
| `volumen_china` | CBM China (suma proveedores) |
| `qty_item` | Cantidad de ítems |
| `fob` | Valor FOB |
| `logistica` | Monto logística |
| `impuesto` | Impuestos |
| `tarifa` | Tarifa |
| `cotizacion` | URL del archivo de cotización |
| `estado` | Estado del cotizador |

### Errores

| HTTP | Body | Causa |
|------|------|-------|
| 401 | `{ "message": "No autorizado." }` | Token ausente o incorrecto |
| 429 | `{ "message": "Too Many Attempts." }` | Superaste el límite de peticiones por minuto (30/min por IP, configurable en el servidor) |
| 500 | `{ "message": "..." }` | Error del servidor |


---

## Validar fechas (Lima)

Los timestamps se **guardan en UTC** en el servidor. Esta API los **devuelve en hora Lima** (`America/Lima`).  
En cotizaciones `CONFIRMADO`, `created_at` (fecha de registro) y `fecha_de_confirmacion` suelen ser distintas.

```bash
curl -s -G "https://intranetback.probusiness.pe/api/public/carga-consolidada/contenedor/cotizaciones/ID_CONTENEDOR/exportar" \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Accept: application/json" \
| jq '.data[] | select(.estado == "CONFIRMADO") | {cod, created_at, fecha_de_confirmacion, updated_at}'
```

Ejemplo de salida esperada (horas ya en Lima):

```json
{
  "cod": "COD-001",
  "created_at": "2024-03-01 05:30:00",
  "fecha_de_confirmacion": "2024-03-05 09:00:00",
  "updated_at": "2024-03-10 04:15:00"
}
```

Para QA, cambia el host a la URL de QA si aplica.

---

## Notas

- El token es **solo lectura**: no permite crear, editar ni eliminar datos.
- No compartas el token en repositorios públicos ni en el frontend.
- Si recibes `401`, verifica que el header sea exactamente `Authorization: Bearer {token}` (con espacio después de Bearer).
- Las respuestas se cachean **5 minutos** por contenedor y combinación de filtros. Las peticiones repetidas dentro de ese plazo responden más rápido.
- Límite de **30 peticiones por minuto por IP**