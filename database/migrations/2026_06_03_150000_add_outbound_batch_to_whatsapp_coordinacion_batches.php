<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOutboundBatchToWhatsappCoordinacionBatches extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_coordinacion_batches')) {
            return;
        }

        Schema::table('whatsapp_coordinacion_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_coordinacion_batches', 'job_domain')) {
                $table->string('job_domain', 80)->nullable()->after('carga');
            }
            if (!Schema::hasColumn('whatsapp_coordinacion_batches', 'outbound_laravel_batch_id')) {
                $table->string('outbound_laravel_batch_id', 36)->nullable()->after('laravel_batch_id');
            }
        });

    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_coordinacion_batches')) {
            return;
        }

        Schema::table('whatsapp_coordinacion_batches', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_coordinacion_batches', 'outbound_laravel_batch_id')) {
                $table->dropColumn('outbound_laravel_batch_id');
            }
            if (Schema::hasColumn('whatsapp_coordinacion_batches', 'job_domain')) {
                $table->dropColumn('job_domain');
            }
        });
    }
}
