<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlmacenDocumentacion extends Model
{
    protected $table = 'contenedor_consolidado_almacen_documentacion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'last_modified',
        'file_ext'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'last_modified' => 'datetime'
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor', 'id');
    }
} 