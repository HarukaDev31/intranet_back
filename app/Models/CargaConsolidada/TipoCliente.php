<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCliente extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'contenedor_consolidado_tipo_cliente';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    /**
     * Indica si el modelo debe ser timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Obtiene todos los tipos de cliente ordenados por nombre.
     */
    public static function getTiposOrdenados()
    {
        return static::orderBy('name')->get();
    }

    /**
     * Scope para buscar por nombre.
     */
    public function scopePorNombre($query, $nombre)
    {
        return $query->where('name', 'LIKE', "%{$nombre}%");
    }

    /**
     * Scope para ordenar por nombre.
     */
    public function scopeOrdenarPorNombre($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Obtiene el nombre del tipo de cliente.
     */
    public function getNombreAttribute()
    {
        return $this->name;
    }

    /**
     * Establece el nombre del tipo de cliente.
     */
    public function setNombreAttribute($value)
    {
        $this->attributes['name'] = $value;
    }
}
