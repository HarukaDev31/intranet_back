<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credencial de la API de integración de UNA organización.
 *
 * @property int $id
 * @property int $organizacion_id
 * @property string $nombre
 * @property string $token_hash
 * @property string $token_prefix
 * @property array<int, string> $scopes
 * @property int|null $id_usuario_bot
 * @property int $rate_limit_per_minute
 * @property int $daily_quota
 * @property bool $activo
 */
class ApiClient extends Model
{
    protected $table = 'api_clients';

    protected $fillable = [
        'organizacion_id',
        'nombre',
        'token_hash',
        'token_prefix',
        'scopes',
        'id_usuario_bot',
        'rate_limit_per_minute',
        'daily_quota',
        'activo',
        'revoked_at',
        'last_used_at',
        'last_used_ip',
        'created_by',
    ];

    /** Nunca se serializa el hash del token. */
    protected $hidden = ['token_hash'];

    protected $casts = [
        'organizacion_id' => 'integer',
        'scopes' => 'array',
        'id_usuario_bot' => 'integer',
        'rate_limit_per_minute' => 'integer',
        'daily_quota' => 'integer',
        'activo' => 'boolean',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'ID_Organizacion');
    }

    public function estaVigente(): bool
    {
        return $this->activo && $this->revoked_at === null;
    }

    public function tieneScope(string $scope): bool
    {
        return in_array($scope, (array) $this->scopes, true);
    }
}
