<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculadoraTarifasConsolidado extends Model
{
    use SoftDeletes;

    protected $table = 'calculadora_tarifas_consolidado';
    
    protected $fillable = [
        'limit_inf',
        'limit_sup',
        'value',
        'type',
        'calculadora_tipo_cliente_id',
        'vigente_hasta',
    ];

    protected $casts = [
        'limit_inf' => 'decimal:2',
        'limit_sup' => 'decimal:2',
        'value' => 'decimal:2',
        'type' => 'string',
        'vigente_hasta' => 'datetime',
    ];

    public function scopeVigente($query)
    {
        return $query->whereNull('vigente_hasta');
    }

    public function isVigente(): bool
    {
        return $this->vigente_hasta === null;
    }

    /**
     * Relación con el tipo de cliente
     */
    public function tipoCliente(): BelongsTo
    {   //where deleted_at is null
        return $this->belongsTo(CalculadoraTipoCliente::class, 'calculadora_tipo_cliente_id')->whereNull('deleted_at');
    }
}
