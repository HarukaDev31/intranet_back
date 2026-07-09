<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p_user', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('firebase_uid')->nullable()->unique();
            $table->enum('auth_provider', ['email', 'firebase'])->default('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token', 64)->nullable();
            $table->timestamp('email_verification_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['auth_provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p_user');
    }
};
