<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pedido_curso', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cliente_importacion')->nullable()->after('Nu_Estado');
            $table->foreign('id_cliente_importacion')->references('id')->on('imports_clientes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedido_curso', function (Blueprint $table) {
            $table->dropForeign(['id_cliente_importacion']);
            $table->dropColumn('id_cliente_importacion');
        });
    }
}; 