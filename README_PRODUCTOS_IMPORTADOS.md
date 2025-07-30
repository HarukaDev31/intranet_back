# Modelo ProductoImportadoExcel - Laravel

Este modelo gestiona los productos importados desde archivos Excel, incluyendo información comercial, arancelaria y de regulaciones.

## Características del Modelo

### Tabla Base
- **Tabla**: `productos_importados_excel`
- **Soft Deletes**: Implementado para borrado lógico
- **Timestamps**: Automáticos (created_at, updated_at, deleted_at)

### Campos Principales

```php
protected $fillable = [
    'idContenedor',        // ID del contenedor
    'item',                // Código del item
    'nombre_comercial',    // Nombre comercial del producto
    'foto',               // Ruta de la foto
    'caracteristicas',    // Características del producto (texto)
    'rubro',              // Rubro o categoría
    'tipo_producto',      // Tipo de producto
    'precio_exw',         // Precio EXW (decimal)
    'subpartida',         // Subpartida arancelaria
    'link',               // Enlace del producto
    'unidad_comercial',   // Unidad comercial
    'arancel_sunat',      // Arancel SUNAT
    'arancel_tlc',        // Arancel TLC
    'antidumping',        // Información antidumping
    'correlativo',        // Número correlativo
    'etiquetado',         // Información de etiquetado
    'doc_especial',       // Documentos especiales
    'tipo'                // Tipo: LIBRE o RESTRINGIDO
];
```

## Tipos de Producto

### Constantes
```php
const TIPO_LIBRE = 'LIBRE';
const TIPO_RESTRINGIDO = 'RESTRINGIDO';
```

### Obtener tipos permitidos
```php
$tipos = ProductoImportadoExcel::getTiposPermitidos();
// ['LIBRE', 'RESTRINGIDO']
```

## Scopes de Consulta

### Filtros por Tipo
```php
// Productos libres
$libres = ProductoImportadoExcel::libres()->get();

// Productos restringidos
$restringidos = ProductoImportadoExcel::restringidos()->get();

// Por tipo específico
$productos = ProductoImportadoExcel::porTipo('LIBRE')->get();
```

### Filtros por Categoría
```php
// Por rubro
$productos = ProductoImportadoExcel::porRubro('Electrónicos')->get();

// Por tipo de producto
$productos = ProductoImportadoExcel::porTipoProducto('Gadget')->get();
```

### Filtros por Contenedor
```php
// Por contenedor específico
$productos = ProductoImportadoExcel::porContenedor(1)->get();
```

### Búsquedas
```php
// Por nombre comercial
$productos = ProductoImportadoExcel::buscarPorNombre('iPhone')->get();

// Por item
$productos = ProductoImportadoExcel::buscarPorItem('ITEM001')->get();
```

### Filtros por Precio
```php
// Por rango de precios
$productos = ProductoImportadoExcel::porRangoPrecio(10, 100)->get();
```

## Atributos Calculados

### Verificaciones de Tipo
```php
$producto = ProductoImportadoExcel::first();

// Verificar si es libre
$esLibre = $producto->es_libre; // bool

// Verificar si es restringido
$esRestringido = $producto->es_restringido; // bool
```

### Información de Archivos
```php
// Verificar si tiene foto
$tieneFoto = $producto->tiene_foto; // bool

// Verificar si tiene link
$tieneLink = $producto->tiene_link; // bool

// Verificar si tiene características
$tieneCaracteristicas = $producto->tiene_caracteristicas; // bool
```

### URLs y Formateo
```php
// URL completa de la foto
$fotoUrl = $producto->foto_url; // string|null

// Precio formateado
$precioFormateado = $producto->precio_formateado; // string

// Tipo formateado
$tipoFormateado = $producto->tipo_formateado; // string

// Rubro formateado
$rubroFormateado = $producto->rubro_formateado; // string

// Tipo de producto formateado
$tipoProductoFormateado = $producto->tipo_producto_formateado; // string
```

### Información Estructurada
```php
// Características como array
$caracteristicas = $producto->caracteristicas_array; // array

// Información de regulaciones
$regulaciones = $producto->informacion_regulaciones; // array

// Información arancelaria
$arancelaria = $producto->informacion_arancelaria; // array
```

## Métodos Estáticos

### Estadísticas Generales
```php
$estadisticas = ProductoImportadoExcel::getEstadisticas();

// Resultado:
[
    'total' => 1000,
    'libres' => 800,
    'restringidos' => 200,
    'con_foto' => 750,
    'con_precio' => 900,
    'con_caracteristicas' => 600,
    'por_contenedor' => [
        1 => 150,
        2 => 200,
        // ...
    ]
]
```

### Agrupaciones
```php
// Productos por rubro
$porRubro = ProductoImportadoExcel::getProductosPorRubro();
// ['Electrónicos' => 300, 'Ropa' => 200, ...]

// Productos por tipo
$porTipo = ProductoImportadoExcel::getProductosPorTipo();
// ['Gadget' => 150, 'Ropa' => 100, ...]
```

## Ejemplos de Uso

