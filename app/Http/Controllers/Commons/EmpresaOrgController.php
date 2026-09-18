<?php

namespace App\Http\Controllers\Commons;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Empresa;
use App\Models\Organizacion;
use App\Models\Grupo;

class EmpresaOrgController extends Controller
{
    /**
     * Listar empresas activas.
     * GET /api/options/empresas
     */
    public function getEmpresas()
    {
        $empresas = Empresa::where('Nu_Estado', 1)
            ->select('ID_Empresa', 'No_Empresa')
            ->orderBy('No_Empresa')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $empresas->map(fn($e) => [
                'id'     => $e->ID_Empresa,
                'nombre' => $e->No_Empresa,
            ]),
        ]);
    }

    /**
     * Listar organizaciones por empresa.
     * GET /api/options/organizaciones?empresa_id={id}
     */
    public function getOrganizaciones(Request $request)
    {
        $authUser = auth()->user();
        $authUserOrgId = $authUser ? (int) $authUser->getAttribute('ID_Organizacion') : 0;

        // Org ≠ 1: solo la suya. No filtrar por empresa_id del request.
        if ($authUserOrgId !== 1) {
            $orgs = Organizacion::where('ID_Organizacion', $authUserOrgId)
                ->where('Nu_Estado', 1)
                ->select('ID_Organizacion', 'No_Organizacion')
                ->orderBy('No_Organizacion')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orgs->map(fn($o) => [
                    'id'     => $o->ID_Organizacion,
                    'nombre' => $o->No_Organizacion,
                ]),
            ]);
        }

        $request->validate(['empresa_id' => 'required|integer']);

        $orgs = Organizacion::where('ID_Empresa', $request->empresa_id)
            ->where('Nu_Estado', 1)
            ->select('ID_Organizacion', 'No_Organizacion')
            ->orderBy('No_Organizacion')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orgs->map(fn($o) => [
                'id'     => $o->ID_Organizacion,
                'nombre' => $o->No_Organizacion,
            ]),
        ]);
    }

    /**
     * Listar grupos/cargos activos por empresa y organización.
     * GET /api/options/grupos?empresa_id={id}&org_id={id}
     */
    public function getGrupos(Request $request)
    {
        $authUser = auth()->user();
        $authUserOrgId = $authUser ? (int) $authUser->getAttribute('ID_Organizacion') : 0;

        $idOrg = $authUserOrgId === 1
            ? (int) $request->input('org_id')
            : $authUserOrgId;

        $organizacion = Organizacion::find($idOrg);
        if (!$organizacion) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $idEmpresa = (int) $organizacion->getAttribute('ID_Empresa');

        $grupos = Grupo::where('ID_Empresa', $idEmpresa)
            ->where('ID_Organizacion', $idOrg)
            ->where('Nu_Estado', 1)
            ->select('ID_Grupo', 'No_Grupo', 'No_Grupo_Descripcion', 'Nu_Tipo_Privilegio_Acceso')
            ->orderBy('No_Grupo')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $grupos->map(fn($g) => [
                'id'          => $g->ID_Grupo,
                'nombre'      => $g->No_Grupo,
                'descripcion' => $g->No_Grupo_Descripcion,
                'privilegio'  => $g->Nu_Tipo_Privilegio_Acceso,
            ]),
        ]);
    }
}
