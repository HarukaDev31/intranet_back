<?php

namespace App\Services\WhatsApp;

use App\Models\WaCopiloto\WaCopilotoConversation;
use App\Models\WaCopiloto\WaCopilotoSession;
use App\Models\WhatsApp\WaContact;
use App\Models\WhatsappInbox\WaInboxConversation;
use App\Models\WhatsappInbox\WaInboxSession;
use App\Services\WaCopiloto\WaCopilotoConversationService;
use App\Services\WaCopiloto\WaCopilotoSessionService;
use App\Services\WhatsappInbox\WhatsappInboxSessionService;
use App\Support\WhatsApp\WaJsonUtf8;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WaContactService
{
    /** @var WaCopilotoSessionService */
    protected $copilotoSessionService;

    /** @var WhatsappInboxSessionService */
    protected $inboxSessionService;

    public function __construct(
        WaCopilotoSessionService $copilotoSessionService,
        WhatsappInboxSessionService $inboxSessionService
    ) {
        $this->copilotoSessionService = $copilotoSessionService;
        $this->inboxSessionService = $inboxSessionService;
    }

    /**
     * @return WaCopilotoConversationService
     */
    protected function conversationService()
    {
        return app(WaCopilotoConversationService::class);
    }

    /**
     * Registra o actualiza un contacto global cuando aparece en el inbox de coordinación.
     */
    public function registerFromInboxConversation(WaInboxConversation $inboxConv)
    {
        $phone = $this->normalizePhoneE164($inboxConv->phone_e164);
        if ($phone === '') {
            return null;
        }

        $name = trim((string) $inboxConv->contact_name);
        if ($name === '') {
            $name = $phone;
        }

        $inboxSession = $inboxConv->session_id
            ? WaInboxSession::query()->find((int) $inboxConv->session_id)
            : $this->inboxSessionService->ensureDefaultSession();
        $origin = $this->originFromInboxSession($inboxSession);

        return $this->upsertContact($phone, $name, 'inbox_coordinacion', (int) $inboxConv->id, $origin);
    }

    /**
     * Registra o actualiza el directorio global cuando el cliente escribe o aparece en Copiloto (ventas).
     */
    public function registerFromCopilotoConversation(WaCopilotoConversation $copilotConv)
    {
        $phone = $this->normalizePhoneE164($copilotConv->phone_e164);
        if ($phone === '') {
            return null;
        }

        $name = trim((string) $copilotConv->contact_name);
        if ($name === '') {
            $name = $phone;
        }

        $session = $copilotConv->session_id
            ? WaCopilotoSession::query()->find((int) $copilotConv->session_id)
            : $this->copilotoSessionService->ensureDefaultSession();
        $origin = $this->originFromCopilotoSession($session);

        $contact = $this->upsertContactFromCopiloto($phone, $name, $origin);

        if ((int) $copilotConv->contact_id !== (int) $contact->id) {
            $copilotConv->contact_id = (int) $contact->id;
            $copilotConv->save();
        }

        return $contact;
    }

    /**
     * @param  string  $phoneE164
     * @param  string  $contactName
     * @param  array<string, mixed>|null  $origin
     * @return WaContact
     */
    protected function upsertContactFromCopiloto($phoneE164, $contactName, $origin = null)
    {
        $phoneE164 = $this->normalizePhoneE164($phoneE164);
        $name = trim((string) $contactName);
        if ($name === '') {
            $name = $phoneE164;
        }

        $contact = WaContact::query()->where('phone_e164', $phoneE164)->first();
        if (!$contact) {
            $payload = [
                'phone_e164' => $phoneE164,
                'contact_name' => $name,
                'source' => 'copiloto_ventas',
                'wa_inbox_conversation_id' => null,
                'synced_at' => now(),
            ];

            return WaContact::create(array_merge($payload, $this->originFieldsForCreate($origin)));
        }

        $dirty = false;
        $existingName = trim((string) $contact->contact_name);
        if ($name !== '' && ($existingName === '' || $existingName === $phoneE164) && $name !== $existingName) {
            $contact->contact_name = $name;
            $dirty = true;
        }
        if ((string) $contact->source === '') {
            $contact->source = 'copiloto_ventas';
            $dirty = true;
        }
        if (trim((string) $contact->origin_line_number) === '' && is_array($origin)) {
            foreach ($this->originFieldsForCreate($origin) as $field => $value) {
                if ($value !== null && $value !== '') {
                    $contact->{$field} = $value;
                    $dirty = true;
                }
            }
        }
        $contact->synced_at = now();
        $dirty = true;

        if ($dirty) {
            $contact->save();
        }

        return $contact;
    }

    /**
     * Importa teléfonos desde wa_inbox_conversations hacia wa_contacts.
     *
     * @param  string|null  $copilotoSessionSlug  Sesión destino para contar pendientes
     * @return array<string, mixed>
     */
    public function syncFromInbox($copilotoSessionSlug = null)
    {
        $copilotoSession = $this->copilotoSessionService->ensureDefaultSession($copilotoSessionSlug);
        $inboxSession = $this->inboxSessionService->ensureDefaultSession();

        $inboxRows = WaInboxConversation::query()
            ->where('session_id', $inboxSession->id)
            ->where('phone_e164', '!=', '')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get(['id', 'phone_e164', 'contact_name']);

        $created = 0;
        $updated = 0;

        $inboxOrigin = $this->originFromInboxSession($inboxSession);

        foreach ($inboxRows as $inboxConv) {
            $phone = $this->normalizePhoneE164($inboxConv->phone_e164);
            if ($phone === '') {
                continue;
            }

            $name = trim((string) $inboxConv->contact_name);
            if ($name === '') {
                $name = $phone;
            }

            $existing = WaContact::query()->where('phone_e164', $phone)->first();
            $this->upsertContact($phone, $name, 'inbox_coordinacion', (int) $inboxConv->id, $inboxOrigin);

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        $linked = (int) WaCopilotoConversation::query()
            ->where('session_id', $copilotoSession->id)
            ->whereNotNull('contact_id')
            ->count();

        return [
            'success' => true,
            'data' => [
                'copiloto_session_id' => (int) $copilotoSession->id,
                'inbox_session_id' => (int) $inboxSession->id,
                'inbox_total' => $inboxRows->count(),
                'created' => $created,
                'updated' => $updated,
                'linked' => $linked,
                'pending' => $this->countPendingForSession($copilotoSession),
            ],
        ];
    }

    /**
     * Contactos globales sin conversación en la sesión indicada.
     *
     * @param  WaCopilotoSession  $session
     * @return int
     */
    public function countPendingForSession(WaCopilotoSession $session)
    {
        return (int) $this->pendingContactsQuery($session)->count();
    }

    /**
     * @param  WaCopilotoSession  $session
     * @param  string  $search
     * @param  int  $limit
     * @return array<int, array<string, mixed>>
     */
    public function listPendingAsConversations(WaCopilotoSession $session, $search = '', $limit = 200)
    {
        $query = $this->pendingContactsQuery($session)
            ->orderBy('contact_name')
            ->orderBy('id');

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', '%' . $search . '%')
                    ->orWhere('phone_e164', 'like', '%' . $search . '%');
            });
        }

        $rows = [];
        foreach ($query->limit(max(1, min(500, (int) $limit)))->get() as $contact) {
            $rows[] = $this->formatPendingConversation($contact, $session);
        }

        return $rows;
    }

    /**
     * Abre conversación en una sesión Copiloto para un contacto del directorio.
     *
     * @param  int  $contactId
     * @param  int  $assignedUserId
     * @param  string|null  $sessionSlug
     * @return array<string, mixed>
     */
    public function openConversationForContact($contactId, $assignedUserId = 0, $sessionSlug = null)
    {
        $contact = WaContact::query()->find((int) $contactId);
        if (!$contact) {
            return ['success' => false, 'message' => 'Contacto no encontrado.'];
        }

        $session = $this->copilotoSessionService->ensureDefaultSession($sessionSlug);

        $existing = WaCopilotoConversation::query()
            ->where('session_id', $session->id)
            ->where('phone_e164', $contact->phone_e164)
            ->first();

        if ($existing) {
            if (!$existing->contact_id) {
                $existing->contact_id = (int) $contact->id;
                $existing->save();
            }

            return [
                'success' => true,
                'created' => false,
                'message' => 'Conversación ya existente en esta línea.',
                'data' => $this->conversationService()->formatConversation($existing),
            ];
        }

        $result = $this->conversationService()->createManualContact([
            'phone' => $contact->phone_e164,
            'contact_name' => $contact->contact_name ?: $contact->phone_e164,
            'assigned_user_id' => (int) $assignedUserId,
            'contact_id' => (int) $contact->id,
            'session_slug' => $session->slug,
        ]);

        return $result;
    }

    /**
     * @param  string  $phoneE164
     * @param  string  $contactName
     * @param  string  $source
     * @param  int|null  $inboxConversationId
     * @param  array<string, mixed>|null  $origin
     * @return WaContact
     */
    public function upsertContact($phoneE164, $contactName, $source = 'manual', $inboxConversationId = null, $origin = null)
    {
        $phoneE164 = $this->normalizePhoneE164($phoneE164);
        $name = WaJsonUtf8::sanitizeString(trim((string) $contactName));
        if ($name === '') {
            $name = $phoneE164;
        }

        $contact = WaContact::query()->where('phone_e164', $phoneE164)->first();
        if (!$contact) {
            $payload = [
                'phone_e164' => $phoneE164,
                'contact_name' => $name,
                'source' => $source,
                'wa_inbox_conversation_id' => $inboxConversationId,
                'synced_at' => now(),
            ];
            $payload = array_merge($payload, $this->originFieldsForCreate($origin));

            return WaContact::create($payload);
        }

        $dirty = false;
        if ($name !== '' && $name !== (string) $contact->contact_name) {
            $contact->contact_name = $name;
            $dirty = true;
        }
        if ($inboxConversationId && (int) $contact->wa_inbox_conversation_id !== (int) $inboxConversationId) {
            $contact->wa_inbox_conversation_id = (int) $inboxConversationId;
            $dirty = true;
        }
        if ($source !== '' && (string) $contact->source === '') {
            $contact->source = $source;
            $dirty = true;
        }
        $contact->synced_at = now();
        $dirty = true;

        if ($dirty) {
            $contact->save();
        }

        return $contact;
    }

    /**
     * @param  WaCopilotoSession  $session
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function pendingContactsQuery(WaCopilotoSession $session)
    {
        return WaContact::query()
            ->whereNotExists(function ($q) use ($session) {
                $q->select(DB::raw(1))
                    ->from('wa_copiloto_conversations')
                    ->whereColumn('wa_copiloto_conversations.phone_e164', 'wa_contacts.phone_e164')
                    ->where('wa_copiloto_conversations.session_id', $session->id);
            });
    }

    /**
     * @param  WaContact  $contact
     * @param  WaCopilotoSession  $session
     * @return array<string, mixed>
     */
    public function formatPendingConversation(WaContact $contact, WaCopilotoSession $session)
    {
        $name = WaJsonUtf8::sanitizeString(trim((string) $contact->contact_name));
        if ($name === '') {
            $name = $contact->phone_e164;
        }

        $sourceLabel = $contact->source === 'inbox_coordinacion'
            ? 'Coordinación'
            : ($contact->source === 'copiloto_ventas'
                ? ($session->label ?: 'Ventas')
                : ucfirst(str_replace('_', ' ', (string) $contact->source)));

        return WaJsonUtf8::sanitize([
            'id' => 0,
            'contact_id' => (int) $contact->id,
            'pending_contact' => true,
            'source' => (string) $contact->source,
            'contact_name' => $name,
            'phone_display' => $this->formatPhoneDisplay($contact->phone_e164),
            'phone_e164' => $contact->phone_e164,
            'initials' => $this->initials($name),
            'last_message_preview' => 'Sin chat en ' . ($session->label ?: 'esta línea'),
            'last_message_at' => $contact->synced_at
                ? Carbon::parse($contact->synced_at)->toIso8601String()
                : null,
            'last_message_time_label' => '',
            'last_direction' => null,
            'last_message_type' => null,
            'last_message_delivery_status' => null,
            'last_message_id' => null,
            'unread_count' => 0,
            'assigned_user_id' => null,
            'assigned_user_name' => null,
            'window_state' => 'closed',
            'window_label' => 'Sin ventana — usa plantilla',
            'window_expires_at' => null,
            'can_send_text' => false,
            'channel_label' => $sourceLabel,
            'status' => 'open',
        ] + $this->originPayload($contact));
    }

    /**
     * @param  WaInboxSession|null  $session
     * @return array<string, mixed>|null
     */
    public function originFromInboxSession($session)
    {
        if (!$session) {
            return null;
        }

        return [
            'origin_module' => 'inbox',
            'origin_session_id' => (int) $session->id,
            'origin_line_number' => trim((string) $session->display_number),
            'origin_line_label' => trim((string) ($session->label ?: 'Coordinación')),
        ];
    }

    /**
     * @param  WaCopilotoSession|null  $session
     * @return array<string, mixed>|null
     */
    public function originFromCopilotoSession($session)
    {
        if (!$session) {
            return null;
        }

        return [
            'origin_module' => 'copiloto',
            'origin_session_id' => (int) $session->id,
            'origin_line_number' => trim((string) $session->display_number),
            'origin_line_label' => trim((string) ($session->label ?: 'Copiloto')),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $origin
     * @return array<string, mixed>
     */
    protected function originFieldsForCreate($origin)
    {
        if (!is_array($origin)) {
            return [
                'origin_module' => null,
                'origin_session_id' => null,
                'origin_line_number' => null,
                'origin_line_label' => null,
            ];
        }

        return [
            'origin_module' => isset($origin['origin_module']) ? (string) $origin['origin_module'] : null,
            'origin_session_id' => isset($origin['origin_session_id']) ? (int) $origin['origin_session_id'] : null,
            'origin_line_number' => isset($origin['origin_line_number']) ? (string) $origin['origin_line_number'] : null,
            'origin_line_label' => isset($origin['origin_line_label']) ? (string) $origin['origin_line_label'] : null,
        ];
    }

    /**
     * @param  WaContact  $contact
     * @return array<string, mixed>
     */
    public function originPayload(WaContact $contact)
    {
        $number = trim((string) $contact->origin_line_number);
        $label = trim((string) $contact->origin_line_label);

        return [
            'origin_line_number' => $number !== '' ? $number : null,
            'origin_line_label' => $label !== '' ? $label : null,
        ];
    }

    /**
     * @param  string  $phone
     * @return string
     */
    public function normalizePhoneE164($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 9) {
            return '51' . $digits;
        }

        return $digits;
    }

    /**
     * @param  string  $phoneE164
     * @return string
     */
    private function formatPhoneDisplay($phoneE164)
    {
        $d = preg_replace('/\D+/', '', (string) $phoneE164);
        if (strlen($d) === 11 && strpos($d, '51') === 0) {
            return '+51 ' . substr($d, 2, 3) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8);
        }

        return $phoneE164 ? ('+' . $d) : '';
    }

    /**
     * @param  string  $name
     * @return string
     */
    private function initials($name)
    {
        $words = preg_split('/\s+/', trim($name));
        if (!$words || !isset($words[0])) {
            return 'CL';
        }
        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 2));
        }

        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
}
