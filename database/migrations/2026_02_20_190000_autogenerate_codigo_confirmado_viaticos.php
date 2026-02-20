<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Asigna codigo_confirmado (VI + año + índice) a viáticos ya CONFIRMED
     * que no tienen código. Respeta códigos existentes por año (el índice sigue al máximo ya usado).
     * Orden: por año de updated_at y por id.
     */
    public function up(): void
    {
        $confirmedSinCodigo = DB::table('viaticos')
            ->where('status', 'CONFIRMED')
            ->where(function ($q) {
                $q->whereNull('codigo_confirmado')
                  ->orWhere('codigo_confirmado', '');
            })
            ->orderByRaw('YEAR(updated_at) ASC, id ASC')
            ->get(['id', 'updated_at']);

        $indicesPorAnio = [];

        foreach ($confirmedSinCodigo as $viatico) {
            $year = date('Y', strtotime($viatico->updated_at));
            if (!isset($indicesPorAnio[$year])) {
                $maxExistente = DB::table('viaticos')
                    ->where('codigo_confirmado', 'like', 'VI' . $year . '%')
                    ->whereNotNull('codigo_confirmado')
                    ->where('codigo_confirmado', '!=', '')
                    ->count();
                $indicesPorAnio[$year] = $maxExistente;
            }
            $indicesPorAnio[$year]++;
            $codigo = 'VI' . $year . str_pad((string) $indicesPorAnio[$year], 3, '0', STR_PAD_LEFT);

            DB::table('viaticos')->where('id', $viatico->id)->update(['codigo_confirmado' => $codigo]);
        }
    }

    /**
     * No revertir: los códigos asignados pueden estar en uso.
     */
    public function down(): void
    {
        //
    }
};
