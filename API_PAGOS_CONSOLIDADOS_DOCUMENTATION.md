# API de Pagos Consolidados - Documentación

## Autenticación

Todas las rutas requieren autenticación JWT. Incluye el token en el header:
```
Authorization: Bearer {token}
```

## Endpoints

### 1. Obtener Consolidado de Pagos

**GET** `/api/carga-consolidada/pagos/consolidado`

Obtiene un consolidado de pagos de cotizaciones con filtros avanzados.

#### Parámetros de Query

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `Filtro_Fe_Inicio` | string | No | Fecha de inicio (formato: YYYY-MM-DD) |
| `Filtro_Fe_Fin` | string | No | Fecha de fin (formato: YYYY-MM-DD) |
| `estado` | string | No | Estado de la cotización (PENDIENTE, CONFIRMADO, DECLINADO) |
| `campana` | string | No | Filtro por campaña/carga |
| `estado_pago` | string | No | Estado del pago (PENDIENTE, ADELANTO, PAGADO, SOBREPAGO) |

#### Ejemplo de Request

```bash
GET /api/carga-consolidada/pagos/consolidado?Filtro_Fe_Inicio=2025-01-01&Filtro_Fe_Fin=2025-12-31&estado=CONFIRMADO&estado_pago=PAGADO
```

#### Respuesta Exitosa

```json
{
    "success": true,
    "data": [
        [
            1,
            "01-08-2025",
            "Juan Pérez",
            "12345678",
            "099123456",
            "Consolidado",
            "#CARG001",
            "<select class=\"form-control form-control-sm bg-success text-white\" disabled>...</select>",
            "$ 1500.00",
            "$ 1500.00",
            "<div class=\"nav gap-1\"><button class=\"nav-link p-2 rounded-lg bg-success\">$750.00</button><button class=\"nav-link p-2 rounded-lg bg-success\">$750.00</button></div>",
            "<div class=\"d-flex gap-1\"><div class=\"d-flex\" onclick=\"viewDetailsPagosConsolidado(1,1500,1500,'Juan Pérez')\"><i class=\"fas fa-eye\" style=\"cursor:pointer;\"></i></div></div>"
        ]
    ]
}
```

#### Estructura de la Respuesta

Cada elemento del array `data` contiene:
1. **Índice** (number): Número de fila
2. **Fecha** (string): Fecha formateada (dd-mm-yyyy)
3. **Nombre** (string): Nombre del cliente
4. **Documento** (string): Número de documento
5. **Teléfono** (string): Número de teléfono
6. **Tipo** (string): Siempre "Consolidado"
7. **Carga** (string): Número de carga con "#"
8. **Estado Pago** (HTML): Select con estado y clase CSS
9. **Monto a Pagar** (string): Monto formateado con "$"
10. **Total Pagado** (string): Total pagado formateado con "$"
11. **Detalle Pagos** (HTML): Botones con montos de pagos individuales
12. **Acciones** (HTML): Iconos de ver detalles y notas

#### Estados de Pago

- **PENDIENTE**: Sin pagos realizados
- **ADELANTO**: Pagos parciales realizados
- **PAGADO**: Pago completo realizado
- **SOBREPAGO**: Pago excede el monto requerido

#### Clases CSS por Estado

- `bg-secondary text-white`: PENDIENTE
- `bg-warning text-dark`: ADELANTO
- `bg-success text-white`: PAGADO
- `bg-danger text-white`: SOBREPAGO

#### Respuesta de Error

```json
{
    "success": false,
    "message": "Error al obtener consolidado de pagos",
    "error": "Detalle del error"
}
```

## Filtros Aplicados

### Filtros de Fecha
- `Filtro_Fe_Inicio`: Filtra cotizaciones desde esta fecha
- `Filtro_Fe_Fin`: Filtra cotizaciones hasta esta fecha

### Filtros de Estado
- `estado`: Estado de la cotización
- `estado_pago`: Estado calculado del pago

### Filtros Específicos
- `campana`: Filtra por número de carga específico

## Lógica de Negocio

### Cálculo de Estado de Pago
1. **Monto a Pagar**: `logistica_final + impuestos_final` (si es 0, usa `monto`)
2. **Total Pagado**: Suma de pagos con conceptos "LOGISTICA" o "IMPUESTOS"
3. **Estado**:
   - PENDIENTE: `total_pagos == 0`
   - ADELANTO: `total_pagos_monto < aPagar`
   - PAGADO: `total_pagos_monto == aPagar`
   - SOBREPAGO: `total_pagos_monto > aPagar`

