# API de Pagos de Cursos - Documentación

## Descripción General

Esta API permite gestionar y consultar los pagos relacionados con cursos en el sistema. Proporciona endpoints para obtener listados de pagos de cursos con filtros avanzados y detalles específicos de cada curso.

## Autenticación

Todos los endpoints requieren autenticación JWT. Incluye el token en el header de autorización:

```
Authorization: Bearer {token}
```

## Endpoints Disponibles

### 1. Lista de Pagos de Cursos

**Endpoint:** `GET /api/carga-consolidada/pagos/cursos`

**Descripción:** Obtiene una lista paginada de cursos con sus pagos asociados.

#### Parámetros de Consulta (Query Parameters)

| Parámetro | Tipo | Requerido | Descripción | Ejemplo |
|-----------|------|-----------|-------------|---------|
| `limit` | integer | No | Número de registros por página (default: 10) | `20` |
| `page` | integer | No | Número de página (default: 1) | `2` |
| `Filtro_Fe_Inicio` | string | No | Fecha de inicio en formato YYYY-MM-DD | `2024-01-01` |
| `Filtro_Fe_Fin` | string | No | Fecha de fin en formato YYYY-MM-DD | `2024-12-31` |
| `campana` | integer | No | ID de la campaña (0 para todas) | `1` |
| `estado_pago` | string | No | Estado del pago (PENDIENTE, ADELANTO, PAGADO, SOBREPAGO) | `ADELANTO` |

#### Ejemplo de Request

```bash
GET /api/carga-consolidada/pagos/cursos?limit=20&page=1&Filtro_Fe_Inicio=2024-01-01&Filtro_Fe_Fin=2024-12-31&campana=1&estado_pago=ADELANTO
```

#### Respuesta Exitosa (200)

```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "index": 1,
      "fecha_registro": "15-01-2024",
      "nombre": "Juan Pérez",
      "telefono": "999888777",
      "tipo": "Curso",
      "campana": "Campaña de Verano 2024",
      "estado_pago": "ADELANTO",
      "estados_disponibles": [
        {
          "value": "PENDIENTE",
          "label": "Pendiente"
        },
        {
          "value": "ADELANTO",
          "label": "Adelanto"
        },
        {
          "value": "PAGADO",
          "label": "Pagado"
        },
        {
          "value": "SOBREPAGO",
          "label": "Sobrepago"
        }
      ],
      "monto_a_pagar": 1500.00,
      "monto_a_pagar_formateado": "1500.00",
      "total_pagado": 500.00,
      "total_pagado_formateado": "500.00",
      "pagos_detalle": [
        {
          "id": 456,
          "monto": 500.00,
          "monto_formateado": "500.00",
          "status": "CONFIRMADO"
        }
      ],
      "note_administracion": "Nota de administración del curso"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 50,
    "from": 1,
    "to": 10
  }
}
```

#### Respuesta de Error (500)

```json
{
  "success": false,
  "message": "Error al obtener pagos de cursos",
  "error": "Detalle del error específico"
}
```

### 2. Detalles de Pagos de un Curso Específico

**Endpoint:** `GET /api/carga-consolidada/pagos/cursos/{idPedidoCurso}`

**Descripción:** Obtiene los detalles completos de los pagos de un curso específico.

#### Parámetros de Ruta (Path Parameters)

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `idPedidoCurso` | integer | Sí | ID del pedido de curso |

#### Ejemplo de Request

```bash
GET /api/carga-consolidada/pagos/cursos/123
```

#### Respuesta Exitosa (200)

```json
{
  "success": true,
  "data": [
    {
      "id": 456,
      "id_pedido_curso": 123,
      "id_concept": 1,
      "monto": "500.00",
      "status": "CONFIRMADO",
      "payment_date": "2024-01-15T10:30:00.000000Z",
      "concepto": {
        "id": 1,
        "name": "ADELANTO",
        "description": "Pago de adelanto"
      }
    }
  ],
  "nota": "Nota de administración del curso",
  "total_a_pagar": 1500.00,
  "total_a_pagar_formateado": "1500.00",
  "total_pagado": 500.00,
  "total_pagado_formateado": "500.00"
}
```

#### Respuesta de Error (404)

```json
{
  "success": false,
  "message": "Error al obtener los detalles de los pagos del curso: Curso no encontrado"
}
```

## Estados de Pago

### Definición de Estados

