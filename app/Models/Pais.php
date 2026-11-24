<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $ID_Pais
 * @property string|null $No_Pais
 * @property string|null $iso3
 * @property-read PaisFlag|null $flag
 */
class Pais extends Model
{
    protected $table = 'pais';
    protected $primaryKey = 'ID_Pais';
    
    protected $fillable = [
        'No_Pais',
        'iso3',
    ];

    /** ISO 3166-1: alfa-2 → alfa-3 de los países que maneja la intranet (PaisFlag::isoDesdeNombre). */
    private const ISO3 = [
        'ae' => 'ARE', 'ar' => 'ARG', 'au' => 'AUS', 'be' => 'BEL', 'bo' => 'BOL', 'br' => 'BRA',
        'ca' => 'CAN', 'ch' => 'CHE', 'cl' => 'CHL', 'cn' => 'CHN', 'co' => 'COL', 'cr' => 'CRI',
        'cu' => 'CUB', 'de' => 'DEU', 'dk' => 'DNK', 'do' => 'DOM', 'ec' => 'ECU', 'eg' => 'EGY',
        'es' => 'ESP', 'fr' => 'FRA', 'gb' => 'GBR', 'gt' => 'GTM', 'hk' => 'HKG', 'hn' => 'HND',
        'id' => 'IDN', 'in' => 'IND', 'it' => 'ITA', 'jp' => 'JPN', 'kp' => 'PRK', 'kr' => 'KOR',
        'ma' => 'MAR', 'mx' => 'MEX', 'my' => 'MYS', 'ni' => 'NIC', 'nl' => 'NLD', 'no' => 'NOR',
        'nz' => 'NZL', 'pa' => 'PAN', 'pe' => 'PER', 'ph' => 'PHL', 'pr' => 'PRI', 'pt' => 'PRT',
        'py' => 'PRY', 'ru' => 'RUS', 'sa' => 'SAU', 'se' => 'SWE', 'sg' => 'SGP', 'sv' => 'SLV',
        'th' => 'THA', 'tr' => 'TUR', 'tw' => 'TWN', 'ua' => 'UKR', 'us' => 'USA', 'uy' => 'URY',
        've' => 'VEN', 'vn' => 'VNM', 'za' => 'ZAF',
    ];

    public static function iso3DesdeIso2(?string $iso2): ?string
    {
        return self::ISO3[strtolower(trim((string) $iso2))] ?? null;
    }

    /**
     * Código internacional guardado del país con ese ISO-2. Cadena vacía si no tiene.
     */
    public static function iso3PorIso2(?string $iso2): string
    {
        $pais = self::query()
            ->whereHas('flag', fn ($q) => $q->where('iso2', strtolower(trim((string) $iso2))))
            ->orderBy('ID_Pais')
            ->first();

        return strtoupper(trim((string) ($pais->iso3 ?? '')));
    }

    public function flag(): HasOne
    {
        return $this->hasOne(PaisFlag::class, 'id_pais', 'ID_Pais');
    }

    /**
     * Relación con Empresa
     */
    public function empresas()
    {
        return $this->hasMany(Empresa::class, 'ID_Pais', 'ID_Pais');
    }

    /**
     * Relación con User
     */
    public function users()
    {
        return $this->hasMany(User::class, 'ID_Pais', 'ID_Pais');
    }
} 