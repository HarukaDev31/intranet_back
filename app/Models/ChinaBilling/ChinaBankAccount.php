<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;

/**
 * Cuenta bancaria de China que aparece en facturas, balances y al pagar el socio.
 *
 * @property int $id
 * @property bool $is_active
 */
class ChinaBankAccount extends Model
{
    /** Campos de datos de la cuenta (todos texto; solo la empresa es obligatoria). */
    public const FIELDS = [
        'company',
        'company_address',
        'beneficiary',
        'beneficiary_address',
        'beneficiary_country',
        'beneficiary_state',
        'beneficiary_city',
        'account_number',
        'intermediary_bank',
        'intermediary_swift',
        'intermediary_country',
        'intermediary_state',
        'intermediary_city',
        'payer_bank',
        'payer_swift',
        'payer_country',
        'payer_state',
        'payer_city',
    ];

    protected $table = 'china_bank_accounts';

    protected $fillable = [...self::FIELDS, 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
