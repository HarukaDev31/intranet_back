<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

/**
 * Documentos generales de la cotización (perfil cotizador).
 * Tabla: contenedor_consolidado_cotizacion_cotizador_documentos
 */
class CotizacionCotizadorDocumento extends Model
{
    use HasFactory;
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_cotizacion_cotizador_documentos';

    protected static function organizacionRelacion(): string
    {
        return 'cotizacion';
    }

    protected $fillable = [
        'id_cotizacion',
        'id_proveedor',
        'tipo_documento',
        'folder_name',
        'file_url',
    ];

    /**
     * Cotización asociada (contenedor_consolidado_cotizacion).
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }
}
