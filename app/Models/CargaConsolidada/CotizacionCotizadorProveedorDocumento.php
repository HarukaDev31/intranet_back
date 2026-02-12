<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Documentos por proveedor (perfil cotizador), hasta 4 por proveedor.
 * Tabla: contenedor_consolidado_cotizacion_cotizador_proveedor_documentos
 */
class CotizacionCotizadorProveedorDocumento extends Model
{
    use HasFactory;

    protected $table = 'contenedor_consolidado_cotizacion_cotizador_proveedor_documentos';

    protected $fillable = [
        'id_cotizacion',
        'id_proveedor',
        'file_url',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    /**
     * Cotización asociada (contenedor_consolidado_cotizacion).
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /**
     * Proveedor asociado (contenedor_consolidado_cotizacion_proveedores).
     */
    public function proveedor()
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }
}
