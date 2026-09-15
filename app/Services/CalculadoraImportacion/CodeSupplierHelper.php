<?php

namespace App\Services\CalculadoraImportacion;

/**
 * Helper para generar y manipular code_supplier de calculadora_importacion_proveedores.
 *
 * Centraliza la lógica que antes vivía duplicada en CalculadoraImportacionService,
 * CalculadoraImportacionExcelService y CalculadoraImportacionController.
 *
 * Convención del code: {iniciales(cliente, máx 4 chars)} + {últimos 2 chars de carga} + "-" + {N}
 *   Ejemplo: "JUPE5-1" para cliente "Juan Perez", carga "B5", índice 1.
 *
 * Notas:
 * - Solo se permiten caracteres [A-Z0-9-] tras normalizar a ASCII mayúsculas.
 * - El parámetro $rowCount en la firma antigua de generateCodeSupplier (en Service/ExcelService)
 *   nunca se usaba; aquí se elimina.
 */
class CodeSupplierHelper
{
    /**
     * Normaliza un code_supplier: ASCII mayúsculas y solo [A-Z0-9-]. Devuelve null si queda vacío.
     */
    public static function sanitize(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $code);
        if ($ascii !== false && $ascii !== null) {
            $code = $ascii;
        }

        $code = strtoupper((string) $code);
        $code = preg_replace('/[^A-Z0-9-]/', '', $code);

