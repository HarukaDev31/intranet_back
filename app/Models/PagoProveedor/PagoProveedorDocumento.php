<?php

namespace App\Models\PagoProveedor;

use Illuminate\Database\Eloquent\Model;

/**
 * Documento emitido (PDF) de una cotización, solicitud o proveedor.
 * Guarda la huella con la que se generó para no re-renderizar si nada cambió.
 *
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $document_type
 * @property int $scope_id
 * @property int $step
 * @property string $fingerprint
 * @property string $data_fingerprint
 * @property string $path
 * @property string|null $filename
 * @property string|null $issued_on
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property int $downloads
 */
class PagoProveedorDocumento extends Model
{
    public const ENTITY_QUOTE = 'cotizacion';
    public const ENTITY_REQUEST = 'solicitud';

    public const TYPE_SUMMARY = 'resumen';
    public const TYPE_BALANCE = 'balance';

    /** Pasos del proceso: 0 = cotización, 1 a 4 = etapas de la solicitud. */
    public const STEP_QUOTE = 0;
    public const STEP_DATA = 1;
    public const STEP_VALIDATION = 2;
    public const STEP_EXECUTION = 3;
    public const STEP_CONFIRMATION = 4;

    protected $table = 'pago_proveedor_documentos';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'document_type',
        'scope_id',
        'step',
        'fingerprint',
        'data_fingerprint',
        'path',
        'filename',
        'issued_on',
        'generated_at',
        'downloads',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'step' => 'int',
        'downloads' => 'int',
    ];

    public static function stepLabel(int $step): string
    {
        return [
            self::STEP_QUOTE => 'Cotización enviada',
            self::STEP_DATA => 'Solicitud y datos de pago',
            self::STEP_VALIDATION => 'Validación de pago',
            self::STEP_EXECUTION => 'Ejecución de pago',
            self::STEP_CONFIRMATION => 'Confirmación',
        ][$step] ?? 'Cotización enviada';
    }
}
