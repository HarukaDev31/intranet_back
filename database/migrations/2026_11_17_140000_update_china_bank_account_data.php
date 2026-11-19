<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cuenta bancaria de China de la factura: datos actualizados + Tax ID, USCC y correo del beneficiario. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('china_bank_accounts')) {
            return;
        }
        Schema::table('china_bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('china_bank_accounts', 'beneficiary_tax_id')) {
                $table->string('beneficiary_tax_id', 40)->nullable()->after('beneficiary_city')->comment('Tax ID del beneficiario (si el banco lo solicita)');
            }
            if (! Schema::hasColumn('china_bank_accounts', 'beneficiary_uscc')) {
                $table->string('beneficiary_uscc', 40)->nullable()->after('beneficiary_tax_id')->comment('USCC del beneficiario (si el banco lo solicita)');
            }
            if (! Schema::hasColumn('china_bank_accounts', 'beneficiary_email')) {
                $table->string('beneficiary_email', 120)->nullable()->after('beneficiary_uscc')->comment('Correo de contacto del beneficiario');
            }
        });

        DB::table('china_bank_accounts')->update([
            'intermediary_swift' => 'CITIUS33XXX',
            'intermediary_bank' => 'CITIBANK N.A. NEW YORK',
            'intermediary_country' => 'ESTADOS UNIDOS DE AMERICA',
            'intermediary_state' => 'NEW YORK',
            'intermediary_city' => 'NEW YORK',
            'payer_swift' => 'CZCBCN2X',
            'payer_bank' => 'ZHEJIANG CHOUZHOU COMMERCIAL BANK',
            'payer_country' => 'CHINA',
            'payer_state' => 'ZHEJIANG',
            'payer_city' => 'YIWU',
            'account_number' => 'NRA15602002010590029396',
            'beneficiary' => 'GLOBAL SHOP INTERNATIONAL COMPANY LIMITED',
            'beneficiary_address' => 'Block B, Chouyin Building, 188 Shangcheng Avenue, Yiwu City, Zhejiang Province, China',
            'beneficiary_country' => 'CHINA',
            'beneficiary_state' => 'ZHEJIANG',
            'beneficiary_city' => 'YIWU',
            'beneficiary_tax_id' => '75892616',
            'beneficiary_uscc' => '91330782MACGSAPX7W',
            'beneficiary_email' => 'mvillegas@probusiness.pe',
        ]);
    }

    public function down(): void
    {
    }
};
