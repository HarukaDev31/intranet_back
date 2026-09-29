<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalculadoraRazonDescarte extends Model
{
    use SoftDeletes;

    protected $table = 'calculadora_razon_descarte';

    protected $fillable = [
        'name',
    ];
}
