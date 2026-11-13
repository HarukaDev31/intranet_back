<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos completos de la cuenta bancaria de China para facturas y para el socio:
 * banco intermediario, banco pagador y beneficiario (Swift, país, estado, ciudad, dirección).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('china_bank_accounts', function (Blueprint $table) {
            $table->string('company_address', 255)->nullable()->after('company')->comment('Dirección de la oficina de la empresa en China');
            $table->string('beneficiary_address', 255)->nullable()->after('beneficiary')->comment('Dirección del beneficiario');
            $table->string('beneficiary_country', 80)->nullable()->after('beneficiary_address')->comment('País del beneficiario');
            $table->string('beneficiary_state', 80)->nullable()->after('beneficiary_country')->comment('Estado o provincia del beneficiario');
            $table->string('beneficiary_city', 80)->nullable()->after('beneficiary_state')->comment('Ciudad del beneficiario');
            $table->string('intermediary_swift', 30)->nullable()->after('intermediary_bank')->comment('Código Swift del banco intermediario');
            $table->string('intermediary_country', 80)->nullable()->after('intermediary_swift')->comment('País del banco intermediario');
            $table->string('intermediary_state', 80)->nullable()->after('intermediary_country')->comment('Estado del banco intermediario');
            $table->string('intermediary_city', 80)->nullable()->after('intermediary_state')->comment('Ciudad del banco intermediario');
            $table->string('payer_swift', 30)->nullable()->after('payer_bank')->comment('Código Swift del banco pagador');
            $table->string('payer_country', 80)->nullable()->after('payer_swift')->comment('País del banco pagador');
            $table->string('payer_state', 80)->nullable()->after('payer_country')->comment('Estado del banco pagador');
            $table->string('payer_city', 80)->nullable()->after('payer_state')->comment('Ciudad del banco pagador');
        });
    }

    public function down(): void
    {
        Schema::table('china_bank_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'company_address', 'beneficiary_address', 'beneficiary_country', 'beneficiary_state', 'beneficiary_city',
                'intermediary_swift', 'intermediary_country', 'intermediary_state', 'intermediary_city',
                'payer_swift', 'payer_country', 'payer_state', 'payer_city',
            ]);
        });
    }
};
