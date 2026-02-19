<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TramiteAduanaCategoria extends Model
{
    protected $table = 'tramite_aduana_categorias';

    /** Categorías por defecto para "Documentos para tramite" (por cada tipo_permiso). */
    public const NOMBRES_DOCUMENTOS_TRAMITE = [
        'Factura comercial',
        'Ficha técnica',
    ];

    /** Slots de seguimiento por tipo_permiso (cada tab tiene los suyos). */
    public const NOMBRES_SEGUIMIENTO_POR_TIPO = [
        'Expediente o CPB',
        'Decreto resolutivo',
        'Hoja resumen',
    ];

    /** Único slot de seguimiento compartido (RH es el mismo para todos los permisos). */
    public const NOMBRE_SEGUIMIENTO_COMPARTIDO = 'RH o Factura del tramitador';

    /** Categoría única para documentos de pago (voucher) del trámite. */
    public const NOMBRE_PAGO_SERVICIO = 'Pago servicio';

    public const SECCION_DOCUMENTOS_TRAMITE = 'documentos_tramite';
    public const SECCION_PAGO_SERVICIO = 'pago_servicio';
    public const SECCION_SEGUIMIENTO = 'seguimiento';

    protected $fillable = [
        'id_tramite',
        'nombre',
        'seccion',
        'id_tipo_permiso',
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
     * Crea todas las categorías necesarias al crear un trámite con sus tipos_permiso:
     * - Por cada tipo_permiso: documentos_tramite (Factura comercial, Ficha técnica) y seguimiento por tipo (Expediente, Decreto, Hoja resumen).
     * - Una sola categoría compartida: RH o Factura del tramitador (seguimiento, id_tipo_permiso null).
     */
    public static function crearCategoriasParaTramiteConTipos(int $idTramite, array $tipoPermisoIds): void
    {
        $seccionDoc = self::SECCION_DOCUMENTOS_TRAMITE;
        $seccionSeg = self::SECCION_SEGUIMIENTO;

        foreach ($tipoPermisoIds as $idTipo) {
            foreach (self::NOMBRES_DOCUMENTOS_TRAMITE as $nombre) {
                self::firstOrCreate([
                    'id_tramite'      => $idTramite,
                    'nombre'          => $nombre,
                    'seccion'         => $seccionDoc,
                    'id_tipo_permiso' => $idTipo,
                ], ['id_tramite' => $idTramite, 'nombre' => $nombre, 'seccion' => $seccionDoc, 'id_tipo_permiso' => $idTipo]);
            }
            foreach (self::NOMBRES_SEGUIMIENTO_POR_TIPO as $nombre) {
                self::firstOrCreate([
                    'id_tramite'      => $idTramite,
                    'nombre'          => $nombre,
                    'seccion'         => $seccionSeg,
                    'id_tipo_permiso' => $idTipo,
                ], ['id_tramite' => $idTramite, 'nombre' => $nombre, 'seccion' => $seccionSeg, 'id_tipo_permiso' => $idTipo]);
            }
        }

        self::firstOrCreate([
            'id_tramite'      => $idTramite,
            'nombre'          => self::NOMBRE_SEGUIMIENTO_COMPARTIDO,
            'seccion'         => $seccionSeg,
            'id_tipo_permiso' => null,
        ], ['id_tramite' => $idTramite, 'nombre' => self::NOMBRE_SEGUIMIENTO_COMPARTIDO, 'seccion' => $seccionSeg, 'id_tipo_permiso' => null]);

        $seccionPago = self::SECCION_PAGO_SERVICIO;
        self::firstOrCreate([
            'id_tramite'      => $idTramite,
            'nombre'          => self::NOMBRE_PAGO_SERVICIO,
            'seccion'         => $seccionPago,
            'id_tipo_permiso' => null,
        ], ['id_tramite' => $idTramite, 'nombre' => self::NOMBRE_PAGO_SERVICIO, 'seccion' => $seccionPago, 'id_tipo_permiso' => null]);
    }

    /**
     * Asegura que existan categorías para los tipos actuales del trámite (p. ej. tras añadir un nuevo tipo al editar).
     */
    public static function asegurarCategoriasParaTramite(int $idTramite, array $tipoPermisoIds): void
    {
        self::crearCategoriasParaTramiteConTipos($idTramite, $tipoPermisoIds);
    }
}
