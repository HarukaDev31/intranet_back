<?php

namespace App\Services\CargaConsolidada;

use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\WhatsappInbox\WhatsappInboxOrgConfigService;
use App\Support\WhatsApp\CoordinacionMediaLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Rotulado para organizaciones socio sin Meta propio: en vez de enviar por WhatsApp
 * se arma un ZIP solo con los PDF de rotulado (uno por proveedor) y se devuelve un enlace de descarga.
 */
class RotuladoDescargaService
{
    const ID_ORGANIZACION_ADMIN = 1;

    /**
     * Socio (org ≠ 1) que no tiene credenciales Meta propias activas.
     */
    public function aplicaDescarga(int $organizacionId): bool
    {
        if ($organizacionId <= 0 || $organizacionId === self::ID_ORGANIZACION_ADMIN) {
            return false;
        }

        return !app(WhatsappInboxOrgConfigService::class)->isEnabled($organizacionId);
    }

    public function organizacionIdDeCotizacion(Cotizacion $cotizacion): int
    {
        $orgId = (int) $cotizacion->getAttribute('organizacion_id');

        return $orgId > 0 ? $orgId : self::ID_ORGANIZACION_ADMIN;
    }

    /**
     * @param  array<int, int|string>  $idsProveedores
     * @return array{url: string, filename: string, proveedores: int}
     * @throws \Exception
     */
    public function generar(Cotizacion $cotizacion, array $idsProveedores, Contenedor $contenedor): array
    {
        $orgId = $this->organizacionIdDeCotizacion($cotizacion);
        $carga = (string) $contenedor->carga;
        $cliente = (string) $cotizacion->nombre;

        $proveedores = CotizacionProveedor::where('id_cotizacion', $cotizacion->id)
            ->whereIn('id', $idsProveedores)
            ->get();
        if ($proveedores->isEmpty()) {
            throw new \Exception('No se encontraron proveedores válidos para generar el rotulado');
        }

        $dir = storage_path('app/rotulado-descarga');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $token = Str::random(8);
        $zipName = 'Rotulado_' . Str::slug($cliente !== '' ? $cliente : 'cliente', '_') . '_C' . Str::slug($carga, '') . '_' . date('Ymd_His') . '.zip';
        $zipPath = $dir . DIRECTORY_SEPARATOR . $token . '_' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('No se pudo crear el archivo ZIP de rotulado');
        }

        try {
            $pdfService = app(RotuladoPdfService::class);

            foreach ($proveedores as $proveedor) {
                $supplierCode = (string) $proveedor->code_supplier;

                $html = $pdfService->buildHtmlForCotizacion($cliente, $supplierCode, $carga, $cotizacion);
                $zip->addFromString("Rotulado_{$supplierCode}.pdf", $pdfService->renderPdf($html));
            }

            if (!$zip->close()) {
                throw new \Exception('Error al cerrar el archivo ZIP de rotulado');
            }
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($zipPath);
            Log::error('[ROTULADO_DESCARGA] Error generando ZIP', [
                'id_cotizacion' => $cotizacion->id,
                'organizacion_id' => $orgId,
                'error' => $e->getMessage(),
            ]);
            throw $e instanceof \Exception ? $e : new \Exception($e->getMessage(), 0, $e);
        }

        $url = CoordinacionMediaLink::uploadLocalFile($zipPath, 'temp/rotulado/' . $token . '_' . $zipName);
        @unlink($zipPath);
        if ($url === null) {
            Log::error('[ROTULADO_DESCARGA] No se pudo subir el ZIP al storage', [
                'id_cotizacion' => $cotizacion->id,
            ]);
            throw new \Exception('No se pudo publicar el archivo de rotulado para descarga');
        }

        CotizacionProveedor::whereIn('id', $proveedores->pluck('id')->all())
            ->update(['send_rotulado_status' => 'SENDED']);

        Log::info('[ROTULADO_DESCARGA] ZIP generado (org sin Meta propio)', [
            'id_cotizacion' => $cotizacion->id,
            'organizacion_id' => $orgId,
            'proveedores' => $proveedores->count(),
        ]);

        return [
            'url' => $url,
            'filename' => $zipName,
            'proveedores' => $proveedores->count(),
        ];
    }
}
