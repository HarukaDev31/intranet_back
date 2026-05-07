<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;

class ReasonDeleteCotizacion extends Model
{
    protected $table = 'reason_delete_cotizacion';

    protected $fillable = [
        'name',
    ];
}

