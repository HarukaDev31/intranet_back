<?php

namespace App\Http\Controllers\WhatsappInbox;

use App\Http\Controllers\Controller;
use App\Jobs\WhatsappInbox\SendWaInboxOutboundJob;
use App\Models\WhatsappInbox\WaInboxConversation;
use App\Services\WhatsappInbox\WhatsappInboxConversationService;
use App\Services\WhatsappInbox\WhatsappInboxMessageService;
use App\Services\WhatsappInbox\WhatsappInboxSessionService;
use App\Services\WhatsappInbox\WhatsappInboxTemplateService;
use App\Services\WhatsappInbox\WhatsappInboxWindowService;
use App\Support\WhatsApp\CoordinacionMediaLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class WhatsappInboxController extends Controller
{
    /** @var WhatsappInboxSessionService */
    protected $sessionService;

    /** @var WhatsappInboxConversationService */
    protected $conversationService;

    /** @var WhatsappInboxMessageService */
    protected $messageService;

    /** @var WhatsappInboxTemplateService */
    protected $templateService;

    /** @var WhatsappInboxWindowService */
    protected $windowService;

    public function __construct(
        WhatsappInboxSessionService $sessionService,
        WhatsappInboxConversationService $conversationService,
        WhatsappInboxMessageService $messageService,
        WhatsappInboxTemplateService $templateService,
        WhatsappInboxWindowService $windowService
    ) {
        $this->sessionService = $sessionService;
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
        $this->templateService = $templateService;
        $this->windowService = $windowService;
    }

    public function session()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->sessionService->getSessionPayload(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function conversations(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $params = $request->all();
            $params['auth_user_id'] = $user ? (int) $user->getIdUsuario() : 0;

            return response()->json($this->conversationService->listConversations($params));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar conversaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function messages(Request $request, $id)
    {
        try {
            return response()->json($this->messageService->listMessages((int) $id, $request->all()));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar mensajes: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function sendMessage(Request $request, $id)
    {
        try {
            $text = trim((string) $request->input('message', ''));
            if ($text === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Mensaje vacío',
                ], 422);
            }

            $conversation = WaInboxConversation::query()->findOrFail((int) $id);
            $window = $this->windowService->computeWindowState($conversation);
            if (empty($window['can_send_text'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ventana cerrada. Envía una plantilla para reactivar.',
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $message = $this->messageService->createOutboundPending(
                $conversation,
                $text,
                $user ? (int) $user->getIdUsuario() : null,
                'text'
            );

            SendWaInboxOutboundJob::dispatch($message->id);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje en cola de envío',
                'data' => $this->messageService->formatMessage($message),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function sendTemplate(Request $request, $id)
    {
        try {
            $templateName = trim((string) $request->input('template_name', ''));
            $params = $request->input('params', []);
            if ($templateName === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Plantilla requerida',
                ], 422);
            }
            if (is_string($params)) {
                $decoded = json_decode($params, true);
                $params = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($params)) {
                $params = [];
            }

            $header = $this->resolveTemplateHeaderFromRequest($request);
            if ($header !== null) {
                $params['_header'] = $header;
            }

            $body = $this->buildTemplatePreviewBody($templateName, $params);

            $conversation = WaInboxConversation::query()->findOrFail((int) $id);
            $user = JWTAuth::parseToken()->authenticate();

            $message = $this->messageService->createOutboundPending(
                $conversation,
                $body,
                $user ? (int) $user->getIdUsuario() : null,
                'template',
                $templateName,
                $params
            );

            SendWaInboxOutboundJob::dispatch($message->id);

            return response()->json([
                'success' => true,
                'message' => 'Plantilla en cola de envío',
                'data' => $this->messageService->formatMessage($message),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar plantilla: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assign(Request $request, $id)
    {
        try {
            $userId = (int) $request->input('user_id', 0);

            return response()->json($this->conversationService->assign((int) $id, $userId));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function markRead($id)
    {
        try {
            return response()->json($this->conversationService->markRead((int) $id));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function templates()
    {
        try {
            return response()->json($this->templateService->listTemplates());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar plantillas: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assignableUsers()
    {
        try {
            return response()->json($this->conversationService->getAssignableUsers());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  string  $templateName
     * @param  array<string, mixed>  $params
     * @return string
     */
    private function buildTemplatePreviewBody($templateName, array $params)
    {
        $list = $this->templateService->listTemplates();
        $templates = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];
        $text = '[' . $templateName . ']';
        foreach ($templates as $tpl) {
            if (isset($tpl['name']) && $tpl['name'] === $templateName) {
                $text = isset($tpl['text']) ? (string) $tpl['text'] : $text;
                break;
            }
        }

        foreach ($params as $key => $val) {
            if ($key === '_header' || is_array($val)) {
                continue;
            }
            $text = str_replace('{{' . $key . '}}', (string) $val, $text);
        }

        return $text;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>|null
     */
    private function resolveTemplateHeaderFromRequest(Request $request)
    {
        $file = $request->file('header_media');
        if (!$file || !$file->isValid()) {
            foreach ($request->allFiles() as $uploaded) {
                $candidate = is_array($uploaded) ? ($uploaded[0] ?? null) : $uploaded;
                if ($candidate && $candidate->isValid()) {
                    $file = $candidate;
                    break;
                }
            }
        }

        if (!$file || !$file->isValid()) {
            return null;
        }

        $kind = strtolower((string) $request->input('header_file_kind', 'document'));
        if (!in_array($kind, ['document', 'image', 'video'], true)) {
            $kind = 'document';
        }

        $storageKey = CoordinacionMediaLink::META_TEMP_PREFIX . '/inbox/'
            . Str::uuid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        $url = CoordinacionMediaLink::uploadLocalFile($file->getRealPath(), $storageKey);
        if ($url === null || $url === '') {
            return null;
        }

        return [
            'type' => $kind,
            'link' => $url,
            'filename' => $file->getClientOriginalName(),
        ];
    }
}
