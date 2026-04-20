<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportUsuarioDatosFacturacion extends Model
{
    protected $table = 'imports_usuario_datos_facturacion';

    protected $fillable = [
        'nombre_archivo',
        'ruta_archivo',
        'cantidad_rows',
        'usuario_id',
        'estadisticas',
        'estado',
        'rollback_at',
    ];

    protected $casts = [
        'estadisticas' => 'array',
        'rollback_at' => 'datetime',
    ];
}

