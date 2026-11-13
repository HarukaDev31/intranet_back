<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes de Almacén China: socios (vinculados a una organización) e importadores (sin portal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('china_clients', function (Blueprint $table) {
            $table->id()->comment('Identificador del cliente de China');
            $table->unsignedInteger('organization_id')->nullable()->unique()->comment('Organización del socio (vacío en los importadores)');
            $table->string('type', 20)->comment('partner (socio) o importer (importador)');
            $table->string('name', 150)->comment('Nombre o empresa');
            $table->string('country', 80)->nullable()->comment('País del cliente');
            $table->string('whatsapp', 40)->nullable()->comment('WhatsApp de contacto');
            $table->string('contact_name', 150)->nullable()->comment('Persona de contacto');
            $table->string('email', 150)->nullable()->comment('Correo de contacto');
            $table->json('prices')->nullable()->comment('Tarifas del socio por servicio (consol, consol_2)');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Usuario que lo registró');
            $table->timestamps();

            $table->foreign('organization_id', 'china_clients_org_fk')
                ->references('ID_Organizacion')->on('organizacion')->nullOnDelete();
            $table->index(['type', 'country'], 'china_clients_type_country_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('china_clients');
    }
};
