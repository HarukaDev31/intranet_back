# Endpoint de Opciones de Filtro - Productos

## Descripción
Endpoint para obtener opciones de filtro para la tabla de productos importados desde Excel, incluyendo cargas desde la tabla `carga_consolidada_contenedor`.

## Endpoint
```
GET /api/base-datos/productos/filter-options
```

## Autenticación
Requiere token JWT válido en el header:
```
Authorization: Bearer {token}
```

## Respuesta Exitosa

### Estructura de la respuesta:
```json
{
    "status": "success",
    "data": {
        "cargas": [
            "10",
            "11", 
            "12",
            "13",
            "14"
        ],
        "rubros": [
            "ACCESORIOS TACTICOS",
            "ALMOHADA CERVICAL",
            "AUTOMOTRIZ",
            "BOTELLAS",
            "CERRAJERIA"
        ],
        "tipos_producto": [
            "LIBRE"
        ],
        "tipos": [
            "LIBRE"
        ],
        "contenedores": [
            58
        ]
    }
}
```

### Campos de la respuesta:

#### `cargas` (Array)
- **Fuente**: Tabla `carga_consolidada_contenedor`
- **Campo**: `carga`
- **Descripción**: Lista de cargas únicas disponibles para filtrado
- **Orden**: Alfabético ascendente

#### `rubros` (Array)
- **Fuente**: Tabla `productos_importados_excel`
- **Campo**: `rubro`
- **Descripción**: Lista de rubros/categorías únicos de productos
- **Orden**: Alfabético ascendente

#### `tipos_producto` (Array)
- **Fuente**: Tabla `productos_importados_excel`
- **Campo**: `tipo_producto`
- **Descripción**: Lista de tipos de producto únicos
- **Orden**: Alfabético ascendente

#### `tipos` (Array)
- **Fuente**: Tabla `productos_importados_excel`
- **Campo**: `tipo`
- **Descripción**: Lista de tipos únicos (LIBRE/RESTRINGIDO)
- **Orden**: Alfabético ascendente

#### `contenedores` (Array)
- **Fuente**: Tabla `productos_importados_excel`
- **Campo**: `idContenedor`
- **Descripción**: Lista de IDs de contenedores únicos
- **Orden**: Numérico ascendente
- **Nota**: Los productos ahora incluyen la información completa del contenedor a través de la relación `contenedor`

## Respuesta de Error

### Error de autenticación:
```json
{
    "status": "error",
    "message": "Token no válido"
}
```

### Error interno:
```json
{
    "status": "error",
    "message": "Error al obtener opciones de filtro: {mensaje_del_error}"
}
```

## Ejemplo de Uso

### cURL
```bash
curl -X GET "http://localhost:8000/api/base-datos/productos/filter-options" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json"
```

### JavaScript (Fetch)
```javascript
const response = await fetch('/api/base-datos/productos/filter-options', {
    method: 'GET',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    }
});

const data = await response.json();
console.log('Opciones de filtro:', data.data);
```

### JavaScript (Axios)
```javascript
const response = await axios.get('/api/base-datos/productos/filter-options', {
    headers: {
        'Authorization': 'Bearer ' + token
    }
});

console.log('Opciones de filtro:', response.data.data);
```

## Ejemplos de Uso con Paginación

### cURL con Paginación
```bash
# Primera página con 5 productos
curl -X GET "http://localhost:8000/api/base-datos/productos?page=1&limit=5" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json"

# Segunda página con 10 productos
curl -X GET "http://localhost:8000/api/base-datos/productos?page=2&limit=10" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json"
```

### JavaScript (Fetch) con Paginación
```javascript
// Obtener primera página con 10 productos
const response = await fetch('/api/base-datos/productos?page=1&limit=10', {
    method: 'GET',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    }
});

const data = await response.json();
console.log('Productos:', data.data);
console.log('Paginación:', data.pagination);

// Navegar a la siguiente página
if (data.pagination.has_more_pages) {
    const nextPage = await fetch(data.pagination.next_page_url, {
        headers: {
            'Authorization': 'Bearer ' + token
        }
    });
    const nextData = await nextPage.json();
    console.log('Siguiente página:', nextData.data);
}
```

