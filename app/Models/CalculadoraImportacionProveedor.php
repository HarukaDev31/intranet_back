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
        // Referencia al proveedor real creado en `contenedor_consolidado_cotizacion_proveedores`
        'id_proveedor',
        'cbm',
        'maxcbm',
        'peso',
        'qty_caja',
        'code_supplier'
    ];

    protected $casts = [
        'cbm' => 'decimal:10',
        'maxcbm' => 'decimal:10',
        'peso' => 'decimal:10',
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
     * Relación con los productos.
     *
     * IMPORTANTE: orden por `id` ASC para preservar el orden de creación.
     * En `CalculadoraImportacionService::actualizarCalculo` los productos se
     * eliminan y recrean siguiendo el orden del payload del frontend, por lo
     * que los nuevos IDs auto-increment quedan consecutivos en ese orden. Sin
     * este orderBy, MySQL no garantiza el orden al recargar la relación
     * (especialmente con `WHERE id_proveedor IN (...)`), lo que provoca que
     * al regenerar el Excel desde la BD los ítems —sobre todo los recién
     * agregados— aparezcan mezclados en lugar de al final del proveedor.
     */
    public function productos(): HasMany
    {
        return $this->hasMany(CalculadoraImportacionProducto::class, 'id_proveedor')
            ->orderBy('id');
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
