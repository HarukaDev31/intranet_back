<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('viaticos_retribuciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')->constrained('viaticos')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('viatico_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viaticos_retribuciones');
    }
};
