<?php

namespace App\Support\WhatsApp;

use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Support\Facades\Log;

/**
 * Payloads listos para queueCoordinacionWhatsApp / SendCoordinacionWhatsAppJob.
 * Nombres alineados con docs/META_WHATSAPP_TEMPLATES_CUERPO.md (WABA consolidado).
 */
class CoordinacionWhatsappPayload
{
    /** Texto fijo D07 — debe coincidir con la plantilla Meta. */
    private const DOCS_RECORDATORIO_AVISO = 'Si no tenemos tus documentos a tiempo, aduana puede aplicarte multas o inmovilización de tus productos.';

    /** Plantilla Meta D02 — Excel de confirmación (QA: link_excel + link_intranet). */
    private const DOCS_EXCEL_LINK_TEMPLATE = 'pb_docs_excel_link_v1_qa';

    /**
     * @return array<string, string>
     */
    public static function documentosRecordatorioMap(): array
    {
        return [
            'commercial_invoice' => 'Commercial Invoice 📄',
            'packing_list' => 'Packing List 📦.',
            'excel_confirmacion' => 'Excel de confirmacion 📄',
        ];
    }

    /**
     * Base del formulario web Excel de confirmación (APP_URL_EXCEL_CONFIRMACION o APP_URL_CLIENTES).
     */
    public static function excelConfirmacionBaseUrl(): string
    {
        $base = rtrim(trim((string) config('app.url_excel_confirmacion', '')), '/');
        if ($base !== '') {
            return $base;
        }

        return rtrim(trim((string) config('app.url_clientes', '')), '/');
    }

    /**
     * URL del formulario web de Excel de confirmación (corrige http:/ → http://).
     */
    public static function buildExcelConfirmacionUrl(string $uuid, ?string $codeSupplier = null): string
    {
        $base = self::excelConfirmacionBaseUrl();
        if ($base === '') {
            return self::normalizeExternalUrl(ltrim($uuid, '/'));
        }

        $url = $base . '/' . ltrim($uuid, '/');
        $code = trim((string) ($codeSupplier ?? ''));
        if ($code !== '') {
            $url .= '?proveedor=' . rawurlencode($code);
        }

        return self::normalizeExternalUrl($url);
    }

    /**
     * URL del formulario datos proveedor (corrige http:/ → http://).
     */
    public static function buildDatosProveedorUrl(string $uuid): string
    {
        $base = rtrim(trim((string) env('APP_URL_DATOS_PROVEEDOR', '')), '/');
        if ($base === '') {
            return self::normalizeExternalUrl(ltrim($uuid, '/'));
        }

        return self::normalizeExternalUrl($base . '/' . ltrim($uuid, '/'));
    }

