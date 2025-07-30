# Modelos de Regulaciones de Productos - Laravel

Este sistema implementa los modelos de Laravel para gestionar las regulaciones de productos, incluyendo antidumping, permisos, etiquetado y documentos especiales.

## Estructura de Modelos

### Modelos Principales

#### 1. ProductoRubro
**Tabla**: `bd_productos_rubro`

```php
use App\Models\BaseDatos\ProductoRubro;

// Obtener todos los rubros
$rubros = ProductoRubro::all();

// Obtener un rubro con sus regulaciones
$rubro = ProductoRubro::with([
    'regulacionesAntidumping',
    'regulacionesPermiso',
    'regulacionesEtiquetado',
    'regulacionesDocumentosEspeciales'
])->find(1);
```

**Relaciones disponibles**:
- `regulacionesAntidumping()` - Regulaciones antidumping del rubro
- `regulacionesPermiso()` - Regulaciones de permisos del rubro
- `regulacionesEtiquetado()` - Regulaciones de etiquetado del rubro
- `regulacionesDocumentosEspeciales()` - Regulaciones de documentos especiales del rubro

#### 2. EntidadReguladora
**Tabla**: `bd_entidades_reguladoras`

```php
use App\Models\BaseDatos\EntidadReguladora;

// Obtener todas las entidades
$entidades = EntidadReguladora::all();

// Obtener una entidad con sus permisos
$entidad = EntidadReguladora::with('regulacionesPermiso')->find(1);
```

**Relaciones disponibles**:
- `regulacionesPermiso()` - Permisos emitidos por esta entidad

#### 3. ProductoRegulacionAntidumping
**Tabla**: `bd_productos_regulaciones_antidumping`

```php
use App\Models\BaseDatos\ProductoRegulacionAntidumping;

// Obtener todas las regulaciones antidumping
$antidumping = ProductoRegulacionAntidumping::with(['rubro', 'media'])->get();

// Crear una nueva regulación
$regulacion = ProductoRegulacionAntidumping::create([
    'id_rubro' => 1,
    'descripcion_producto' => 'Producto de ejemplo',
    'partida' => '123456',
    'antidumping' => 15.50,
    'observaciones' => 'Observaciones adicionales'
]);
```

**Relaciones disponibles**:
- `rubro()` - Rubro asociado
- `media()` - Archivos multimedia asociados

#### 4. ProductoRegulacionPermiso
**Tabla**: `bd_productos_regulaciones_permiso`

```php
use App\Models\BaseDatos\ProductoRegulacionPermiso;

// Obtener todos los permisos
$permisos = ProductoRegulacionPermiso::with(['rubro', 'entidadReguladora', 'media'])->get();

// Crear un nuevo permiso
$permiso = ProductoRegulacionPermiso::create([
    'id_rubro' => 1,
    'id_entidad_reguladora' => 1,
    'nombre' => 'Permiso de importación',
    'c_permiso' => 123.45,
    'c_tramitador' => 67.89,
    'observaciones' => 'Observaciones del permiso'
]);
```

**Relaciones disponibles**:
- `rubro()` - Rubro asociado
- `entidadReguladora()` - Entidad que emite el permiso
- `media()` - Archivos multimedia asociados

#### 5. ProductoRegulacionEtiquetado
**Tabla**: `bd_productos_regulaciones_etiquetado`

```php
use App\Models\BaseDatos\ProductoRegulacionEtiquetado;

// Obtener todas las regulaciones de etiquetado
$etiquetado = ProductoRegulacionEtiquetado::with(['rubro', 'media'])->get();

// Crear una nueva regulación de etiquetado
$etiquetado = ProductoRegulacionEtiquetado::create([
    'id_rubro' => 1,
    'observaciones' => 'Requisitos de etiquetado específicos'
]);
```

**Relaciones disponibles**:
- `rubro()` - Rubro asociado
- `media()` - Archivos multimedia asociados

#### 6. ProductoRegulacionDocumentoEspecial
**Tabla**: `bd_productos_regulaciones_documentos_especiales`

```php
use App\Models\BaseDatos\ProductoRegulacionDocumentoEspecial;

// Obtener todas las regulaciones de documentos especiales
$documentos = ProductoRegulacionDocumentoEspecial::with(['rubro', 'media'])->get();

// Crear una nueva regulación de documentos especiales
$documento = ProductoRegulacionDocumentoEspecial::create([
    'id_rubro' => 1,
    'observaciones' => 'Documentos especiales requeridos'
]);
```

**Relaciones disponibles**:
- `rubro()` - Rubro asociado
- `media()` - Archivos multimedia asociados

### Modelos de Media

Todos los modelos de media heredan de `BaseMediaModel` y proporcionan funcionalidades comunes:

