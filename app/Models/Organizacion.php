<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $ID_Organizacion
 * @property int|null $ID_Empresa
 * @property int|null $Nu_Estado
 * @property string|null $No_Organizacion
 * @property string|null $Txt_Organizacion
 * @property int|null $id_pais
 * @property-read OrganizacionPortal|null $portal
 * @property-read OrganizacionMensajeria|null $mensajeria
 * @property-read Pais|null $pais
 * @property-read PaisFlag|null $paisFlag
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Pais> $paisesHabilitados
 */
class Organizacion extends Model
{
    protected $table = 'organizacion';
    protected $primaryKey = 'ID_Organizacion';
    public $timestamps = false;


    protected $fillable = [
        'Nu_Estado',
        'ID_Empresa',
        'No_Organizacion',
        'Txt_Organizacion',
        'id_pais',
    ];

    /**
     * Relación con Empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Usuario
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'ID_Organizacion', 'ID_Organizacion');
    }

    /**
     * Relación con Almacen
     */
    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class, 'ID_Organizacion', 'ID_Organizacion');
    }

    /**
     * @return HasOne<OrganizacionPortal, $this>
     */
    public function portal(): HasOne
    {
        return $this->hasOne(OrganizacionPortal::class, 'organizacion_id', 'ID_Organizacion');
    }

    public function mensajeria(): HasOne
    {
        return $this->hasOne(OrganizacionMensajeria::class, 'organizacion_id', 'ID_Organizacion');
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'id_pais', 'ID_Pais');
    }

    public function paisFlag(): HasOne
    {
        return $this->hasOne(PaisFlag::class, 'id_pais', 'id_pais');
    }

    public function paisesHabilitados(): BelongsToMany
    {
        return $this->belongsToMany(
            Pais::class,
            'organizacion_paises_habilitados',
            'organizacion_id',
            'id_pais',
            'ID_Organizacion',
            'ID_Pais'
        );
    }
} 