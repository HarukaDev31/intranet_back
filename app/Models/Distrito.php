<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distrito extends Model
{
    protected $table = 'distrito';
    protected $primaryKey = 'ID_Distrito';
    
    protected $fillable = [
        'No_Distrito'
    ];
} 