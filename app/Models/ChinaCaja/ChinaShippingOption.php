<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;

/**
 * Naviera (line) o shipping (shipper) creado desde las Etapas.
 *
 * @property int $id
 * @property string $kind
 * @property string $name
 */
class ChinaShippingOption extends Model
{
    public const KIND_LINE = 'line';

    public const KIND_SHIPPER = 'shipper';

    protected $table = 'china_shipping_options';

    protected $fillable = [
        'kind',
        'name',
    ];
}
