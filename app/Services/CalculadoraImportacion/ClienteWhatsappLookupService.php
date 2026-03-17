<?php

namespace App\Services\CalculadoraImportacion;

use App\Models\BaseDatos\Clientes\Cliente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClienteWhatsappLookupService
{
    public function searchClientesByWhatsapp(string $whatsapp, int $limit = 100): array
    {
        $telefonoNormalizado = preg_replace('/[\s\-\(\)\.\+]/', '', $whatsapp);

        // Si empieza con 51 y tiene más de 9 dígitos, remover prefijo
        if (preg_match('/^51(\d{9})$/', $telefonoNormalizado, $matches)) {
            $telefonoNormalizado = $matches[1];
        }

        $clientes = Cliente::where('telefono', '!=', null)
            ->where('telefono', '!=', '')
            ->where(function ($query) use ($whatsapp, $telefonoNormalizado) {
                $query->where('telefono', 'like', '%' . $whatsapp . '%');

                if (!empty($telefonoNormalizado)) {
                    $query->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?',
                        ["%{$telefonoNormalizado}%"]
                    )->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?',
                        ["%51{$telefonoNormalizado}%"]
                    );
                }
            })
            ->limit($limit)
            ->get();

        if ($clientes->isEmpty()) {
            return [];
        }

        $clienteIds = $clientes->pluck('id')->toArray();
        $serviciosPorCliente = $this->obtenerServiciosEnLote($clienteIds);

        $clientesTransformados = [];
        foreach ($clientes as $cliente) {
            $servicios = $serviciosPorCliente[$cliente->id] ?? [];
            $categoria = $this->determinarCategoriaCliente($servicios);

            $clientesTransformados[] = [
                'id' => $cliente->id,
                'value' => $cliente->telefono,
                'nombre' => $cliente->nombre,
                'documento' => $cliente->documento,
                'correo' => $cliente->correo,
                'label' => $cliente->telefono,
                'ruc' => $cliente->ruc,
                'empresa' => $cliente->empresa,
                'fecha' => $cliente->fecha ? $cliente->fecha->format('d/m/Y') : null,
                'categoria' => $categoria,
                'total_servicios' => count($servicios),
                'primer_servicio' => !empty($servicios) ? [
                    'servicio' => $servicios[0]['servicio'],
                    'fecha' => Carbon::parse($servicios[0]['fecha'])->format('d/m/Y'),
                    'categoria' => $categoria,
                ] : null,
                'servicios' => collect($servicios)->map(function ($servicio) use ($categoria) {
                    return [
                        'servicio' => $servicio['servicio'],
                        'fecha' => Carbon::parse($servicio['fecha'])->format('d/m/Y'),
                        'categoria' => $categoria,
                        'monto' => $servicio['monto'] ?? null,
                    ];
                }),
            ];
        }

        return $clientesTransformados;
    }

    public function determinarCategoriaCliente(array $servicios): string
    {
        $totalServicios = count($servicios);

        if ($totalServicios === 0) {
            return 'NUEVO';
        }

        if ($totalServicios === 1) {
            return 'RECURRENTE';
        }

        $ultimoServicio = end($servicios);
        $fechaUltimoServicio = Carbon::parse($ultimoServicio['fecha']);
        $hoy = Carbon::now();
        $mesesDesdeUltimaCompra = $fechaUltimoServicio->diffInMonths($hoy);

        if ($mesesDesdeUltimaCompra > 6) {
            return 'INACTIVO';
        }

        if ($totalServicios >= 2) {
            $primerServicio = $servicios[0];
            $fechaPrimerServicio = Carbon::parse($primerServicio['fecha']);
            $mesesEntrePrimeraYUltima = $fechaPrimerServicio->diffInMonths($fechaUltimoServicio);
            $frecuenciaPromedio = $mesesEntrePrimeraYUltima / ($totalServicios - 1);

            if ($frecuenciaPromedio <= 2 && $mesesDesdeUltimaCompra <= 2) {
                return 'PREMIUM';
            } elseif ($mesesDesdeUltimaCompra <= 6) {
                return 'RECURRENTE';
            }
        }

        return 'INACTIVO';
    }

    private function obtenerServiciosEnLote(array $clienteIds): array
    {
        if (empty($clienteIds)) {
            return [];
        }

        $serviciosPorCliente = [];

        $pedidosCurso = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.Nu_Estado', 2)
            ->whereIn('pc.id_cliente', $clienteIds)
            ->select(
                'pc.id_cliente',
                'e.Fe_Registro as fecha',
                DB::raw("'Curso' as servicio"),
                DB::raw('NULL as monto')
            )
            ->get();

        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereIn('id_cliente', $clienteIds)
            ->select(
                'id_cliente',
                'fecha',
                DB::raw("'Consolidado' as servicio"),
                'monto'
            )
            ->get();

        foreach ($pedidosCurso as $pedido) {
            $serviciosPorCliente[$pedido->id_cliente][] = [
                'servicio' => $pedido->servicio,
                'fecha' => $pedido->fecha,
                'monto' => $pedido->monto,
            ];
        }

        foreach ($cotizaciones as $cotizacion) {
            $serviciosPorCliente[$cotizacion->id_cliente][] = [
                'servicio' => $cotizacion->servicio,
                'fecha' => $cotizacion->fecha,
                'monto' => $cotizacion->monto,
            ];
        }

        foreach ($serviciosPorCliente as $clienteId => &$servicios) {
            usort($servicios, function ($a, $b) {
                return strtotime($a['fecha']) - strtotime($b['fecha']);
            });
        }

        return $serviciosPorCliente;
    }
}