| Estado | Descripción | Condición |
|--------|-------------|-----------|
| `PENDIENTE` | No se han realizado pagos | `total_pagado = 0` |
| `ADELANTO` | Se han realizado pagos pero no cubren el monto total | `0 < total_pagado < monto_a_pagar` |
| `PAGADO` | Los pagos cubren exactamente el monto total | `total_pagado = monto_a_pagar` |
| `SOBREPAGO` | Los pagos superan el monto total | `total_pagado > monto_a_pagar` |

### Cálculo del Monto a Pagar

El monto a pagar se calcula de la siguiente manera:

```php
$aPagar = ($logistica_final + $impuestos_final) == 0 ? 
    $Ss_Total : ($logistica_final + $impuestos_final);
```

- Si `logistica_final + impuestos_final = 0`, entonces `monto_a_pagar = Ss_Total`
- En caso contrario, `monto_a_pagar = logistica_final + impuestos_final`

## Filtros Disponibles

### Filtros de Fecha

- **Filtro_Fe_Inicio**: Filtra cursos desde esta fecha (inclusive)
- **Filtro_Fe_Fin**: Filtra cursos hasta esta fecha (inclusive)

### Filtros de Campaña

- **campana**: ID de la campaña específica
- Valor `0` muestra todas las campañas

### Filtros de Estado de Pago

- **estado_pago**: Filtra por el estado actual del pago
- Valores válidos: `PENDIENTE`, `ADELANTO`, `PAGADO`, `SOBREPAGO`

## Estructura de Datos

### Campos del Curso

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID del pedido de curso |
| `index` | integer | Índice en la lista paginada |
| `fecha_registro` | string | Fecha de registro (formato: dd-mm-yyyy) |
| `nombre` | string | Nombre del cliente |
| `telefono` | string | Teléfono del cliente |
| `tipo` | string | Tipo de servicio (siempre "Curso") |
| `campana` | string | Nombre de la campaña |
| `estado_pago` | string | Estado actual del pago |
| `monto_a_pagar` | decimal | Monto total a pagar |
| `monto_a_pagar_formateado` | string | Monto formateado con separadores |
| `total_pagado` | decimal | Total pagado hasta el momento |
| `total_pagado_formateado` | string | Total pagado formateado |
| `pagos_detalle` | array | Lista de pagos individuales |
| `note_administracion` | string | Notas de administración |

### Campos de Pagos Detalle

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID del pago |
| `monto` | decimal | Monto del pago |
| `monto_formateado` | string | Monto formateado |
| `status` | string | Estado del pago |

## Paginación

La respuesta incluye información de paginación con los siguientes campos:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `current_page` | integer | Página actual |
| `last_page` | integer | Última página disponible |
| `per_page` | integer | Registros por página |
| `total` | integer | Total de registros |
| `from` | integer | Primer registro de la página |
| `to` | integer | Último registro de la página |

## Códigos de Estado HTTP

| Código | Descripción |
|--------|-------------|
| 200 | OK - Solicitud exitosa |
| 401 | Unauthorized - Token de autenticación inválido |
| 404 | Not Found - Recurso no encontrado |
| 500 | Internal Server Error - Error interno del servidor |

## Ejemplos de Uso

### Ejemplo 1: Obtener todos los cursos con pagos

```bash
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/pagos/cursos" \
  -H "Authorization: Bearer {token}"
```

### Ejemplo 2: Filtrar por fecha y estado

```bash
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/pagos/cursos?Filtro_Fe_Inicio=2024-01-01&Filtro_Fe_Fin=2024-12-31&estado_pago=PAGADO" \
  -H "Authorization: Bearer {token}"
```

### Ejemplo 3: Obtener detalles de un curso específico

```bash
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/pagos/cursos/123" \
  -H "Authorization: Bearer {token}"
```

## Notas Importantes

1. **Autenticación**: Todos los endpoints requieren un token JWT válido
2. **Filtros**: Los filtros son opcionales y se pueden combinar
3. **Paginación**: Por defecto se muestran 10 registros por página
4. **Fechas**: Las fechas deben estar en formato YYYY-MM-DD
5. **Estados**: Los estados de pago se calculan automáticamente según los montos
6. **Empresa**: Los resultados se filtran automáticamente por la empresa del usuario autenticado

## Modelos Relacionados

- **PedidoCurso**: Modelo principal de cursos
- **PedidoCursoPago**: Modelo de pagos de cursos
- **PedidoCursoPagoConcept**: Modelo de conceptos de pago
- **Entidad**: Modelo de entidades (clientes)
- **Campana**: Modelo de campañas
- **Moneda**: Modelo de monedas
- **Usuario**: Modelo de usuarios

## Versión de la API

Esta documentación corresponde a la versión 1.0 de la API de Pagos de Cursos. 