<?php

namespace App\Http\Controllers\Landing;

use App\Exports\LandingConsolidadoLeadsExport;
use App\Exports\LandingCursoLeadsExport;
use App\Http\Controllers\Controller;
use App\Services\Landing\LandingLeadAdminService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LandingLeadAdminController extends Controller
{
    /** @var LandingLeadAdminService */
    protected $landingLeadAdminService;

    public function __construct(LandingLeadAdminService $landingLeadAdminService)
    {
        $this->landingLeadAdminService = $landingLeadAdminService;
    }

    public function consolidado(Request $request)
    {
        try {
            $payload = $this->landingLeadAdminService->getConsolidado($request->all());
            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar leads de landing consolidado: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function curso(Request $request)
    {
        try {
            $payload = $this->landingLeadAdminService->getCurso($request->all());
            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar leads de landing curso: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportConsolidado(Request $request)
    {
        try {
            $rows = $this->landingLeadAdminService->getConsolidadoForExport($request->all());
            $filename = 'landing_consolidado_leads_' . date('Y-m-d_H-i-s') . '.xlsx';
            return Excel::download(new LandingConsolidadoLeadsExport($rows), $filename);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar leads de landing consolidado: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportCurso(Request $request)
    {
        try {
            $rows = $this->landingLeadAdminService->getCursoForExport($request->all());
            $filename = 'landing_curso_leads_' . date('Y-m-d_H-i-s') . '.xlsx';
            return Excel::download(new LandingCursoLeadsExport($rows), $filename);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar leads de landing curso: ' . $e->getMessage(),
            ], 500);
        }
    }
}