### JavaScript (Axios) con Paginación
```javascript
// Obtener productos paginados
const response = await axios.get('/api/base-datos/productos', {
    params: {
        page: 1,
        limit: 5
    },
    headers: {
        'Authorization': 'Bearer ' + token
    }
});

console.log('Productos:', response.data.data);
console.log('Total de productos:', response.data.pagination.total);
console.log('Páginas disponibles:', response.data.pagination.last_page);

// Función para navegar entre páginas
async function getPage(page, limit = 10) {
    const response = await axios.get('/api/base-datos/productos', {
        params: { page, limit },
        headers: { 'Authorization': 'Bearer ' + token }
    });
    return response.data;
}
```

## Implementación

### Modelo: `ProductoImportadoExcel` (Relación agregada)
```php
/**
 * Relación con CargaConsolidadaContenedor
 */
public function contenedor()
{
    return $this->belongsTo(CargaConsolidadaContenedor::class, 'idContenedor', 'id');
}
```

### Modelo: `CargaConsolidadaContenedor`
```php
<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;

class CargaConsolidadaContenedor extends Model
{
    protected $table = 'carga_consolidada_contenedor';
    
    protected $fillable = [
        'carga'
    ];

    /**
     * Obtener todas las cargas únicas para filtros
     */
    public static function getCargasUnicas()
    {
        return self::select('carga')
            ->whereNotNull('carga')
            ->where('carga', '!=', '')
            ->distinct()
            ->orderBy('carga')
            ->pluck('carga')
            ->toArray();
    }
}
```

### Controlador: `ProductosController`
```php
/**
 * Obtener productos con campo carga_contenedor incluido
 */
public function index()
{
    try {
        $productos = ProductoImportadoExcel::with('contenedor')
            ->get()
            ->map(function ($producto) {
                // Obtener todos los campos del producto
                $productoData = $producto->toArray();
                
                // Agregar el campo carga del contenedor directamente al producto
                $productoData['carga_contenedor'] = $producto->contenedor ? $producto->contenedor->carga : null;
                
                // Remover el objeto contenedor completo
                unset($productoData['contenedor']);
                
                return $productoData;
            });
        
        return response()->json($productos);
    } catch (\Exception $e) {
        Log::error('Error al obtener los productos: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
```

/**
 * Obtener opciones de filtro para productos
 */
