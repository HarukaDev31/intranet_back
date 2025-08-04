<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDocumentoIdentidad extends Model
{
    protected $table = 'tipo_documento_identidad';
    protected $primaryKey = 'ID_Tipo_Documento_Identidad';
    
    protected $fillable = [
        'No_Tipo_Documento_Identidad_Breve'
    ];
} 