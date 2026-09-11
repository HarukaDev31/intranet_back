<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPhoneCodeToPaisFlags extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('pais_flags')) {
            return;
        }

        if (!Schema::hasColumn('pais_flags', 'phone_code')) {
            Schema::table('pais_flags', function (Blueprint $table) {
                $table->string('phone_code', 8)->nullable()->after('iso2');
            });
        }

        $isoPhoneCodes = [
            'pe' => '51', 'ec' => '593', 'co' => '57', 'cl' => '56', 'mx' => '52',
            'bo' => '591', 'ar' => '54', 'br' => '55', 've' => '58', 'pa' => '507',
            'py' => '595', 'uy' => '598', 'us' => '1', 'ca' => '1', 'cn' => '86',
            'es' => '34', 'cr' => '506', 'gt' => '502', 'hn' => '504', 'sv' => '503',
            'ni' => '505', 'do' => '1809', 'cu' => '53', 'pr' => '1787',
        ];

        $rows = DB::table('pais_flags')->select('id', 'iso2', 'phone_code')->get();
        foreach ($rows as $row) {
            $iso = strtolower(trim((string) $row->iso2));
            $code = isset($isoPhoneCodes[$iso]) ? $isoPhoneCodes[$iso] : null;
            if ($code === null) {
                continue;
            }
            if ((string) $row->phone_code === $code) {
                continue;
            }
            DB::table('pais_flags')->where('id', $row->id)->update(['phone_code' => $code]);
        }
    }

    public function down()
    {
        if (Schema::hasColumn('pais_flags', 'phone_code')) {
            Schema::table('pais_flags', function (Blueprint $table) {
                $table->dropColumn('phone_code');
            });
        }
    }
}
