<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculadoraImportacionProveedor extends Model
{
    use HasFactory;

    protected $table = 'calculadora_importacion_proveedores';

    protected $fillable = [
        'id_calculadora_importacion',
        'cbm',
        'peso',
        'qty_caja'
    ];

    protected $casts = [
        'cbm' => 'decimal:2',
        'peso' => 'decimal:2',
        'qty_caja' => 'integer'
    ];

    /**
     * Relación con la calculadora de importación
     */
    public function calculadoraImportacion(): BelongsTo
    {
        return $this->belongsTo(CalculadoraImportacion::class, 'id_calculadora_importacion');
    }

    /**
     * Relación con los productos
     */
    public function productos(): HasMany
    {
        return $this->hasMany(CalculadoraImportacionProducto::class, 'id_proveedor');
    }

    /**
     * Calcular total de productos
     */
    public function getTotalProductosAttribute(): int
    {
        return $this->productos->sum('cantidad');
    }

    /**
     * Calcular valor total de productos
     */
    public function getValorTotalProductosAttribute(): float
    {
        return $this->productos->sum(function ($producto) {
            return $producto->precio * $producto->cantidad;
        });
    }
}
