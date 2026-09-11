<?php

use App\Models\PaisFlag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePaisFlagsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('pais_flags')) {
            Schema::create('pais_flags', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('id_pais')->nullable();
                $table->char('iso2', 2);
                $table->string('nombre', 120);
                $table->string('flag_url', 255);
                $table->timestamps();

                $table->unique('id_pais');
                $table->index('iso2');
            });
        }

        $this->seedFlags();
    }

    public function down()
    {
        Schema::dropIfExists('pais_flags');
    }

    private function seedFlags()
    {
        $now = date('Y-m-d H:i:s');
        $existentes = [];

        if (Schema::hasTable('pais')) {
            $paises = DB::table('pais')->select('ID_Pais', 'No_Pais')->get();
            foreach ($paises as $pais) {
                $iso = PaisFlag::isoDesdeNombre($pais->No_Pais);
                if (!$iso) {
                    continue;
                }
                $existentes[strtolower($iso)] = true;
                $payload = [
                    'id_pais' => $pais->ID_Pais,
                    'iso2' => $iso,
                    'nombre' => $pais->No_Pais,
                    'flag_url' => PaisFlag::flagCdnUrl($iso),
                    'updated_at' => $now,
                ];
                $ya = DB::table('pais_flags')->where('id_pais', $pais->ID_Pais)->first();
                if ($ya) {
                    DB::table('pais_flags')->where('id', $ya->id)->update($payload);
                } else {
                    $payload['created_at'] = $now;
                    DB::table('pais_flags')->insert($payload);
                }
            }
        }

        $base = [
            ['iso2' => 'cn', 'nombre' => 'China'],
            ['iso2' => 'pe', 'nombre' => 'Perú'],
            ['iso2' => 'ec', 'nombre' => 'Ecuador'],
            ['iso2' => 'co', 'nombre' => 'Colombia'],
            ['iso2' => 'cl', 'nombre' => 'Chile'],
            ['iso2' => 'mx', 'nombre' => 'México'],
            ['iso2' => 'bo', 'nombre' => 'Bolivia'],
            ['iso2' => 'ar', 'nombre' => 'Argentina'],
            ['iso2' => 'us', 'nombre' => 'Estados Unidos'],
        ];

        foreach ($base as $row) {
            $iso = $row['iso2'];
            if (isset($existentes[$iso])) {
                continue;
            }
            $yaIso = DB::table('pais_flags')->where('iso2', $iso)->whereNull('id_pais')->first();
            if ($yaIso) {
                continue;
            }
            DB::table('pais_flags')->insert([
                'id_pais' => null,
                'iso2' => $iso,
                'nombre' => $row['nombre'],
                'flag_url' => PaisFlag::flagCdnUrl($iso),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
