<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $table = 'departamento';
    protected $primaryKey = 'ID_Departamento';
    
    protected $fillable = [
        'No_Departamento'
    ];
} 