<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\BaseDatos\EntidadReguladora;
use App\Models\CargaConsolidada\Contenedor as CargaConsolidadaContenedor;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;

use App\Models\BaseDatos\Contenedor;
class ProductoImportadoExcel extends Model
{
    use SoftDeletes;

    protected $table = 'productos_importados_excel';

    protected $fillable = [
        'idContenedor',
        'id_import_producto',
        'item',
        'nombre_comercial',
        'foto',
        'caracteristicas',
        'rubro',
        'tipo_producto',
        'entidad_id',
        'precio_exw',
        'subpartida',
        'link',
        'unidad_comercial',
        'arancel_sunat',
        'arancel_tlc',
        'antidumping',
        'antidumping_value',
        'correlativo',
        'etiquetado',
        'tipo_etiquetado_id',
        'doc_especial',
        'tiene_observaciones',
        'observaciones',
        'tipo'
    ];

    protected $casts = [
        'precio_exw' => 'decimal:2',
        'tiene_observaciones' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Los valores permitidos para el campo tipo
     */
    const TIPO_LIBRE = 'LIBRE';
    const TIPO_RESTRINGIDO = 'RESTRINGIDO';

    /**
     * Obtener los valores permitidos para el campo tipo
     */
    public static function getTiposPermitidos(): array
    {
        return [
            self::TIPO_LIBRE,
            self::TIPO_RESTRINGIDO
        ];
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para productos libres
     */
    public function scopeLibres($query)
    {
        return $query->where('tipo', self::TIPO_LIBRE);
    }

    /**
     * Scope para productos restringidos
     */
    public function scopeRestringidos($query)
    {
        return $query->where('tipo', self::TIPO_RESTRINGIDO);
    }

    /**
     * Scope para filtrar por rubro
     */
    public function scopePorRubro($query, $rubro)
    {
        return $query->where('rubro', 'LIKE', "%{$rubro}%");
    }

    /**
     * Scope para filtrar por tipo de producto
     */
    public function scopePorTipoProducto($query, $tipoProducto)
    {
        return $query->where('tipo_producto', 'LIKE', "%{$tipoProducto}%");
    }

    /**
     * Scope para filtrar por contenedor
     */
    public function scopePorContenedor($query, $idContenedor)
    {
        return $query->where('idContenedor', $idContenedor);
    }

    /**
     * Scope para buscar por nombre comercial
     */
    public function scopeBuscarPorNombre($query, $nombre)
    {
        return $query->where('nombre_comercial', 'LIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por item
     */
    public function scopeBuscarPorItem($query, $item)
    {
        return $query->where('item', 'LIKE', "%{$item}%");
    }

    /**
     * Scope para filtrar por rango de precios
     */
    public function scopePorRangoPrecio($query, $precioMin, $precioMax)
    {
        return $query->whereBetween('precio_exw', [$precioMin, $precioMax]);
    }

    /**
     * Verificar si el producto es libre
     */
    public function getEsLibreAttribute(): bool
    {
        return $this->tipo === self::TIPO_LIBRE;
    }

    /**
     * Verificar si el producto es restringido
     */
    public function getEsRestringidoAttribute(): bool
    {
        return $this->tipo === self::TIPO_RESTRINGIDO;
    }

    /**
     * Obtener la URL completa de la foto
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (!$this->foto) {
            return null;
        }

        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($this->foto, FILTER_VALIDATE_URL)) {
            return $this->foto;
        }

        // Si es una ruta relativa, construir la URL completa
        return asset('storage/' . $this->foto);
    }

    /**
     * Obtener el precio formateado
     */
    public function getPrecioFormateadoAttribute(): string
    {
        if (!$this->precio_exw) {
            return 'No especificado';
        }

        return '$' . $this->precio_exw;
    }

    /**
     * Obtener características como array
     */
    public function getCaracteristicasArrayAttribute(): array
    {
        if (!$this->caracteristicas) {
            return [];
        }

        // Intentar decodificar como JSON
        $decoded = json_decode($this->caracteristicas, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Si no es JSON, dividir por líneas
        return array_filter(array_map('trim', explode("\n", $this->caracteristicas)));
    }

    /**
     * Obtener información de regulaciones basada en los campos
     */
    public function getInformacionRegulacionesAttribute(): array
    {
        return [
            'antidumping' => $this->antidumping,
            'etiquetado' => $this->etiquetado,
            'doc_especial' => $this->doc_especial,
            'arancel_sunat' => $this->arancel_sunat,
            'arancel_tlc' => $this->arancel_tlc,
            'subpartida' => $this->subpartida
        ];
    }

    /**
     * Obtener información de aranceles
     */
    public function getInformacionArancelariaAttribute(): array
    {
        return [
            'subpartida' => $this->subpartida,
            'arancel_sunat' => $this->arancel_sunat,
            'arancel_tlc' => $this->arancel_tlc,
            'unidad_comercial' => $this->unidad_comercial
        ];
    }

    /**
     * Verificar si tiene foto
     */
    public function getTieneFotoAttribute(): bool
    {
        return !empty($this->foto);
    }

    /**
     * Verificar si tiene link
     */
    public function getTieneLinkAttribute(): bool
    {
        return !empty($this->link);
    }

    /**
     * Verificar si tiene características
     */
    public function getTieneCaracteristicasAttribute(): bool
    {
        return !empty($this->caracteristicas);
    }

    /**
     * Obtener el tipo de producto formateado
     */
    public function getTipoProductoFormateadoAttribute(): string
    {
        return ucfirst(strtolower($this->tipo_producto ?? 'No especificado'));
    }

    /**
     * Obtener el rubro formateado
     */
    public function getRubroFormateadoAttribute(): string
    {
        return ucfirst(strtolower($this->rubro ?? 'No especificado'));
    }

    /**
     * Obtener el tipo formateado
     */
    public function getTipoFormateadoAttribute(): string
    {
        return $this->tipo === self::TIPO_LIBRE ? 'Libre' : 'Restringido';
    }

    /**
     * Relación con CargaConsolidadaContenedor  
     */
    public function contenedor()
    {
        return $this->belongsTo(CargaConsolidadaContenedor::class, 'idContenedor', 'id');
    }

    /**
     * Relación con EntidadReguladora
     */
    public function entidad()
    {
        return $this->belongsTo(EntidadReguladora::class, 'entidad_id', 'id');
    }

    /**
     * Relación con ProductoRubro (tipo de etiquetado)
     */
    public function tipoEtiquetado()
    {
        return $this->belongsTo(ProductoRubro::class, 'tipo_etiquetado_id', 'id');
    }

    /**
     * Relación con ImportProducto
     */
    public function importProducto()
    {
        return $this->belongsTo(\App\Models\ImportProducto::class, 'id_import_producto');
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de guardar, asegurar que el tipo sea válido
        static::saving(function ($model) {
            if ($model->tipo && !in_array($model->tipo, self::getTiposPermitidos())) {
                $model->tipo = self::TIPO_LIBRE; // Valor por defecto
            }
        });
    }

    /**
     * Obtener estadísticas de productos
     */
    public static function getEstadisticas(): array
    {
        return [
            'total' => self::count(),
            'libres' => self::libres()->count(),
            'restringidos' => self::restringidos()->count(),
            'con_foto' => self::whereNotNull('foto')->count(),
            'con_precio' => self::whereNotNull('precio_exw')->count(),
            'con_caracteristicas' => self::whereNotNull('caracteristicas')->count(),
            'por_contenedor' => self::selectRaw('idContenedor, COUNT(*) as total')
                ->groupBy('idContenedor')
                ->get()
                ->pluck('total', 'idContenedor')
                ->toArray()
        ];
    }

    /**
     * Obtener productos agrupados por rubro
     */
    public static function getProductosPorRubro(): array
    {
        return self::selectRaw('rubro, COUNT(*) as total')
            ->whereNotNull('rubro')
            ->groupBy('rubro')
            ->orderBy('total', 'desc')
            ->get()
            ->pluck('total', 'rubro')
            ->toArray();
    }

    /**
     * Obtener productos agrupados por tipo de producto
     */
    public static function getProductosPorTipo(): array
    {
        return self::selectRaw('tipo_producto, COUNT(*) as total')
            ->whereNotNull('tipo_producto')
            ->groupBy('tipo_producto')
            ->orderBy('total', 'desc')
            ->get()
            ->pluck('total', 'tipo_producto')
            ->toArray();
    }
}
