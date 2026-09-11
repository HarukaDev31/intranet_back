<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

/**
 * Auditoria del archivo subido y de lo que la IA extrajo de el, para poder
 * depurar/reprocesar una extraccion sin depender de lo que haya quedado (o
 * se haya editado a mano) en CotizacionProveedorResumen.
 *
 * @property int $id
 * @property int|null $id_contenedor
 * @property int $id_cotizacion
 * @property int $id_proveedor
 * @property int|null $organizacion_id
 */
class CotizacionProveedorArchivoIa extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'cotizacion_proveedor_archivo_ia';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    protected $fillable = [
        'id_contenedor',
        'id_cotizacion',
        'id_proveedor',
        'archivo_path',
        'archivo_nombre_original',
        'estado',
        'data_extraida_json',
        'editado_manualmente',
        'id_usuario_creador',
    ];

    protected $casts = [
        'data_extraida_json' => 'array',
        'editado_manualmente' => 'boolean',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(CotizacionProveedor::class, 'id_proveedor');
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }
}
