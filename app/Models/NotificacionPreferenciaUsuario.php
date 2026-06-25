<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionPreferenciaUsuario extends Model
{
    protected $table = 'notificacion_preferencia_usuario';

    protected $fillable = [
        'usuario_id',
        'notification_key',
        'canal',
        'habilitado',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Canales soportados (deben coincidir con el catálogo del frontend)
    const CANAL_MODAL = 'modal';
    const CANAL_SONIDO = 'sonido';
    const CANAL_NAVEGADOR = 'navegador';

    /**
     * Lista de canales válidos.
     *
     * @return string[]
     */
    public static function canalesValidos()
    {
        return [
            self::CANAL_MODAL,
            self::CANAL_SONIDO,
            self::CANAL_NAVEGADOR,
        ];
    }

    /**
     * Usuario dueño de la preferencia.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'ID_Usuario');
    }
}
