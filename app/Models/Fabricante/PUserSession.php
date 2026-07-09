<?php

namespace App\Models\Fabricante;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PUserSession extends Model
{
    protected $table = 'p_user_session';

    protected $fillable = [
        'user_id',
        'token_hash',
        'token_prefix',
        'device_id',
        'device_name',
        'platform',
        'fcm_token',
        'ip_address',
        'user_agent',
        'last_activity_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(PUser::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
