<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWaCopilotoTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_copiloto_sessions')) {
            Schema::create('wa_copiloto_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('slug', 64)->default('ventas');
                $table->string('phone_number_id', 32);
                $table->string('display_number', 32)->default('');
                $table->string('label', 64)->default('Copiloto Ventas');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_webhook_at')->nullable();
                $table->timestamps();

                $table->unique('slug', 'wa_copiloto_sessions_slug_uq');
                $table->unique('phone_number_id', 'wa_copiloto_sessions_phone_uq');
            });
        }

        if (!Schema::hasTable('wa_copiloto_conversations')) {
            Schema::create('wa_copiloto_conversations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('session_id')->index();
                $table->string('wa_contact_id', 64)->nullable();
                $table->string('phone_e164', 20)->index();
                $table->string('contact_name', 255)->nullable();
                $table->string('contact_avatar_url', 512)->nullable();
                $table->string('channel_label', 64)->default('Copiloto Ventas');
                $table->unsignedInteger('assigned_user_id')->nullable()->index();
                $table->timestamp('assigned_at')->nullable();
                $table->enum('status', ['open', 'closed', 'archived'])->default('open');
                $table->unsignedInteger('unread_count')->default(0);
                $table->timestamp('last_customer_message_at')->nullable()->index();
                $table->timestamp('window_expires_at')->nullable();
                $table->string('last_message_preview', 500)->nullable();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->enum('last_direction', ['in', 'out'])->nullable();
                $table->unsignedBigInteger('last_message_id')->nullable();
                $table->string('last_message_type', 32)->nullable();
                $table->string('last_message_delivery_status', 32)->nullable();
                $table->timestamps();

                $table->unique(['session_id', 'phone_e164'], 'wa_copiloto_conv_session_phone_uq');
                $table->index(['assigned_user_id', 'last_message_at'], 'wa_copiloto_conv_assign_last_idx');
                $table->index(['status', 'last_message_at'], 'wa_copiloto_conv_status_last_idx');
                $table->index(['session_id', 'last_message_at', 'id'], 'wa_copiloto_conv_session_last_id_idx');

                $table->foreign('session_id')
                    ->references('id')
                    ->on('wa_copiloto_sessions')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('wa_copiloto_messages')) {
            Schema::create('wa_copiloto_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('session_id')->index();
                $table->enum('direction', ['in', 'out']);
                $table->text('body')->nullable();
                $table->string('message_type', 32)->default('text');
                $table->string('template_name', 120)->nullable();
                $table->json('template_params')->nullable();
                $table->string('media_url', 512)->nullable();
                $table->string('media_mime', 128)->nullable();
                $table->string('meta_message_id', 128)->nullable()->unique();
                $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'read', 'failed'])->nullable();
                $table->string('failed_reason', 255)->nullable();
                $table->timestamp('sent_at')->nullable()->index();
                $table->unsignedInteger('sent_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'sent_at'], 'wa_copiloto_msg_conv_sent_idx');
                $table->index(['conversation_id', 'sent_at', 'id'], 'wa_copiloto_msg_conv_sent_id_idx');
                $table->index(['delivery_status', 'conversation_id'], 'wa_copiloto_msg_pending_conv_idx');

                $table->foreign('conversation_id')
                    ->references('id')
                    ->on('wa_copiloto_conversations')
                    ->onDelete('cascade');
                $table->foreign('session_id')
                    ->references('id')
                    ->on('wa_copiloto_sessions')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('wa_copiloto_webhook_logs')) {
            Schema::create('wa_copiloto_webhook_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->json('payload');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['processed_at', 'created_at'], 'wa_copiloto_webhook_processed_idx');
                $table->index(['processed_at'], 'wa_copiloto_webhook_pending_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('wa_copiloto_messages');
        Schema::dropIfExists('wa_copiloto_conversations');
        Schema::dropIfExists('wa_copiloto_webhook_logs');
        Schema::dropIfExists('wa_copiloto_sessions');
    }
}
