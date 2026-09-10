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
        $request->validate(['empresa_id' => 'required|integer']);

        $query = Organizacion::where('ID_Empresa', $request->empresa_id)
            ->where('Nu_Estado', 1);

        // Solo la organizacion 1 (admin) puede elegir entre todas; el resto
        // solo debe recibir la suya, sin importar que empresa_id le pidan.
        $authUser = auth()->user();
        $authUserOrgId = $authUser ? (int) $authUser->getAttribute('ID_Organizacion') : 0;
        if ($authUserOrgId !== 1) {
            $query->where('ID_Organizacion', $authUserOrgId);
        }

        $orgs = $query
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
        $request->validate([
            'empresa_id' => 'required|integer',
            'org_id'     => 'required|integer',
        ]);

        $grupos = Grupo::where('ID_Empresa', $request->empresa_id)
            ->where('ID_Organizacion', $request->org_id)
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
