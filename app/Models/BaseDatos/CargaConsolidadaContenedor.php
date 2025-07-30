<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;

class CargaConsolidadaContenedor extends Model
{
    protected $table = 'carga_consolidada_contenedor';
    
    protected $fillable = [
        'carga',
        // Agregar otros campos según la estructura de la tabla
    ];

    /**
     * Obtener todas las cargas únicas para filtros
     */
    public static function getCargasUnicas()
    {
        return self::select('carga')
            ->whereNotNull('carga')
            ->where('carga', '!=', '')
            ->distinct()
            ->orderBy('carga')
            ->pluck('carga')
            ->toArray();
    }

    /**
     * Scope para filtrar por carga
     */
    public function scopePorCarga($query, $carga)
    {
        return $query->where('carga', 'LIKE', "%{$carga}%");
    }

    /**
     * Obtener estadísticas de cargas
     */
    public static function getEstadisticasCargas()
    {
        return self::selectRaw('carga, COUNT(*) as total')
            ->whereNotNull('carga')
            ->where('carga', '!=', '')
            ->groupBy('carga')
            ->orderBy('total', 'desc')
            ->get()
            ->pluck('total', 'carga')
            ->toArray();
    }
} 