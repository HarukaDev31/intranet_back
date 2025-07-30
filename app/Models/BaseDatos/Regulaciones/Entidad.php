<?php

namespace App\Models\BaseDatos\Regulaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entidad extends Model
{
    use HasFactory;
    protected $table = 'bd_entidades_reguladoras';
    protected $fillable = ['nombre', 'descripcion'];
    public $timestamps = false;
   
}
