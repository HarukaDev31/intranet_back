<?php

if (!function_exists('normalizePhone')) {
    /**
     * Normaliza un número de teléfono removiendo espacios, guiones, paréntesis
     * y manejando el prefijo +51 de Perú
     *
     * @param string $phone
     * @return string
     */
    function normalizePhone($phone)
    {
        if (empty($phone)) {
            return '';
        }

        // Convertir a string y remover espacios, guiones, paréntesis, puntos
        $normalized = preg_replace('/[\s\-\(\)\.\+]/', '', $phone);
        
        // Si empieza con 51 y tiene más de 9 dígitos, asumir que tiene prefijo de país
        if (preg_match('/^51(\d{9})$/', $normalized, $matches)) {
            $normalized = $matches[1]; // Remover el prefijo 51
        }
        
        // Si empieza con 051, remover el 0 inicial también
        if (preg_match('/^051(\d{9})$/', $normalized, $matches)) {
            $normalized = $matches[1];
        }
        
        return $normalized;
    }
}

if (!function_exists('generatePhoneVariations')) {
    /**
     * Genera variaciones posibles de un número de teléfono para búsqueda
     *
     * @param string $phone
     * @return array
     */
    function generatePhoneVariations($phone)
    {
        $normalized = normalizePhone($phone);
        
        if (empty($normalized)) {
            return [];
        }
        
        $variations = [];
        
        // Número normalizado (solo dígitos)
        $variations[] = $normalized;
        
        // Con prefijo +51
        $variations[] = '+51' . $normalized;
        
        // Con prefijo 51
        $variations[] = '51' . $normalized;
        
        // Con prefijo 051
        $variations[] = '051' . $normalized;
        
        // Con espacios comunes
        if (strlen($normalized) == 9) {
            $variations[] = substr($normalized, 0, 3) . ' ' . substr($normalized, 3, 3) . ' ' . substr($normalized, 6);
            $variations[] = substr($normalized, 0, 3) . '-' . substr($normalized, 3, 3) . '-' . substr($normalized, 6);
            $variations[] = '(' . substr($normalized, 0, 3) . ') ' . substr($normalized, 3, 3) . '-' . substr($normalized, 6);
            
            // Con prefijo y espacios
            $variations[] = '+51 ' . substr($normalized, 0, 3) . ' ' . substr($normalized, 3, 3) . ' ' . substr($normalized, 6);
            $variations[] = '51 ' . substr($normalized, 0, 3) . ' ' . substr($normalized, 3, 3) . ' ' . substr($normalized, 6);
        }
        
        return array_unique($variations);
    }
}