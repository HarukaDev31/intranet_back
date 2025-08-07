<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionDocumentacion extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'contenedor_consolidado_cotizacion_documentacion';
    public $timestamps = false;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'id_cotizacion',
        'name',
        'file_url',
        'id_proveedor'
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