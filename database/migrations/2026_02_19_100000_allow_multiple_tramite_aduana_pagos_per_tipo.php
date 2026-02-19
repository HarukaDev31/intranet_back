    <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permitir varios pagos (vouchers) por trámite: quitar unique id_tramite + id_tipo_permiso.
     * MySQL exige un índice para las FK; primero añadimos un índice no único y luego quitamos el unique.
     */
    public function up(): void
    {
        Schema::table('tramite_aduana_pagos', function (Blueprint $table) {
            $table->index(['id_tramite', 'id_tipo_permiso'], 'tramite_aduana_pagos_tramite_tipo_permiso_index');
        });
        Schema::table('tramite_aduana_pagos', function (Blueprint $table) {
            $table->dropUnique('tramite_aduana_pagos_tramite_tipo_permiso_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tramite_aduana_pagos', function (Blueprint $table) {
            $table->unique(['id_tramite', 'id_tipo_permiso'], 'tramite_aduana_pagos_tramite_tipo_permiso_unique');
        });
        Schema::table('tramite_aduana_pagos', function (Blueprint $table) {
            $table->dropIndex('tramite_aduana_pagos_tramite_tipo_permiso_index');
        });
    }
};
