<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCotizacionExport extends Model
{
    protected $table = 'user_cotizacion_exports';

    protected $fillable = [
        'user_id',
        'ip',
        'user_agent',
        'file_path',
        'file_url',
        'excel_path',
        'excel_url',
        'cliente_nombre',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Usuario::class, 'user_id', 'ID_Usuario');
    }
}
