<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Añade id_categoria (FK a tramite_aduana_categorias), migra datos desde categoria string y elimina categoria.
     */
    public function up(): void
    {
        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_categoria')->nullable()->after('id_tramite');
        });

        // Crear categorías únicas (id_tramite, categoria) e insertar en tramite_aduana_categorias
        $pares = DB::table('tramite_aduana_documentos')
            ->select('id_tramite', 'categoria')
            ->distinct()
            ->get();

        foreach ($pares as $par) {
            $idCategoria = DB::table('tramite_aduana_categorias')->insertGetId([
                'id_tramite' => $par->id_tramite,
                'nombre' => $par->categoria,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('tramite_aduana_documentos')
                ->where('id_tramite', $par->id_tramite)
                ->where('categoria', $par->categoria)
                ->update(['id_categoria' => $idCategoria]);
        }

        // Quitar índice de categoria si existe (Laravel lo nombra así)
        try {
            Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
                $table->dropIndex('tramite_aduana_documentos_categoria_index');
            });
        } catch (\Throwable $e) {
            // Ignorar si el índice no existe o tiene otro nombre
        }

        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });

        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_categoria')->nullable(false)->change();
            $table->foreign('id_categoria')
                ->references('id')
                ->on('tramite_aduana_categorias')
                ->onDelete('cascade');
            $table->index('id_categoria');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->dropForeign(['id_categoria']);
            $table->dropIndex(['id_categoria']);
        });

        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->string('categoria', 255)->after('id_tramite');
        });

        $documentos = DB::table('tramite_aduana_documentos')
            ->join('tramite_aduana_categorias', 'tramite_aduana_documentos.id_categoria', '=', 'tramite_aduana_categorias.id')
            ->select('tramite_aduana_documentos.id', 'tramite_aduana_categorias.nombre as categoria')
            ->get();

        foreach ($documentos as $doc) {
            DB::table('tramite_aduana_documentos')
                ->where('id', $doc->id)
                ->update(['categoria' => $doc->categoria]);
        }

        Schema::table('tramite_aduana_documentos', function (Blueprint $table) {
            $table->dropColumn('id_categoria');
            $table->index('categoria');
        });
    }
};
