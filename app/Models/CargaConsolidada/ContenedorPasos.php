<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContenedorPasos extends Model
{
    use HasFactory;
    protected $table = 'contenedor_consolidado_order_steps';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'contenedor_id');
    }
}
