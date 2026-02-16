<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TramiteAduanaTipoPermiso extends Model
{
    use SoftDeletes;

    protected $table = 'tramite_aduana_tipos_permiso';

    const UPDATED_AT = null;

    protected $fillable = ['nombre'];

    protected $dates = ['created_at', 'deleted_at'];
}
