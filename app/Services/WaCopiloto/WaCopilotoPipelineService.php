<?php

namespace App\Services\WaCopiloto;

use App\Http\Middleware\EnsureCopilotoWaAccess;
use App\Models\Usuario;
use App\Models\WaCopiloto\WaCopilotoAssignmentLog;
use App\Models\WaCopiloto\WaCopilotoConversation;
use App\Models\WaCopiloto\WaCopilotoMessage;
use App\Models\WaCopiloto\WaCopilotoPipelineStage;
use App\Models\WaCopiloto\WaCopilotoSession;
use App\Models\WaCopiloto\WaCopilotoPipelineTransition;
use App\Support\WhatsApp\WaJsonUtf8;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WaCopilotoPipelineService
{
    /** @var WaCopilotoSessionService */
    protected $sessionService;

    /** @var WaCopilotoCacheService */
    protected $cacheService;

    /** @var bool */
    protected $stageIndexLoaded = false;

    /** @var array<int, WaCopilotoPipelineStage> */
    protected $stagesById = [];

    /** @var array<string, WaCopilotoPipelineStage> */
    protected $stagesBySlug = [];

    public function __construct(
        WaCopilotoSessionService $sessionService,
        WaCopilotoCacheService $cacheService
    ) {
        $this->sessionService = $sessionService;
        $this->cacheService = $cacheService;
    }

    /**
     * @return array<string, mixed>
     */
    public function listStages()
    {
        return $this->cacheService->rememberStages(function () {
            $stages = WaCopilotoPipelineStage::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return [
                'success' => true,
                'data' => $stages->map(function (WaCopilotoPipelineStage $stage) {
                    return $this->formatStage($stage);
                })->values()->all(),
            ];
        });
    }

    /**
     * @param  string  $label
     * @param  int  $userId
     * @return array<string, mixed>
     */
    public function createProgressStage($label, $userId)
    {
        if (!$this->isPipelineManager($userId)) {
            return [
                'success' => false,
                'message' => 'Solo el jefe puede crear etapas.',
            ];
        }

        $label = trim((string) $label);
        if ($label === '' || mb_strlen($label) < 2) {
            return [
                'success' => false,
                'message' => 'Indica un nombre de etapa (mínimo 2 caracteres).',
            ];
        }

        if (mb_strlen($label) > 120) {
            return [
                'success' => false,
                'message' => 'El nombre es demasiado largo.',
            ];
        }

        $slug = $this->uniqueProgressSlug($label);
        $maxOrder = (int) WaCopilotoPipelineStage::query()
            ->where('major', 'en_progreso')
            ->max('sort_order');

        $stage = WaCopilotoPipelineStage::query()->create([
            'major' => 'en_progreso',
            'slug' => $slug,
            'label' => $label,
            'sort_order' => ($maxOrder > 0 ? $maxOrder : 20) + 10,
            'is_system' => false,
            'is_active' => true,
        ]);

        $this->resetStageIndex();
        $this->cacheService->invalidateStages();
        $session = $this->sessionService->ensureDefaultSession();
        $this->cacheService->invalidateSession((int) $session->id);

        return [
            'success' => true,
            'data' => $this->formatStage($stage),
        ];
    }

    /**
     * @param  array<int, int>  $orderedStageIds
     * @param  int  $userId
     * @return array<string, mixed>
     */
    public function reorderProgressStages(array $orderedStageIds, $userId)
    {
        if (!$this->isPipelineManager($userId)) {
            return [
                'success' => false,
                'message' => 'Solo el jefe puede reordenar etapas.',
            ];
        }

        $orderedStageIds = array_values(array_unique(array_map('intval', $orderedStageIds)));
        $orderedStageIds = array_filter($orderedStageIds, function ($id) {
            return $id > 0;
        });

        if (empty($orderedStageIds)) {
            return [
                'success' => false,
                'message' => 'No hay etapas para reordenar.',
            ];
        }

        $stages = WaCopilotoPipelineStage::query()
            ->whereIn('id', $orderedStageIds)
            ->get();

        if ($stages->count() !== count($orderedStageIds)) {
            return [
                'success' => false,
                'message' => 'Hay etapas inválidas en el orden enviado.',
            ];
        }

        foreach ($stages as $stage) {
            if ($stage->major !== 'en_progreso') {
                return [
                    'success' => false,
                    'message' => 'Solo se pueden reordenar etapas en progreso.',
                ];
            }
        }

        $nuevoOrder = (int) (WaCopilotoPipelineStage::query()
            ->where('slug', 'nuevo')
            ->value('sort_order') ?: 10);

        $order = $nuevoOrder;
        foreach ($orderedStageIds as $stageId) {
            $order += 10;
            WaCopilotoPipelineStage::query()
                ->where('id', (int) $stageId)
                ->update(['sort_order' => $order, 'updated_at' => now()]);
        }

        $this->resetStageIndex();
        $this->cacheService->invalidateStages();
        $session = $this->sessionService->ensureDefaultSession();
        $this->cacheService->invalidateSession((int) $session->id);

        return $this->listStages();
    }

    /**
     * @param  string  $label
     * @return string
     */
    protected function uniqueProgressSlug($label)
    {
        $base = Str::slug($label);
        if ($base === '') {
            $base = 'etapa';
        }

        $slug = $base;
        $i = 1;
        while (WaCopilotoPipelineStage::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getKanban(array $params = [])
    {
        $session = $this->sessionService->ensureDefaultSession();

        return $this->cacheService->rememberKanban(
            (int) $session->id,
            $this->kanbanCacheFilters($params),
            function () use ($session, $params) {
                return $this->buildKanbanResponse($session, $params);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function buildKanbanResponse(WaCopilotoSession $session, array $params = [])
    {
        $assignedUserId = isset($params['assigned_user_id']) ? (int) $params['assigned_user_id'] : 0;
        $soloClienteInbound = $this->boolParam($params, 'solo_cliente_inbound', true);
        $authUserId = isset($params['auth_user_id']) ? (int) $params['auth_user_id'] : 0;
        $isJefe = $this->isPipelineManager($authUserId);

        $this->ensureStageIndex();
        $stages = collect($this->stagesById)
            ->sortBy(function (WaCopilotoPipelineStage $stage) {
                return [(int) $stage->sort_order, (int) $stage->id];
            })
            ->values();

        $query = WaCopilotoConversation::query()
            ->where('session_id', $session->id)
            ->whereNotNull('pipeline_stage_id');

        if ($soloClienteInbound) {
            $query->whereNotNull('customer_initiated_at');
        }

        if ($assignedUserId > 0) {
            $query->where('assigned_user_id', $assignedUserId);
        } elseif (!$isJefe && $authUserId > 0) {
            $query->where('assigned_user_id', $authUserId);
        }

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $assignedUserIds = [];
        foreach ($conversations as $conv) {
            if ($conv->assigned_user_id) {
                $assignedUserIds[] = (int) $conv->assigned_user_id;
            }
        }
        $userNamesById = $this->cacheService->batchUserDisplayNames($assignedUserIds);

        $byStage = [];
        foreach ($stages as $stage) {
            $byStage[(int) $stage->id] = [];
        }

        foreach ($conversations as $conv) {
            $stageId = (int) $conv->pipeline_stage_id;
            if (!isset($byStage[$stageId])) {
                $byStage[$stageId] = [];
            }
            $byStage[$stageId][] = $this->formatKanbanCard($conv, $userNamesById);
        }

        $columns = [];
        foreach ($stages as $stage) {
            $cards = $byStage[(int) $stage->id] ?? [];
            $columns[] = [
                'stage' => $this->formatStage($stage),
                'cards' => $cards,
                'count' => count($cards),
            ];
        }

        return WaJsonUtf8::sanitize([
            'success' => true,
            'data' => [
                'columns' => $columns,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function kanbanCacheFilters(array $params)
    {
        return [
            'assigned_user_id' => isset($params['assigned_user_id']) ? (int) $params['assigned_user_id'] : 0,
            'auth_user_id' => isset($params['auth_user_id']) ? (int) $params['auth_user_id'] : 0,
            'solo_cliente_inbound' => $this->boolParam($params, 'solo_cliente_inbound', true),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function getKpis(array $params = [])
    {
        $session = $this->sessionService->ensureDefaultSession();

        return $this->cacheService->rememberKpis(
            (int) $session->id,
            $this->kanbanCacheFilters($params),
            function () use ($session, $params) {
                return $this->buildKpisResponse($session, $params);
            }
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function buildKpisResponse(WaCopilotoSession $session, array $params = [])
    {
        $assignedUserId = isset($params['assigned_user_id']) ? (int) $params['assigned_user_id'] : 0;
        $authUserId = isset($params['auth_user_id']) ? (int) $params['auth_user_id'] : 0;
        $isJefe = $this->isPipelineManager($authUserId);

        $base = WaCopilotoConversation::query()
            ->where('session_id', $session->id)
            ->whereNotNull('customer_initiated_at');

        if ($assignedUserId > 0) {
            $base->where('assigned_user_id', $assignedUserId);
        } elseif (!$isJefe && $authUserId > 0) {
            $base->where('assigned_user_id', $authUserId);
        }

        $this->ensureStageIndex();
        $cerradoStageIds = [];
        $enProgresoStageIds = [];
        foreach ($this->stagesById as $stage) {
            if ($stage->major === 'cerrado') {
                $cerradoStageIds[] = (int) $stage->id;
            } elseif ($stage->major === 'en_progreso') {
                $enProgresoStageIds[] = (int) $stage->id;
            }
        }

        $monthStart = Carbon::now()->startOfMonth()->toDateTimeString();
        $cerradoList = !empty($cerradoStageIds) ? implode(',', $cerradoStageIds) : '0';
        $enProgresoList = !empty($enProgresoStageIds) ? implode(',', $enProgresoStageIds) : '0';

        $row = (clone $base)->selectRaw(
            'COUNT(*) as total_inbound,
            SUM(CASE WHEN pipeline_stage_id IN (' . $cerradoList . ')
                AND (updated_at >= ? OR last_message_at >= ?) THEN 1 ELSE 0 END) as cerrados_mes,
            SUM(CASE WHEN pipeline_stage_id IN (' . $enProgresoList . ') THEN 1 ELSE 0 END) as en_progreso,
            SUM(CASE WHEN unread_count > 0 THEN 1 ELSE 0 END) as unread_alertas,
            SUM(CASE WHEN ai_temperatura >= 70 AND pipeline_stage_id NOT IN (' . $cerradoList . ') THEN 1 ELSE 0 END) as hot_leads',
            [$monthStart, $monthStart]
        )->first();

        $totalInbound = (int) ($row->total_inbound ?? 0);
        $cerradosMes = (int) ($row->cerrados_mes ?? 0);
        $enProgreso = (int) ($row->en_progreso ?? 0);
        $alertas = (int) ($row->unread_alertas ?? 0) + (int) ($row->hot_leads ?? 0);

        $metaDeals = $assignedUserId > 0 ? 8 : max(8, $this->countActiveAdvisors() * 8);
        $conversion = $totalInbound > 0
            ? (int) round(($cerradosMes / $totalInbound) * 100)
            : 0;

        return [
            'success' => true,
            'data' => [
                'deals_cerrados' => $cerradosMes,
                'deals_meta' => $metaDeals,
                'pipeline_activo' => $enProgreso,
                'conversion_pct' => $conversion,
                'alertas' => $alertas,
                'leads_activos' => $totalInbound,
            ],
        ];
    }

    /**
     * @param  int  $conversationId
     * @param  int  $toStageId
     * @param  int  $userId
     * @param  string|null  $note
     * @return array<string, mixed>
     */
    public function transition($conversationId, $toStageId, $userId, $note = null)
    {
        $conversation = WaCopilotoConversation::query()->findOrFail($conversationId);
        $toStage = WaCopilotoPipelineStage::query()->findOrFail($toStageId);
        $fromStage = $conversation->pipeline_stage_id
            ? WaCopilotoPipelineStage::query()->find($conversation->pipeline_stage_id)
            : null;

        $isJefe = $this->isPipelineManager($userId);
        if (!$this->canUserMoveTo($fromStage, $toStage, $isJefe)) {
            return [
                'success' => false,
                'message' => 'No tienes permiso para mover este lead a esa etapa.',
            ];
        }

        $this->applyTransition($conversation, $fromStage, $toStage, $userId, $note);

        if ($toStage->major === 'cerrado') {
            $conversation->status = 'closed';
            $conversation->save();
        } elseif ($conversation->status === 'closed' && $toStage->major !== 'cerrado') {
            $conversation->status = 'open';
            $conversation->save();
        }

        return [
            'success' => true,
            'data' => app(WaCopilotoConversationService::class)->formatConversation($conversation->fresh()),
        ];
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @param  Carbon|null  $at
     */
    public function onCustomerInbound(WaCopilotoConversation $conversation, $at = null)
    {
        if (!$conversation->customer_initiated_at) {
            $conversation->customer_initiated_at = $at ?: now();
        }

        $stage = $this->stageForConversation($conversation);
        if ($stage && $stage->major === 'cerrado') {
            $postventa = $this->stageBySlug('postventa');
            if ($postventa) {
                $this->applyTransition($conversation, $stage, $postventa, 0, 'Cliente escribió tras cierre');
            }
        }

        $conversation->save();
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @param  int  $advisorUserId
     */
    public function onAdvisorOutbound(WaCopilotoConversation $conversation, $advisorUserId)
    {
        $stage = $this->stageForConversation($conversation);
        if (!$stage) {
            $this->ensureDefaultStage($conversation);
            $stage = $this->stageForConversation($conversation);
        }

        if (!$stage || $stage->major !== 'nuevo') {
            return;
        }

        $outboundCount = WaCopilotoMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'out')
            ->limit(2)
            ->get(['id'])
            ->count();

        if ($outboundCount !== 1) {
            return;
        }

        $contactado = $this->stageBySlug('contactado');
        if ($contactado) {
            $this->applyTransition(
                $conversation,
                $stage,
                $contactado,
                (int) $advisorUserId,
                'Primer mensaje del asesor'
            );
        }
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @param  int|null  $fromUserId
     * @param  int|null  $toUserId
     * @param  int  $byUserId
     */
    public function logAssignment(WaCopilotoConversation $conversation, $fromUserId, $toUserId, $byUserId)
    {
        if ((int) $fromUserId === (int) $toUserId) {
            return;
        }

        WaCopilotoAssignmentLog::query()->create([
            'conversation_id' => (int) $conversation->id,
            'from_user_id' => $fromUserId > 0 ? (int) $fromUserId : null,
            'to_user_id' => $toUserId > 0 ? (int) $toUserId : null,
            'changed_by_user_id' => $byUserId > 0 ? (int) $byUserId : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  int  $conversationId
     * @return array<string, mixed>
     */
    public function assignmentHistory($conversationId)
    {
        $rows = WaCopilotoAssignmentLog::query()
            ->where('conversation_id', (int) $conversationId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $userIds = [];
        foreach ($rows as $row) {
            if ($row->from_user_id) {
                $userIds[] = (int) $row->from_user_id;
            }
            if ($row->to_user_id) {
                $userIds[] = (int) $row->to_user_id;
            }
            if ($row->changed_by_user_id) {
                $userIds[] = (int) $row->changed_by_user_id;
            }
        }
        $userNamesById = $this->cacheService->batchUserDisplayNames($userIds);

        return [
            'success' => true,
            'data' => $rows->map(function (WaCopilotoAssignmentLog $row) use ($userNamesById) {
                return [
                    'id' => (int) $row->id,
                    'from_user_id' => $row->from_user_id ? (int) $row->from_user_id : null,
                    'from_user_name' => $this->userName($row->from_user_id, $userNamesById),
                    'to_user_id' => $row->to_user_id ? (int) $row->to_user_id : null,
                    'to_user_name' => $this->userName($row->to_user_id, $userNamesById),
                    'changed_by_user_id' => $row->changed_by_user_id ? (int) $row->changed_by_user_id : null,
                    'changed_by_name' => $this->userName($row->changed_by_user_id, $userNamesById),
                    'created_at' => $row->created_at,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  int  $conversationId
     * @return array<string, mixed>
     */
    public function transitionHistory($conversationId)
    {
        $rows = WaCopilotoPipelineTransition::query()
            ->where('conversation_id', (int) $conversationId)
            ->with(['fromStage', 'toStage'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $userIds = [];
        foreach ($rows as $row) {
            if ($row->changed_by_user_id) {
                $userIds[] = (int) $row->changed_by_user_id;
            }
        }
        $userNamesById = $this->cacheService->batchUserDisplayNames($userIds);

        return [
            'success' => true,
            'data' => $rows->map(function (WaCopilotoPipelineTransition $row) use ($userNamesById) {
                return [
                    'id' => (int) $row->id,
                    'from_stage_id' => $row->from_stage_id ? (int) $row->from_stage_id : null,
                    'from_stage_label' => $row->fromStage ? $row->fromStage->label : null,
                    'to_stage_id' => (int) $row->to_stage_id,
                    'to_stage_label' => $row->toStage ? $row->toStage->label : null,
                    'major_from' => $row->major_from,
                    'major_to' => $row->major_to,
                    'changed_by_user_id' => $row->changed_by_user_id ? (int) $row->changed_by_user_id : null,
                    'changed_by_name' => $this->userName($row->changed_by_user_id, $userNamesById),
                    'note' => $row->note,
                    'created_at' => $row->created_at,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     */
    public function ensureDefaultStage(WaCopilotoConversation $conversation)
    {
        if ($conversation->pipeline_stage_id) {
            return;
        }

        $nuevo = $this->stageBySlug('nuevo');
        if ($nuevo) {
            $conversation->pipeline_stage_id = (int) $nuevo->id;
            $conversation->save();
        }
    }

    /**
     * @param  WaCopilotoPipelineStage|null  $from
     * @param  WaCopilotoPipelineStage  $to
     * @param  bool  $isJefe
     */
    protected function canUserMoveTo($from, WaCopilotoPipelineStage $to, $isJefe)
    {
        if ($isJefe) {
            return true;
        }

        if ($to->major === 'nuevo' || $to->slug === 'contactado') {
            if ($from && ($from->major !== 'nuevo' && $from->slug !== 'contactado')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @param  WaCopilotoPipelineStage|null  $from
     * @param  WaCopilotoPipelineStage  $to
     * @param  int  $userId
     * @param  string|null  $note
     */
    protected function applyTransition(
        WaCopilotoConversation $conversation,
        $from,
        WaCopilotoPipelineStage $to,
        $userId,
        $note = null
    ) {
        WaCopilotoPipelineTransition::query()->create([
            'conversation_id' => (int) $conversation->id,
            'from_stage_id' => $from ? (int) $from->id : null,
            'to_stage_id' => (int) $to->id,
            'major_from' => $from ? (string) $from->major : null,
            'major_to' => (string) $to->major,
            'changed_by_user_id' => $userId > 0 ? (int) $userId : null,
            'note' => $note ? mb_substr((string) $note, 0, 500) : null,
            'created_at' => now(),
        ]);

        $conversation->pipeline_stage_id = (int) $to->id;
        $conversation->save();

        $this->cacheService->invalidateSession((int) $conversation->session_id);
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @return WaCopilotoPipelineStage|null
     */
    protected function stageForConversation(WaCopilotoConversation $conversation)
    {
        if (!$conversation->pipeline_stage_id) {
            return null;
        }

        $this->ensureStageIndex();
        $stageId = (int) $conversation->pipeline_stage_id;

        return isset($this->stagesById[$stageId]) ? $this->stagesById[$stageId] : null;
    }

    /**
     * @param  string  $slug
     * @return WaCopilotoPipelineStage|null
     */
    protected function stageBySlug($slug)
    {
        $this->ensureStageIndex();
        $slug = (string) $slug;

        return isset($this->stagesBySlug[$slug]) ? $this->stagesBySlug[$slug] : null;
    }

    protected function ensureStageIndex()
    {
        if ($this->stageIndexLoaded) {
            return;
        }

        $stages = WaCopilotoPipelineStage::query()
            ->where('is_active', true)
            ->get();

        foreach ($stages as $stage) {
            $this->stagesById[(int) $stage->id] = $stage;
            $this->stagesBySlug[(string) $stage->slug] = $stage;
        }

        $this->stageIndexLoaded = true;
    }

    protected function resetStageIndex()
    {
        $this->stageIndexLoaded = false;
        $this->stagesById = [];
        $this->stagesBySlug = [];
    }

    /**
     * @param  WaCopilotoPipelineStage  $stage
     * @return array<string, mixed>
     */
    protected function formatStage(WaCopilotoPipelineStage $stage)
    {
        return [
            'id' => (int) $stage->id,
            'major' => (string) $stage->major,
            'slug' => (string) $stage->slug,
            'label' => WaJsonUtf8::sanitizeString((string) $stage->label),
            'sort_order' => (int) $stage->sort_order,
            'is_system' => (bool) $stage->is_system,
        ];
    }

    /**
     * @param  WaCopilotoConversation  $conversation
     * @return array<string, mixed>
     */
    protected function formatKanbanCard(WaCopilotoConversation $conversation, array $userNamesById = [])
    {
        $name = WaJsonUtf8::sanitizeString(trim((string) $conversation->contact_name));
        if ($name === '') {
            $name = (string) $conversation->phone_e164;
        }

        return [
            'conversation_id' => (int) $conversation->id,
            'contact_name' => $name,
            'phone_e164' => (string) $conversation->phone_e164,
            'assigned_user_id' => $conversation->assigned_user_id ? (int) $conversation->assigned_user_id : null,
            'assigned_user_name' => $this->userName($conversation->assigned_user_id, $userNamesById),
            'unread_count' => (int) $conversation->unread_count,
            'temperatura' => $conversation->ai_temperatura !== null ? (int) $conversation->ai_temperatura : null,
            'last_message_at' => $conversation->last_message_at,
            'last_message_preview' => WaJsonUtf8::sanitizeString((string) $conversation->last_message_preview),
            'pipeline_stage_id' => $conversation->pipeline_stage_id ? (int) $conversation->pipeline_stage_id : null,
        ];
    }

    /**
     * @param  int|null  $userId
     * @return string|null
     */
    protected function userName($userId, array $userNamesById = [])
    {
        if (!$userId) {
            return null;
        }

        $uid = (int) $userId;
        if (isset($userNamesById[$uid])) {
            return $userNamesById[$uid];
        }

        $u = Usuario::query()->find($uid);
        if (!$u) {
            return null;
        }

        return WaJsonUtf8::sanitizeString((string) ($u->No_Nombres_Apellidos ?: $u->No_Usuario));
    }

    /**
     * @param  int  $userId
     */
    protected function isPipelineManager($userId)
    {
        if ((int) $userId === EnsureCopilotoWaAccess::JEFE_VENTAS_ID) {
            return true;
        }

        $user = Usuario::query()->find((int) $userId);
        if (!$user) {
            return false;
        }

        $grupo = $user->getNombreGrupo();

        return $grupo === Usuario::ROL_GERENCIA || $grupo === Usuario::ROL_ADMINISTRACION;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  string  $key
     * @param  bool  $default
     */
    protected function boolParam(array $params, $key, $default = true)
    {
        if (!array_key_exists($key, $params)) {
            return $default;
        }

        return filter_var($params[$key], FILTER_VALIDATE_BOOLEAN);
    }

    protected function countActiveAdvisors()
    {
        return (int) DB::table('usuario as u')
            ->join('grupo as g', 'g.ID_Grupo', '=', 'u.ID_Grupo')
            ->where('g.No_Grupo', Usuario::ROL_COTIZADOR)
            ->where('u.Nu_Estado', 1)
            ->count();
    }
}
