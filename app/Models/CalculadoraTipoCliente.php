<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculadoraTipoCliente extends Model
{
    use SoftDeletes;

    protected $table = 'calculadora_tipo_cliente';
    
    protected $fillable = [
        'nombre'
    ];

    /**
     * Relación con las tarifas
     */
    public function tarifas(): HasMany
    {
        return $this->hasMany(CalculadoraTarifasConsolidado::class, 'calculadora_tipo_cliente_id');
    }
}
