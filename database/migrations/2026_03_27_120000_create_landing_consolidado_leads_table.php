<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_consolidado_leads', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('whatsapp', 64);
            $table->enum('proveedor', ['si', 'no', 'buscando']);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_consolidado_leads');
    }
};
