<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;

/**
 * Factura o balance de pago ya emitido. El snapshot conserva los datos de esa fecha,
 * así cada reenvío queda en el historial aunque la operación cambie después.
 *
 * @property int $id
 * @property string $type
 * @property int $organization_id
 * @property int|null $operation_id
 * @property \Illuminate\Support\Carbon $issued_on
 * @property array<string, mixed> $snapshot
 * @property string $path
 * @property string $file_name
 * @property string|null $generated_by_name
 * @property int|null $version
 * @property string|null $status
 * @property string|null $reason
 * @property string|null $status_reason
 * @property \Illuminate\Support\Carbon|null $status_changed_at
 * @property string|null $status_changed_by_name
 */
class ChinaDocument extends Model
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_BALANCE = 'balance';

    /** Factura: una sola vigente por operación. */
    public const STATUS_CURRENT = 'vigente';

    public const STATUS_REPLACED = 'reemplazada';

    public const STATUS_ANNULLED = 'anulada';

    protected $table = 'china_documents';

    protected $fillable = [
        'type', 'organization_id', 'operation_id', 'issued_on', 'snapshot', 'path', 'file_name', 'generated_by', 'generated_by_name',
        'version', 'status', 'reason', 'status_reason', 'status_changed_at', 'status_changed_by', 'status_changed_by_name',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'snapshot' => 'array',
        'version' => 'integer',
        'status_changed_at' => 'datetime',
    ];

    /** N° de la factura con su versión: OP-0007-v2. */
    public function number(): string
    {
        $code = (string) ($this->snapshot['operation']['code'] ?? '');

        return $this->version ? $code.'-v'.$this->version : $code;
    }
}
