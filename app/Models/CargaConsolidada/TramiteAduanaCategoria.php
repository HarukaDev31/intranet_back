<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TramiteAduanaCategoria extends Model
{
    protected $table = 'tramite_aduana_categorias';

    /** Las 3 categorías (carpetas) que se crean por defecto en cada trámite. */
    public const NOMBRES_POR_DEFECTO = [
        'Documentos para tramite',
        'CPB de tramite',
        'Documento resolutivo',
    ];

    protected $fillable = [
        'id_tramite',
        'nombre',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(ConsolidadoCotizacionAduanaTramite::class, 'id_tramite');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(TramiteAduanaDocumento::class, 'id_categoria');
    }

    /**
     * Crea las 3 categorías por defecto para un trámite (si no existen).
     */
    public static function crearPorDefectoParaTramite(int $idTramite): void
    {
        foreach (self::NOMBRES_POR_DEFECTO as $nombre) {
            self::firstOrCreate(
                [
                    'id_tramite' => $idTramite,
                    'nombre' => $nombre,
                ]
            );
        }
    }
}
