<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TramiteAduanaEntidad extends Model
{
    use SoftDeletes;

    protected $table = 'tramite_aduana_entidades';

    const UPDATED_AT = null;

    protected $fillable = ['nombre'];

    protected $dates = ['created_at', 'deleted_at'];
}