#### Características comunes:
- **URL del archivo**: `$archivo->url`
- **Tamaño formateado**: `$archivo->tamanio_formateado`
- **Tipo de archivo**: `$archivo->tipo_archivo`
- **Es imagen**: `$archivo->es_imagen`
- **Es documento**: `$archivo->es_documento`

#### Modelos de Media disponibles:
- `ProductoRegulacionAntidumpingMedia`
- `ProductoRegulacionPermisoMedia`
- `ProductoRegulacionEtiquetadoMedia`
- `ProductoRegulacionDocumentoEspecialMedia`

## Ejemplos de Uso

### 1. Obtener todas las regulaciones de un rubro

```php
$rubro = ProductoRubro::with([
    'regulacionesAntidumping.media',
    'regulacionesPermiso.media',
    'regulacionesEtiquetado.media',
    'regulacionesDocumentosEspeciales.media'
])->find(1);

// Acceder a las regulaciones
foreach ($rubro->regulacionesAntidumping as $antidumping) {
    echo "Antidumping: " . $antidumping->descripcion_producto;
    echo "Partida: " . $antidumping->partida;
    echo "Valor: " . $antidumping->antidumping;
    
    // Archivos asociados
    foreach ($antidumping->media as $archivo) {
        echo "Archivo: " . $archivo->nombre_original;
        echo "URL: " . $archivo->url;
        echo "Tamaño: " . $archivo->tamanio_formateado;
    }
}
```

### 2. Crear una regulación con archivos

```php
// Crear la regulación
$regulacion = ProductoRegulacionAntidumping::create([
    'id_rubro' => 1,
    'descripcion_producto' => 'Producto de ejemplo',
    'partida' => '123456',
    'antidumping' => 15.50
]);

// Agregar archivos
$regulacion->media()->create([
    'extension' => 'pdf',
    'peso' => 1024000,
    'nombre_original' => 'documento.pdf',
    'ruta' => 'regulaciones/antidumping/documento.pdf'
]);
```

### 3. Búsqueda y filtrado

```php
// Buscar regulaciones por partida
$regulaciones = ProductoRegulacionAntidumping::where('partida', 'LIKE', '%123%')->get();

// Buscar permisos por entidad
$permisos = ProductoRegulacionPermiso::whereHas('entidadReguladora', function($query) {
    $query->where('nombre', 'LIKE', '%SENASA%');
})->get();

// Obtener rubros con regulaciones
$rubros = ProductoRubro::whereHas('regulacionesAntidumping')->get();
```

### 4. Estadísticas

```php
// Contar regulaciones por tipo
$stats = [
    'antidumping' => ProductoRegulacionAntidumping::count(),
    'permisos' => ProductoRegulacionPermiso::count(),
    'etiquetado' => ProductoRegulacionEtiquetado::count(),
    'documentos_especiales' => ProductoRegulacionDocumentoEspecial::count()
];

// Rubros con más regulaciones
$rubrosPopulares = ProductoRubro::withCount([
    'regulacionesAntidumping',
    'regulacionesPermiso',
    'regulacionesEtiquetado',
    'regulacionesDocumentosEspeciales'
])->orderBy('regulaciones_antidumping_count', 'desc')->get();
```

## Comandos de Prueba

### Probar Modelos
```bash
php artisan test:regulaciones
```

Este comando prueba:
- Conexión a las tablas
- Relaciones entre modelos
- Consultas básicas
- Estructura de datos

## Características Destacadas

### 1. Relaciones Eloquent
- Todas las relaciones están correctamente definidas
- Carga eager loading optimizada
- Relaciones bidireccionales

### 2. Modelo Base para Media
- Funcionalidades comunes para archivos
- Métodos helper para URLs y tamaños
- Detección automática de tipos de archivo

### 3. Casts Automáticos
- Fechas como objetos Carbon
- Decimales para valores monetarios
- Enteros para tamaños de archivo

### 4. Validación y Seguridad
- Fillable fields definidos
- Protección contra asignación masiva
- Validación de tipos de datos

## Migración desde CodeIgniter

Si estás migrando desde CodeIgniter, estos modelos mantienen la misma estructura de datos:

```php
// CodeIgniter
$query = $this->db->get('bd_productos_rubro');
$rubros = $query->result();

// Laravel
$rubros = ProductoRubro::all();
```

## Notas Importantes

1. **Integridad Referencial**: Las foreign keys están configuradas con CASCADE para mantener la integridad de datos.

2. **Performance**: Usa eager loading (`with()`) para evitar el problema N+1 en consultas con relaciones.

3. **Archivos**: Los modelos de media incluyen métodos helper para manejar URLs y tamaños de archivo.

4. **Extensibilidad**: Fácil de extender para agregar nuevos tipos de regulaciones o campos adicionales.

5. **Validación**: Considera agregar reglas de validación específicas según tus necesidades de negocio. 