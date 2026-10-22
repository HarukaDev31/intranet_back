<?php

use App\Http\Controllers\Commons\PaisController;
use App\Models\PaisFlag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CFG-26-0041: el registro de clientes necesita ofrecer más países en el selector
 * de WhatsApp (ej. Corea del Sur +82). /api/options/paises solo devuelve países con
 * pais_flags.phone_code, así que se agregan los que faltan en pais y pais_flags.
 * Es idempotente: reutiliza el país si ya existe (por nombre) y no pisa phone_code ya cargado.
 */
class SeedPaisesFaltantesConPhoneCode extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('pais') || !Schema::hasTable('pais_flags') || !Schema::hasColumn('pais_flags', 'phone_code')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $existentes = DB::table('pais')->select('ID_Pais', 'No_Pais')->get();

        foreach ($this->paises() as $iso => $data) {
            $idPais = null;
            foreach ($existentes as $fila) {
                if (PaisFlag::isoDesdeNombre($fila->No_Pais) === $iso) {
                    $idPais = (int) $fila->ID_Pais;
                    break;
                }
            }
            if ($idPais === null) {
                $idPais = (int) DB::table('pais')->insertGetId($this->filaPais($data['nombre']), 'ID_Pais');
            }

            $flag = DB::table('pais_flags')->where('id_pais', $idPais)->first();
            if (!$flag) {
                $flag = DB::table('pais_flags')->where('iso2', $iso)->whereNull('id_pais')->first();
            }

            if ($flag) {
                $update = ['id_pais' => $idPais, 'updated_at' => $now];
                if ((string) $flag->phone_code === '') {
                    $update['phone_code'] = $data['code'];
                }
                DB::table('pais_flags')->where('id', $flag->id)->update($update);
            } else {
                DB::table('pais_flags')->insert([
                    'id_pais' => $idPais,
                    'iso2' => $iso,
                    'phone_code' => $data['code'],
                    'nombre' => $data['nombre'],
                    'flag_url' => PaisFlag::flagCdnUrl($iso),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        try {
            PaisController::forgetPaisesCache();
        } catch (\Throwable $e) {
            // la caché expira sola (7 días); no bloquea la migración
        }
    }

    /**
     * pais tiene columnas NOT NULL sin default (ej. Nu_Codigo_Sunat_Pais): se rellenan con un valor neutro.
     *
     * @return array<string, mixed>
     */
    private function filaPais($nombre)
    {
        $fila = ['No_Pais' => $nombre];
        $cols = DB::select(
            "SELECT COLUMN_NAME AS nombre, DATA_TYPE AS tipo FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pais'
             AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE '%auto_increment%'"
        );
        foreach ($cols as $col) {
            if ($col->nombre === 'No_Pais' || isset($fila[$col->nombre])) {
                continue;
            }
            if (in_array($col->tipo, ['datetime', 'timestamp'], true)) {
                $fila[$col->nombre] = date('Y-m-d H:i:s');
            } elseif ($col->tipo === 'date') {
                $fila[$col->nombre] = date('Y-m-d');
            } elseif (in_array($col->tipo, ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'], true)) {
                $fila[$col->nombre] = '';
            } else {
                $fila[$col->nombre] = 0;
            }
        }

        return $fila;
    }

    public function down()
    {
        // No se borran países: pueden estar referenciados por usuarios o empresas.
    }

    /**
     * @return array<string, array{nombre: string, code: string}>
     */
    private function paises()
    {
        return [
            'kr' => ['nombre' => 'Corea del Sur', 'code' => '82'],
            'us' => ['nombre' => 'Estados Unidos', 'code' => '1'],
            'jp' => ['nombre' => 'Japón', 'code' => '81'],
            'in' => ['nombre' => 'India', 'code' => '91'],
            'hk' => ['nombre' => 'Hong Kong', 'code' => '852'],
            'tw' => ['nombre' => 'Taiwán', 'code' => '886'],
            'th' => ['nombre' => 'Tailandia', 'code' => '66'],
            'vn' => ['nombre' => 'Vietnam', 'code' => '84'],
            'id' => ['nombre' => 'Indonesia', 'code' => '62'],
            'ph' => ['nombre' => 'Filipinas', 'code' => '63'],
            'my' => ['nombre' => 'Malasia', 'code' => '60'],
            'sg' => ['nombre' => 'Singapur', 'code' => '65'],
            'ae' => ['nombre' => 'Emiratos Árabes Unidos', 'code' => '971'],
            'tr' => ['nombre' => 'Turquía', 'code' => '90'],
            'au' => ['nombre' => 'Australia', 'code' => '61'],
            'de' => ['nombre' => 'Alemania', 'code' => '49'],
            'fr' => ['nombre' => 'Francia', 'code' => '33'],
            'it' => ['nombre' => 'Italia', 'code' => '39'],
            'gb' => ['nombre' => 'Reino Unido', 'code' => '44'],
            'pt' => ['nombre' => 'Portugal', 'code' => '351'],
            'nl' => ['nombre' => 'Países Bajos', 'code' => '31'],
        ];
    }
}
