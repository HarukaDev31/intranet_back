# Dashboard de Ventas - Documentación de API

## Descripción General
API para el dashboard de ventas de carga consolidada. Proporciona endpoints para visualizar métricas de ventas, volúmenes y rendimiento por contenedor y vendedor.

## Base URL
```
/api/carga-consolidada/dashboard-ventas
```

## Endpoints

### 1. Resumen de Ventas
Obtiene un resumen general de ventas por contenedor.

```
GET /resumen
```

#### Parámetros Query
- `fecha_inicio` (opcional): Fecha inicial para filtrar (YYYY-MM-DD)
- `fecha_fin` (opcional): Fecha final para filtrar (YYYY-MM-DD)
- `id_vendedor` (opcional): ID del vendedor para filtrar

#### Respuesta
```json
{
  "success": true,
  "data": [
    {
      "id_contenedor": 1,
      "carga": "C001",
      "fecha_zarpe": "15/09/2025",
      "vendedor": "Juan Pérez",
      "total_clientes": 10,
      "volumenes": {
        "china": 100.50,
        "total": 120.30,
        "vendido": 80.20,
        "pendiente": 40.10
      },
      "totales": {
        "impuestos": 5000.00,
        "logistica": 8000.00,
        "fob": 50000.00
      },
      "metricas": {
        "porcentaje_avance": 66.67,
        "meta_volumen": 0,
        "meta_clientes": 0
      }
    }
  ],
  "totales": {
    "total_clientes": 10,
    "volumenes": {
      "china": 100.50,
      "total": 120.30,
      "vendido": 80.20,
      "pendiente": 40.10
    },
    "totales": {
      "impuestos": 5000.00,
      "logistica": 8000.00,
      "fob": 50000.00
    }
  }
}
```

### 2. Ventas por Vendedor
Obtiene estadísticas detalladas por vendedor.

```
GET /por-vendedor
```

#### Parámetros Query
- `fecha_inicio` (opcional): Fecha inicial para filtrar (YYYY-MM-DD)
- `fecha_fin` (opcional): Fecha final para filtrar (YYYY-MM-DD)

#### Respuesta
```json
{
  "success": true,
  "data": [
    {
      "id_vendedor": 1,
      "vendedor": "Juan Pérez",
      "metricas": {
        "total_cotizaciones": 20,
        "cotizaciones_confirmadas": 15,
        "porcentaje_efectividad": 75.00
      },
      "volumenes": {
        "total": 150.30,
        "vendido": 120.20,
        "pendiente": 30.10
      },
      "totales": {
        "logistica": 12000.00,
        "fob": 75000.00
      }
    }
  ]
}
```

### 3. Filtro de Contenedores
Obtiene la lista de contenedores disponibles para filtrar.

```
GET /filtros/contenedores
```

#### Parámetros Query
- `fecha_inicio` (opcional): Fecha inicial para filtrar (YYYY-MM-DD)
- `fecha_fin` (opcional): Fecha final para filtrar (YYYY-MM-DD)

#### Respuesta
```json
{
  "success": true,
  "data": [
    {
      "value": 1,
      "label": "C001 - 15/09/2025",
      "carga": "C001",
      "fecha_zarpe": "15/09/2025"
    }
  ]
}
```

### 4. Filtro de Vendedores
Obtiene la lista de vendedores disponibles para filtrar.

```
GET /filtros/vendedores
```

#### Parámetros Query
- `fecha_inicio` (opcional): Fecha inicial para filtrar (YYYY-MM-DD)
- `fecha_fin` (opcional): Fecha final para filtrar (YYYY-MM-DD)
- `id_contenedor` (opcional): ID del contenedor para filtrar

#### Respuesta
```json
{
  "success": true,
  "data": [
    {
      "value": 1,
      "label": "Juan Pérez",
      "total_cotizaciones": 20,
      "volumen_total": 150.30
    }
  ]
}
```

### 5. Evolución Total de Volúmenes
Obtiene la evolución de volúmenes para todos los contenedores, útil para gráficas de tendencia.

```
GET /evolucion-total
```

#### Parámetros Query
- `fecha_inicio` (opcional): Fecha inicial para filtrar (YYYY-MM-DD)
- `fecha_fin` (opcional): Fecha final para filtrar (YYYY-MM-DD)

#### Respuesta
```json
{
  "success": true,
  "data": {
    "evolucion": [
      {
        "contenedor": {
          "id": 1,
          "carga": "C001",
          "fecha": "15/09/2025"
        },
        "volumenes": {
          "china": 100.50,
          "vendido": 80.20,
          "pendiente": 40.10,
          "total": 120.30
        }
      }
    ],
    "totales": {
      "volumenes": {
        "china": 500.50,
        "vendido": 400.20,
        "pendiente": 200.10,
        "total": 600.30
      },
      "porcentajes": {
        "vendido": 66.67,
        "pendiente": 33.33
      }
    },
    "promedios": {
      "volumenes": {
        "china": 100.10,
        "vendido": 80.04,
        "pendiente": 40.02,
        "total": 120.06
      }
    },
    "total_contenedores": 5
  }
}
```

#### Notas
- Los contenedores se ordenan por fecha de zarpe ascendente
- Se excluyen contenedores sin fecha de zarpe
- Se excluyen contenedores con empresa = 1
- Todos los valores numéricos están redondeados a 2 decimales
- Los volúmenes se calculan:
  - China: Total de volumen china de cotizaciones confirmadas
  - Vendido: Total de volumen de cotizaciones confirmadas
  - Pendiente: Total de volumen de cotizaciones no confirmadas
  - Total: Suma de volumen vendido + pendiente
- Los porcentajes se calculan sobre el volumen total
- Los promedios se calculan por contenedor

## Notas Técnicas

### Formatos
- Todas las fechas en respuestas se devuelven en formato "dd/mm/yyyy"
- Todos los valores monetarios tienen 2 decimales
- Todos los volúmenes tienen 2 decimales

### Filtros
- Los filtros de fecha son inclusivos (incluyen el día inicial y final)
- Los filtros son opcionales y se pueden combinar
- Si no se proporcionan fechas, se muestran todos los registros disponibles

### Seguridad
- Todos los endpoints requieren autenticación JWT
- Se requiere el middleware 'jwt.auth'

### Errores
Todas las respuestas de error siguen este formato:
```json
{
  "success": false,
  "message": "Mensaje descriptivo del error",
  "error": "Detalles técnicos del error (solo en desarrollo)"
}
```

## Códigos de Estado HTTP
- 200: Éxito
- 400: Error en la solicitud
- 401: No autorizado
- 403: Prohibido
- 404: No encontrado
- 500: Error interno del servidor
