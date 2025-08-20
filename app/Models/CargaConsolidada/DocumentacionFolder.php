<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentacionFolder extends Model
{
    protected $table = 'contenedor_consolidado_documentacion_folders';
    
    protected $fillable = [
        'id_contenedor',
        'folder_name',
        'description',
        'only_doc_profile',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'only_doc_profile' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene los archivos de esta carpeta
     */
    public function files(): HasMany
    {
        return $this->hasMany(DocumentacionFile::class, 'id_folder', 'id');
    }

    /**
     * Obtiene el contenedor asociado
     */
    public function contenedor(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor', 'id');
    }

    /**
     * Scope para filtrar por contenedor o carpetas globales
     */
    public function scopeForContenedor($query, $idContenedor)
    {
        return $query->where(function($q) use ($idContenedor) {
            $q->where('id_contenedor', $idContenedor)
              ->orWhereNull('id_contenedor');
        });
    }

    /**
     * Scope para filtrar por perfil de documentación
     */
    public function scopeForUserGroup($query, $userGroup, $roleDocumentacion)
    {
        if ($userGroup != $roleDocumentacion) {
            return $query->where('only_doc_profile', 0);
        }
        return $query;
    }
}
