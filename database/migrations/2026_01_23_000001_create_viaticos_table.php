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
        Schema::dropIfExists('viaticos');
        Schema::create('viaticos', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->date('reimbursement_date');
            $table->string('requesting_area');
            $table->text('expense_description');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['PENDING', 'CONFIRMED', 'REJECTED'])->default('PENDING');
            $table->string('receipt_file')->nullable(); // Archivo inicial subido por el usuario
            $table->string('payment_receipt_file')->nullable(); // Comprobante de retribución subido por admin
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                  ->references('ID_Usuario')
                  ->on('usuario')
                  ->onDelete('cascade');

            $table->index('status');
            $table->index('user_id');
            $table->index('reimbursement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viaticos');
    }
};
