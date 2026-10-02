<?php

namespace App\Services\CargaConsolidada;

use App\Helpers\UserLookupHelper;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de marketing por contenedor: cliente, tipo de cliente, ubicación (Lima / provincia),
 * logística (cotización preliminar o final) y origen. Mismas cotizaciones que ve marketing en
 * prospectos: CONFIRMADO, con proveedores y sin cliente de importación.
 */
class ReporteMarketingService
{
    const TIPO_PRELIMINAR = 'preliminar';
    const TIPO_FINAL = 'final';

    /**
     * @param  int  $idContenedor
     * @param  string  $tipo  preliminar|final
     * @return array<int, array<string, mixed>>
     */
    public function rows($idContenedor, $tipo)
    {
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion as cc')
            ->leftJoin('contenedor_consolidado_tipo_cliente as t', 't.id', '=', 'cc.id_tipo_cliente')
            ->where('cc.id_contenedor', (int) $idContenedor)
            ->whereNull('cc.deleted_at')
            ->where('cc.estado_cotizador', 'CONFIRMADO')
            ->whereNull('cc.id_cliente_importacion')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores as p')
                    ->whereColumn('p.id_cotizacion', 'cc.id');
            })
            ->orderBy('cc.nombre')
            ->get([
                'cc.id',
                'cc.nombre',
                'cc.documento',
                'cc.correo',
                'cc.telefono',
                'cc.organizacion_id',
                'cc.monto',
                'cc.logistica_final',
                'cc.origen_marketing',
                't.name as tipo_cliente',
            ]);

        $ids = $cotizaciones->pluck('id')->all();
        $ubicaciones = $this->ubicacionesDesdeEntrega($ids);

        $rows = [];
        $numero = 1;
        foreach ($cotizaciones as $c) {
            $ubicacion = isset($ubicaciones[$c->id])
                ? $ubicaciones[$c->id]
                : $this->ubicacionDesdePerfil($c);

            $logistica = $tipo === self::TIPO_FINAL ? $c->logistica_final : $c->monto;

            $rows[] = [
                'numero' => $numero++,
                'cliente' => (string) $c->nombre,
                'tipo_cliente' => (string) ($c->tipo_cliente ?? ''),
                'lima_provincia' => $ubicacion['lima_provincia'],
                'departamento' => $ubicacion['departamento'],
                'provincia' => $ubicacion['provincia'],
                'logistica' => $logistica !== null ? (float) $logistica : null,
                'origen' => (string) ($c->origen_marketing ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Ubicación desde el formulario de entrega (Lima o provincia con departamento/provincia).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array{lima_provincia: string, departamento: string, provincia: string}>
     */
    private function ubicacionesDesdeEntrega(array $ids)
    {
        if ($ids === []) {
            return [];
        }

        $result = [];

        $lima = DB::table('consolidado_delivery_form_lima')
            ->whereIn('id_cotizacion', $ids)
            ->pluck('id_cotizacion');
        foreach ($lima as $idCotizacion) {
            $result[(int) $idCotizacion] = [
                'lima_provincia' => 'Lima',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
            ];
        }

        // El formulario de provincia más reciente prevalece sobre el de Lima.
        $provincias = DB::table('consolidado_delivery_form_province as f')
            ->leftJoin('departamento as d', 'd.ID_Departamento', '=', 'f.id_department')
            ->leftJoin('provincia as p', 'p.ID_Provincia', '=', 'f.id_province')
            ->whereIn('f.id_cotizacion', $ids)
            ->orderBy('f.id')
            ->get(['f.id_cotizacion', 'd.No_Departamento', 'p.No_Provincia']);
        foreach ($provincias as $f) {
            $result[(int) $f->id_cotizacion] = [
                'lima_provincia' => 'Provincia',
                'departamento' => (string) ($f->No_Departamento ?? ''),
                'provincia' => (string) ($f->No_Provincia ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Respaldo: provincia del perfil del cliente (users) encontrado por correo, teléfono o documento.
     *
     * @param  object  $cotizacion
     * @return array{lima_provincia: string, departamento: string, provincia: string}
     */
    private function ubicacionDesdePerfil($cotizacion)
    {
        $vacio = ['lima_provincia' => '', 'departamento' => '', 'provincia' => ''];

        $user = UserLookupHelper::findUserByContact(
            $cotizacion->correo ?? null,
            $cotizacion->telefono ?? null,
            $cotizacion->documento ?? null,
            (int) ($cotizacion->organizacion_id ?? 0) ?: null
        );
        if (!$user) {
            return $vacio;
        }

        $idProvincia = $user->provincia_id ?? ($user->idcity ?? null);
        if (!$idProvincia) {
            return $vacio;
        }

        $prov = DB::table('provincia as p')
            ->leftJoin('departamento as d', 'd.ID_Departamento', '=', 'p.ID_Departamento')
            ->where('p.ID_Provincia', (int) $idProvincia)
            ->first(['p.No_Provincia', 'd.No_Departamento']);
        if (!$prov) {
            return $vacio;
        }

        $provincia = (string) ($prov->No_Provincia ?? '');
        $esLima = in_array(strtoupper(trim($provincia)), ['LIMA', 'CALLAO'], true);

        return [
            'lima_provincia' => $esLima ? 'Lima' : 'Provincia',
            'departamento' => (string) ($prov->No_Departamento ?? ''),
            'provincia' => $provincia,
        ];
    }
}
