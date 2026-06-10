<?php

namespace App\Http\Controllers\WaCopiloto;

use App\Http\Controllers\Controller;
use App\Jobs\WaCopiloto\SendWaCopilotoOutboundJob;
use App\Models\WaCopiloto\WaCopilotoConversation;
use App\Services\WhatsApp\WaContactService;
use App\Services\WaCopiloto\WaCopilotoConversationService;
use App\Services\WaCopiloto\WaCopilotoPipelineService;
use App\Services\WaCopiloto\WaCopilotoMessageService;
use App\Services\WaCopiloto\WaCopilotoSessionService;
use App\Services\WaCopiloto\WaCopilotoTemplateService;
use App\Services\WaCopiloto\WaCopilotoMediaUploadService;
use App\Services\WaCopiloto\WaCopilotoScheduledMessageService;
use App\Services\WaCopiloto\WaCopilotoSuggestionUsageService;
use App\Services\WaCopiloto\WaCopilotoWindowService;
use Carbon\Carbon;
use App\Support\WhatsApp\CoordinacionMediaLink;
use App\Support\WhatsApp\WaCopilotoJobContext;
use App\Support\WhatsApp\WaCopilotoLog;
use App\Support\WhatsApp\WaInboxVideoTranscoder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class WaCopilotoController extends Controller
{
    /** @var WaCopilotoSessionService */
    protected $sessionService;

    /** @var WaCopilotoConversationService */
    protected $conversationService;

    /** @var WaCopilotoMessageService */
    protected $messageService;

    /** @var WaCopilotoTemplateService */
    protected $templateService;

    /** @var WaCopilotoWindowService */
    protected $windowService;

    /** @var WaContactService */
    protected $contactService;

    /** @var WaCopilotoPipelineService */
    protected $pipelineService;

    public function __construct(
        WaCopilotoSessionService $sessionService,
        WaCopilotoConversationService $conversationService,
        WaCopilotoMessageService $messageService,
        WaCopilotoTemplateService $templateService,
        WaCopilotoWindowService $windowService,
        WaContactService $contactService,
        WaCopilotoPipelineService $pipelineService
    ) {
        $this->sessionService = $sessionService;
        $this->conversationService = $conversationService;
        $this->messageService = $messageService;
        $this->templateService = $templateService;
        $this->windowService = $windowService;
        $this->contactService = $contactService;
        $this->pipelineService = $pipelineService;
    }

    public function session(Request $request)
    {
        try {
            $slug = $request->query('slug');

            return response()->json([
                'success' => true,
                'data' => $this->sessionService->getSessionPayload($slug),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function sessions()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->sessionService->listActiveSessions(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function syncContacts(Request $request)
    {
        try {
            $slug = $request->input('session_slug');
            $result = $this->contactService->syncFromInbox($slug);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar contactos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function openContactConversation(Request $request, $contactId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $assignedUserId = (int) $request->input('assigned_user_id', 0);
            if ($assignedUserId <= 0 && $user) {
                $assignedUserId = (int) $user->getIdUsuario();
            }

            $result = $this->contactService->openConversationForContact(
                (int) $contactId,
                $assignedUserId,
                $request->input('session_slug')
            );
            $status = !empty($result['success']) ? 200 : 422;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al abrir conversación: ' . $e->getMessage(),
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

    public function storeConversation(Request $request)
    {
        try {
            $result = $this->conversationService->createManualContact([
                'phone' => $request->input('phone'),
                'contact_name' => $request->input('contact_name'),
                'assigned_user_id' => (int) $request->input('assigned_user_id', 0),
            ]);

            $status = !empty($result['success']) ? 200 : 422;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar contacto: ' . $e->getMessage(),
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
            $replyMetaId = trim((string) $request->input('reply_to_meta_message_id', ''));
            $file = $request->file('file');

            $conversation = WaCopilotoConversation::query()->findOrFail((int) $id);
            $window = $this->windowService->computeWindowState($conversation);
            if (empty($window['can_send_text'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ventana cerrada. Envía una plantilla para reactivar.',
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user ? (int) $user->getIdUsuario() : null;

            $templateParams = null;
            if ($replyMetaId !== '') {
                $templateParams = ['_context' => ['message_id' => $replyMetaId]];
            }

            if ($file && $file->isValid()) {
                /** @var WaCopilotoMediaUploadService $uploader */
                $uploader = app(WaCopilotoMediaUploadService::class);
                $kind = strtolower(trim((string) $request->input('media_kind', '')));
                if ($kind === '') {
                    $kind = $uploader->guessKindFromFile($file);
                }
                $uploadError = '';
                $uploaded = $uploader->uploadFromFile($file, $kind, $uploadError, (int) $conversation->id);
                if ($uploaded === null) {
                    return response()->json([
                        'success' => false,
                        'message' => $uploadError !== '' ? $uploadError : 'No se pudo subir el archivo',
                    ], 422);
                }

                $messageType = (string) $uploaded['type'];
                $body = $text !== '' ? $text : (string) $uploaded['filename'];
                $params = is_array($templateParams) ? $templateParams : [];
                $params['_media_filename'] = (string) $uploaded['filename'];

                $message = $this->messageService->createOutboundPending(
                    $conversation,
                    $body,
                    $userId,
                    $messageType,
                    null,
                    $params,
                    (string) $uploaded['path'],
                    (string) $uploaded['mime']
                );
            } elseif ($text !== '') {
                $message = $this->messageService->createOutboundPending(
                    $conversation,
                    $text,
                    $userId,
                    'text',
                    null,
                    $templateParams
                );
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Escribe un mensaje o adjunta un archivo',
                ], 422);
            }

            SendWaCopilotoOutboundJob::dispatch($message->id, WaCopilotoJobContext::resolveJobDomain());

            WaCopilotoLog::info('sendMessage.queued', [
                'conversation_id' => (int) $conversation->id,
                'message_id' => (int) $message->id,
                'phone_e164' => $conversation->phone_e164,
                'message_type' => $message->message_type,
                'queue' => (string) config('meta_whatsapp_copiloto.queue', 'notificaciones'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje en cola de envío',
                'data' => $this->messageService->formatMessage($message),
            ]);
        } catch (\Exception $e) {
            WaCopilotoLog::error('sendMessage.exception', [
                'conversation_id' => (int) $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function scheduleMessage(Request $request, $id)
    {
        try {
            $text = trim((string) $request->input('message', ''));
            $scheduledRaw = trim((string) $request->input('scheduled_at', ''));
            if ($scheduledRaw === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Indica fecha y hora de envío.',
                ], 422);
            }

            $conversation = WaCopilotoConversation::query()->findOrFail((int) $id);
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user ? (int) $user->getIdUsuario() : 0;

            /** @var WaCopilotoScheduledMessageService $scheduledService */
            $scheduledService = app(WaCopilotoScheduledMessageService::class);
            $result = $scheduledService->schedule(
                $conversation,
                $userId,
                $text,
                Carbon::parse($scheduledRaw)
            );

            if (empty($result['success'])) {
                return response()->json($result, 422);
            }

            WaCopilotoLog::info('scheduleMessage.created', [
                'conversation_id' => (int) $conversation->id,
                'scheduled_at' => $scheduledRaw,
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            WaCopilotoLog::error('scheduleMessage.exception', [
                'conversation_id' => (int) $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al programar: ' . $e->getMessage(),
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

            $conversation = WaCopilotoConversation::query()->with('session')->findOrFail((int) $id);
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user ? (int) $user->getIdUsuario() : null;

            $sessionSlug = $conversation->session ? (string) $conversation->session->slug : null;

            try {
                $this->templateService->assertTemplateAllowed($templateName, $sessionSlug);
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $headerFormat = $this->templateService->getTemplateHeaderFormat($templateName, $sessionSlug);
            $requiresHeaderMedia = $this->templateService->templateRequiresHeaderMedia($templateName, $sessionSlug)
                || count($request->allFiles()) > 0;
            $fileKeys = array_keys($request->allFiles());

            WaCopilotoLog::info('sendTemplate.start', [
                'conversation_id' => (int) $conversation->id,
                'phone_e164' => $conversation->phone_e164,
                'template' => $templateName,
                'header_format' => $headerFormat,
                'requires_header_media' => $requiresHeaderMedia,
                'uploaded_file_keys' => $fileKeys,
                'header_file_kind' => $request->input('header_file_kind'),
                'param_keys' => array_keys($params),
                'user_id' => $userId,
            ]);

            $header = null;
            if ($requiresHeaderMedia) {
                if ($headerFormat !== null && count($request->allFiles()) === 0) {
                    WaCopilotoLog::warning('sendTemplate.missing_header_file', [
                        'conversation_id' => (int) $conversation->id,
                        'template' => $templateName,
                        'header_format' => $headerFormat,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Esta plantilla requiere un archivo en el encabezado (PDF para documentos).',
                    ], 422);
                }

                $headerError = '';
                $header = $this->resolveTemplateHeaderFromRequest($request, $templateName, $headerError);
                if ($header === null) {
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
                    $sizeMsg = ($file && $file->isValid())
                        ? $this->validateTemplateHeaderFileSize($file, $headerFormat, $templateName)
                        : null;

                    $message = $headerError !== ''
                        ? $headerError
                        : ($sizeMsg !== null
                        ? $sizeMsg
                        : ($headerFormat === 'DOCUMENT'
                            ? 'No se pudo subir el PDF a S3. Esta plantilla exige un PDF en el encabezado.'
                            : 'No se pudo subir el archivo a S3. Revisa la configuración de almacenamiento.'));

                    WaCopilotoLog::error('sendTemplate.header_upload_failed', [
                        'conversation_id' => (int) $conversation->id,
                        'template' => $templateName,
                        'header_format' => $headerFormat,
                        'uploaded_file_keys' => $fileKeys,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }
                $params['_header'] = $header;

                WaCopilotoLog::info('sendTemplate.header_ready', [
                    'conversation_id' => (int) $conversation->id,
                    'template' => $templateName,
                    'header_type' => isset($header['type']) ? $header['type'] : null,
                    'header_has_link' => !empty($header['link']),
                    'header_filename' => isset($header['filename']) ? $header['filename'] : null,
                ]);
            }

            $body = $this->buildTemplatePreviewBody($templateName, $params);

            if ($requiresHeaderMedia && $header !== null) {
                WaCopilotoLog::info('sendTemplate.path_sync', [
                    'conversation_id' => (int) $conversation->id,
                    'template' => $templateName,
                ]);

                $sync = $this->messageService->sendTemplateWithHeaderSync(
                    $conversation,
                    $body,
                    $userId,
                    $templateName,
                    $params
                );

                if (empty($sync['success'])) {
                    WaCopilotoLog::error('sendTemplate.sync_failed', [
                        'conversation_id' => (int) $conversation->id,
                        'template' => $templateName,
                        'error' => isset($sync['error']) ? (string) $sync['error'] : null,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => isset($sync['error']) ? (string) $sync['error'] : 'Error al enviar plantilla',
                    ], 422);
                }

                WaCopilotoLog::info('sendTemplate.sync_ok', [
                    'conversation_id' => (int) $conversation->id,
                    'message_id' => isset($sync['message']) ? (int) $sync['message']->id : null,
                    'meta_message_id' => isset($sync['message']) ? $sync['message']->meta_message_id : null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Plantilla enviada',
                    'data' => $this->messageService->formatMessage($sync['message']),
                ]);
            }

            WaCopilotoLog::info('sendTemplate.path_queue', [
                'conversation_id' => (int) $conversation->id,
                'template' => $templateName,
                'requires_header_media' => $requiresHeaderMedia,
                'has_header_in_params' => isset($params['_header']),
            ]);

            $message = $this->messageService->createOutboundPending(
                $conversation,
                $body,
                $userId,
                'template',
                $templateName,
                $params
            );

            SendWaCopilotoOutboundJob::dispatch($message->id, WaCopilotoJobContext::resolveJobDomain());

            WaCopilotoLog::info('sendTemplate.queued', [
                'conversation_id' => (int) $conversation->id,
                'message_id' => (int) $message->id,
                'template' => $templateName,
                'queue' => (string) config('meta_whatsapp_copiloto.queue', 'notificaciones'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plantilla en cola de envío',
                'data' => $this->messageService->formatMessage($message),
            ]);
        } catch (\Exception $e) {
            WaCopilotoLog::error('sendTemplate.exception', [
                'conversation_id' => (int) $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar plantilla: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assign(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = (int) $request->input('user_id', 0);
            $changedBy = $user ? (int) $user->getIdUsuario() : 0;

            return response()->json($this->conversationService->assign((int) $id, $userId, $changedBy));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineStages()
    {
        try {
            return response()->json($this->pipelineService->listStages());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar etapas: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineCreateStage(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user ? (int) $user->getIdUsuario() : 0;
            $result = $this->pipelineService->createProgressStage(
                $request->input('label', ''),
                $userId
            );
            $status = !empty($result['success']) ? 200 : 422;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear etapa: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineReorderStages(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user ? (int) $user->getIdUsuario() : 0;
            $ordered = $request->input('stage_ids', []);
            if (!is_array($ordered)) {
                $ordered = [];
            }
            $result = $this->pipelineService->reorderProgressStages($ordered, $userId);
            $status = !empty($result['success']) ? 200 : 422;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reordenar etapas: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineKanban(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $params = $request->all();
            $params['auth_user_id'] = $user ? (int) $user->getIdUsuario() : 0;

            return response()->json($this->pipelineService->getKanban($params));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar kanban: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineKpis(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $params = $request->all();
            $params['auth_user_id'] = $user ? (int) $user->getIdUsuario() : 0;

            return response()->json($this->pipelineService->getKpis($params));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar KPIs: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineTransition(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $stageId = (int) $request->input('stage_id', 0);
            $note = $request->input('note');
            $userId = $user ? (int) $user->getIdUsuario() : 0;

            $result = $this->pipelineService->transition((int) $id, $stageId, $userId, $note);
            $status = !empty($result['success']) ? 200 : 422;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar etapa: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineAssignmentHistory($id)
    {
        try {
            return response()->json($this->pipelineService->assignmentHistory((int) $id));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar asignaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function pipelineTransitionHistory($id)
    {
        try {
            return response()->json($this->pipelineService->transitionHistory((int) $id));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar historial de etapas: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function renameContact(Request $request, $id)
    {
        try {
            $result = $this->conversationService->renameContact(
                (int) $id,
                $request->input('contact_name', '')
            );
            $status = !empty($result['success']) ? 200 : 422;

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al renombrar: ' . $e->getMessage(),
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

    public function templates(Request $request)
    {
        try {
            $sessionSlug = $request->query('session_slug') ?: $request->query('slug');

            return response()->json($this->templateService->listTemplates($sessionSlug));
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
        return $this->templateService->buildPreviewBody($templateName, $params);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>|null
     */
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $templateName
     * @return array<string, mixed>|null
     */
    private function resolveTemplateHeaderFromRequest(Request $request, $templateName = '', &$errorMessage = '')
    {
        $errorMessage = '';
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
            WaCopilotoLog::warning('resolveHeader.no_valid_file', [
                'template' => $templateName,
                'all_file_keys' => array_keys($request->allFiles()),
            ]);

            return null;
        }

        $headerFormat = $this->templateService->getTemplateHeaderFormat($templateName);
        $sizeError = $this->validateTemplateHeaderFileSize($file, $headerFormat, $templateName);
        if ($sizeError !== null) {
            WaCopilotoLog::warning('resolveHeader.file_too_large', [
                'template' => $templateName,
                'header_format' => $headerFormat,
                'size_bytes' => $file->getSize(),
                'message' => $sizeError,
            ]);

            return null;
        }

        if ($headerFormat === 'DOCUMENT') {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if ($ext !== 'pdf') {
                Log::warning('WaCopiloto: plantilla DOCUMENT requiere PDF', [
                    'template' => $templateName,
                    'extension' => $ext,
                ]);

                return null;
            }
        }

        $kind = strtolower((string) $request->input('header_file_kind', ''));
        if ($headerFormat === 'DOCUMENT') {
            $kind = 'document';
        } elseif ($headerFormat === 'IMAGE') {
            $kind = 'image';
        } elseif ($headerFormat === 'VIDEO') {
            $kind = 'video';
        } elseif (!in_array($kind, ['document', 'image', 'video'], true)) {
            $kind = $this->guessHeaderKindFromFile($file);
        }

        $storageKey = CoordinacionMediaLink::META_TEMP_PREFIX . '/inbox/'
            . Str::uuid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        $localRelative = $file->store('temp/wa-inbox-uploads', 'local');
        if ($localRelative === false) {
            Log::error('WaCopiloto: no se pudo guardar archivo temporal', [
                'name' => $file->getClientOriginalName(),
            ]);

            return null;
        }

        $fullPath = storage_path('app/' . $localRelative);
        $uploadPath = $fullPath;
        $uploadFilename = $file->getClientOriginalName();
        $transcodedPath = null;

        if ($headerFormat === 'VIDEO') {
            @set_time_limit(max(180, (int) config('meta_whatsapp_copiloto.video_transcode_timeout', 120) + 30));
            $transcoder = new WaInboxVideoTranscoder();
            $transcode = $transcoder->transcodeForWhatsAppTemplate($fullPath);
            if (!empty($transcode['success']) && !empty($transcode['path'])) {
                $transcodedPath = (string) $transcode['path'];
                $uploadPath = $transcodedPath;
                $uploadFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.mp4';
            } else {
                $errorMessage = isset($transcode['error']) ? (string) $transcode['error'] : 'No se pudo convertir el video';

                try {
                    Storage::disk('local')->delete($localRelative);
                } catch (\Exception $e) {
                    // ignorar
                }

                return null;
            }
        }

        $url = CoordinacionMediaLink::uploadLocalFile($uploadPath, $storageKey);

        try {
            Storage::disk('local')->delete($localRelative);
            if ($transcodedPath !== null && is_file($transcodedPath)) {
                @unlink($transcodedPath);
            }
        } catch (\Exception $e) {
            // ignorar limpieza local
        }

        if ($url === null || $url === '') {
            WaCopilotoLog::error('resolveHeader.s3_upload_failed', [
                'template' => $templateName,
                'storage_key' => $storageKey,
                'name' => $file->getClientOriginalName(),
                'kind' => $kind,
            ]);

            return null;
        }

        WaCopilotoLog::info('resolveHeader.ok', [
            'template' => $templateName,
            'kind' => $kind,
            'header_format' => $headerFormat,
            'storage_key' => $storageKey,
            'size_bytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);

        return [
            'type' => $kind,
            'link' => $url,
            'path' => $storageKey,
            'filename' => $uploadFilename,
        ];
    }

    /**
     * @param  UploadedFile  $file
     * @param  string|null  $headerFormat  DOCUMENT|IMAGE|VIDEO
     * @param  string  $templateName
     * @return string|null  Mensaje de error o null si OK
     */
    private function validateTemplateHeaderFileSize(UploadedFile $file, $headerFormat, $templateName = '')
    {
        $limits = config('meta_whatsapp_copiloto.header_max_bytes', []);
        $format = strtoupper((string) $headerFormat);
        $key = 'document';
        if ($format === 'IMAGE') {
            $key = 'image';
        } elseif ($format === 'VIDEO') {
            $key = 'video';
        }

        if ($key === 'video') {
            $max = (int) config('meta_whatsapp_copiloto.header_max_video_input_bytes', 80 * 1024 * 1024);
        } else {
            $max = isset($limits[$key]) ? (int) $limits[$key] : 0;
        }
        if ($max <= 0) {
            return null;
        }

        $size = (int) $file->getSize();
        if ($size <= $max) {
            return null;
        }

        $maxMb = round($max / 1024 / 1024, 1);
        $fileMb = round($size / 1024 / 1024, 1);

        if ($key === 'image') {
            return 'La imagen pesa ' . $fileMb . ' MB. WhatsApp permite máximo ' . $maxMb . ' MB en plantillas con encabezado de imagen.';
        }
        if ($key === 'video') {
            return 'El video pesa ' . $fileMb . ' MB. Máximo ' . $maxMb . ' MB al subir; el servidor lo convertirá para WhatsApp.';
        }

        return 'El archivo pesa ' . $fileMb . ' MB. WhatsApp permite máximo ' . $maxMb . ' MB para documentos en plantilla.';
    }

    /**
     * @param  UploadedFile  $file
     * @return string
     */
    private function guessHeaderKindFromFile(UploadedFile $file)
    {
        $mime = strtolower((string) $file->getMimeType());
        if (strpos($mime, 'image/') === 0) {
            return 'image';
        }
        if (strpos($mime, 'video/') === 0) {
            return 'video';
        }

        return 'document';
    }

    public function suggestionUsages(Request $request, $id)
    {
        try {
            $service = app(WaCopilotoSuggestionUsageService::class);

            return response()->json(
                $service->listForConversation((int) $id, (int) $request->query('limit', 30))
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al listar historial: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function recordSuggestionUsage(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user ? (int) $user->getIdUsuario() : null;
            $service = app(WaCopilotoSuggestionUsageService::class);
            $result = $service->record((int) $id, $request->all(), $userId);

            if (empty($result['success'])) {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar sugerencia: ' . $e->getMessage(),
            ], 500);
        }
    }
}
