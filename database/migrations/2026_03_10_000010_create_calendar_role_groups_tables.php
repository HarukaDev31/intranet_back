<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('calendar_role_groups');
        Schema::dropIfExists('calendar_role_group_members');
        Schema::dropIfExists('calendar_role_group_configs');

        Schema::create('calendar_role_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->boolean('usa_consolidado')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('calendar_role_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_group_id');
            // Debe coincidir con el tipo de usuario.ID_Usuario (generalmente unsignedInteger)
            $table->unsignedInteger('user_id');
            $table->string('role_type', 50); // JEFE, COORDINACION, DOCUMENTACION, etc.
            $table->timestamps();

            $table->foreign('role_group_id')
                ->references('id')
                ->on('calendar_role_groups')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('ID_Usuario')
                ->on('usuario')
                ->onDelete('cascade');

            $table->unique(['role_group_id', 'user_id']);
        });

        Schema::create('calendar_role_group_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_group_id');
            $table->string('color_prioridad', 20)->nullable();
            $table->string('color_actividad', 20)->nullable();
            $table->string('color_consolidado', 20)->nullable();
            $table->string('color_completado', 20)->nullable();
            $table->timestamps();

            $table->foreign('role_group_id')
                ->references('id')
                ->on('calendar_role_groups')
                ->onDelete('cascade');
        });

        Schema::table('calendars', function (Blueprint $table) {
            if (!Schema::hasColumn('calendars', 'role_group_id')) {
                $table->unsignedBigInteger('role_group_id')->nullable()->after('user_id');
                $table->foreign('role_group_id')
                    ->references('id')
                    ->on('calendar_role_groups')
                    ->onDelete('set null');
            }
        });
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            if (Schema::hasColumn('calendars', 'role_group_id')) {
                $table->dropForeign(['role_group_id']);
                $table->dropColumn('role_group_id');
            }
        });

        Schema::dropIfExists('calendar_role_group_configs');
        Schema::dropIfExists('calendar_role_group_members');
        Schema::dropIfExists('calendar_role_groups');
    }
};

