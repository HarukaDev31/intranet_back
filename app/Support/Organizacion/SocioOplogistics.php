<?php

namespace App\Support\Organizacion;

use App\Models\Organizacion;
use App\Support\CargaConsolidada\ResumenCostoClasificador;

/**
 * Socio OPLOGISTICS (Ecuador). Igual que la org 2, pero sin ISD y con su propio rotulado.
 * Se reconoce por nombre: el ID no coincide entre qa y prod.
 */
class SocioOplogistics
{
    public const NOMBRE = 'OPLOGISTICS';

    /** Número de empresa de su rotulado y de sus códigos de proveedor (ECU4-…). */
    public const NUMERO = 4;

    /** @var array<int, bool> */
    private static array $cache = [];

    public static function es(mixed $organizacionId): bool
    {
        $id = (int) $organizacionId;
        // Las orgs 1 a 3 son Probusiness, David y Genuino.
        if ($id <= 3) {
            return false;
        }

        return self::$cache[$id] ??= self::esNombre(Organizacion::query()->whereKey($id)->value('No_Organizacion'));
    }

    public static function esNombre(mixed $nombre): bool
    {
        return mb_strtoupper(trim((string) $nombre)) === self::NOMBRE;
    }

    /**
     * Su cotización repite los montos en varias tablas (base imponible, tributos, pagos). Deja un concepto por
     * tipo, el de «Resumen de cotización» si está, para no sumarlos dos veces. Sin ISD.
     *
     * @param  array<int, array<string, mixed>>  $costos
     * @return array<int, array<string, mixed>>
     */
    public static function costosDelResumen(array $costos): array
    {
        $preferidos = [
            ResumenCostoClasificador::FOB => '/^valor de carga \(pago/u',
            ResumenCostoClasificador::LOGISTICA => '/^servicio de importaci/u',
            ResumenCostoClasificador::IMPUESTO => '/^impuestos$/u',
        ];
        $elegidos = [];
        foreach ($costos as $costo) {
            $concepto = mb_strtolower(trim((string) ($costo['concepto'] ?? '')));
            $tipo = str_starts_with($concepto, 'valor de carga')
                ? ResumenCostoClasificador::FOB
                : ResumenCostoClasificador::tipo($concepto);
            if (!isset($preferidos[$tipo])) {
                continue;
            }
            if ($tipo === ResumenCostoClasificador::FOB) {
                $costo['concepto'] = 'Valor de carga (FOB)';
            }
            $esPreferido = preg_match($preferidos[$tipo], $concepto) === 1;
            if (!isset($elegidos[$tipo]) || ($esPreferido && !$elegidos[$tipo]['preferido'])) {
                $elegidos[$tipo] = ['costo' => $costo, 'preferido' => $esPreferido];
            }
        }

        return array_values(array_map(fn (array $e) => $e['costo'], array_replace(
            array_intersect_key($preferidos, $elegidos),
            $elegidos
        )));
    }

    /**
     * @param  array<string, mixed>  $data  cliente y proveedores leídos del documento
     * @return array<string, mixed>
     */
    public static function ajustarExtraccion(array $data): array
    {
        $costos = [];
        foreach ($data['proveedores'] ?? [] as $proveedor) {
            $costos = array_merge($costos, $proveedor['costos'] ?? []);
        }
        $costos = self::costosDelResumen($costos);
        foreach ($data['proveedores'] ?? [] as $i => $proveedor) {
            $data['proveedores'][$i] = array_merge($proveedor, ['costos' => $i === 0 ? $costos : [], 'isd' => null]);
        }

        return $data;
    }

    /** @param array<int, bool> $valores */
    public static function fijarCache(array $valores): void
    {
        self::$cache = $valores;
    }
}
