<?php

namespace App\Models\BaseDatos\Regulaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoRubro extends Model
{
    use HasFactory;
    protected $table = 'bd_productos';
    protected $fillable = ['nombre', 'descripcion','tipo'];
    public $timestamps = false;
    
}
