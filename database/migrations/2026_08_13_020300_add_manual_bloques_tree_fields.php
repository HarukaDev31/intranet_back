<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddManualBloquesTreeFields extends Migration
{
    public function up()
    {
        Schema::table('manual_bloques', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('pagina_id');
            $table->string('clave', 160)->nullable()->after('titulo')->comment('Ruta/clave del grupo, ej: cargaconsolidada/abiertos');
            $table->index(['pagina_id', 'parent_id', 'orden'], 'manual_bloques_pagina_parent_orden_idx');
        });

        Schema::table('manual_bloques', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('manual_bloques')
                ->nullOnDelete();
        });

        // Widgets que quedaron en raíz → envolver en grupo (título + clave).
        $roots = DB::table('manual_bloques')->whereNull('parent_id')->orderBy('id')->get();
        $grupoAliases = ['grupo', 'section', 'group'];
        $now = now();

        foreach ($roots as $block) {
            $tipo = (string) $block->tipo;
            if (in_array($tipo, $grupoAliases, true)) {
                if ($tipo !== 'grupo') {
                    DB::table('manual_bloques')->where('id', $block->id)->update(['tipo' => 'grupo']);
                }
                continue;
            }

            $grupoId = DB::table('manual_bloques')->insertGetId([
                'pagina_id' => $block->pagina_id,
                'parent_id' => null,
                'tipo' => 'grupo',
                'titulo' => $block->titulo ?: 'Sección',
                'clave' => 'seccion-tmp-' . $block->id,
                'payload' => json_encode(['subtitulo' => null, 'snapshot' => []], JSON_UNESCAPED_UNICODE),
                'orden' => $block->orden,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('manual_bloques')->where('id', $grupoId)->update([
                'clave' => 'seccion-' . $grupoId,
            ]);

            DB::table('manual_bloques')->where('id', $block->id)->update([
                'parent_id' => $grupoId,
                'orden' => 1,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        Schema::table('manual_bloques', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex('manual_bloques_pagina_parent_orden_idx');
            $table->dropColumn(['parent_id', 'clave']);
        });
    }
}
