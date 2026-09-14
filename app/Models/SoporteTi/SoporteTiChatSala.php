<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read SoporteTiSolicitud|null $solicitud
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SoporteTiMensaje> $mensajes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SoporteTiChatMiembro> $miembros
 */
class SoporteTiChatSala extends Model
{
    protected $table = 'soporte_ti_chat_salas';

    protected $fillable = [
        'chat_uuid',
        'solicitud_id',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SoporteTiSolicitud::class, 'solicitud_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(SoporteTiMensaje::class, 'sala_id');
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(SoporteTiChatMiembro::class, 'sala_id');
    }
}
