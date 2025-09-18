# Dashboard Ventas Controller - Documentación

## Descripción General
El `DashboardVentasController` proporciona endpoints para obtener métricas y datos de ventas del sistema de carga consolidada, optimizados para dashboards y visualizaciones.

## Endpoints Disponibles

### 1. Resumen de Ventas
**Endpoint:** `GET /api/carga-consolidada/dashboard-ventas/resumen`

Obtiene el resumen general de ventas por contenedor.

**Parámetros de consulta:**
- `fecha_inicio` (opcional): Fecha de inicio en formato Y-m-d
- `fecha_fin` (opcional): Fecha de fin en formato Y-m-d
- `id_vendedor` (opcional): ID del vendedor específico
- `id_contenedor` (opcional): ID del contenedor específico

### 2. Ventas por Vendedor
**Endpoint:** `GET /api/carga-consolidada/dashboard-ventas/por-vendedor`

Obtiene el detalle de ventas agrupado por vendedor.

### 3. Filtros - Contenedores
**Endpoint:** `GET /api/carga-consolidada/dashboard-ventas/filtros/contenedores`

Obtiene la lista de contenedores disponibles para filtros.

### 4. Filtros - Vendedores
**Endpoint:** `GET /api/carga-consolidada/dashboard-ventas/filtros/vendedores`

Obtiene la lista de vendedores disponibles para filtros.

### 5. Evolución por Contenedor
**Endpoint:** `GET /api/carga-consolidada/dashboard-ventas/evolucion/{idContenedor}`

Obtiene la evolución de ventas de un contenedor específico por mes.

### 6. Evolución Total
**Endpoint:** `GET /api/carga-consolidada/dashboard-ventas/evolucion-total`

Obtiene la evolución total de volúmenes para todos los contenedores.

### 7. Cotizaciones Confirmadas por Vendedor por Día 📊

**Endpoint:** `GET /api/carga-consolidada/dashboard-ventas/cotizaciones-confirmadas-por-vendedor-por-dia`

**Descripción:** Obtiene las cotizaciones confirmadas por vendedor agrupadas por día, optimizado para Chart.js. Incluye tanto el conteo de cotizaciones como el volumen CBM.

#### Parámetros de Consulta

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `fecha_inicio` | string | No | Fecha de inicio (Y-m-d). Por defecto: hace 30 días |
| `fecha_fin` | string | No | Fecha de fin (Y-m-d). Por defecto: hoy |
| `id_vendedor` | integer | No | Filtrar por vendedor específico |
| `id_contenedor` | integer | No | Filtrar por contenedor específico |

#### Ejemplo de Uso

```javascript
// Consulta básica - últimos 30 días
fetch('/api/carga-consolidada/dashboard-ventas/cotizaciones-confirmadas-por-vendedor-por-dia')

// Con filtros específicos
fetch('/api/carga-consolidada/dashboard-ventas/cotizaciones-confirmadas-por-vendedor-por-dia?fecha_inicio=2024-09-01&fecha_fin=2024-09-30&id_vendedor=5')
```

#### Estructura de Respuesta

```json
{
  "success": true,
  "data": {
    "chart": {
      "labels": ["18/09/2024", "19/09/2024", "20/09/2024"],
      "datasets": [
        {
          "label": "Juan Pérez (Cotizaciones)",
          "data": [5, 3, 8],
          "backgroundColor": "#FF6384",
          "borderColor": "#FF6384",
          "borderWidth": 2,
          "fill": false,
          "tension": 0.1,
          "yAxisID": "y",
          "type": "line"
        },
        {
          "label": "Juan Pérez (CBM)",
          "data": [125.50, 89.75, 234.25],
          "backgroundColor": "#FF638480",
          "borderColor": "#FF6384",
          "borderWidth": 2,
          "fill": false,
          "tension": 0.1,
          "borderDash": [5, 5],
          "yAxisID": "y1",
          "type": "line"
        }
      ]
    },
    "estadisticas": {
      "total_cotizaciones": 150,
      "total_volumen": 1250.50,
      "total_monto_logistica": 45000.00,
      "total_monto_fob": 125000.00,
      "total_monto_impuestos": 15000.00,
      "promedio_diario": 5.0,
      "total_vendedores": 3,
      "periodo": {
        "inicio": "01/09/2024",
        "fin": "30/09/2024",
        "dias": 30
      }
    },
    "detalle_vendedores": [
      {
        "vendedor": "Juan Pérez",
        "total_cotizaciones": 45,
        "total_volumen": 567.25,
        "total_monto_logistica": 15000.00,
        "promedio_diario": 1.5,
        "dias_activos": 18
      }
    ],
    "datos_detalle": [
      {
        "fecha": "18/09/2024",
        "vendedor": "Juan Pérez",
        "cotizaciones_confirmadas": 5,
        "volumen_confirmado": 125.50,
        "monto_logistica": 3500.00,
        "monto_fob": 8500.00,
        "monto_impuestos": 1200.00
      }
    ]
  }
}
```

#### Implementación con Chart.js

```javascript
async function cargarGraficoCotizaciones() {
    try {
        const response = await fetch('/api/carga-consolidada/dashboard-ventas/cotizaciones-confirmadas-por-vendedor-por-dia');
        const data = await response.json();
        
        if (data.success) {
            const ctx = document.getElementById('chartCotizaciones').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: data.data.chart,
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Cotizaciones Confirmadas y Volumen CBM por Vendedor'
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Fecha'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Cantidad de Cotizaciones'
                            },
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Volumen CBM'
                            },
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error al cargar el gráfico:', error);
    }
}
```

#### Características Técnicas

- **Doble Eje Y**: 
  - Eje izquierdo (y): Cantidad de cotizaciones
  - Eje derecho (y1): Volumen CBM
- **Diferenciación Visual**:
  - Líneas sólidas para cotizaciones
  - Líneas punteadas para volumen CBM
  - Transparencia en el fondo de las líneas CBM
- **Colores Automáticos**: Sistema de colores rotativo para múltiples vendedores
- **Datos Completos**: Incluye estadísticas adicionales y datos detallados

#### Casos de Uso

1. **Dashboard Ejecutivo**: Visualizar rendimiento diario de vendedores
2. **Análisis de Tendencias**: Identificar patrones de ventas por período
3. **Comparación de Vendedores**: Evaluar performance relativa
4. **Planificación**: Usar datos históricos para proyecciones
5. **Reportes Gerenciales**: Generar informes con métricas clave

#### Notas Importantes

- Si no se proporcionan fechas, se usan los últimos 30 días por defecto
- Los datos se basan en `fecha_confirmacion` de las cotizaciones
- Solo se incluyen cotizaciones con `estado_cotizador = 'CONFIRMADO'`
- Los volúmenes se obtienen de la tabla `contenedor_consolidado_cotizacion_proveedores`
- Todos los montos están redondeados a 2 decimales

---

## Middleware y Autenticación

Todos los endpoints requieren autenticación JWT mediante el middleware `jwt.auth`.

## Manejo de Errores

Todos los endpoints devuelven una estructura consistente de errores:

```json
{
  "success": false,
  "message": "Descripción del error",
  "error": "Detalles técnicos del error"
}
```

Los errores se registran automáticamente en los logs de Laravel para debugging.
