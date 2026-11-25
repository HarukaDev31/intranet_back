<?php

namespace App\Models\ChinaCaja;

use Illuminate\Database\Eloquent\Model;

/**
 * Reserva, BL y telex de un consolidado (Etapas y Estado de contenedores).
 *
 * @property int $id
 * @property int $container_id
 * @property float|null $freight_usd
 * @property string|null $filling_on
 * @property string|null $sailing_on
 * @property string|null $arrival_on
 * @property string|null $shipping_line
 * @property string|null $destination_port
 * @property string|null $free_days
 * @property string|null $container_type
 * @property string|null $shipper
 * @property bool $list_updated
 * @property string|null $bl_consignee
 * @property string|null $bl_description
 * @property string|null $bl_number
 * @property string|null $bl_draft_path
 * @property string|null $bl_draft_name
 * @property string|null $bl_official_path
 * @property string|null $bl_official_name
 * @property bool $telex
 * @property int|null $updated_by
 */
class ChinaContainerStage extends Model
{
    protected $table = 'china_container_stages';

    protected $fillable = [
        'container_id',
        'freight_usd',
        'filling_on',
        'sailing_on',
        'arrival_on',
        'shipping_line',
        'destination_port',
        'free_days',
        'container_type',
        'shipper',
        'list_updated',
        'bl_consignee',
        'bl_description',
        'bl_number',
        'bl_draft_path',
        'bl_draft_name',
        'bl_official_path',
        'bl_official_name',
        'telex',
        'updated_by',
    ];

    protected $casts = [
        'freight_usd' => 'float',
        'list_updated' => 'boolean',
        'telex' => 'boolean',
    ];
}
