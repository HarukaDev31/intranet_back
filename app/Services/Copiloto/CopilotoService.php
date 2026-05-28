<?php

namespace App\Services\Copiloto;

use App\Models\Copiloto\CopilotoConversacion;
use App\Models\Copiloto\CopilotoFicha;
use App\Models\Copiloto\WhatsappMessage;

class CopilotoService
{
    public function getLeads(array $params = [])
    {
        $perPage = isset($params['per_page']) ? max(1, (int) $params['per_page']) : 20;
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $lineasPermitidas = ['ventas_cc', 'consolidado'];

        $query = CopilotoConversacion::query()
            ->where(function ($q) use ($lineasPermitidas) {
                $q->whereIn('linea', $lineasPermitidas)
                    ->orWhereNull('linea')
                    ->orWhere('linea', '');
            })
            ->orderByDesc('last_message_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', '%' . $search . '%')
                    ->orWhere('contact_name', 'like', '%' . $search . '%');
            });
        }

        $paginated = $query->paginate($perPage);
        $phones = collect($paginated->items())->pluck('phone')->filter()->unique()->values()->all();

        $fichas = CopilotoFicha::query()
            ->whereIn('phone', $phones)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('phone')
            ->map(function ($rows) {
                return $rows->first();
            });

        $rows = [];
        foreach ($paginated->items() as $conv) {
            $ficha = $fichas->get($conv->phone);
            $rows[] = [
                'id' => (int) $conv->id,
                'phone' => $conv->phone,
                'contact_name' => $conv->contact_name,
                'last_message_at' => $conv->last_message_at,
                'last_message' => $conv->last_message_preview,
                'last_direction' => $conv->last_direction,
                'linea' => $conv->linea,
                'messages_count' => (int) $conv->messages_count,
                'temperatura' => $ficha ? (int) $ficha->temperatura : null,
                'nivel' => $ficha ? $ficha->nivel : null,
                'sugerencia_corta' => $ficha ? $ficha->sugerencia_corta : null,
            ];
        }

        return [
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    public function getConversacion($phone, array $params = [])
    {
        $perPage = isset($params['per_page']) ? max(1, (int) $params['per_page']) : 100;
        $conversationId = isset($params['conversation_id']) ? (int) $params['conversation_id'] : 0;

        $query = WhatsappMessage::query()->orderByDesc('sent_at')->orderByDesc('id');

        if ($conversationId > 0) {
            $query->where('conversation_id', $conversationId);
        } else {
            $query->where('phone', $phone);
        }

        $messages = $query->paginate($perPage);

        return [
            'success' => true,
            'data' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ];
    }

    public function getFicha($phone)
    {
        $ficha = CopilotoFicha::query()
            ->where('phone', $phone)
            ->orderByDesc('created_at')
            ->first();

        return [
            'success' => true,
            'data' => $ficha,
        ];
    }
}
