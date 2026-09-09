<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

/**
 * Documentos por proveedor (perfil cotizador), hasta 4 por proveedor.
 * Tabla: contenedor_consolidado_cotizacion_cotizador_proveedor_documentos
 */
class CotizacionCotizadorProveedorDocumento extends Model
{
    use HasFactory;
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_cotizacion_cotizador_proveedor_documentos';

    protected static function organizacionRelacion(): string
    {
        return 'cotizacion';
    }

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
