<?php

namespace App\Services\WhatsappInbox;

use App\Models\Grupo;
use App\Models\Usuario;
use App\Models\WhatsappInbox\WaInboxConversation;
use App\Models\WhatsappInbox\WaInboxMessage;
use App\Models\WhatsappInbox\WaInboxSession;
use Carbon\Carbon;

class WhatsappInboxConversationService
{
    /** @var WhatsappInboxSessionService */
    protected $sessionService;

    /** @var WhatsappInboxWindowService */
    protected $windowService;

    public function __construct(
        WhatsappInboxSessionService $sessionService,
        WhatsappInboxWindowService $windowService
    ) {
        $this->sessionService = $sessionService;
        $this->windowService = $windowService;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function listConversations(array $params = [])
    {
        $session = $this->sessionService->ensureDefaultSession();
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 30)));
        $search = trim((string) ($params['search'] ?? ''));
        $filter = trim((string) ($params['filter'] ?? 'todas'));
        $userId = isset($params['auth_user_id']) ? (int) $params['auth_user_id'] : 0;

        $query = WaInboxConversation::query()
            ->where('session_id', $session->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', '%' . $search . '%')
                    ->orWhere('phone_e164', 'like', '%' . $search . '%');
            });
        }

        if ($filter === 'sin-asignar') {
            $query->whereNull('assigned_user_id');
        } elseif ($filter === 'mis' && $userId > 0) {
            $query->where('assigned_user_id', $userId);
        } elseif ($filter === 'cerradas') {
            $query->where('status', 'closed');
        }

        $paginated = $query->paginate($perPage);
        $rows = [];
        foreach ($paginated->items() as $conv) {
            $rows[] = $this->formatConversation($conv);
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

    /**
     * @param  WaInboxConversation  $conversation
     * @return array<string, mixed>
     */
    public function formatConversation(WaInboxConversation $conversation)
    {
        $window = $this->windowService->computeWindowState($conversation);
        $name = trim((string) $conversation->contact_name);
        if ($name === '') {
            $name = $conversation->phone_e164;
        }

        $assignedName = null;
        if ($conversation->assigned_user_id) {
            $u = Usuario::query()->find($conversation->assigned_user_id);
            $assignedName = $u ? ($u->No_Nombres_Apellidos ?: $u->No_Usuario) : null;
        }

        $initials = $this->initials($name);
        $timeLabel = $conversation->last_message_at
            ? Carbon::parse($conversation->last_message_at)->format('H:i')
            : '';

        return [
            'id' => (int) $conversation->id,
            'contact_name' => $name,
            'phone_display' => $this->formatPhoneDisplay($conversation->phone_e164),
            'phone_e164' => $conversation->phone_e164,
            'initials' => $initials,
            'last_message_preview' => $conversation->last_message_preview,
            'last_message_at' => $conversation->last_message_at,
            'last_message_time_label' => $timeLabel,
            'last_direction' => $conversation->last_direction,
            'last_message_type' => $conversation->last_message_type,
            'last_message_delivery_status' => $conversation->last_direction === 'out'
                ? $conversation->last_message_delivery_status
                : null,
            'last_message_id' => $conversation->last_message_id
                ? (int) $conversation->last_message_id
                : null,
            'unread_count' => (int) $conversation->unread_count,
            'assigned_user_id' => $conversation->assigned_user_id,
            'assigned_user_name' => $assignedName,
            'window_state' => $window['state'],
            'window_label' => $window['label'],
            'window_expires_at' => $window['expires_at'],
            'can_send_text' => $window['can_send_text'],
            'channel_label' => $conversation->channel_label,
            'status' => $conversation->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAssignableUsers()
    {
        $grupo = Grupo::query()->where('No_Grupo', Usuario::ROL_COORDINACION)->first();
        if (!$grupo) {
            return ['success' => true, 'data' => []];
        }

        $users = Usuario::query()
            ->where('ID_Grupo', $grupo->ID_Grupo)
            ->where('Nu_Estado', 1)
            ->orderBy('No_Nombres_Apellidos')
            ->get(['ID_Usuario', 'No_Nombres_Apellidos', 'No_Usuario']);

        $rows = [];
        foreach ($users as $u) {
            $rows[] = [
                'id' => (int) $u->ID_Usuario,
                'name' => $u->No_Nombres_Apellidos ?: $u->No_Usuario,
            ];
        }

        return ['success' => true, 'data' => $rows];
    }

    /**
     * @param  int  $conversationId
     * @param  int  $userId
     * @return array<string, mixed>
     */
    public function assign($conversationId, $userId)
    {
        $conversation = WaInboxConversation::query()->findOrFail($conversationId);
        $conversation->assigned_user_id = $userId > 0 ? $userId : null;
        $conversation->assigned_at = $userId > 0 ? now() : null;
        $conversation->save();

        return [
            'success' => true,
            'data' => $this->formatConversation($conversation->fresh()),
        ];
    }

    /**
     * @param  int  $conversationId
     * @return array<string, mixed>
     */
    public function markRead($conversationId)
    {
        $conversation = WaInboxConversation::query()->findOrFail($conversationId);
        $conversation->unread_count = 0;
        $conversation->save();

        return ['success' => true, 'data' => $this->formatConversation($conversation)];
    }

    /**
     * @param  int  $conversationId
     * @param  string  $contactName
     * @return array<string, mixed>
     */
    public function renameContact($conversationId, $contactName)
    {
        $name = trim((string) $contactName);
        if ($name === '') {
            return [
                'success' => false,
                'message' => 'El nombre no puede estar vacío.',
            ];
        }

        if (mb_strlen($name) > 120) {
            return [
                'success' => false,
                'message' => 'El nombre es demasiado largo (máximo 120 caracteres).',
            ];
        }

        $conversation = WaInboxConversation::query()->findOrFail($conversationId);
        $conversation->contact_name = $name;
        $conversation->save();

        return [
            'success' => true,
            'message' => 'Nombre actualizado',
            'data' => $this->formatConversation($conversation),
        ];
    }

    /**
     * Registra un contacto manual en wa_inbox_conversations (sesión activa).
     *
     * @param  array<string, mixed>  $params  phone, contact_name, assigned_user_id (opcional)
     * @return array<string, mixed>
     */
    public function createManualContact(array $params = [])
    {
        $session = $this->sessionService->ensureDefaultSession();
        $phoneE164 = $this->normalizePhoneE164(isset($params['phone']) ? $params['phone'] : '');
        if ($phoneE164 === '' || strlen($phoneE164) < 10 || strlen($phoneE164) > 15) {
            return [
                'success' => false,
                'message' => 'Indica un teléfono válido (9 dígitos Perú o con código 51).',
            ];
        }

        $contactName = trim((string) ($params['contact_name'] ?? ''));
        if ($contactName === '' || mb_strlen($contactName) < 2) {
            return [
                'success' => false,
                'message' => 'Indica el nombre del contacto (mínimo 2 caracteres).',
            ];
        }

        $assignedUserId = isset($params['assigned_user_id']) ? (int) $params['assigned_user_id'] : 0;
        if ($assignedUserId > 0) {
            $allowed = [];
            foreach ($this->getAssignableUsers()['data'] as $row) {
                if (isset($row['id'])) {
                    $allowed[] = (int) $row['id'];
                }
            }
            if (!in_array($assignedUserId, $allowed, true)) {
                return [
                    'success' => false,
                    'message' => 'El usuario para asignar no es válido.',
                ];
            }
        }

        $existing = WaInboxConversation::query()
            ->where('session_id', $session->id)
            ->where('phone_e164', $phoneE164)
            ->first();

        if ($existing) {
            $dirty = false;
            if ($contactName !== '' && trim((string) $existing->contact_name) === '') {
                $existing->contact_name = $contactName;
                $dirty = true;
            }
            if ($assignedUserId > 0 && !$existing->assigned_user_id) {
                $existing->assigned_user_id = $assignedUserId;
                $existing->assigned_at = now();
                $dirty = true;
            }
            if ($dirty) {
                $existing->save();
            }

            return [
                'success' => true,
                'created' => false,
                'message' => 'Este número ya está registrado en el inbox.',
                'data' => $this->formatConversation($existing->fresh()),
            ];
        }

        $conversation = WaInboxConversation::create([
            'session_id' => $session->id,
            'wa_contact_id' => null,
            'phone_e164' => $phoneE164,
            'contact_name' => $contactName,
            'channel_label' => 'Coordinación',
            'status' => 'open',
            'assigned_user_id' => $assignedUserId > 0 ? $assignedUserId : null,
            'assigned_at' => $assignedUserId > 0 ? now() : null,
        ]);

        return [
            'success' => true,
            'created' => true,
            'message' => 'Contacto registrado. Para el primer mensaje usa una plantilla aprobada.',
            'data' => $this->formatConversation($conversation),
        ];
    }

    /**
     * @param  WaInboxSession  $session
     * @param  string  $phoneE164
     * @param  string|null  $waContactId
     * @param  string|null  $contactName
     * @return WaInboxConversation
     */
    public function findOrCreateConversation(WaInboxSession $session, $phoneE164, $waContactId = null, $contactName = null)
    {
        $phoneE164 = $this->normalizePhoneE164($phoneE164);
        $conversation = WaInboxConversation::query()
            ->where('session_id', $session->id)
            ->where('phone_e164', $phoneE164)
            ->first();

        if ($conversation) {
            if ($waContactId && empty($conversation->wa_contact_id)) {
                $conversation->wa_contact_id = $waContactId;
            }
            if ($contactName && empty($conversation->contact_name)) {
                $conversation->contact_name = $contactName;
            }
            $conversation->save();

            return $conversation;
        }

        return WaInboxConversation::create([
            'session_id' => $session->id,
            'wa_contact_id' => $waContactId,
            'phone_e164' => $phoneE164,
            'contact_name' => $contactName,
            'channel_label' => 'Coordinación',
            'status' => 'open',
        ]);
    }

    /**
     * @param  string  $messageType
     * @param  string|null  $body
     * @param  string|null  $mediaFilename
     * @return string
     */
    public function buildLastMessagePreview($messageType, $body = null, $mediaFilename = null)
    {
        $type = strtolower(trim((string) $messageType));
        if ($type === 'image') {
            return 'Foto';
        }
        if ($type === 'sticker') {
            return 'Sticker';
        }
        if ($type === 'video') {
            return 'Video';
        }
        if ($type === 'audio') {
            return 'Audio';
        }
        if ($type === 'document') {
            $name = trim((string) $mediaFilename);
            if ($name === '') {
                $name = trim((string) $body);
            }
            if ($name !== '' && strpos($name, '[') !== 0) {
                return mb_strlen($name) > 80 ? mb_substr($name, 0, 80) . '…' : $name;
            }

            return 'Documento';
        }
        if ($type === 'template') {
            return 'Plantilla';
        }

        $text = trim((string) $body);
        if ($text !== '' && strpos($text, '[') !== 0) {
            return mb_substr($text, 0, 500);
        }

        return 'Mensaje';
    }

    /**
     * @param  WaInboxMessage  $message
     */
    public function refreshHeaderFromMessage(WaInboxConversation $conversation, WaInboxMessage $message, $fromCustomer = false)
    {
        $direction = $message->direction === 'out' ? 'out' : 'in';
        $filename = null;
        if (is_array($message->template_params) && !empty($message->template_params['_media_filename'])) {
            $filename = (string) $message->template_params['_media_filename'];
        }
        $preview = $this->buildLastMessagePreview($message->message_type, $message->body, $filename);
        $sentAt = $message->sent_at ? $message->sent_at : now();

        $this->refreshHeader($conversation, $preview, $direction, $sentAt, $fromCustomer, [
            'message_id' => (int) $message->id,
            'message_type' => (string) $message->message_type,
            'delivery_status' => $direction === 'out' ? (string) $message->delivery_status : null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function refreshHeader(
        WaInboxConversation $conversation,
        $preview,
        $direction,
        $sentAt,
        $fromCustomer = false,
        $meta = null
    ) {
        $sentAt = $sentAt instanceof Carbon ? $sentAt : Carbon::parse($sentAt);
        $current = $conversation->last_message_at;

        if (!$current || $sentAt >= Carbon::parse($current)) {
            $conversation->last_message_at = $sentAt;
            $conversation->last_message_preview = mb_substr(trim((string) $preview), 0, 500);
            $conversation->last_direction = $direction === 'out' ? 'out' : 'in';

            if (is_array($meta)) {
                if (!empty($meta['message_id'])) {
                    $conversation->last_message_id = (int) $meta['message_id'];
                }
                if (!empty($meta['message_type'])) {
                    $conversation->last_message_type = (string) $meta['message_type'];
                }
                if ($direction === 'out' && !empty($meta['delivery_status'])) {
                    $conversation->last_message_delivery_status = (string) $meta['delivery_status'];
                } else {
                    $conversation->last_message_delivery_status = null;
                }
            }
        }

        if ($fromCustomer) {
            $conversation->last_customer_message_at = $sentAt;
            $conversation->window_expires_at = $sentAt->copy()->addHours(24);
            if ($direction === 'in') {
                $conversation->unread_count = (int) $conversation->unread_count + 1;
            }
            $conversation->status = 'open';
        }

        $conversation->save();
    }

    /**
     * Actualiza el estado en sidebar si el mensaje sigue siendo el último saliente.
     *
     * @param  WaInboxMessage  $message
     */
    public function syncLastMessageDeliveryStatus(WaInboxMessage $message)
    {
        $conversation = WaInboxConversation::query()->find($message->conversation_id);
        if (!$conversation || $conversation->last_direction !== 'out') {
            return;
        }

        if ((int) $conversation->last_message_id !== (int) $message->id) {
            return;
        }

        $conversation->last_message_delivery_status = (string) $message->delivery_status;
        $conversation->save();
    }

    public function normalizePhoneE164($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 9) {
            $digits = '51' . $digits;
        }

        return $digits;
    }

    private function formatPhoneDisplay($phoneE164)
    {
        $d = preg_replace('/\D+/', '', (string) $phoneE164);
        if (strlen($d) === 11 && substr($d, 0, 2) === '51') {
            return '+51 ' . substr($d, 2, 3) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8);
        }

        return '+' . $d;
    }

    private function initials($name)
    {
        $parts = preg_split('/\s+/', trim((string) $name));
        $letters = '';
        foreach ($parts as $p) {
            if ($p !== '') {
                $letters .= mb_strtoupper(mb_substr($p, 0, 1));
            }
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : '?';
    }
}