        return $code !== '' ? $code : null;
    }

    /**
     * Iniciales del nombre del cliente: hasta 4 caracteres tomando 2 primeras letras de cada palabra.
     */
    public static function initialsFromClienteNombre(string $nombre): string
    {
        $words = preg_split('/\s+/', trim($nombre)) ?: [];
        $code = '';
        foreach ($words as $word) {
            if (strlen($code) >= 4) {
                break;
            }
            $token = self::sanitize((string) $word);
            if ($token !== null && strlen($token) >= 2) {
                $code .= substr($token, 0, 2);
            }
        }

        return self::sanitize($code) ?? '';
    }

    /**
     * Normaliza la "carga" del contenedor al segmento usable en code_supplier.
     * - Si es numérica: la convierte a int -> string.
     * - Si es texto: toma los últimos 2 chars alfanuméricos en mayúsculas.
     *
     * @param mixed $carga
     */
    public static function normalizeCargaSegment($carga): string
    {
        if (is_numeric($carga)) {
            return (string) (int) $carga;
        }
        if ($carga !== null && $carga !== '') {
            $seg = substr((string) $carga, -2);
            $seg = preg_replace('/[^A-Za-z0-9]/', '', (string) $seg);

            return strtoupper((string) $seg);
        }

        return '';
    }

    /**
     * Prefijo del code_supplier antes de "-N" (ej. "JUPE5"). Útil para detectar índices ya usados.
     *
     * @param mixed $carga
     */
    public static function basePrefix(string $nombreCliente, $carga): string
    {
        return self::initialsFromClienteNombre($nombreCliente) . self::normalizeCargaSegment($carga);
    }

    /**
     * Mayor N entre códigos que coinciden exactamente con "{base}-N". 0 si no hay coincidencias.
     *
     * @param iterable<int, string|null> $codeSuppliers
     */
    public static function maxSuffixForBase(string $base, iterable $codeSuppliers): int
    {
        if ($base === '') {
            return 0;
        }
        $max = 0;
        $pattern = '/^' . preg_quote($base, '/') . '-(\d+)$/';
        foreach ($codeSuppliers as $cs) {
            $t = trim((string) $cs);
            if ($t === '') {
                continue;
            }
            if (preg_match($pattern, $t, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    /**
     * Genera un code_supplier completo. Si la sanitización deja vacío, retorna "SUP-{index}".
     *
     * @param mixed $carga
     */
    public static function generate(string $nombreCliente, $carga, int $index): string
    {
        $base = self::basePrefix($nombreCliente, $carga);
        $code = self::sanitize($base . '-' . $index);

        return $code ?? ('SUP-' . $index);
    }

    /**
     * Primeras 3 letras del país (ASCII). Ecuador → ECU, Argentina → ARG, Perú → PER.
     *
     * @param mixed $nombrePais
     * @param mixed $iso2
     */
    public static function paisPrefix($nombrePais = null, $iso2 = null): string
    {
        $sanitized = self::sanitize((string) $nombrePais);
        if ($sanitized !== null) {
            $letters = preg_replace('/[^A-Z]/', '', $sanitized);
            if ($letters !== null && strlen($letters) >= 3) {
                return substr($letters, 0, 3);
            }
        }

        $iso = strtoupper((string) preg_replace('/[^A-Z]/', '', (string) $iso2));
        $map = [
            'EC' => 'ECU', 'AR' => 'ARG', 'PE' => 'PER', 'CL' => 'CHL', 'MX' => 'MEX',
            'CO' => 'COL', 'CN' => 'CHN', 'BO' => 'BOL', 'PY' => 'PRY', 'UY' => 'URY',
            'VE' => 'VEN', 'PA' => 'PAN', 'BR' => 'BRA', 'US' => 'USA', 'ES' => 'ESP',
        ];
        if ($iso !== '' && isset($map[$iso])) {
            return $map[$iso];
        }

        return $iso !== '' ? str_pad(substr($iso, 0, 3), 3, 'X') : 'PAI';
    }

    /**
     * Número de empresa en rotulado: org 2 → 1, org 3 → 2.
     *
     * @param mixed $organizacionId
     */
    public static function socioEmpresaNumero($organizacionId): int
    {
        $n = (int) $organizacionId - 1;

        return $n > 0 ? $n : 0;
    }

    /**
     * Prefijo resumen socios: {PAIS3}{orgId-1}-{INICIALES}{CARGA}
     * Org 2 Ecuador + Juan Perez + B5 → ECU1-JUPE5
     *
     * @param mixed $nombrePais
     * @param mixed $organizacionId
     * @param mixed $carga
     * @param mixed $iso2
     */
    public static function basePrefixWithPais($nombrePais, $organizacionId, string $nombreCliente, $carga, $iso2 = null): string
    {
        $head = self::paisPrefix($nombrePais, $iso2) . self::socioEmpresaNumero($organizacionId);
        $rest = self::basePrefix($nombreCliente, $carga);

        return $rest !== '' ? ($head . '-' . $rest) : $head;
    }

    /**
     * code_supplier resumen: {PAIS3}{orgId-1}-{INICIALES}{CARGA}-{N}
     * Org 2 Ecuador, Juan Perez, B5 → ECU1-JUPE5-1
     *
     * @param mixed $nombrePais
     * @param mixed $organizacionId
     * @param mixed $carga
     * @param mixed $iso2
     */
    public static function generateWithPaisPrefix($nombrePais, $organizacionId, string $nombreCliente, $carga, int $index, $iso2 = null): string
    {
        $base = self::basePrefixWithPais($nombrePais, $organizacionId, $nombreCliente, $carga, $iso2);
        $code = self::sanitize($base . '-' . $index);

        return $code ?? ('SUP-' . $index);
    }
    /**
     * Primeras 3 letras de la organización (legacy). El resumen socios usa paisPrefix.
     */
    public static function orgPrefix(?string $nombreOrganizacion): string
    {
        $sanitized = self::sanitize((string) $nombreOrganizacion);
        if ($sanitized === null) {
            return 'ORG';
        }
        $letters = preg_replace('/[^A-Z]/', '', $sanitized);
        if ($letters !== null && strlen($letters) >= 3) {
            return substr($letters, 0, 3);
        }
        $alnum = preg_replace('/[^A-Z0-9]/', '', $sanitized);
        $prefix = $alnum !== null ? substr($alnum, 0, 3) : '';

        return $prefix !== '' ? str_pad($prefix, 3, 'X') : 'ORG';
    }

    /**
     * Prefijo completo resumen: {3 letras org}{iniciales cliente}{carga}
     *
     * @param mixed $carga
     */
    public static function basePrefixWithOrg(?string $nombreOrganizacion, string $nombreCliente, $carga): string
    {
        return self::orgPrefix($nombreOrganizacion) . self::basePrefix($nombreCliente, $carga);
    }

    /**
     * code_supplier de resumen: igual que el cotizador, con prefijo de 3 letras de la org.
     * Ejemplo: org "Andes Import", cliente "Juan Perez", carga "B5" → ANDJUPE5-1
     *
     * @param mixed $carga
     */
    public static function generateWithOrgPrefix(?string $nombreOrganizacion, string $nombreCliente, $carga, int $index): string
    {
        $base = self::basePrefixWithOrg($nombreOrganizacion, $nombreCliente, $carga);
        $code = self::sanitize($base . '-' . $index);

        return $code ?? ('SUP-' . $index);
    }
}
