<?php

namespace App\Models\ChinaBilling;

use Illuminate\Database\Eloquent\Model;

/**
 * Cliente de Almacén China. Los socios van ligados a una organización; los importadores no.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $type
 * @property string $name
 * @property string|null $country
 * @property string|null $whatsapp
 * @property string|null $contact_name
 * @property string|null $email
 * @property array<string, mixed>|null $prices
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ChinaClient extends Model
{
    public const TYPE_PARTNER = 'partner';
    public const TYPE_IMPORTER = 'importer';

    /** Tarifas que se piden al socio (USD por CBM del consolidado). */
    public const PRICE_FIELDS = ['consol', 'consol_2'];

    protected $table = 'china_clients';

    protected $fillable = ['organization_id', 'type', 'name', 'country', 'whatsapp', 'contact_name', 'email', 'prices', 'created_by'];

    protected $casts = ['prices' => 'array'];
}
