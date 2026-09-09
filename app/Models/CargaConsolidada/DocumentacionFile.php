<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CargaConsolidada\Concerns\SincronizaOrganizacionId;

class DocumentacionFile extends Model
{
    use SincronizaOrganizacionId;

    protected $table = 'contenedor_consolidado_documentacion_files';

    protected static function organizacionRelacion(): string
    {
        return 'contenedor';
    }


    protected $fillable = [
        'id_folder',
        'id_contenedor',
        'file_url',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',

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