### Filtros de Cotización
- Solo cotizaciones con `estado_cotizador = 'CONFIRMADO'`
- Debe cumplir una de estas condiciones:
  1. Tener pagos registrados (LOGISTICA o IMPUESTOS)
  2. Tener `estado_cliente` no nulo Y contenedor con `estado_china = 'COMPLETADO'`

## Conceptos de Pago

Los pagos se clasifican en dos conceptos:
- **LOGISTICA**: Pagos por servicios logísticos
- **IMPUESTOS**: Pagos de impuestos

## Notas Técnicas

- Los montos se formatean con separador de miles y 2 decimales
- Las fechas se formatean como dd-mm-yyyy
- Los elementos HTML incluyen clases Bootstrap para estilos
- Los iconos usan Font Awesome (fas fa-eye, fas fa-sticky-note)
- Los onclick handlers están preparados para JavaScript del frontend 

### 2. Obtener Detalles de Pagos Consolidados

**GET** `/api/carga-consolidada/pagos/consolidado/{idCotizacion}/detalles`

Obtiene los detalles específicos de pagos para una cotización determinada.

#### Parámetros de Ruta

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `idCotizacion` | integer | Sí | ID de la cotización |

#### Ejemplo de Request

```bash
GET /api/carga-consolidada/pagos/consolidado/123/detalles
```

#### Respuesta Exitosa

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "id_cotizacion": 123,
            "id_concept": 1,
            "monto": 750.00,
            "status": "CONFIRMADO",
            "payment_date": "2025-07-15",
            "voucher_url": "https://example.com/voucher1.pdf",
            "banco": "Banco del Pacífico",
            "is_confirmed": 1,
            "created_at": "2025-07-15T10:30:00.000000Z",
            "updated_at": "2025-07-15T10:30:00.000000Z"
        },
        {
            "id": 2,
            "id_cotizacion": 123,
            "id_concept": 2,
            "monto": 750.00,
            "status": "CONFIRMADO",
            "payment_date": "2025-07-15",
            "voucher_url": "https://example.com/voucher2.pdf",
            "banco": "Banco del Pacífico",
            "is_confirmed": 1,
            "created_at": "2025-07-15T10:30:00.000000Z",
            "updated_at": "2025-07-15T10:30:00.000000Z"
        }
    ],
    "nota": "Cliente VIP - Prioridad alta",
    "cotizacion_inicial_url": "https://example.com/cotizacion_inicial.pdf",
    "cotizacion_final_url": "https://example.com/cotizacion_final.pdf",
    "total_a_pagar": 1500.00,
    "total_a_pagar_formateado": "1500.00",
    "total_pagado": 1500.00,
    "total_pagado_formateado": "1500.00"
}
```

#### Estructura de la Respuesta

- **success** (boolean): Indica si la operación fue exitosa
- **data** (array): Lista de pagos asociados a la cotización
  - **id** (integer): ID del pago
  - **id_cotizacion** (integer): ID de la cotización
  - **id_concept** (integer): ID del concepto de pago (1=LOGISTICA, 2=IMPUESTOS)
  - **monto** (decimal): Monto del pago
  - **status** (string): Estado del pago (CONFIRMADO, PENDIENTE, OBSERVADO)
  - **payment_date** (date): Fecha del pago
  - **voucher_url** (string): URL del comprobante de pago
  - **banco** (string): Banco donde se realizó el pago
  - **is_confirmed** (boolean): Indica si el pago está confirmado
  - **created_at** (datetime): Fecha de creación del registro
  - **updated_at** (datetime): Fecha de última actualización
- **nota** (string): Nota administrativa de la cotización
- **cotizacion_inicial_url** (string): URL de la cotización inicial
- **cotizacion_final_url** (string): URL de la cotización final
- **total_a_pagar** (decimal): Monto total a pagar calculado
- **total_a_pagar_formateado** (string): Monto total a pagar formateado con separadores de miles
- **total_pagado** (decimal): Suma total de todos los pagos realizados
- **total_pagado_formateado** (string): Total pagado formateado con separadores de miles

#### Respuesta de Error

```json
{
    "success": false,
    "message": "Error al obtener los detalles de los pagos consolidados: Detalle del error"
}
```

#### Notas Técnicas

- Los pagos se ordenan por fecha de pago descendente (más recientes primero)
- Solo se incluyen pagos con conceptos LOGISTICA e IMPUESTOS
- Las URLs pueden estar vacías si no se han subido los documentos
- El campo `nota` puede estar vacío si no se ha agregado ninguna nota administrativa