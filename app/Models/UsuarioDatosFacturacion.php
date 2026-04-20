<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioDatosFacturacion extends Model
{
    protected $table = 'usuario_datos_facturacion';

    protected $fillable = [
        'id_user',
        'id_import',
        'destino',
        'nombre_completo',
        'dni',
        'ruc',
        'razon_social',
        'domicilio_fiscal',
    ];

    public function importacion()
    {
        return $this->belongsTo(ImportUsuarioDatosFacturacion::class, 'id_import');
    }
}
