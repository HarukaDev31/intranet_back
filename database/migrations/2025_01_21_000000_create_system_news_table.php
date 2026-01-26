<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemNewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('system_news');
        Schema::create('system_news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->text('summary')->nullable();
            $table->enum('type', ['update', 'feature', 'fix', 'announcement'])->default('update');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_published')->default(false);
            $table->date('published_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('created_by')->references('ID_Usuario')->on('usuario')->onDelete('cascade');

            // Indexes
            $table->index('type');
            $table->index('priority');
            $table->index('is_published');
            $table->index('published_at');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_news');
    }
}

