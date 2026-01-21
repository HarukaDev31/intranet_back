<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculadoraImportacion extends Model
{
    use HasFactory;

    protected $table = 'calculadora_importacion';

    protected $fillable = [
        'id_cliente',
        'id_usuario',
        'created_by',
        'cod_cotizacion',
        'id_cotizacion',
        'nombre_cliente',
        'tipo_documento',
        'dni_cliente',
        'ruc_cliente',
        'razon_social',
        'correo_cliente',
        'whatsapp_cliente',
        'tipo_cliente',
        'qty_proveedores',
        'tarifa_total_extra_proveedor',
        'tarifa_total_extra_item',
        'url_cotizacion',
        'url_cotizacion_pdf',
        'tarifa',
        'tarifa_descuento',
        'tc',
        'total_fob',
        'total_impuestos',
        'logistica',
        'estado',
        'id_carga_consolidada_contenedor',
    ];

    protected $casts = [
        'qty_proveedores' => 'integer',
        'tarifa_total_extra_proveedor' => 'decimal:2',
        'tarifa_total_extra_item' => 'decimal:2',
        'tarifa' => 'decimal:2',
        'tarifa_descuento' => 'decimal:2',
        'tc' => 'decimal:4',
        'total_fob' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
        'logistica' => 'decimal:2',
        'estado' => 'string',
        'id_carga_consolidada_contenedor' => 'integer'
    ];

        // Constantes para los estados
    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_COTIZADO = 'COTIZADO';
    const ESTADO_CONFIRMADO = 'CONFIRMADO';

    /**
     * Obtener todos los estados posibles
     */
    public static function getEstadosDisponibles(): array
    {
        return [
            self::ESTADO_PENDIENTE,
            self::ESTADO_COTIZADO,
            self::ESTADO_CONFIRMADO
        ];
    }

    /**
     * Verificar si el estado es válido
     */
    public static function esEstadoValido(string $estado): bool
    {
        return in_array($estado, self::getEstadosDisponibles());
    }

    /**
     * Verificar si está pendiente
     */
    public function isPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    /**
     * Verificar si está cotizado
     */
    public function isCotizado(): bool
    {
        return $this->estado === self::ESTADO_COTIZADO;
    }

    /**
     * Verificar si está confirmado
     */
    public function isConfirmado(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADO;
    }

    /**
     * Marcar como cotizado
     */
    public function marcarComoCotizado(): bool
    {
        return $this->update(['estado' => self::ESTADO_COTIZADO]);
    }

    /**
     * Marcar como confirmado
     */
    public function marcarComoConfirmado(): bool
    {
        return $this->update(['estado' => self::ESTADO_CONFIRMADO]);
    }

    /**
     * Marcar como pendiente
     */
    public function marcarComoPendiente(): bool
    {
        return $this->update(['estado' => self::ESTADO_PENDIENTE]);
    }

    /**
     * Relación con el cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(BaseDatos\Clientes\Cliente::class, 'id_cliente');
    }

    /**
     * Relación con los proveedores
     */
    public function proveedores(): HasMany
    {
        return $this->hasMany(CalculadoraImportacionProveedor::class, 'id_calculadora_importacion');
    }

    /**
     * Relación con el contenedor de carga consolidada
     */
    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CargaConsolidada\Contenedor::class, 'id_carga_consolidada_contenedor');
    }

    /**
     * Relación con el usuario creador
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Usuario::class, 'created_by', 'ID_Usuario');
    }
   
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Usuario::class, 'id_usuario', 'ID_Usuario');
    }
    /**
     * Calcular total de CBM de todos los proveedores
     */
    public function getTotalCbmAttribute(): float
    {
        return $this->proveedores->sum('cbm');
    }

    /**
     * Calcular total de peso de todos los proveedores
     */
    public function getTotalPesoAttribute(): float
    {
        return $this->proveedores->sum('peso');
    }

    /**
     * Calcular total de productos
     */
    public function getTotalProductosAttribute(): int
    {
        return $this->proveedores->sum(function ($proveedor) {
            return $proveedor->productos->sum('cantidad');
        });
    }
    //get all status as filter
    public static function getEstadosDisponiblesFilter(): array
    {
        return [
            ['value' => self::ESTADO_PENDIENTE, 'label' => 'PENDIENTE'],
            ['value' => self::ESTADO_COTIZADO, 'label' => 'COTIZADO'],
            ['value' => self::ESTADO_CONFIRMADO, 'label' => 'CONFIRMADO']
        ];
    }

    /**
     * Relación con la cotización de carga consolidada
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CargaConsolidada\Cotizacion::class, 'id_cotizacion');
    }
}
