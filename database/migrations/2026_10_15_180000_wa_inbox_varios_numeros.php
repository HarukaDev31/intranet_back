<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WaInboxVariosNumeros extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_inbox_organizacion_config')) {
            return;
        }

        $this->dropUniqueOrganizacion();

        Schema::table('wa_inbox_organizacion_config', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_inbox_organizacion_config', 'nombre')) {
                $table->string('nombre', 80)->default('')->after('organizacion_id');
            }
            if (!Schema::hasColumn('wa_inbox_organizacion_config', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('nombre');
            }
        });

        if (!Schema::hasTable('wa_inbox_config_roles')) {
            Schema::create('wa_inbox_config_roles', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('config_id');
                $table->string('role_name', 80);
                $table->timestamps();
                $table->unique(['config_id', 'role_name'], 'wa_inbox_cfg_role_unique');
                $table->index('config_id', 'wa_inbox_cfg_role_cfg_idx');
            });
        }

        if (Schema::hasTable('wa_inbox_sessions') && !Schema::hasColumn('wa_inbox_sessions', 'config_id')) {
            Schema::table('wa_inbox_sessions', function (Blueprint $table) {
                $table->unsignedInteger('config_id')->nullable()->after('organizacion_id');
                $table->index('config_id', 'wa_inbox_sessions_cfg_idx');
            });
        }

        $this->seedLineasExistentes();
    }

    public function down()
    {
        if (Schema::hasTable('wa_inbox_sessions') && Schema::hasColumn('wa_inbox_sessions', 'config_id')) {
            Schema::table('wa_inbox_sessions', function (Blueprint $table) {
                $table->dropIndex('wa_inbox_sessions_cfg_idx');
                $table->dropColumn('config_id');
            });
        }

        Schema::dropIfExists('wa_inbox_config_roles');

        if (Schema::hasTable('wa_inbox_organizacion_config')) {
            Schema::table('wa_inbox_organizacion_config', function (Blueprint $table) {
                if (Schema::hasColumn('wa_inbox_organizacion_config', 'is_default')) {
                    $table->dropColumn('is_default');
                }
                if (Schema::hasColumn('wa_inbox_organizacion_config', 'nombre')) {
                    $table->dropColumn('nombre');
                }
            });
        }
    }

    private function dropUniqueOrganizacion()
    {
        $rows = DB::select("SHOW INDEX FROM wa_inbox_organizacion_config WHERE Column_name = 'organizacion_id'");
        $dropped = [];
        foreach ($rows as $row) {
            $name = isset($row->Key_name) ? (string) $row->Key_name : (string) $row->key_name;
            if ($name === '' || $name === 'PRIMARY' || isset($dropped[$name])) {
                continue;
            }
            $nonUnique = isset($row->Non_unique) ? (int) $row->Non_unique : (int) $row->non_unique;
            if ($nonUnique === 0) {
                DB::statement('ALTER TABLE wa_inbox_organizacion_config DROP INDEX `' . $name . '`');
                $dropped[$name] = true;
            }
        }

        $still = DB::select("SHOW INDEX FROM wa_inbox_organizacion_config WHERE Key_name = 'wa_inbox_org_cfg_org_idx'");
        if ($still === []) {
            Schema::table('wa_inbox_organizacion_config', function (Blueprint $table) {
                $table->index('organizacion_id', 'wa_inbox_org_cfg_org_idx');
            });
        }
    }

    private function seedLineasExistentes()
    {
        $rows = DB::table('wa_inbox_organizacion_config')->orderBy('id')->get();
        $defaultMarcado = [];
        foreach ($rows as $row) {
            $orgId = (int) $row->organizacion_id;
            $esDefault = !isset($defaultMarcado[$orgId]);
            $defaultMarcado[$orgId] = true;
            $nombre = trim((string) $row->nombre);
            if ($nombre === '') {
                $nombre = $orgId === 1 ? 'Coordinación' : 'WhatsApp';
            }
            DB::table('wa_inbox_organizacion_config')->where('id', $row->id)->update([
                'nombre' => $nombre,
                'is_default' => $esDefault ? 1 : 0,
            ]);

            if ($orgId === 1 && $esDefault && Schema::hasTable('wa_inbox_config_roles')) {
                $ya = DB::table('wa_inbox_config_roles')
                    ->where('config_id', $row->id)
                    ->where('role_name', 'Coordinación')
                    ->exists();
                if (!$ya) {
                    DB::table('wa_inbox_config_roles')->insert([
                        'config_id' => $row->id,
                        'role_name' => 'Coordinación',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $phone = trim((string) $row->phone_number_id);
            if ($phone !== '' && Schema::hasColumn('wa_inbox_sessions', 'config_id')) {
                DB::table('wa_inbox_sessions')
                    ->where('phone_number_id', $phone)
                    ->whereNull('config_id')
                    ->update(['config_id' => $row->id]);
            }
        }
    }
}
