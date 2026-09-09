<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManualUsuarioCmsIndexes extends Migration
{
    public function up()
    {
        Schema::table('manual_paginas', function (Blueprint $table) {
            $table->index(['role_slug', 'publicado', 'orden'], 'manual_paginas_role_pub_orden_idx');
            $table->index(['publicado', 'orden'], 'manual_paginas_pub_orden_idx');
        });

        Schema::table('manual_bloques', function (Blueprint $table) {
            $table->index(['tipo', 'pagina_id'], 'manual_bloques_tipo_pagina_idx');
        });

        Schema::table('manual_media', function (Blueprint $table) {
            $table->index(['uploaded_by'], 'manual_media_uploaded_by_idx');
            $table->index(['created_at'], 'manual_media_created_at_idx');
        });
    }

    public function down()
    {
        Schema::table('manual_paginas', function (Blueprint $table) {
            $table->dropIndex('manual_paginas_role_pub_orden_idx');
            $table->dropIndex('manual_paginas_pub_orden_idx');
        });

        Schema::table('manual_bloques', function (Blueprint $table) {
            $table->dropIndex('manual_bloques_tipo_pagina_idx');
        });

        Schema::table('manual_media', function (Blueprint $table) {
            $table->dropIndex('manual_media_uploaded_by_idx');
            $table->dropIndex('manual_media_created_at_idx');
        });
    }
}
