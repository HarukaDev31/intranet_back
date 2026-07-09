<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p_user_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('p_user')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('token_prefix', 16)->index();
            $table->string('device_id', 128);
            $table->string('device_name')->nullable();
            $table->enum('platform', ['ios', 'android', 'web'])->default('android');
            $table->text('fcm_token')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index(['user_id', 'revoked_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p_user_session');
    }
};