### 1. Crear un Producto
```php
$producto = ProductoImportadoExcel::create([
    'idContenedor' => 1,
    'item' => 'ITEM001',
    'nombre_comercial' => 'iPhone 15 Pro',
    'foto' => 'productos/iphone15.jpg',
    'caracteristicas' => "Pantalla 6.1 pulgadas\nChip A17 Pro\nCámara triple",
    'rubro' => 'Electrónicos',
    'tipo_producto' => 'Smartphone',
    'precio_exw' => 999.99,
    'subpartida' => '8517.12.00.00',
    'arancel_sunat' => '6%',
    'arancel_tlc' => '0%',
    'tipo' => ProductoImportadoExcel::TIPO_LIBRE
]);
```

### 2. Búsqueda Avanzada
```php
$productos = ProductoImportadoExcel::query()
    ->libres()
    ->porRubro('Electrónicos')
    ->porRangoPrecio(100, 1000)
    ->whereNotNull('foto')
    ->orderBy('precio_exw', 'desc')
    ->get();
```

### 3. Obtener Información Completa
```php
$producto = ProductoImportadoExcel::first();

echo "Nombre: " . $producto->nombre_comercial;
echo "Precio: " . $producto->precio_formateado;
echo "Tipo: " . $producto->tipo_formateado;
echo "Rubro: " . $producto->rubro_formateado;

if ($producto->tiene_foto) {
    echo "Foto: " . $producto->foto_url;
}

if ($producto->tiene_caracteristicas) {
    echo "Características:";
    foreach ($producto->caracteristicas_array as $caracteristica) {
        echo "- " . $caracteristica;
    }
}

$regulaciones = $producto->informacion_regulaciones;
echo "Antidumping: " . ($regulaciones['antidumping'] ?: 'No aplica');
echo "Etiquetado: " . ($regulaciones['etiquetado'] ?: 'No aplica');
```

### 4. Estadísticas por Contenedor
```php
$estadisticas = ProductoImportadoExcel::getEstadisticas();

foreach ($estadisticas['por_contenedor'] as $contenedor => $total) {
    echo "Contenedor {$contenedor}: {$total} productos";
}
```

### 5. Importación desde Excel
```php
// Ejemplo de procesamiento de datos de Excel
$datosExcel = [
    'idContenedor' => 1,
    'item' => 'ITEM001',
    'nombre_comercial' => 'Producto Excel',
    'precio_exw' => 50.00,
    'tipo' => 'LIBRE'
];

// Validar tipo antes de crear
if (in_array($datosExcel['tipo'], ProductoImportadoExcel::getTiposPermitidos())) {
    $producto = ProductoImportadoExcel::create($datosExcel);
}
```

## Validaciones y Seguridad

### Validación de Tipo
El modelo incluye validación automática del campo `tipo`:

```php
// Si se intenta guardar un tipo inválido, se asigna 'LIBRE' por defecto
$producto = new ProductoImportadoExcel(['tipo' => 'INVALIDO']);
$producto->save(); // Se guarda con tipo = 'LIBRE'
```

### Soft Deletes
```php
// Borrado lógico
$producto->delete(); // No se elimina físicamente

// Restaurar
$producto->restore();

// Eliminar permanentemente
$producto->forceDelete();

// Incluir eliminados en consultas
$todos = ProductoImportadoExcel::withTrashed()->get();
```

## Comandos de Prueba

### Probar Modelo
```bash
php artisan test:productos-importados
```

Este comando prueba:
- Consultas básicas
- Scopes de filtrado
- Atributos calculados
- Métodos estáticos
- Creación de productos

## Características Destacadas

### 1. Flexibilidad de Datos
- Manejo de características como texto o JSON
- URLs de fotos automáticas
- Formateo inteligente de campos

### 2. Scopes Optimizados
- Filtros predefinidos para consultas comunes
- Búsquedas por texto con LIKE
- Rangos de precios

### 3. Atributos Calculados
- Verificaciones booleanas
- Formateo automático
- Información estructurada

### 4. Estadísticas Integradas
- Métodos estáticos para reportes
- Agrupaciones automáticas
- Contadores optimizados

### 5. Seguridad
- Validación de tipos
- Soft deletes
- Fillable fields definidos

## Migración desde Excel

Si estás migrando datos desde Excel:

```php
// Ejemplo de importación
$datosExcel = Excel::toArray(new ProductosImport, 'productos.xlsx');

foreach ($datosExcel[0] as $fila) {
    ProductoImportadoExcel::create([
        'idContenedor' => $fila['contenedor'],
        'item' => $fila['item'],
        'nombre_comercial' => $fila['nombre'],
        'precio_exw' => $fila['precio'],
        'tipo' => $fila['tipo'] ?? ProductoImportadoExcel::TIPO_LIBRE
    ]);
}
```

## Notas Importantes

1. **Performance**: Usa índices en la base de datos para campos de búsqueda frecuente.

2. **Fotos**: Las fotos se almacenan como rutas relativas, el modelo construye URLs completas.

3. **Características**: Pueden ser texto plano o JSON, el modelo maneja ambos formatos.

4. **Precios**: Se almacenan como decimales con 2 decimales de precisión.

5. **Tipos**: Solo acepta 'LIBRE' o 'RESTRINGIDO', otros valores se convierten a 'LIBRE'. 