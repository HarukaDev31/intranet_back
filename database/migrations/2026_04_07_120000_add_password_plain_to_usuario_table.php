<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega una columna para almacenar la contraseña visible del usuario interno.
     * Además, rellena registros existentes con el correo (valor actual usado operativamente).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('usuario', 'No_Password_Sin_Encriptar')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->string('No_Password_Sin_Encriptar', 255)
                    ->nullable()
                    ->after('No_Password');
            });
        }

        DB::table('usuario')
            ->where(function ($q) {
                $q->whereNull('No_Password_Sin_Encriptar')
                  ->orWhere('No_Password_Sin_Encriptar', '');
            })
            ->update([
                'No_Password_Sin_Encriptar' => DB::raw("COALESCE(NULLIF(Txt_Email, ''), No_Usuario)"),
            ]);
    }

    /**
     * Revierte el cambio eliminando la columna.
     */
    public function down(): void
    {
        if (Schema::hasColumn('usuario', 'No_Password_Sin_Encriptar')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->dropColumn('No_Password_Sin_Encriptar');
            });
        }
    }
};

