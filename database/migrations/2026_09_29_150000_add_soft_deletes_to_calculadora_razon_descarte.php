<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToCalculadoraRazonDescarte extends Migration
{
    public function up()
    {
        if (Schema::hasTable('calculadora_razon_descarte') && !Schema::hasColumn('calculadora_razon_descarte', 'deleted_at')) {
            Schema::table('calculadora_razon_descarte', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('calculadora_razon_descarte') && Schema::hasColumn('calculadora_razon_descarte', 'deleted_at')) {
            Schema::table('calculadora_razon_descarte', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
}
