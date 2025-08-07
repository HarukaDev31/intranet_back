<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlmacenDocumentacion extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'contenedor_consolidado_almacen_documentacion';
    public $timestamps = false;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'id_cotizacion',
        'id_proveedor',
        'media_id',
        'file_name',
        'file_path',
        'file_type',
        'last_modified',
        'file_size',
        'file_ext'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'last_modified' => 'datetime'
    ];

    /**
     * Obtiene la cotización asociada a esta documentación.
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /**
     * Obtiene el proveedor asociado a esta documentación.
     */
    public function proveedor()
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }
} 