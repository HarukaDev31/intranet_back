<?php

namespace App\Models\WhatsappInbox;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $config_id
 * @property string $role_name
 */
class WaInboxConfigRole extends Model
{
    protected $table = 'wa_inbox_config_roles';

    protected $fillable = [
        'config_id',
        'role_name',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(WaInboxOrganizacionConfig::class, 'config_id');
    }
}
