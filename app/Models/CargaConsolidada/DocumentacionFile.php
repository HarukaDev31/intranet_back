<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentacionFile extends Model
{
    protected $table = 'contenedor_consolidado_documentacion_files';
    
    protected $fillable = [
        'id_folder',
        'id_contenedor',
        'file_url',
        'file_name',
        'file_size',
        'file_type',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene la carpeta a la que pertenece este archivo
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentacionFolder::class, 'id_folder', 'id');
    }

    /**
     * Obtiene el contenedor asociado
     */
    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor', 'id');
    }

    /**
     * Obtiene el tipo de archivo basado en la extensión
     */
    public function getFileTypeAttribute($value)
    {
        if ($this->file_url) {
            $extension = pathinfo($this->file_url, PATHINFO_EXTENSION);
            return strtolower($extension);
        }
        return $value;
    }
}
