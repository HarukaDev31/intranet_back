<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class ContenedorPasos extends Model
{
    use HasFactory;
    use SincronizaOrganizacionId;
    protected $table = 'contenedor_consolidado_order_steps';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'contenedor_id');
    }
}
