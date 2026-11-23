<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cuenta que debe figurar en la factura de consolidado. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('china_bank_accounts')) {
            return;
        }

        $datos = [
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
            'updated_at' => now(),
        ];

        if (! DB::table('china_bank_accounts')->exists()) {
            DB::table('china_bank_accounts')->insert(array_merge($datos, [
                'company' => 'GLOBAL SHOP INTERNATIONAL COMPANY LIMITED',
                'is_active' => true,
                'created_at' => now(),
            ]));

            return;
        }

        DB::table('china_bank_accounts')->update($datos);
    }

    public function down(): void
    {
    }
};
