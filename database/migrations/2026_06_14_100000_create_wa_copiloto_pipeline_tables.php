<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateWaCopilotoPipelineTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_copiloto_pipeline_stages')) {
            Schema::create('wa_copiloto_pipeline_stages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('major', 32)->index();
                $table->string('slug', 64)->unique();
                $table->string('label', 120);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_system')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $this->seedStages();

        if (Schema::hasTable('wa_copiloto_conversations')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                if (!Schema::hasColumn('wa_copiloto_conversations', 'pipeline_stage_id')) {
                    $table->unsignedBigInteger('pipeline_stage_id')->nullable()->index();
                }
                if (!Schema::hasColumn('wa_copiloto_conversations', 'customer_initiated_at')) {
                    $table->timestamp('customer_initiated_at')->nullable()->index();
                }
            });

            $nuevoId = DB::table('wa_copiloto_pipeline_stages')->where('slug', 'nuevo')->value('id');
            if ($nuevoId) {
                DB::table('wa_copiloto_conversations')
                    ->whereNull('pipeline_stage_id')
                    ->update(['pipeline_stage_id' => $nuevoId]);
            }

            DB::statement('
                UPDATE wa_copiloto_conversations c
                SET customer_initiated_at = (
                    SELECT MIN(m.sent_at)
                    FROM wa_copiloto_messages m
                    WHERE m.conversation_id = c.id AND m.direction = "in"
                )
                WHERE customer_initiated_at IS NULL
                AND EXISTS (
                    SELECT 1 FROM wa_copiloto_messages m2
                    WHERE m2.conversation_id = c.id AND m2.direction = "in"
                )
            ');
        }

        if (!Schema::hasTable('wa_copiloto_pipeline_transitions')) {
            Schema::create('wa_copiloto_pipeline_transitions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('from_stage_id')->nullable();
                $table->unsignedBigInteger('to_stage_id');
                $table->string('major_from', 32)->nullable();
                $table->string('major_to', 32);
                $table->unsignedInteger('changed_by_user_id')->nullable()->index();
                $table->string('note', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('conversation_id', 'wa_copiloto_pt_conv_fk')
                    ->references('id')
                    ->on('wa_copiloto_conversations')
                    ->onDelete('cascade');
                $table->foreign('from_stage_id', 'wa_copiloto_pt_from_fk')
                    ->references('id')
                    ->on('wa_copiloto_pipeline_stages')
                    ->onDelete('set null');
                $table->foreign('to_stage_id', 'wa_copiloto_pt_to_fk')
                    ->references('id')
                    ->on('wa_copiloto_pipeline_stages')
                    ->onDelete('restrict');
            });
        }

        if (!Schema::hasTable('wa_copiloto_assignment_logs')) {
            Schema::create('wa_copiloto_assignment_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedInteger('from_user_id')->nullable()->index();
                $table->unsignedInteger('to_user_id')->nullable()->index();
                $table->unsignedInteger('changed_by_user_id')->nullable()->index();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('conversation_id', 'wa_copiloto_al_conv_fk')
                    ->references('id')
                    ->on('wa_copiloto_conversations')
                    ->onDelete('cascade');
            });
        }
    }

    protected function seedStages()
    {
        $now = now();
        $rows = [
            ['major' => 'nuevo', 'slug' => 'nuevo', 'label' => 'Nuevo', 'sort_order' => 10],
            ['major' => 'en_progreso', 'slug' => 'contactado', 'label' => 'Contactado', 'sort_order' => 20],
     
            ['major' => 'postventa', 'slug' => 'postventa', 'label' => 'Postventa', 'sort_order' => 80],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('wa_copiloto_pipeline_stages')->where('slug', $row['slug'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('wa_copiloto_pipeline_stages')->insert(array_merge($row, [
                'is_system' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down()
    {
        if (Schema::hasTable('wa_copiloto_conversations')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                if (Schema::hasColumn('wa_copiloto_conversations', 'pipeline_stage_id')) {
                    $table->dropColumn('pipeline_stage_id');
                }
                if (Schema::hasColumn('wa_copiloto_conversations', 'customer_initiated_at')) {
                    $table->dropColumn('customer_initiated_at');
                }
            });
        }

        Schema::dropIfExists('wa_copiloto_assignment_logs');
        Schema::dropIfExists('wa_copiloto_pipeline_transitions');
        Schema::dropIfExists('wa_copiloto_pipeline_stages');
    }
}
