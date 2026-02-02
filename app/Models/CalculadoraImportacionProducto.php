<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculadoraImportacionProducto extends Model
{
    use HasFactory;

    protected $table = 'calculadora_importacion_productos';

    protected $fillable = [
        'id_proveedor',
        'nombre',
        'precio',
        'valoracion',
        'cantidad',
        'antidumping_cu',
        'ad_valorem_p'
    ];

    protected $casts = [
        'precio' => 'decimal:10',
        'valoracion' => 'integer',
        'cantidad' => 'integer',
        'antidumping_cu' => 'decimal:10',
        'ad_valorem_p' => 'decimal:10'
    ];

    /**
     * Relación con el proveedor
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CalculadoraImportacionProveedor::class, 'id_proveedor');
    }

    /**
     * Calcular valor total del producto
     */
    public function getValorTotalAttribute(): float
    {
        return $this->precio * $this->cantidad;
    }

    /**
     * Calcular total de antidumping
     */
    public function getTotalAntidumpingAttribute(): float
    {
        return $this->antidumping_cu * $this->cantidad;
    }

    /**
     * Calcular total de ad valorem
     */
    public function getTotalAdValoremAttribute(): float
    {
        return ($this->precio * $this->cantidad * $this->ad_valorem_p) / 100;
    }
}
