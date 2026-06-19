<?php

namespace App\Services;

use App\Exports\CampaignStudentsMarketingExport;
use App\Helpers\DateHelper;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;

class CampaignStudentsExportService
{
    public function exportStudents($campaignId, Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        if ($user->getNombreGrupo() !== Usuario::JEFE_MARKETING) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado para exportar estudiantes de campaña',
            ], 403);
        }

        try {
            $search = $request->get('search', '');
            $fechaInicio = $request->get('fechaInicio', '');
            $fechaFin = $request->get('fechaFin', '');
            $estadoPago = $request->get('estados_pago', '');
            $tipoCurso = $request->get('tipos_curso', '');

            $query = $this->buildStudentsQuery($campaignId, $user->ID_Empresa);
            $this->applyFilters($query, $search, $fechaInicio, $fechaFin, $estadoPago, $tipoCurso);
            $query->orderBy('PC.ID_Pedido_Curso', 'desc');

            $students = $query->get();
            $rows = collect();
            $numero = 1;

            foreach ($students as $student) {
                $rows->push($this->mapStudentRow($student, $numero));
                $numero++;
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "campana_{$campaignId}_estudiantes_{$timestamp}.xlsx";

            return Excel::download(new CampaignStudentsMarketingExport($rows), $filename);
        } catch (\Exception $e) {
            Log::error('Error en exportStudents campaña: ' . $e->getMessage(), [
                'campaign_id' => $campaignId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al exportar estudiantes de la campaña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buildStudentsQuery($campaignId, $empresaId)
    {
        return DB::table('pedido_curso AS PC')
            ->select([
                'PC.*',
                'CLI.No_Entidad',
                'CLI.Nu_Documento_Identidad',
                'CLI.Nu_Celular_Entidad',
                'CLI.Txt_Email_Entidad',
                'CAMP.Fe_Inicio',
                'CAMP.No_Campana',
                DB::raw('tipo_curso'),
                DB::raw('(
                    SELECT IFNULL(SUM(cccp.monto), 0)
                    FROM pedido_curso_pagos AS cccp
                    JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                    WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                    AND ccp.name = "ADELANTO"
                ) AS total_pagos'),
            ])
            ->leftJoin('entidad AS CLI', 'CLI.ID_Entidad', '=', 'PC.ID_Entidad')
            ->leftJoin('campana_curso AS CAMP', 'CAMP.ID_Campana', '=', 'PC.ID_Campana')
            ->where('PC.ID_Campana', $campaignId)
            ->where('PC.ID_Empresa', $empresaId);
    }

    private function applyFilters($query, $search, $fechaInicio, $fechaFin, $estadoPago, $tipoCurso)
    {
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('CLI.No_Entidad', 'like', "%$search%")
                    ->orWhere('CLI.Nu_Documento_Identidad', 'like', "%$search%")
                    ->orWhere('PC.ID_Pedido_Curso', 'like', "%$search%");
            });
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('PC.Fe_Registro', [$fechaInicio, $fechaFin]);
        }

        if ($estadoPago && $estadoPago != '0') {
            $query->whereRaw('(
                CASE 
                    WHEN (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) = 0 THEN "pendiente"
                    WHEN (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) < PC.Ss_Total AND (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) > 0 THEN "adelanto"
                    WHEN (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) = PC.Ss_Total THEN "pagado"
                    WHEN (
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos AS cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = PC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) > PC.Ss_Total THEN "sobrepagado"
                    ELSE "pendiente"
                END
            ) = ?', [$estadoPago]);
        }

        if ($tipoCurso && $tipoCurso !== '') {
            $query->where('PC.tipo_curso', $tipoCurso);
        }
    }

    private function mapStudentRow($student, $numero)
    {
        $importe = (float) ($student->Ss_Total ?? 0);
        $totalPagos = (float) ($student->total_pagos ?? 0);

        $estado = 'Pendiente';
        if ($totalPagos > $importe) {
            $estado = 'Sobrepago';
        } elseif ($totalPagos < $importe && $totalPagos !== 0.0) {
            $estado = 'Adelanto';
        } elseif ($totalPagos === $importe && $importe !== 0.0) {
            $estado = 'Pagado';
        }

        $usuarioEstado = $student->Nu_Estado_Usuario_Externo ?? 1;
        if (($student->send_constancia ?? '') === 'SENDED') {
            $usuarioEstado = 4;
        }

        $usuarioLabels = [
            3 => 'Pendiente',
            2 => 'Creado',
            4 => 'Constancia',
        ];
        $usuario = $usuarioLabels[$usuarioEstado] ?? 'Creado';

        $nombre = $student->No_Entidad ?: ('Cliente ID: ' . ($student->ID_Entidad ?? ''));
        $cliente = implode("\n", [
            $nombre,
            $student->Nu_Documento_Identidad ?: '-',
            $student->Nu_Celular_Entidad ?: '-',
            $student->Txt_Email_Entidad ?: '-',
        ]);

        return [
            'numero' => $numero,
            'fecha' => DateHelper::formatDate($student->Fe_Registro, '-', 0),
            'cliente' => $cliente,
            'curso' => ((int) $student->tipo_curso === 0) ? 'Virtual' : 'En vivo',
            'campana' => $this->getCampanaLabel($student),
            'usuario' => $usuario,
            'importe' => $importe,
            'estado' => $estado,
        ];
    }

    private function getCampanaLabel($student)
    {
        if (!empty($student->No_Campana)) {
            return $student->No_Campana;
        }

        if (empty($student->Fe_Inicio)) {
            return 'Campaña ' . ($student->ID_Campana ?? '');
        }

        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $mes = (int) date('n', strtotime($student->Fe_Inicio));
        $anio = date('Y', strtotime($student->Fe_Inicio));

        return ($meses[$mes] ?? 'Campaña') . ' ' . $anio;
    }
}
