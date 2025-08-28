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
        'nombre_cliente',
        'dni_cliente',
        'correo_cliente',
        'whatsapp_cliente',
        'tipo_cliente',
        'qty_proveedores',
        'tarifa_total_extra_proveedor',
        'tarifa_total_extra_item',
        'url_cotizacion',
        'tarifa'
    ];

    protected $casts = [
        'qty_proveedores' => 'integer',
        'tarifa_total_extra_proveedor' => 'decimal:2',
        'tarifa_total_extra_item' => 'decimal:2',
        'tarifa' => 'decimal:2'
    ];

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
}
