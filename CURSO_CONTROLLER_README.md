# CursoController - Documentación de Funcionalidad

## Descripción
El `CursoController` ha sido modificado para replicar la funcionalidad del código original de CodeIgniter, implementando una consulta compleja con joins y subconsultas para obtener información detallada de cursos y pedidos.

## Funcionalidades Implementadas

### 1. Método `index()`
Obtiene una lista paginada de cursos con información completa del cliente, pagos y estado.

#### Parámetros de entrada:
- `limit` (int): Número de registros por página (por defecto: 10)
- `page` (int): Número de página (por defecto: 1)
- `search` (string): Término de búsqueda opcional
- `campana` (int): ID de campaña para filtrar
- `estado_pago` (string): Estado de pago para filtrar
- `fecha_inicio` (date): Fecha de inicio para filtrar
- `fecha_fin` (date): Fecha de fin para filtrar

#### Filtros disponibles:
- **Estado de pago**: pendiente, adelanto, pagado, sobrepagado, constancia
- **Campaña**: Filtra por ID de campaña específica
- **Rango de fechas**: Filtra por rango de fechas de registro
- **Búsqueda**: Búsqueda en campos de texto

#### Datos retornados:
- Información básica del pedido de curso
- Datos completos del cliente (entidad, documento, contacto, ubicación)
- Información de pagos (conteo y total)
- Estado del pago calculado automáticamente
- Información de la campaña y tipo de curso
- Datos de ubicación geográfica (distrito, provincia, departamento, país)
- Información adicional (fecha de nacimiento, edad, signo zodiacal)

### 2. Método `filterOptions()`
Proporciona opciones de filtro disponibles para el frontend.

#### Datos retornados:
- **Campañas**: Lista de campañas activas con fechas
- **Estados de pago**: Opciones disponibles para filtrar
- **Tipos de curso**: Virtual (0) y En vivo (1)

## Estructura de la Consulta

La consulta principal incluye:

### Joins principales:
- `pedido_curso` (PC) - Tabla principal
- `pais` (P) - Información del país
- `cliente` (CLI) - Datos del cliente
- `tipo_documento_identidad` (TDI) - Tipo de documento
- `moneda` (M) - Información de moneda
- `usuario` (USR) - Datos del usuario
- `distrito`, `provincia`, `departamento` - Ubicación geográfica

### Subconsultas:
- **Conteo de pagos**: Cuenta pagos con concepto 'ADELANTO'
- **Total de pagos**: Suma total de pagos con concepto 'ADELANTO'

### Cálculo de estado de pago:
```sql
CASE 
    WHEN total_pagos = 0 THEN "pendiente"
    WHEN total_pagos < Ss_Total AND total_pagos > 0 THEN "adelanto"
    WHEN total_pagos = Ss_Total THEN "pagado"
    WHEN total_pagos > Ss_Total THEN "sobrepagado"
    ELSE "pendiente"
END
```

## Formato de Datos

### Fechas:
- Se proporcionan tanto en formato formateado (dd-mm-yyyy) como en formato original
- Se usa el helper `DateHelper` para el formateo

### Estados de pago:
- **pendiente**: Sin pagos realizados
- **adelanto**: Pagos parciales
- **pagado**: Pago completo
- **sobrepagado**: Pago excede el total
- **constancia**: Curso en vivo con fecha fin pasada

## Rutas API

### GET `/api/cursos/`
- Obtiene lista de cursos con filtros y paginación

### GET `/api/cursos/filters/options`
- Obtiene opciones de filtro disponibles

## Comando de Prueba

Se incluye un comando Artisan para probar la funcionalidad:

```bash
# Probar con parámetros por defecto
php artisan test:curso-controller

# Probar con parámetros personalizados
php artisan test:curso-controller --limit=10 --page=1 --campana=1 --estado-pago=pendiente
```

## Dependencias

- **Modelos**: `PedidoCurso`, `Campana`, `Cliente`, `Usuario`, etc.
- **Helpers**: `DateHelper` para formateo de fechas
- **Middleware**: JWT para autenticación

## Notas de Implementación

1. **Autenticación**: El controlador requiere autenticación JWT
2. **Empresa**: Los datos se filtran por la empresa del usuario autenticado
3. **Paginación**: Implementa paginación estándar con offset/limit
4. **Filtros**: Los filtros son opcionales y se aplican condicionalmente
5. **Formato**: Los datos se retornan en formato JSON estructurado

## Ejemplo de Respuesta

```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 10,
        "total": 50
    },
    "headers": {
        "importe_total": {
            "value": 15000.00,
            "label": "Importe Total"
        },
        "total_pedidos": {
            "value": 50,
            "label": "Total Pedidos"
        }
    },
    "filters": {
        "campanas": [...]
    }
}
``` 