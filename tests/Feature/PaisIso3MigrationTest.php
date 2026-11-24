<?php

namespace Tests\Feature;

use App\Models\Pais;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaisIso3MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');

        Schema::create('pais', function (Blueprint $table) {
            $table->increments('ID_Pais');
            $table->string('No_Pais');
        });
        Schema::create('pais_flags', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_pais')->nullable();
            $table->char('iso2', 2);
        });
        DB::table('pais')->insert([
            ['ID_Pais' => 1, 'No_Pais' => 'PERÚ'],
            ['ID_Pais' => 2, 'No_Pais' => 'Ecuador'],
            ['ID_Pais' => 3, 'No_Pais' => 'Chile'],
            ['ID_Pais' => 4, 'No_Pais' => 'Desconocido'],
        ]);
        DB::table('pais_flags')->insert([['id_pais' => 2, 'iso2' => 'ec'], ['id_pais' => null, 'iso2' => 'bo']]);
    }

    public function test_llena_el_codigo_internacional_y_se_puede_repetir(): void
    {
        $migracion = require database_path('migrations/2026_11_24_150000_add_iso3_to_pais.php');
        $migracion->up();
        DB::table('pais')->where('ID_Pais', 3)->update(['iso3' => 'XXX']);
        $migracion->up();

        $this->assertSame(
            [1 => 'PER', 2 => 'ECU', 3 => 'XXX', 4 => null],
            DB::table('pais')->orderBy('ID_Pais')->pluck('iso3', 'ID_Pais')->all()
        );
        $this->assertSame('ECU', Pais::iso3PorIso2('EC'));
        $this->assertSame('', Pais::iso3PorIso2('bo'));

        $migracion->down();
        $this->assertFalse(Schema::hasColumn('pais', 'iso3'));
    }
}
