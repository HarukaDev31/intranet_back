<?php

namespace App\Models\WhatsappInbox;

use App\Support\WhatsApp\WaInboxSecretCrypt;
use Illuminate\Database\Eloquent\Model;

class WaInboxOrganizacionConfig extends Model
{
    protected $table = 'wa_inbox_organizacion_config';

    protected $fillable = [
        'organizacion_id',
        'enabled',
        'access_token',
        'phone_number_id',
        'app_secret',
        'webhook_verify_token',
        'waba_id',
        'graph_api_version',
        'default_language',
        'display_number',
        'legacy_fallback',
        'preview_from_template',
        'session_when_window_open',
    ];

    protected $hidden = [
        'access_token',
        'app_secret',
        'webhook_verify_token',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'legacy_fallback' => 'boolean',
        'preview_from_template' => 'boolean',
        'session_when_window_open' => 'boolean',
        'organizacion_id' => 'integer',
    ];

    public function getAccessTokenAttribute($value)
    {
        return WaInboxSecretCrypt::decrypt($value);
    }

    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = WaInboxSecretCrypt::encrypt($value);
    }

    public function getAppSecretAttribute($value)
    {
        return WaInboxSecretCrypt::decrypt($value);
    }

    public function setAppSecretAttribute($value)
    {
        $this->attributes['app_secret'] = WaInboxSecretCrypt::encrypt($value);
    }

    public function getWebhookVerifyTokenAttribute($value)
    {
        return WaInboxSecretCrypt::decrypt($value);
    }

    public function setWebhookVerifyTokenAttribute($value)
    {
        $this->attributes['webhook_verify_token'] = WaInboxSecretCrypt::encrypt($value);
    }
}
