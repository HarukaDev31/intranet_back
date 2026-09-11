<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

/**
 * @property int $id
 * @property int $id_contenedor
 * @property float|string|null $tc_yuan
 */
class ContenedorTcYuan extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'carga_consolidada_contenedor_tc_yuan';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }

    protected $fillable = [
        'id_contenedor',
        'tc_yuan',
    ];

    protected $casts = [
        'tc_yuan' => 'decimal:8',
    ];

    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }
}
