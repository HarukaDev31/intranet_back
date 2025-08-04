<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportCliente extends Model
{
    protected $table = 'imports_clientes';
    
    protected $fillable = [
        'nombre_archivo',
        'ruta_archivo',
        'cantidad_rows',
        'tipo_importacion',
        'empresa_id',
        'usuario_id',
        'estadisticas'
    ];

    protected $casts = [
        'estadisticas' => 'array'
    ];

    /**
     * Relación con PedidoCurso
     */
    public function pedidosCurso(): HasMany
    {
        return $this->hasMany(PedidoCurso::class, 'id_cliente_importacion');
    }

    /**
     * Relación con Cotizacion
     */
    public function cotizaciones(): HasMany
    {
        return $this->hasMany(\App\Models\CargaConsolidada\Cotizacion::class, 'id_cliente_importacion');
    }

    /**
     * Relación con Usuario
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'ID_Usuario');
    }

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id', 'ID_Empresa');
    }
} 