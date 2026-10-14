<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API de integración para otras organizaciones:
 * - api_clients: credenciales por organización (token solo como hash, scopes, límites).
 * - api_client_audit_logs: un registro por request (uso, origen, resultado) para medir y limitar.
 */
class CreateApiClientsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('api_clients')) {
            Schema::create('api_clients', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('organizacion_id');
                $table->string('nombre', 120);
                // SHA-256 del token completo (el token en claro se muestra una sola vez).
                $table->char('token_hash', 64)->unique();
                $table->string('token_prefix', 16);
                $table->json('scopes');
                // Usuario BOT de la organización: es el "vendedor" de lo que cree la integración.
                $table->unsignedInteger('id_usuario_bot')->nullable();
                $table->unsignedInteger('rate_limit_per_minute')->default(60);
                $table->unsignedInteger('daily_quota')->default(2000);
                $table->boolean('activo')->default(true);
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('organizacion_id', 'idx_api_clients_org');
                $table->foreign('organizacion_id', 'fk_api_clients_org')
                    ->references('ID_Organizacion')
                    ->on('organizacion')
                    ->onDelete('restrict');
            });
        }

        if (!Schema::hasTable('api_client_audit_logs')) {
            Schema::create('api_client_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('api_client_id')->nullable();
                $table->unsignedInteger('organizacion_id')->nullable();
                // Solo el prefijo del token presentado (intentos fallidos); nunca el token completo.
                $table->string('token_prefix', 16)->nullable();
                $table->string('method', 8);
                $table->string('path', 255);
                $table->string('scope', 64)->nullable();
                $table->unsignedSmallInteger('status_code');
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('error', 190)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['api_client_id', 'created_at'], 'idx_acal_client_fecha');
                $table->index('created_at', 'idx_acal_fecha');
                $table->index(['organizacion_id', 'created_at'], 'idx_acal_org_fecha');
                $table->index(['ip', 'created_at'], 'idx_acal_ip_fecha');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('api_client_audit_logs');
        Schema::dropIfExists('api_clients');
    }
}
