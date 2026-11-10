<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;

/**
 * Cuenta bancaria de China que aparece en facturas, balances y al pagar el socio.
 *
 * @property int $id
 * @property string $company
 * @property string|null $beneficiary
 * @property string|null $intermediary_bank
 * @property string|null $payer_bank
 * @property string|null $account_number
 * @property bool $is_active
 */
class ChinaBankAccount extends Model
{
    protected $table = 'china_bank_accounts';

    protected $fillable = ['company', 'beneficiary', 'intermediary_bank', 'payer_bank', 'account_number', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
