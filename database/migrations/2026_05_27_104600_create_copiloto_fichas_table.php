<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCopilotoFichasTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('copiloto_fichas')) {
        Schema::create('copiloto_fichas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('phone', 20)->index();
            $table->unsignedTinyInteger('temperatura')->default(0);
            $table->enum('nivel', ['caliente', 'tibio', 'enfriando', 'frio'])->default('frio');
            $table->json('senales')->nullable();
            $table->text('objecion')->nullable();
            $table->text('sugerencia')->nullable();
            $table->string('sugerencia_corta', 255)->nullable();
            $table->unsignedBigInteger('bitrix_contact_id')->nullable();
            $table->timestamps();

            $table->index(['phone', 'created_at'], 'idx_copiloto_fichas_phone_created_at');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('copiloto_fichas');
    }
}

