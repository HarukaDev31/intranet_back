<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUsuarioDatosFacturacionTable extends Migration
{
    protected $table = 'usuario_datos_facturacion';

    public function up()
    {
        if (!Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('id_user');
                $table->enum('destino', ['Lima', 'Provincia'])->nullable();
                $table->string('nombre_completo')->nullable();
                $table->string('dni', 20)->nullable();
                $table->string('ruc', 20)->nullable();
                $table->string('razon_social')->nullable();
                $table->string('domicilio_fiscal', 2000)->nullable();
                $table->timestamps();

                $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
                $table->index('id_user', 'idx_usuario_datos_facturacion_user');
            });
        }

        // Backfill inicial desde consolidado_comprobante_forms.
        if (Schema::hasTable('consolidado_comprobante_forms')) {
            $rows = DB::table('consolidado_comprobante_forms as cf')
                ->join('users as u', 'u.id', '=', 'cf.id_user')
                ->select(
                    'cf.id_user',
                    'cf.destino_entrega',
                    'cf.nombre_completo',
                    'cf.dni_carnet',
                    'cf.ruc',
                    'cf.razon_social',
                    'cf.domicilio_fiscal',
                    'cf.created_at',
                    'cf.updated_at'
                )
                ->orderBy('cf.id', 'asc')
                ->get();

            $payload = [];
            foreach ($rows as $row) {
                $destino = in_array($row->destino_entrega, ['Lima', 'Provincia'])
                    ? $row->destino_entrega
                    : null;

                $payload[] = [
                    'id_user' => $row->id_user,
                    'destino' => $destino,
                    'nombre_completo' => $row->nombre_completo ?: null,
                    'dni' => $row->dni_carnet ?: null,
                    'ruc' => $row->ruc ?: null,
                    'razon_social' => $row->razon_social ?: null,
                    'domicilio_fiscal' => $row->domicilio_fiscal ?: null,
                    'created_at' => $row->created_at ?: now(),
                    'updated_at' => $row->updated_at ?: now(),
                ];
            }

            if (!empty($payload)) {
                DB::table($this->table)->insert($payload);
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable($this->table)) {
            Schema::drop($this->table);
        }
    }
}
