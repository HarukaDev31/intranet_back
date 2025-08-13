<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlmacenInspection extends Model
{
    protected $table = 'contenedor_consolidado_almacen_inspection';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_proveedor',
        'id_cotizacion',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'last_modified',
        'file_ext',
        'send_status'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'last_modified' => 'datetime'
    ];

    // Enums disponibles
    const SEND_STATUS = ['PENDING', 'SENDED'];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor', 'id');
    }

    public function scopePorEstadoEnvio($query, $estado)
    {
        if ($estado && $estado !== '0') {
            return $query->where('send_status', $estado);
        }
        return $query;
    }

    public function scopePorTipoArchivo($query, $tipo)
    {
        if ($tipo) {
            return $query->where('file_type', $tipo);
        }
        return $query;
    }
} 