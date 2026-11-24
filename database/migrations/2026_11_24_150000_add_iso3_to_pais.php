<?php

use App\Models\Pais;
use App\Models\PaisFlag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Código internacional del país (ISO 3166-1 alfa-3), para los códigos de proveedor y el rotulado de los socios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pais')) {
            return;
        }

        if (!Schema::hasColumn('pais', 'iso3')) {
            Schema::table('pais', function (Blueprint $table) {
                $table->char('iso3', 3)->nullable()
                    ->comment('Código internacional ISO 3166-1 alfa-3 (PER, ECU, BOL). Prefijo de los códigos de proveedor de socios');
            });
        }

        $iso2PorPais = Schema::hasTable('pais_flags')
            ? DB::table('pais_flags')->whereNotNull('id_pais')->pluck('iso2', 'id_pais')
            : collect();

        foreach (DB::table('pais')->whereNull('iso3')->get(['ID_Pais', 'No_Pais']) as $pais) {
            $iso2 = (string) ($iso2PorPais[$pais->ID_Pais] ?? PaisFlag::isoDesdeNombre($pais->No_Pais));
            $iso3 = Pais::iso3DesdeIso2($iso2);
            if ($iso3 !== null) {
                DB::table('pais')->where('ID_Pais', $pais->ID_Pais)->update(['iso3' => $iso3]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pais', 'iso3')) {
            Schema::table('pais', function (Blueprint $table) {
                $table->dropColumn('iso3');
            });
        }
    }
};
