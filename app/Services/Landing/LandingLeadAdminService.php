<?php

namespace App\Services\Landing;

use App\Models\LandingConsolidadoLead;
use App\Models\LandingCursoLead;

class LandingLeadAdminService
{
    private function buildConsolidadoQuery(string $search = '')
    {
        $query = LandingConsolidadoLead::query()
            ->select([
                'id',
                'nombre',
                'whatsapp',
                'proveedor',
                'codigo_campana',
                'ip_address',
                'created_at',
            ])
            ->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp', 'like', '%' . $search . '%')
                    ->orWhere('codigo_campana', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    private function buildCursoQuery(string $search = '')
    {
        $query = LandingCursoLead::query()
            ->select([
                'id',
                'nombre',
                'whatsapp',
                'email',
                'experiencia_importando',
                'ip_address',
                'created_at',
            ])
            ->orderBy('id', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    public function getConsolidado(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($params['per_page'] ?? 20)));
        $search = trim((string) ($params['search'] ?? ''));

        $query = $this->buildConsolidadoQuery($search);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'success' => true,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function getCurso(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($params['per_page'] ?? 20)));
        $search = trim((string) ($params['search'] ?? ''));

        $query = $this->buildCursoQuery($search);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'success' => true,
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function getConsolidadoForExport(array $params)
    {
        $search = trim((string) ($params['search'] ?? ''));
        return $this->buildConsolidadoQuery($search)->get();
    }

    public function getCursoForExport(array $params)
    {
        $search = trim((string) ($params['search'] ?? ''));
        return $this->buildCursoQuery($search)->get();
    }
}

