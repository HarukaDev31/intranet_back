<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_curso_planes', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 64)->default('curso_membresia')->index();
            $table->unsignedTinyInteger('tipo_pago')->nullable()->comment('1=formToken,2=formTokenv2,3=formTokenv3 (curso_membresia)');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('price_current', 64);
            $table->string('price_original', 64)->nullable();
            $table->json('benefits');
            $table->string('button_label', 120)->default('Ir a pagar');
            $table->text('button_css_classes')->nullable();
            $table->text('card_css_classes')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['page_key', 'tipo_pago']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_curso_planes');
    }
};
