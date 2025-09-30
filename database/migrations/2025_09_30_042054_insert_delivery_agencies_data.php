<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertDeliveryAgenciesData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $agencias = [
            [
                'name' => 'Marisur',
                'ruc' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Shalom',
                'ruc' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'name' => 'Otra Opción',
                'ruc' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('delivery_agencies')->insert($agencias);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('delivery_agencies')->whereIn('name', [
            'Marisur',
            'Shalom',
            'Olva Courier',
            'SERPOST',
            'Cargo Express',
            'Delivery Express',
            'Fast Delivery',
            'Speed Courier',
            'Express Peru',
            'Rapid Transport'
        ])->delete();
    }
}