public function filterOptions()
{
    try {
        // Obtener cargas únicas de la tabla carga_consolidada_contenedor
        $cargas = CargaConsolidadaContenedor::getCargasUnicas();
        
        // Obtener otras opciones de filtro de productos
        $rubros = ProductoImportadoExcel::select('rubro')
            ->whereNotNull('rubro')
            ->where('rubro', '!=', '')
            ->distinct()
            ->orderBy('rubro')
            ->pluck('rubro')
            ->toArray();

        $tiposProducto = ProductoImportadoExcel::select('tipo_producto')
            ->whereNotNull('tipo_producto')
            ->where('tipo_producto', '!=', '')
            ->distinct()
            ->orderBy('tipo_producto')
            ->pluck('tipo_producto')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => [
                'cargas' => $cargas,
                'rubros' => $rubros,
                'tipos_producto' => $tiposProducto
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error al obtener opciones de filtro: ' . $e->getMessage()
        ], 500);
    }
}
```

### Ruta
```php
Route::group(['prefix' => 'base-datos', 'middleware' => 'jwt.auth'], function () {
    Route::get('productos/filter-options', [ProductosController::class, 'filterOptions']);
});
```

## Endpoints Disponibles

### 1. Obtener Productos con Contenedor
```
GET /api/base-datos/productos
```

**Parámetros de consulta:**
- `page` (opcional): Número de página (por defecto: 1)
- `limit` (opcional): Items por página (por defecto: 10, máximo: 100)

**Ejemplo de petición:**
```
GET /api/base-datos/productos?page=1&limit=5
```

**Respuesta:**
```json
{
    "data": [
        {
            "id": 1,
            "item": "1",
            "nombre_comercial": "VIDEO MONITOR PARA BEBES",
            "rubro": "TECNOLOGÍA",
            "tipo": "LIBRE",
            "idContenedor": 58,
            "carga_contenedor": "8",
            "precio_exw": "150.00",
            "subpartida": "8528.72.00.00",
            "unidad_comercial": "UNIDAD",
            "arancel_sunat": "0%",
            "arancel_tlc": "0%",
            "antidumping": null,
            "etiquetado": null,
            "doc_especial": null,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        },
        {
            "id": 2,
            "item": "2",
            "nombre_comercial": "ROLLO DE ETIQUETA DE 10*12 CM",
            "rubro": "Papel",
            "tipo": "LIBRE",
            "idContenedor": 58,
            "carga_contenedor": "8",
            "precio_exw": "25.50",
            "subpartida": "4821.10.00.00",
            "unidad_comercial": "ROLLO",
            "arancel_sunat": "0%",
            "arancel_tlc": "0%",
            "antidumping": null,
            "etiquetado": null,
            "doc_especial": null,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 25,
        "per_page": 5,
        "total": 125,
        "from": 1,
        "to": 5,
        "has_more_pages": true,
        "next_page_url": "http://localhost:8000/api/base-datos/productos?page=2&limit=5",
        "prev_page_url": null
    }
}
```

### 2. Obtener Opciones de Filtro
```
GET /api/base-datos/productos/filter-options
```

## Estructura de Respuesta

### Productos con Carga de Contenedor
- **Estructura plana**: Todos los campos del producto + `carga_contenedor`
- **Sin objeto anidado**: No incluye el objeto `contenedor` completo
- **Campo agregado**: `carga_contenedor` contiene el valor de carga del contenedor asociado

### Campos Incluidos en Productos:
- `id` - ID del producto
- `item` - Número de item
- `nombre_comercial` - Nombre comercial del producto
- `rubro` - Categoría/rubro del producto
- `tipo` - Tipo (LIBRE/RESTRINGIDO)
- `idContenedor` - ID del contenedor asociado
- `carga_contenedor` - **NUEVO**: Carga del contenedor (ej: "8")
- `precio_exw` - Precio EXW
- `subpartida` - Partida arancelaria
- `unidad_comercial` - Unidad comercial
- `arancel_sunat` - Arancel SUNAT
- `arancel_tlc` - Arancel TLC
- `antidumping` - Información antidumping
- `etiquetado` - Información de etiquetado
- `doc_especial` - Documentos especiales
- `created_at` - Fecha de creación
- `updated_at` - Fecha de actualización

### Campos de Paginación:
- `current_page` - Página actual
- `last_page` - Última página disponible
- `per_page` - Items por página
- `total` - Total de productos
- `from` - Primer item de la página actual
- `to` - Último item de la página actual
- `has_more_pages` - Indica si hay más páginas
- `next_page_url` - URL de la página siguiente (null si es la última)
- `prev_page_url` - URL de la página anterior (null si es la primera)

## Casos de Uso

### 1. Cargar filtros en formulario
```javascript
// Al cargar la página de productos
const filterOptions = await getFilterOptions();

// Llenar dropdowns
filterOptions.data.cargas.forEach(carga => {
    const option = document.createElement('option');
    option.value = carga;
    option.textContent = carga;
    document.getElementById('carga-filter').appendChild(option);
});
```

### 2. Filtrado dinámico
```javascript
// Aplicar filtro por carga
function filterByCarga(carga) {
    const productos = document.querySelectorAll('.producto-item');
    productos.forEach(producto => {
        if (producto.dataset.carga === carga || carga === '') {
            producto.style.display = 'block';
        } else {
            producto.style.display = 'none';
        }
    });
}
```

### 3. Validación de filtros
```javascript
// Validar que el filtro seleccionado existe en las opciones
function validateFilter(filterValue, filterType) {
    const options = filterOptions.data[filterType];
    return options.includes(filterValue);
}
```

## Notas Técnicas

### Rendimiento
- Las consultas utilizan `distinct()` para evitar duplicados
- Se aplican filtros `whereNotNull()` y `where('campo', '!=', '')` para excluir valores vacíos
- Los resultados se ordenan para mejor experiencia de usuario

### Seguridad
- Endpoint protegido con middleware JWT
- Validación de autenticación en cada petición
- Manejo de errores con try-catch

### Mantenimiento
- El modelo `CargaConsolidadaContenedor` es extensible para futuras funcionalidades
- Métodos estáticos facilitan la reutilización
- Documentación completa para desarrollo futuro

## Comando de Prueba
```bash
php artisan test:filter-options
```

Este comando prueba todas las funcionalidades del endpoint y muestra estadísticas de los datos disponibles. 