    public static function normalizeExternalUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        $url = preg_replace('#^(https?):/([^/])#i', '$1://$2', $url) ?? $url;
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return $url;
    }

    /**
     * Lista compacta para parámetros Meta (sin saltos de línea).
     *
     * @param  iterable<object|array<string, mixed>>  $providers
     */
    public static function formatListaProveedoresForMeta(iterable $providers): string
    {
        $items = [];

        foreach ($providers as $provider) {
            $code = trim((string) self::providerField($provider, 'code_supplier'));
            if ($code === '') {
                continue;
            }

            $supplier = trim((string) self::providerField($provider, 'supplier'));
            $phone = trim((string) self::providerField($provider, 'supplier_phone'));

            $details = [];
            if ($supplier !== '') {
                $details[] = 'Vendedor: ' . $supplier;
            }
            if ($phone !== '') {
                $details[] = 'WeChat: ' . $phone;
            }

            $items[] = $details === [] ? $code : $code . ' (' . implode(', ', $details) . ')';
        }

        if ($items === []) {
            return 'Sin proveedores pendientes por completar.';
        }

        if (count($items) === 1) {
            return 'Proveedor pendiente: ' . $items[0];
        }

        return 'Proveedores pendientes: ' . implode(' · ', $items);
    }

    /**
     * Códigos de proveedor pendientes (formulario datos) — una línea para Meta.
     *
     * @param  iterable<object|array<string, mixed>>  $pendientes
     */
    public static function formatCodigosProveedoresPendientesForMeta(iterable $pendientes): string
    {
        $codes = [];

        foreach ($pendientes as $item) {
            if (is_array($item)) {
                $code = trim((string) ($item['code_supplier'] ?? ''));
            } else {
                $code = trim((string) self::providerField($item, 'code_supplier'));
            }
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        if ($codes === []) {
            return '—';
        }

        return implode(' · ', $codes);
    }

    /**
     * Documentos faltantes en una línea para parámetros Meta (sin saltos de línea).
     *
     * @param  array<int, string>  $documentos
     * @param  array<string, string>  $documentosMap
     */
    public static function formatDocumentosFaltantesForMeta(array $documentos, array $documentosMap = []): string
    {
        $labels = [];

        foreach ($documentos as $doc) {
            $key = trim((string) $doc);
            if ($key === '') {
                continue;
            }

            $labels[] = $documentosMap[$key] ?? ucwords(str_replace('_', ' ', $key));
        }

        if ($labels === []) {
            return '—';
        }

        return implode(' · ', $labels);
    }

    /**
     * @param  object|array<string, mixed>  $provider
     */
    private static function providerField($provider, string $field): string
    {
        if (is_array($provider)) {
            return (string) ($provider[$field] ?? '');
        }

        return (string) ($provider->{$field} ?? '');
    }

    /**
     * @param  array<string, string>  $bodyParameters  nombre Meta => valor
     * @return array<string, mixed>
     */
    /**
     * @param  string|null  $chatPreview  Opcional; si se omite, el inbox arma el texto desde la plantilla Meta (Graph).
     */
    public static function template(
        string $phone,
        string $templateName,
        array $bodyParameters,
        ?string $chatPreview = null,
        int $sleep = 0,
        ?array $header = null
    ): array {
        $payload = [
            'type' => 'template',
            'phone' => $phone,
            'template' => $templateName,
            'language' => 'es_PE',
            'body_parameters' => $bodyParameters,
            'sleep' => $sleep,
        ];
        if ($chatPreview !== null && trim($chatPreview) !== '') {
            $payload['chat_preview'] = $chatPreview;
        }
        if ($header !== null) {
            $payload['header'] = $header;
        }

        return $payload;
    }

    public static function entregaLinkLima(
        string $phone,
        string $carga,
        string $nombreCliente,
        string $linkFormulario,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_entrega_link_lima_v1', [
            'carga' => $carga,
            'nombre_cliente' => $nombreCliente,
            'link_formulario' => $linkFormulario,
        ], $bitrixMessage, $sleep);
    }

    public static function entregaReglasLima(string $phone, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_entrega_reglas_lima_v1', [], $bitrixMessage, $sleep);
    }

    public static function entregaLinkProvincia(
        string $phone,
        string $carga,
        string $nombreCliente,
        string $linkFormulario,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_entrega_link_provincia_v1', [
            'carga' => $carga,
            'nombre_cliente' => $nombreCliente,
            'link_formulario' => $linkFormulario,
        ], $bitrixMessage, $sleep);
    }

    public static function entregaReglasProvinciaFleteFinal(string $phone, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_entrega_reglas_provincia_flete_final_v1', [], $bitrixMessage, $sleep);
    }

    public static function entregaReglasProvinciaFleteCotiza(string $phone, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_entrega_reglas_provincia_flete_cotiza_v1', [], $bitrixMessage, $sleep);
    }

    public static function welcomeRotulado(string $phone, string $carga, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_welcome_rotulado_v1', [
            'carga' => $carga,
        ], $bitrixMessage, $sleep);
    }

    public static function rotuladoNuevoProveedor(string $phone, string $carga, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_rotulado_nuevo_proveedor_v1', [
            'carga' => $carga,
        ], $bitrixMessage, $sleep);
    }

    public static function confirmLima(
        string $phone,
        array $params,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_entrega_confirm_lima_v1', $params, $bitrixMessage, $sleep);
    }

    public static function confirmProvincia(
        string $phone,
        array $params,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_entrega_confirm_provincia_v1', $params, $bitrixMessage, $sleep);
    }

    /**
     * @param  array<string, string>  $bodyParameters
     */
    public static function documentTemplate(
        string $phone,
        string $templateName,
        array $bodyParameters,
        string $filePath,
        ?string $chatPreview = null,
        ?string $filename = null,
        ?string $mimeType = null,
        int $sleep = 0
    ): array {
        return self::template(
            $phone,
            $templateName,
            $bodyParameters,
            $chatPreview,
            $sleep,
            [
                'type' => 'document',
                'path' => $filePath,
                'filename' => $filename ?? basename($filePath),
                'mimeType' => $mimeType,
            ]
        );
    }

    /**
     * @param  array<string, string>  $bodyParameters
     */
    public static function imageTemplate(
        string $phone,
        string $templateName,
        array $bodyParameters,
        string $filePath,
        ?string $chatPreview = null,
        int $sleep = 0
    ): array {
        return self::template(
            $phone,
            $templateName,
            $bodyParameters,
            $chatPreview,
            $sleep,
            [
                'type' => 'image',
                'path' => $filePath,
            ]
        );
    }

    public static function docsPaso1(string $phone, string $carga, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_docs_paso1_excel_video_v1', [
            'carga' => $carga,
        ], $bitrixMessage, $sleep);
    }

    /**
     * Un solo mensaje D02 por cotización: Drive (Excel general) + intranet (sin ?proveedor=).
     *
     * @param  int|null  $idProveedorRef  Proveedor de referencia para refrescar links desde BD
     */
    public static function docsExcelLinkForCotizacion(
        string $phone,
        string $carga,
        string $codigoProveedorLabel,
        string $linkExcel,
        string $linkIntranet,
        int $sleep = 0,
        $idProveedorRef = null
    ): array {
        $bodyParameters = [
            'carga' => $carga,
            'codigo_proveedor' => $codigoProveedorLabel !== '' ? $codigoProveedorLabel : 'General',
            'link_excel' => self::normalizeExternalUrl($linkExcel),
            'link_intranet' => self::normalizeExternalUrl($linkIntranet),
        ];

        if (($bodyParameters['link_excel'] ?? '') === '' && ($bodyParameters['link_intranet'] ?? '') === '') {
            Log::error('CoordinacionWhatsappPayload: sin enlace para Excel de confirmación (cotización)', [
                'codigo_proveedor' => $codigoProveedorLabel,
                'template' => self::DOCS_EXCEL_LINK_TEMPLATE,
            ]);
        }

        $payload = self::template($phone, self::DOCS_EXCEL_LINK_TEMPLATE, $bodyParameters, null, $sleep);
        if ($idProveedorRef !== null && (int) $idProveedorRef > 0) {
            $payload['id_proveedor'] = (int) $idProveedorRef;
        }

        return $payload;
    }

    public static function docsExcelLinkForProveedor(
        string $phone,
        string $carga,
        int $idProveedor,
        string $codigoProveedor,
        int $sleep = 0
    ): array {
        $bodyParameters = self::docsExcelLinkBodyParameters($idProveedor, $carga, $codigoProveedor);
        if (($bodyParameters['link_excel'] ?? '') === '' && ($bodyParameters['link_intranet'] ?? '') === '') {
            Log::error('CoordinacionWhatsappPayload: sin enlace para Excel de confirmación', [
                'id_proveedor' => $idProveedor,
                'codigo_proveedor' => $codigoProveedor,
                'template' => self::DOCS_EXCEL_LINK_TEMPLATE,
            ]);
        }

        $payload = self::template($phone, self::DOCS_EXCEL_LINK_TEMPLATE, $bodyParameters, null, $sleep);
        $payload['id_proveedor'] = $idProveedor;

        return $payload;
    }

    /**
     * @return array{carga: string, codigo_proveedor: string, link_excel: string, link_intranet?: string}
     */
    public static function docsExcelLinkBodyParameters(int $idProveedor, string $carga, string $codigoProveedor): array
    {
        $linkExcel = self::resolveExcelConfirmacionDriveLink($idProveedor) ?? '';
        $linkIntranet = self::resolveExcelConfirmacionIntranetLink($idProveedor) ?? '';

        return [
            'carga' => $carga,
            'codigo_proveedor' => $codigoProveedor !== '' ? $codigoProveedor : 'General',
            'link_excel' => $linkExcel,
            'link_intranet' => $linkIntranet,
        ];
    }

    private static function rebuildExcelConfirmacionLinkForProveedor(int $idProveedor): ?string
    {
        $proveedor = CotizacionProveedor::query()
            ->with(['cotizacion:id,uuid'])
            ->find($idProveedor);

        if ($proveedor === null) {
            return null;
        }

        $uuid = trim((string) ($proveedor->cotizacion->uuid ?? ''));
        if ($uuid === '') {
            return null;
        }

        // Link de intranet a nivel cotización (todos los proveedores en el mismo formulario).
        return self::buildExcelConfirmacionUrl($uuid);
    }

    public static function resolveExcelConfirmacionIntranetLink(int $idProveedor): ?string
    {
        if ($idProveedor <= 0) {
            return null;
        }

        $rebuilt = self::rebuildExcelConfirmacionLinkForProveedor($idProveedor);

        return $rebuilt !== null && $rebuilt !== ''
            ? self::normalizeExternalUrl($rebuilt)
            : null;
    }

    /**
     * @deprecated Usar docsExcelLinkForProveedor — el link debe venir de excel_confirmacion_drive_link (Drive).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function refreshDocsExcelLinkPayload(array &$payload): bool
    {
        if (($payload['template'] ?? '') !== self::DOCS_EXCEL_LINK_TEMPLATE) {
            return true;
        }

        $idProveedor = (int) ($payload['id_proveedor'] ?? 0);
        if ($idProveedor <= 0) {
            $currentLink = (string) (($payload['body_parameters']['link_excel'] ?? '') ?: '');
            if ($currentLink !== '' && !self::isLegacyTempExcelConfirmacionUrl($currentLink)) {
                return true;
            }

            Log::warning('CoordinacionWhatsappPayload: D02 sin id_proveedor y link legacy S3', [
                'link' => $currentLink,
            ]);

            return false;
        }

        $carga = (string) (($payload['body_parameters']['carga'] ?? '') ?: '');
        $codigo = (string) (($payload['body_parameters']['codigo_proveedor'] ?? '') ?: '');

        $payload['body_parameters'] = self::docsExcelLinkBodyParameters($idProveedor, $carga, $codigo);
        unset($payload['chat_preview'], $payload['bitrix_message']);

        return ($payload['body_parameters']['link_excel'] ?? '') !== ''
            || ($payload['body_parameters']['link_intranet'] ?? '') !== '';
    }

    public static function resolveExcelConfirmacionDriveLink(int $idProveedor): ?string
    {
        if ($idProveedor <= 0) {
            return null;
        }

        $raw = CotizacionProveedor::where('id', $idProveedor)->value('excel_confirmacion_drive_link');
        $link = trim((string) ($raw ?? ''));
        if ($link === '') {
            return null;
        }

        if (self::isLegacyTempExcelConfirmacionUrl($link)) {
            Log::warning('CoordinacionWhatsappPayload: excel_confirmacion_drive_link apunta a S3 legacy', [
                'id_proveedor' => $idProveedor,
                'link' => $link,
            ]);

            return null;
        }

        // Solo Drive: URLs de formulario web ya no se usan como link_excel.
        if (self::isGoogleDriveUrl($link)) {
            return self::normalizeExternalUrl($link);
        }

        if (self::isExcelConfirmacionFormUrl($link)) {
            Log::warning('CoordinacionWhatsappPayload: excel_confirmacion_drive_link es URL de formulario (legacy)', [
                'id_proveedor' => $idProveedor,
            ]);

            return null;
        }

        Log::warning('CoordinacionWhatsappPayload: excel_confirmacion_drive_link no es URL de Drive válida', [
            'id_proveedor' => $idProveedor,
            'link' => $link,
        ]);

        return null;
    }

    public static function isExcelConfirmacionFormUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $base = self::excelConfirmacionBaseUrl();
        if ($base !== '' && str_starts_with($url, $base)) {
            return true;
        }

        return (bool) preg_match('~^https?://[^/]+/[^/?#]+(\?proveedor=[^&#]+)?$~i', $url);
    }

    public static function isLegacyTempExcelConfirmacionUrl(string $url): bool
    {
        $url = strtolower(trim($url));

        return $url !== '' && (
            strpos($url, 'temp/excel-confirmacion') !== false
            || (strpos($url, 'amazonaws.com') !== false && strpos($url, 'excel_confirmacion') !== false)
        );
    }

    public static function isGoogleDriveUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://(drive\.google\.com|docs\.google\.com)/#i', trim($url));
    }

    public static function docsExcelLink(
        string $phone,
        string $carga,
        string $codigoProveedor,
        string $linkExcel,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        if (self::isLegacyTempExcelConfirmacionUrl($linkExcel)) {
            Log::warning('CoordinacionWhatsappPayload: docsExcelLink rechazó URL S3 legacy', [
                'codigo_proveedor' => $codigoProveedor,
                'link' => $linkExcel,
            ]);
            $linkExcel = '';
        }

        return self::template($phone, self::DOCS_EXCEL_LINK_TEMPLATE, [
            'carga' => $carga,
            'codigo_proveedor' => $codigoProveedor,
            'link_excel' => self::normalizeExternalUrl($linkExcel),
            'link_intranet' => '',
        ], $bitrixMessage, $sleep);
    }

    public static function docsExcelLinkPreview(string $carga, string $codigoProveedor, string $linkExcel, ?string $linkIntranet = null): string
    {
        $intranet = trim((string) ($linkIntranet ?? ''));
        $drive = trim($linkExcel);
        $label = trim($codigoProveedor) !== '' ? trim($codigoProveedor) : 'General';

        return "Documentación: CONSOLIDADO #{$carga}\n\n"
            . "Excel de confirmación — {$label}\n\n"
            . ($drive !== '' ? "Descárgalo aquí: {$drive} 📄" : '')
            . ($intranet !== '' ? (($drive !== '' ? "\n\n" : '') . "Llénalo en la intranet: {$intranet}") : '');
    }

    public static function docsPaso2(string $phone, string $bitrixMessage, ?string $fechaMaxima = null, int $sleep = 0): array
    {
        if ($fechaMaxima !== null && $fechaMaxima !== '') {
            return self::template($phone, 'pb_docs_paso2_word_fecha_v1', [
                'fecha_maxima' => $fechaMaxima,
            ], $bitrixMessage, $sleep);
        }

        return self::template($phone, 'pb_docs_paso2_word_v1', [], $bitrixMessage, $sleep);
    }

    public static function docsConsideraciones(
        string $phone,
        string $filePath,
        ?string $chatPreview = null,
        ?string $filename = null,
        ?string $mimeType = null,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_docs_consideraciones_doc_v1',
            [],
            $filePath,
            $chatPreview,
            $filename,
            $mimeType ?? 'application/pdf',
            $sleep
        );
    }

    public static function docsRecordatorioIntro(
        string $phone,
        string $nombreCliente,
        string $carga,
        int $sleep = 0,
        ?string $bitrixOverride = null
    ): array {
        return self::template($phone, 'pb_docs_recordatorio_intro_v1', [
            'nombre_cliente' => $nombreCliente,
            'carga' => $carga,
        ], $bitrixOverride ?? self::docsRecordatorioIntroBitrix($nombreCliente, $carga), $sleep);
    }

    public static function docsRecordatorioProveedor(
        string $phone,
        string $codigoProveedor,
        array $documentos,
        int $sleep = 0
    ): array {
        $documentosFaltantes = self::formatDocumentosFaltantesForMeta($documentos, self::documentosRecordatorioMap());

        return self::template($phone, 'pb_docs_recordatorio_proveedor_v1', [
            'codigo_proveedor' => $codigoProveedor,
            'documentos_faltantes' => $documentosFaltantes,
        ], self::docsRecordatorioProveedorBitrix($codigoProveedor, $documentosFaltantes), $sleep);
    }

    public static function docsRecordatorioAviso(string $phone, int $sleep = 0): array
    {
        return self::template($phone, 'pb_docs_recordatorio_aviso_v1', [], self::DOCS_RECORDATORIO_AVISO, $sleep);
    }

    /**
     * Secuencia Meta D05 → D06 (por proveedor) → D07.
     *
     * @param  array<int, array{code: string, documentos: array<int, string>}>  $proveedores
     * @return array<int, array<string, mixed>>
     */
    public static function docsRecordatorioSteps(
        string $phone,
        string $nombreCliente,
        string $carga,
        array $proveedores
    ): array {
        $steps = [
            self::docsRecordatorioIntro(
                $phone,
                $nombreCliente,
                $carga,
                5,
                self::docsRecordatorioBitrixCompleto($nombreCliente, $carga, $proveedores)
            ),
        ];

        foreach ($proveedores as $proveedor) {
            $code = trim((string) ($proveedor['code'] ?? ''));
            $documentos = (array) ($proveedor['documentos'] ?? []);
            if ($code === '' || $documentos === []) {
                continue;
            }

            $steps[] = self::docsRecordatorioProveedor($phone, $code, $documentos, 3);
        }

        $steps[] = self::docsRecordatorioAviso($phone, 3);

        return $steps;
    }

    /**
     * Texto legacy (API antigua / sin Meta) — mismo contenido que las plantillas, para fallback.
     *
     * @param  array<int, array{code: string, documentos: array<int, string>}>  $proveedores
     */
    public static function docsRecordatorioLegacyMessage(
        string $nombreCliente,
        string $carga,
        array $proveedores
    ): string {
        return self::docsRecordatorioBitrixCompleto($nombreCliente, $carga, $proveedores);
    }

    /**
     * @param  array<int, array{code: string, documentos: array<int, string>}>  $proveedores
     */
    private static function docsRecordatorioBitrixCompleto(
        string $nombreCliente,
        string $carga,
        array $proveedores
    ): string {
        $map = self::documentosRecordatorioMap();
        $lines = [
            self::docsRecordatorioIntroBitrix($nombreCliente, $carga),
            '',
        ];

        foreach ($proveedores as $proveedor) {
            $code = trim((string) ($proveedor['code'] ?? ''));
            $documentos = (array) ($proveedor['documentos'] ?? []);
            if ($code === '' || $documentos === []) {
                continue;
            }

            $lines[] = self::docsRecordatorioProveedorBitrix(
                $code,
                self::formatDocumentosFaltantesForMeta($documentos, $map)
            );
            $lines[] = '';
        }

        $lines[] = self::DOCS_RECORDATORIO_AVISO;

        return implode("\n", $lines);
    }

    private static function docsRecordatorioIntroBitrix(string $nombreCliente, string $carga): string
    {
        return "Hola {$nombreCliente}, estamos esperando que nos envíes los documentos de tu importación del consolidado #{$carga}. A continuación detallo los que faltan:";
    }

    private static function docsRecordatorioProveedorBitrix(string $codigoProveedor, string $documentosFaltantes): string
    {
        return "Proveedor {$codigoProveedor} — pendientes: {$documentosFaltantes}";
    }

    public static function calcIntro(string $phone, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_calc_intro_v1', [], $bitrixMessage, $sleep);
    }

    public static function calcPdf(
        string $phone,
        string $filePath,
        string $bitrixMessage,
        ?string $filename = null,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_calc_pdf_v1',
            [],
            $filePath,
            $bitrixMessage,
            $filename,
            'application/pdf',
            $sleep
        );
    }

    public static function calcResumenTexto(string $phone, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_calc_resumen_texto_v1', [], $bitrixMessage, $sleep);
    }

    public static function calcResumenImg(
        string $phone,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::imageTemplate($phone, 'pb_calc_resumen_img_v1', [], $filePath, $bitrixMessage, $sleep);
    }

    public static function rotuladoPdfProducto(
        string $phone,
        string $nombreProducto,
        string $codigoProveedor,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_rotulado_pdf_producto_v1',
            [
                'nombre_producto' => $nombreProducto,
                'codigo_proveedor' => $codigoProveedor,
            ],
            $filePath,
            $bitrixMessage,
            basename($filePath),
            'application/pdf',
            $sleep
        );
    }

    public static function rotuladoEtiquetaCalzado(
        string $phone,
        string $codigoProveedor,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_rotulado_etiqueta_calzado_v1',
            ['codigo_proveedor' => $codigoProveedor],
            $filePath,
            $bitrixMessage,
            basename($filePath),
            'application/pdf',
            $sleep
        );
    }

    public static function rotuladoEtiquetaRopa(
        string $phone,
        string $codigoProveedor,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_rotulado_etiqueta_ropa_v1',
            ['codigo_proveedor' => $codigoProveedor],
            $filePath,
            $bitrixMessage,
            basename($filePath),
            'application/pdf',
            $sleep
        );
    }

    public static function rotuladoEtiquetaRopaInterior(
        string $phone,
        string $codigoProveedor,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_rotulado_etiqueta_ropa_interior_v1',
            ['codigo_proveedor' => $codigoProveedor],
            $filePath,
            $bitrixMessage,
            basename($filePath),
            'application/pdf',
            $sleep
        );
    }

    public static function rotuladoEtiquetaMaquinaria(
        string $phone,
        string $codigoProveedor,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_rotulado_etiqueta_maquinaria_v1',
            ['codigo_proveedor' => $codigoProveedor],
            $filePath,
            $bitrixMessage,
            basename($filePath),
            'application/pdf',
            $sleep
        );
    }

    public static function rotuladoAlmacenChinaImg(
        string $phone,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::imageTemplate($phone, 'pb_rotulado_almacen_china_img_v1', [], $filePath, $bitrixMessage, $sleep);
    }

    public static function rotuladoDatosProveedor(string $phone, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_rotulado_datos_proveedor_v1', [], $bitrixMessage, $sleep);
    }

    public static function rotuladoDatosProveedorLink(
        string $phone,
        string $linkDatosProveedor,
        string $listaProveedores,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_rotulado_datos_proveedor_link_v1', [
            'link_datos_proveedor' => self::normalizeExternalUrl($linkDatosProveedor),
            'lista_proveedores' => $listaProveedores,
        ], $bitrixMessage, $sleep);
    }

    public static function rotuladoVinLink(
        string $phone,
        string $linkVin,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_rotulado_vin_link_v1', [
            'link_vin' => $linkVin,
        ], $bitrixMessage, $sleep);
    }

    public static function entregaConformidadTexto(
        string $phone,
        string $nombre,
        string $carga,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_entrega_conformidad_texto_v1', [
            'nombre' => $nombre,
            'carga' => $carga,
        ], $bitrixMessage, $sleep);
    }

    public static function entregaConformidadFoto(
        string $phone,
        string $numero,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::imageTemplate($phone, 'pb_entrega_conformidad_foto_v1', [
            'numero' => $numero,
        ], $filePath, $bitrixMessage, $sleep);
    }

    public static function entregaCargoFirmado(
        string $phone,
        string $nombre,
        string $carga,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_entrega_cargo_firmado_v1',
            [
                'nombre' => $nombre,
                'carga' => $carga,
            ],
            $filePath,
            $bitrixMessage,
            'cargo_entrega_firmado.pdf',
            'application/pdf',
            $sleep
        );
    }

    public static function entregaCobroServicios(
        string $phone,
        string $carga,
        string $nombre,
        string $bloqueServicios,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_entrega_cobro_servicios_v1', [
            'carga' => $carga,
            'nombre' => $nombre,
            'bloque_servicios' => $bloqueServicios,
        ], $bitrixMessage, $sleep);
    }

    public static function entregaRecordatorio(string $phone, string $mensaje, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_entrega_recordatorio_v1', [
            'mensaje' => $mensaje,
        ], $bitrixMessage, $sleep);
    }

    public static function consolidadoPagosImg(
        string $phone,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::imageTemplate($phone, 'pb_consolidado_pagos_img_v1', [], $filePath, $bitrixMessage, $sleep);
    }

    public static function consolidadoCotizacionFinal(
        string $phone,
        string $carga,
        string $nombre,
        string $costoCbm,
        string $impuestos,
        string $serviciosExtras,
        string $total,
        string $fechaLimite,
        string $filePath,
        ?string $bitrixMessage = null,
        int $sleep = 0
    ): array {
        $params = [
            'carga' => $carga,
            'nombre' => $nombre,
            'costo_cbm' => $costoCbm,
            'impuestos' => $impuestos,
            'servicios_extras' => $serviciosExtras,
            'total' => $total,
            'fecha_limite' => $fechaLimite,
        ];

        if ($filePath !== '' && is_file($filePath)) {
            return self::documentTemplate(
                $phone,
                'pb_consolidado_cotizacion_final_v1',
                $params,
                $filePath,
                $bitrixMessage,
                basename($filePath),
                'application/pdf',
                $sleep
            );
        }

        return self::template($phone, 'pb_consolidado_cotizacion_final_v1', $params, $bitrixMessage, $sleep);
    }

    public static function consolidadoResumenPago(
        string $phone,
        string $totalCotizacion,
        string $adelanto,
        string $pendiente,
        string $filePath,
        ?string $bitrixMessage = null,
        int $sleep = 0
    ): array {
        return self::imageTemplate(
            $phone,
            'pb_consolidado_resumen_pago_v1',
            [
                'total_cotizacion' => $totalCotizacion,
                'adelanto' => $adelanto,
                'pendiente' => $pendiente,
            ],
            $filePath,
            $bitrixMessage,
            $sleep
        );
    }

    public static function consolidadoCotizacionFinalPdf(
        string $phone,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::documentTemplate(
            $phone,
            'pb_consolidado_cotizacion_final_pdf_v1',
            [],
            $filePath,
            $bitrixMessage,
            basename($filePath),
            'application/pdf',
            $sleep
        );
    }

    public static function proveedorLlegadaChina(
        string $phone,
        string $nombreCliente,
        string $codigoProveedor,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_proveedor_llegada_china_v1', [
            'nombre_cliente' => $nombreCliente,
            'codigo_proveedor' => $codigoProveedor,
        ], $bitrixMessage, $sleep);
    }

    public static function proveedorDatosLink(
        string $phone,
        string $nombreCliente,
        string $linkDatosProveedor,
        string $listaProveedores,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_proveedor_datos_link_v1', [
            'nombre_cliente' => $nombreCliente,
            'link_datos_proveedor' => self::normalizeExternalUrl($linkDatosProveedor),
            'lista_proveedores' => $listaProveedores,
        ], $bitrixMessage, $sleep);
    }

    public static function proveedorInspeccionManual(string $phone, string $mensaje, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_proveedor_inspeccion_manual_v1', [
            'mensaje' => $mensaje,
        ], $bitrixMessage, $sleep);
    }

    public static function proveedorDatosGuardadoPendiente(
        string $phone,
        string $codigosPendientes,
        string $linkDatosProveedor,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_proveedor_datos_guardado_pendiente_v1', [
            'codigos_pendientes' => $codigosPendientes,
            'link_datos_proveedor' => self::normalizeExternalUrl($linkDatosProveedor),
        ], $bitrixMessage, $sleep);
    }

    public static function proveedorDatosGuardadoCompleto(
        string $phone,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template($phone, 'pb_proveedor_datos_guardado_completo_v1', [], $bitrixMessage, $sleep);
    }

    public static function generalCliente(string $phone, string $mensaje, string $bitrixMessage, int $sleep = 0): array
    {
        return self::template($phone, 'pb_general_cliente_v1', [
            'mensaje' => $mensaje,
        ], $bitrixMessage, $sleep);
    }

    public static function deliveryWhatsapp(
        string $phone,
        string $nombre,
        string $carga,
        ?string $bitrixMessage = null,
        int $sleep = 0
    ): array {
        $preview = $bitrixMessage ?? self::deliveryWhatsappPreview($nombre, $carga);

        return self::template($phone, 'pb_delivery_whatsapp_v1', [
            'nombre' => $nombre,
            'carga' => $carga,
        ], $preview, $sleep);
    }

    /**
     * Vista previa Bitrix / legacy (mismo texto que la plantilla Meta P05).
     */
    public static function deliveryWhatsappPreview(string $nombre, string $carga): string
    {
        return "Hola {$nombre}.\n\nGracias por llenar nuestro formulario del consolidado #{$carga}, le estaremos avisando de nuevos avances.";
    }

    /**
     * @return array{template: string, params: array<string, string>, cantidad_line: string}
     */
    public static function resolveInspeccionLlegadaTemplate(
        int $qtyBoxChina,
        int $qtyPalletChina
    ): array {
        $boxes = max(0, $qtyBoxChina);
        $pallets = max(0, $qtyPalletChina);

        if ($boxes > 0 && $pallets > 0) {
            return [
                'template' => 'pb_inspeccion_llegada_v1_pallets_boxes',
                'params' => [
                    'cantidad_boxes' => (string) $boxes,
                    'cantidad_pallets' => (string) $pallets,
                ],
                'cantidad_line' => "{$boxes} boxes y {$pallets} pallets",
            ];
        }

        if ($pallets > 0) {
            return [
                'template' => 'pb_inspeccion_llegada_v1_pallets',
                'params' => [
                    'cantidad_pallets' => (string) $pallets,
                ],
                'cantidad_line' => "{$pallets} pallets",
            ];
        }

        return [
            'template' => 'pb_inspeccion_llegada_v1',
            'params' => [
                'cantidad_cajas' => (string) $boxes,
            ],
            'cantidad_line' => "{$boxes} boxes",
        ];
    }

    public static function inspeccionLlegadaPreview(
        string $nombreCliente,
        string $codigoProveedor,
        string $cantidadLine,
        string $linkInspeccion
    ): string {
        return "📦 Cliente: {$nombreCliente} — Proveedor {$codigoProveedor} — {$cantidadLine}.\n\n"
            . "Tu carga llegó a nuestro almacén de Yiwu, te comparto las fotos y videos.\n\n"
            . "🔗 Ver inspección: {$linkInspeccion} 📦";
    }

    public static function inspeccionLlegada(
        string $phone,
        string $nombreCliente,
        string $codigoProveedor,
        int $qtyBoxChina,
        int $qtyPalletChina,
        string $linkInspeccion,
        ?string $bitrixMessage = null,
        int $sleep = 0
    ): array {
        $resolved = self::resolveInspeccionLlegadaTemplate($qtyBoxChina, $qtyPalletChina);

        $params = array_merge([
            'nombre_cliente' => $nombreCliente,
            'codigo_proveedor' => $codigoProveedor,
            'link_inspeccion' => $linkInspeccion,
        ], $resolved['params']);

        $preview = $bitrixMessage ?? self::inspeccionLlegadaPreview(
            $nombreCliente,
            $codigoProveedor,
            $resolved['cantidad_line'],
            $linkInspeccion
        );

        return self::template($phone, $resolved['template'], $params, $preview, $sleep);
    }

    public static function inspeccionImagen(
        string $phone,
        string $codigoProveedor,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::imageTemplate($phone, 'pb_inspeccion_imagen_v1', [
            'codigo_proveedor' => $codigoProveedor,
        ], $filePath, $bitrixMessage, $sleep);
    }

    public static function inspeccionVideo(
        string $phone,
        string $codigoProveedor,
        string $filePath,
        string $bitrixMessage,
        int $sleep = 0
    ): array {
        return self::template(
            $phone,
            'pb_inspeccion_video_v1',
            ['codigo_proveedor' => $codigoProveedor],
            $bitrixMessage,
            $sleep,
            [
                'type' => 'video',
                'path' => $filePath,
            ]
        );
    }
}
