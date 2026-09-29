<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculadoraRazonDescarte extends Model
{
    protected $table = 'calculadora_razon_descarte';

    protected $fillable = [
        'name',
    ];
}
