<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Catálogo de entidades para el módulo de trámites aduana (independiente de regulaciones).
     */
    public function up(): void
    {
        Schema::create('tramite_aduana_entidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tramite_aduana_entidades');
    }
};
