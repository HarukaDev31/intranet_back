<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_proveedor_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 20)->comment('Entidad dueña del documento: cotizacion o solicitud');
            $table->unsignedBigInteger('entity_id')->comment('Id de esa entidad. Sin clave foránea');
            $table->string('document_type', 20)->comment('Documento emitido: resumen o balance');
            $table->unsignedBigInteger('scope_id')->default(0)->comment('Proveedor al que pertenece el documento. Cero cuando cubre toda la cotización o solicitud');
            $table->unsignedTinyInteger('step')->default(0)->comment('Paso del flujo con el que se emitió: 0 cotización, 1 datos, 2 validación, 3 ejecución, 4 confirmación');
            $table->string('fingerprint', 40)->comment('Huella de datos, paso y plantilla. Si cambia se vuelve a generar el PDF');
            $table->string('data_fingerprint', 40)->comment('Huella solo de los datos que muestra el PDF');
            $table->string('path', 255)->comment('Ruta del PDF en el almacenamiento');
            $table->string('filename')->nullable()->comment('Nombre con el que se descarga el PDF');
            $table->string('issued_on', 20)->nullable()->comment('Fecha de emisión que figura en el PDF');
            $table->timestamp('generated_at')->nullable()->comment('Momento en que se generó el PDF');
            $table->unsignedInteger('downloads')->default(0)->comment('Veces que se descargó el PDF emitido');
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'document_type', 'scope_id'], 'pp_documento_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_proveedor_documentos');
    }
};
