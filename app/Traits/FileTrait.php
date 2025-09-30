<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait FileTrait
{
    public function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }
        
        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }
        
        // Generar URL completa desde storage
        return Storage::disk('public')->url($ruta);
    }
}