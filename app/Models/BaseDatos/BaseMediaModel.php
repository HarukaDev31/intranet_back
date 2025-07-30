<?php

namespace App\Models\BaseDatos;

use Illuminate\Database\Eloquent\Model;

abstract class BaseMediaModel extends Model
{
    protected $fillable = [
        'extension',
        'peso',
        'nombre_original',
        'ruta'
    ];

    protected $casts = [
        'peso' => 'integer',
        'created_at' => 'datetime'
    ];

    /**
     * Obtener la URL completa del archivo
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->ruta);
    }

    /**
     * Obtener el tamaño del archivo formateado
     */
    public function getTamanioFormateadoAttribute(): string
    {
        $bytes = $this->peso;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Obtener el tipo de archivo basado en la extensión
     */
    public function getTipoArchivoAttribute(): string
    {
        $extension = strtolower($this->extension);
        
        $tipos = [
            'pdf' => 'PDF',
            'doc' => 'Word',
            'docx' => 'Word',
            'xls' => 'Excel',
            'xlsx' => 'Excel',
            'jpg' => 'Imagen',
            'jpeg' => 'Imagen',
            'png' => 'Imagen',
            'gif' => 'Imagen',
            'txt' => 'Texto',
            'zip' => 'Comprimido',
            'rar' => 'Comprimido'
        ];
        
        return $tipos[$extension] ?? 'Archivo';
    }

    /**
     * Verificar si el archivo es una imagen
     */
    public function getEsImagenAttribute(): bool
    {
        $extension = strtolower($this->extension);
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
    }

    /**
     * Verificar si el archivo es un documento
     */
    public function getEsDocumentoAttribute(): bool
    {
        $extension = strtolower($this->extension);
        return in_array($extension, ['pdf', 'doc', 'docx', 'txt']);
    }
} 