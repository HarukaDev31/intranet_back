<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Dirección de la oficina en China en chino (se muestra en facturas y balances). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('china_bank_accounts') || ! Schema::hasColumn('china_bank_accounts', 'company_address')) {
            return;
        }
        DB::table('china_bank_accounts')->update(['company_address' => '浙江省金华市义乌市稠州北路1133号双创大厦1栋1013室']);
    }

    public function down(): void
    {
    }
};
