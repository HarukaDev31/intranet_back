<?php

namespace App\Models\ManualUsuario;

use Illuminate\Database\Eloquent\Model;

class ManualMedia extends Model
{
    protected $table = 'manual_media';

    protected $fillable = [
        'path',
        'nombre',
        'alt',
        'mime',
        'uploaded_by',
    ];
}
