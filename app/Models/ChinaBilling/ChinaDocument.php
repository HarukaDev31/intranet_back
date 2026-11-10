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
 */
class ChinaDocument extends Model
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_BALANCE = 'balance';

    protected $table = 'china_documents';

    protected $fillable = [
        'type', 'organization_id', 'operation_id', 'issued_on', 'snapshot', 'path', 'file_name', 'generated_by', 'generated_by_name',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'snapshot' => 'array',
    ];
}
