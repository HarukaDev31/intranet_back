<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facturas China por versiones: una vigente por operación, las demás reemplazadas o anuladas, cada una con su motivo.
 * La operación y la cuenta bancaria guardan quién las modificó por última vez (para el aviso «Actualizar factura»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('china_documents', function (Blueprint $table) {
            $table->unsignedSmallInteger('version')->nullable()->after('operation_id')->comment('Versión de la factura en su operación (1, 2, …)');
            $table->string('status', 20)->nullable()->after('version')->comment('Factura: vigente, reemplazada o anulada');
            $table->string('reason', 255)->nullable()->after('status')->comment('Motivo de la emisión');
            $table->string('status_reason', 255)->nullable()->after('reason')->comment('Motivo del último cambio de estado (anulación o paso a vigente)');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason')->comment('Fecha del último cambio de estado');
            $table->unsignedBigInteger('status_changed_by')->nullable()->after('status_changed_at')->comment('Usuario del último cambio de estado');
            $table->string('status_changed_by_name')->nullable()->after('status_changed_by')->comment('Nombre de quien cambió el estado');
            $table->index(['operation_id', 'status'], 'china_doc_op_status_idx');
        });

        Schema::table('china_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by')->comment('Último usuario que la editó');
            $table->string('updated_by_name')->nullable()->after('updated_by')->comment('Nombre de quien la editó por última vez');
        });

        Schema::table('china_bank_accounts', function (Blueprint $table) {
            $table->string('updated_by_name')->nullable()->after('is_active')->comment('Nombre de quien cambió la cuenta por última vez');
        });

        // Facturas ya emitidas: versiones por orden de emisión; la última queda vigente.
        $invoices = DB::table('china_documents')->where('type', 'invoice')->whereNotNull('operation_id')
            ->orderBy('operation_id')->orderBy('id')->get(['id', 'operation_id']);
        foreach ($invoices->groupBy('operation_id') as $documents) {
            $last = $documents->count();
            foreach ($documents->values() as $index => $document) {
                DB::table('china_documents')->where('id', $document->id)->update([
                    'version' => $index + 1,
                    'status' => $index + 1 === $last ? 'vigente' : 'reemplazada',
                    'reason' => $index === 0 ? 'Emisión inicial' : 'Nueva emisión',
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('china_documents', function (Blueprint $table) {
            $table->dropIndex('china_doc_op_status_idx');
            $table->dropColumn(['version', 'status', 'reason', 'status_reason', 'status_changed_at', 'status_changed_by', 'status_changed_by_name']);
        });
        Schema::table('china_operations', function (Blueprint $table) {
            $table->dropColumn(['updated_by', 'updated_by_name']);
        });
        Schema::table('china_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('updated_by_name');
        });
    }
};
