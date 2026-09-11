<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateOrganizacionPortales extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('organizacion_portales')) {
            Schema::create('organizacion_portales', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('organizacion_id')->unique();
                $table->string('public_key', 64)->unique();
                $table->string('url_clientes', 255)->nullable();
                $table->string('url_excel_confirmacion', 255)->nullable();
                $table->string('url_datos_proveedor', 255)->nullable();
                $table->string('drive_folder_id', 128)->nullable();
                $table->string('logo_url', 255)->nullable();
                $table->string('nombre_publico', 120)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'organizacion_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('organizacion_id')->nullable()->after('id');
            });
            DB::table('users')->whereNull('organizacion_id')->update(['organizacion_id' => 1]);
            Schema::table('users', function (Blueprint $table) {
                $table->index('organizacion_id', 'users_organizacion_id_idx');
            });
        }

        $this->seedOrganizacionUnoDesdeEnv();
    }

    public function down()
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'organizacion_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_organizacion_id_idx');
                $table->dropColumn('organizacion_id');
            });
        }

        Schema::dropIfExists('organizacion_portales');
    }

    private function seedOrganizacionUnoDesdeEnv()
    {
        if (DB::table('organizacion_portales')->where('organizacion_id', 1)->exists()) {
            return;
        }

        $urlClientes = $this->nullableEnv('APP_URL_CLIENTES') ?: 'https://clientes.probusiness.pe';

        DB::table('organizacion_portales')->insert([
            'organizacion_id' => 1,
            'public_key' => (string) Str::uuid(),
            'url_clientes' => $urlClientes,
            'url_excel_confirmacion' => $this->nullableEnv('APP_URL_EXCEL_CONFIRMACION'),
            'url_datos_proveedor' => $this->nullableEnv('APP_URL_DATOS_PROVEEDOR'),
            'drive_folder_id' => null,
            'logo_url' => null,
            'nombre_publico' => 'Probusiness',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  string  $key
     * @return string|null
     */
    private function nullableEnv($key)
    {
        $value = trim((string) env($key, ''));

        return $value !== '' ? $value : null;
    }
}
