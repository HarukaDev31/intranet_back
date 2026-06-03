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
use App\Support\WhatsApp\WaInboxLog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

            WaInboxLog::info('sendMessage.queued', [
                'conversation_id' => (int) $conversation->id,
                'message_id' => (int) $message->id,
                'phone_e164' => $conversation->phone_e164,
                'queue' => (string) config('meta_whatsapp.inbox_queue', 'notificaciones'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mensaje en cola de envío',
                'data' => $this->messageService->formatMessage($message),
            ]);
        } catch (\Exception $e) {
            WaInboxLog::error('sendMessage.exception', [
                'conversation_id' => (int) $id,
                'error' => $e->getMessage(),
            ]);

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

            $conversation = WaInboxConversation::query()->findOrFail((int) $id);
            $user = JWTAuth::parseToken()->authenticate();
            $userId = $user ? (int) $user->getIdUsuario() : null;

            $headerFormat = $this->templateService->getTemplateHeaderFormat($templateName);
            $requiresHeaderMedia = $this->templateService->templateRequiresHeaderMedia($templateName)
                || count($request->allFiles()) > 0;
            $fileKeys = array_keys($request->allFiles());

            WaInboxLog::info('sendTemplate.start', [
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
                    WaInboxLog::warning('sendTemplate.missing_header_file', [
                        'conversation_id' => (int) $conversation->id,
                        'template' => $templateName,
                        'header_format' => $headerFormat,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Esta plantilla requiere un archivo en el encabezado (PDF para documentos).',
                    ], 422);
                }

                $header = $this->resolveTemplateHeaderFromRequest($request, $templateName);
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

                    $message = $sizeMsg !== null
                        ? $sizeMsg
                        : ($headerFormat === 'DOCUMENT'
                            ? 'No se pudo subir el PDF a S3. Esta plantilla exige un PDF en el encabezado.'
                            : 'No se pudo subir el archivo a S3. Revisa la configuración de almacenamiento.');

                    WaInboxLog::error('sendTemplate.header_upload_failed', [
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

                WaInboxLog::info('sendTemplate.header_ready', [
                    'conversation_id' => (int) $conversation->id,
                    'template' => $templateName,
                    'header_type' => isset($header['type']) ? $header['type'] : null,
                    'header_has_link' => !empty($header['link']),
                    'header_filename' => isset($header['filename']) ? $header['filename'] : null,
                ]);
            }

            $body = $this->buildTemplatePreviewBody($templateName, $params);

            if ($requiresHeaderMedia && $header !== null) {
                WaInboxLog::info('sendTemplate.path_sync', [
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
                    WaInboxLog::error('sendTemplate.sync_failed', [
                        'conversation_id' => (int) $conversation->id,
                        'template' => $templateName,
                        'error' => isset($sync['error']) ? (string) $sync['error'] : null,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => isset($sync['error']) ? (string) $sync['error'] : 'Error al enviar plantilla',
                    ], 422);
                }

                WaInboxLog::info('sendTemplate.sync_ok', [
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

            WaInboxLog::info('sendTemplate.path_queue', [
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

            SendWaInboxOutboundJob::dispatch($message->id);

            WaInboxLog::info('sendTemplate.queued', [
                'conversation_id' => (int) $conversation->id,
                'message_id' => (int) $message->id,
                'template' => $templateName,
                'queue' => (string) config('meta_whatsapp.inbox_queue', 'notificaciones'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plantilla en cola de envío',
                'data' => $this->messageService->formatMessage($message),
            ]);
        } catch (\Exception $e) {
            WaInboxLog::error('sendTemplate.exception', [
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
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $templateName
     * @return array<string, mixed>|null
     */
    private function resolveTemplateHeaderFromRequest(Request $request, $templateName = '')
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
            WaInboxLog::warning('resolveHeader.no_valid_file', [
                'template' => $templateName,
                'all_file_keys' => array_keys($request->allFiles()),
            ]);

            return null;
        }

        $headerFormat = $this->templateService->getTemplateHeaderFormat($templateName);
        $sizeError = $this->validateTemplateHeaderFileSize($file, $headerFormat, $templateName);
        if ($sizeError !== null) {
            WaInboxLog::warning('resolveHeader.file_too_large', [
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
                Log::warning('WhatsappInbox: plantilla DOCUMENT requiere PDF', [
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
            Log::error('WhatsappInbox: no se pudo guardar archivo temporal', [
                'name' => $file->getClientOriginalName(),
            ]);

            return null;
        }

        $fullPath = storage_path('app/' . $localRelative);
        $url = CoordinacionMediaLink::uploadLocalFile($fullPath, $storageKey);

        try {
            Storage::disk('local')->delete($localRelative);
        } catch (\Exception $e) {
            // ignorar limpieza local
        }

        if ($url === null || $url === '') {
            WaInboxLog::error('resolveHeader.s3_upload_failed', [
                'template' => $templateName,
                'storage_key' => $storageKey,
                'name' => $file->getClientOriginalName(),
                'kind' => $kind,
            ]);

            return null;
        }

        WaInboxLog::info('resolveHeader.ok', [
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
            'filename' => $file->getClientOriginalName(),
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
        $limits = config('meta_whatsapp.inbox_header_max_bytes', []);
        $format = strtoupper((string) $headerFormat);
        $key = 'document';
        if ($format === 'IMAGE') {
            $key = 'image';
        } elseif ($format === 'VIDEO') {
            $key = 'video';
        }

        $max = isset($limits[$key]) ? (int) $limits[$key] : 0;
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
            return 'El video pesa ' . $fileMb . ' MB. WhatsApp permite máximo ' . $maxMb . ' MB.';
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
}
