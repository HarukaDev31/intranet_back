<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BaseDatos\ProductoImportadoExcel;

class ImportProducto extends Model
{
    protected $table = 'imports_productos';
    
    protected $fillable = [
        'nombre_archivo',
        'ruta_archivo',
        'cantidad_rows',
        'estadisticas'
    ];

    protected $casts = [
        'estadisticas' => 'array'
    ];

    /**
     * Relación con ProductoImportadoExcel
     */
    public function productos(): HasMany
    {
        return $this->hasMany(ProductoImportadoExcel::class, 'id_import_producto');
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

    /**
     * Relación con Contenedor
     */
    public function contenedor()
    {
        return $this->belongsTo(\App\Models\CargaConsolidada\Contenedor::class, 'id_contenedor');
    }

    /**
     * Obtener estadísticas de la importación
     */
    public function getEstadisticasAttribute($value)
    {
        return json_decode($value, true) ?: [];
    }

    /**
     * Establecer estadísticas de la importación
     */
    public function setEstadisticasAttribute($value)
    {
        $this->attributes['estadisticas'] = json_encode($value);
    }

    /**
     * Scope para importaciones por empresa
     */
    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /**
     * Scope para importaciones por usuario
     */
    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    /**
     * Scope para importaciones por contenedor
     */
    public function scopePorContenedor($query, $contenedorId)
    {
        return $query->where('id_contenedor', $contenedorId);
    }

    /**
     * Verificar si la importación tiene productos asociados
     */
    public function getTieneProductosAttribute(): bool
    {
        return $this->productos()->count() > 0;
    }

    /**
     * Obtener el total de productos importados
     */
    public function getTotalProductosAttribute(): int
    {
        return $this->productos()->count();
    }
}
