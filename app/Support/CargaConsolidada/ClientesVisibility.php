<?php

namespace App\Support\CargaConsolidada;

/**
 * Visibilidad de filas en Clientes / Embarcados / Variación.
 * Abierto: mismas filas que Cotizaciones (socio incluye resumen COTIZADO/CONFIRMADO).
 * Cerrado: solo las que ya tienen estado_cliente (se setea al pasar un item a LOADED).
 */
class ClientesVisibility
{
    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $alias alias de contenedor_consolidado_cotizacion
     * @param bool $esSocio
     * @param bool $contenedorCerrado
     * @return void
     */
    public static function applyListado($query, $alias, $esSocio, $contenedorCerrado)
    {
        if ($contenedorCerrado) {
            $query->whereNotNull($alias . '.estado_cliente');
            return;
        }

        if ($esSocio) {
            $query->where(function ($q) use ($alias) {
                $q->where($alias . '.estado_cotizador', 'CONFIRMADO')
                    ->orWhereIn($alias . '.estado_resumen', ['COTIZADO', 'CONFIRMADO']);
            });
            return;
        }

        $query->where($alias . '.estado_cotizador', 'CONFIRMADO');
    }

    /**
     * Confirmada para BD / almacén: cotizador clásico o resumen socio.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string $alias
     * @return void
     */
    public static function applyConfirmadoParaBd($query, $alias)
    {
        $cotizador = $alias === '' ? 'estado_cotizador' : $alias . '.estado_cotizador';
        $resumen = $alias === '' ? 'estado_resumen' : $alias . '.estado_resumen';
        $query->where(function ($q) use ($cotizador, $resumen) {
            $q->where($cotizador, 'CONFIRMADO')
                ->orWhere($resumen, 'CONFIRMADO');
        });
    }

    /**
     * Empareja una fila de cotización con un registro de `clientes`
     * por id_cliente, teléfono, documento o correo.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param object $cliente
     * @param string $alias
     * @return void
     */
    public static function applyMatchCliente($query, $cliente, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';
        $idCliente = isset($cliente->id) ? $cliente->id : null;
        $telefono = isset($cliente->telefono) ? $cliente->telefono : null;
        $documento = isset($cliente->documento) ? $cliente->documento : null;
        $correo = isset($cliente->correo) ? $cliente->correo : null;

        $query->where(function ($q) use ($p, $idCliente, $telefono, $documento, $correo) {
            $started = false;
            if ($idCliente) {
                $q->where($p . 'id_cliente', $idCliente);
                $started = true;
            }
            if (!empty($telefono)) {
                $telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);
                $telefonoSinCodigo = preg_replace('/^51/', '', $telefonoLimpio);
                $fn = $started ? 'orWhere' : 'where';
                $q->{$fn}(function ($q2) use ($p, $telefonoLimpio, $telefonoSinCodigo) {
                    $col = $p . 'telefono';
                    $q2->where(\Illuminate\Support\Facades\DB::raw('REPLACE(REPLACE(' . $col . ', " ", ""), "-", "")'), 'LIKE', '%' . $telefonoLimpio . '%')
                        ->orWhere(\Illuminate\Support\Facades\DB::raw('REPLACE(REPLACE(' . $col . ', " ", ""), "-", "")'), 'LIKE', '%' . $telefonoSinCodigo . '%');
                });
                $started = true;
            }
            if (!empty($documento)) {
                $q->orWhere($p . 'documento', $documento);
                $started = true;
            }
            if (!empty($correo)) {
                $q->orWhere(function ($q2) use ($p, $correo) {
                    $q2->whereNotNull($p . 'correo')
                        ->where($p . 'correo', '!=', '')
                        ->where($p . 'correo', $correo);
                });
                $started = true;
            }
            if (!$started) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    public static function excludeGraduadosDeCustomers($query, $cotizacionAlias, $contenedorAlias, $proveedoresTable)
    {
        $query->where(function ($q) use ($cotizacionAlias, $contenedorAlias, $proveedoresTable) {
            $q->where(function ($open) use ($contenedorAlias) {
                $open->where($contenedorAlias . '.estado_china', '!=', 'COMPLETADO')
                    ->orWhereNull($contenedorAlias . '.estado_china');
            })->orWhereNotExists(function ($sub) use ($cotizacionAlias, $proveedoresTable) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from($proveedoresTable)
                    ->whereColumn($proveedoresTable . '.id_cotizacion', $cotizacionAlias . '.id')
                    ->where('estados_proveedor', 'LOADED');
            });
        });
    }
}
