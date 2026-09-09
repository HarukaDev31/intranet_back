<?php

namespace App\Models\SoporteTi;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read SoporteTiEstado|null $estado
 * @property-read SoporteTiEstado|null $estadoAnterior
 * @property-read Usuario|null $usuario
 */
class SoporteTiSolicitudEstado extends Model
{
    protected $table = 'soporte_ti_solicitud_estados';

    public $timestamps = false;

    protected $fillable = [
        'solicitud_id',
        'estado_id',
        'estado_anterior_id',
        'usuario_id',
        'comentario',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function estado(): BelongsTo
    {
        return $this->belongsTo(SoporteTiEstado::class, 'estado_id');
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(SoporteTiEstado::class, 'estado_anterior_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'ID_Usuario');
    }
}
