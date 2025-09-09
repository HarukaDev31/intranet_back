<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $table = 'contenedor_consolidado_order_steps';
    private string $oldUrl = 'https://intranet.probusiness.pe/assets/icons/cotizacion.png';
    private string $newUrl = 'https://intranet.probusiness.pe/assets/icons/clientes.png';

    public function up(): void
    {
        DB::table($this->table)
            ->where('name', 'CLIENTES')
            ->where('iconURL', $this->oldUrl)
            ->update(['iconURL' => $this->newUrl]);
    }

    public function down(): void
    {
        DB::table($this->table)
            ->where('name', 'CLIENTES')
            ->where('iconURL', $this->newUrl)
            ->update(['iconURL' => $this->oldUrl]);
    }
};
