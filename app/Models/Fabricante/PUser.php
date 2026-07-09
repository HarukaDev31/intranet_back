<?php

namespace App\Models\Fabricante;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PUser extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    protected $table = 'p_user';

    protected $fillable = [
        'email',
        'password',
        'company_name',
        'contact_name',
        'phone',
        'country',
        'avatar_url',
        'firebase_uid',
        'auth_provider',
        'email_verified_at',
        'email_verification_token',
        'email_verification_sent_at',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'email_verification_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(PUserSession::class, 'user_id');
    }

    public function activeSessions(): HasMany
    {
        return $this->sessions()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isEmailProvider(): bool
    {
        return $this->auth_provider === 'email';
    }
}
