<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaisFlag extends Model
{
    protected $table = 'pais_flags';

    protected $fillable = [
        'id_pais',
        'iso2',
        'nombre',
        'flag_url',
    ];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'id_pais', 'ID_Pais');
    }

    /**
     * URL SVG de flagcdn.com (bandera real del ISO-2).
     */
    public static function flagCdnUrl($iso2)
    {
        $iso2 = strtolower(trim((string) $iso2));
        if ($iso2 === '' || strlen($iso2) !== 2) {
            return null;
        }

        return 'https://flagcdn.com/' . $iso2 . '.svg';
    }

    public static function urlForPaisId($idPais)
    {
        if (!$idPais) {
            return null;
        }

        $row = static::where('id_pais', $idPais)->first();

        return $row && $row->flag_url ? $row->flag_url : null;
    }

    public static function urlForIso($iso2)
    {
        $iso2 = strtolower(trim((string) $iso2));
        if ($iso2 === '') {
            return null;
        }

        $row = static::where('iso2', $iso2)->first();
        if ($row && $row->flag_url) {
            return $row->flag_url;
        }

        return static::flagCdnUrl($iso2);
    }

    public static function normalizarNombre($nombre)
    {
        $nombre = trim((string) $nombre);
        $repl = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
            'ü' => 'u', 'Ü' => 'u',
        ];

        return strtolower(strtr($nombre, $repl));
    }

    /**
     * ISO-2 a partir del nombre en español (o inglés común).
     */
    public static function isoDesdeNombre($nombre)
    {
        $map = [
            'china' => 'cn',
            'republica popular china' => 'cn',
            'peoples republic of china' => 'cn',
            'prc' => 'cn',
            'peru' => 'pe',
            'ecuador' => 'ec',
            'colombia' => 'co',
            'chile' => 'cl',
            'mexico' => 'mx',
            'bolivia' => 'bo',
            'argentina' => 'ar',
            'brasil' => 'br',
            'brazil' => 'br',
            'venezuela' => 've',
            'panama' => 'pa',
            'paraguay' => 'py',
            'uruguay' => 'uy',
            'estados unidos' => 'us',
            'eeuu' => 'us',
            'ee.uu.' => 'us',
            'usa' => 'us',
            'united states' => 'us',
            'espana' => 'es',
            'spain' => 'es',
            'costa rica' => 'cr',
            'guatemala' => 'gt',
            'honduras' => 'hn',
            'el salvador' => 'sv',
            'nicaragua' => 'ni',
            'republica dominicana' => 'do',
            'cuba' => 'cu',
            'puerto rico' => 'pr',
            'canada' => 'ca',
            'alemania' => 'de',
            'francia' => 'fr',
            'italia' => 'it',
            'reino unido' => 'gb',
            'inglaterra' => 'gb',
            'japon' => 'jp',
            'corea del sur' => 'kr',
            'india' => 'in',
            'australia' => 'au',
            'nueva zelanda' => 'nz',
            'portugal' => 'pt',
            'holanda' => 'nl',
            'paises bajos' => 'nl',
            'suiza' => 'ch',
            'belgica' => 'be',
            'suecia' => 'se',
            'noruega' => 'no',
            'dinamarca' => 'dk',
            'rusia' => 'ru',
            'ucrania' => 'ua',
            'turquia' => 'tr',
            'emiratos arabes unidos' => 'ae',
            'arabia saudita' => 'sa',
            'sudafrica' => 'za',
            'egipto' => 'eg',
            'marruecos' => 'ma',
            'tailandia' => 'th',
            'vietnam' => 'vn',
            'indonesia' => 'id',
            'filipinas' => 'ph',
            'malasia' => 'my',
            'singapur' => 'sg',
            'hong kong' => 'hk',
            'taiwan' => 'tw',
            'corea del norte' => 'kp',
        ];

        $key = static::normalizarNombre($nombre);

        return isset($map[$key]) ? $map[$key] : null;
    }
}
