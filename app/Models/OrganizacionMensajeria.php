<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organizacion_id
 * @property int $envios_habilitados
 * @property int $rotulado_habilitado
 * @property string|null $img_rotulado_paso1
 * @property string|null $img_rotulado_paso2
 * @property string|null $img_rotulado_direccion
 */
class OrganizacionMensajeria extends Model
{
    protected $table = 'organizacion_mensajeria';

    protected $fillable = [
        'organizacion_id',
        'envios_habilitados',
        'rotulado_habilitado',
        'flujos',
        'img_rotulado_paso1',
        'img_rotulado_paso2',
        'img_rotulado_direccion',
    ];

    protected $casts = [
        'organizacion_id' => 'integer',
        'envios_habilitados' => 'boolean',
        'rotulado_habilitado' => 'boolean',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'ID_Organizacion');
    }
}
