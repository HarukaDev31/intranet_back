<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContenedorTcYuan extends Model
{
    protected $table = 'carga_consolidada_contenedor_tc_yuan';

    protected $fillable = [
        'id_contenedor',
        'tc_yuan',
    ];

    protected $casts = [
        'tc_yuan' => 'decimal:8',
    ];

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }
}
