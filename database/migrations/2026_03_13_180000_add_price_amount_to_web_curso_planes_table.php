<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_curso_planes', function (Blueprint $table) {
            $table->unsignedInteger('price_amount')
                  ->nullable()
                  ->after('price_original')
                  ->comment('Monto en PEN (entero). CI multiplica x100 para Izipay céntimos.');
        });
    }

    public function down(): void
    {
        Schema::table('web_curso_planes', function (Blueprint $table) {
            $table->dropColumn('price_amount');
        });
    }
};
