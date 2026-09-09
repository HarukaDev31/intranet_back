<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManualUsuarioCmsTables extends Migration
{
    public function up()
    {
        Schema::create('manual_paginas', function (Blueprint $table) {
            $table->id();
            $table->string('role_slug', 64)->index();
            $table->unsignedInteger('id_grupo')->nullable()->index();
            $table->string('modulo_key', 120)->index()->comment('Ej: cargaconsolidada/abiertos');
            $table->string('titulo', 200);
            $table->string('descripcion', 500)->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('publicado')->default(true)->index();
            $table->timestamps();

            $table->unique(['role_slug', 'modulo_key'], 'manual_paginas_role_modulo_unique');
        });

        Schema::create('manual_bloques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('manual_paginas')->cascadeOnDelete();
            $table->string('tipo', 40)->index()->comment('texto, ui_toolbar, ui_filters, ui_tabs, ui_table, ui_callout, media_shot');
            $table->string('titulo', 200)->nullable();
            $table->json('payload');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['pagina_id', 'orden']);
        });

        Schema::create('manual_media', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500);
            $table->string('alt', 255)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('manual_bloques');
        Schema::dropIfExists('manual_media');
        Schema::dropIfExists('manual_paginas');
    }
}
