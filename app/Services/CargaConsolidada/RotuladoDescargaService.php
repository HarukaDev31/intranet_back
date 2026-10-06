<?php

namespace App\Services\CargaConsolidada;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Services\Organizacion\OrganizacionMensajeriaService;
use App\Services\WhatsappInbox\WhatsappInboxOrgConfigService;
use App\Support\WhatsApp\CoordinacionMediaLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Rotulado para organizaciones socio sin Meta propio: en vez de enviar por WhatsApp
 * se arma un ZIP con los mismos archivos y se devuelve un enlace de descarga.
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

        $temporales = [];
        try {
            $pdfService = app(RotuladoPdfService::class);
            $mensajeria = app(OrganizacionMensajeriaService::class);

            foreach ($proveedores as $proveedor) {
                $supplierCode = (string) $proveedor->code_supplier;
                $carpeta = 'Proveedor_' . ($supplierCode !== '' ? Str::slug($supplierCode, '_') : $proveedor->id) . '/';

                $html = $pdfService->buildHtmlForCotizacion($cliente, $supplierCode, $carga, $cotizacion);
                $zip->addFromString($carpeta . "Rotulado_{$supplierCode}.pdf", $pdfService->renderPdf($html));

                $tipo = strtolower(trim((string) $proveedor->tipo_rotulado));
                if ($tipo === 'movilidad_personal') {
                    $this->agregarMovilidadPersonal($zip, $carpeta, $cotizacion, $proveedor, $carga);
                } elseif (in_array($tipo, ['calzado', 'ropa', 'ropa_interior', 'maquinaria'], true)) {
                    $this->agregarArchivoLocal(
                        $zip,
                        $this->rutaTemplateRotulado($tipo),
                        $carpeta . $tipo . '_ejemplo.pdf'
                    );
                }
            }

            $this->agregarArchivoLocal(
                $zip,
                $mensajeria->localPathImagenConFallback($orgId, OrganizacionMensajeriaService::IMG_DIRECCION),
                'Direccion_almacen_China.jpeg'
            );
            foreach ([OrganizacionMensajeriaService::IMG_PASO1 => 'Rotulado_paso1', OrganizacionMensajeriaService::IMG_PASO2 => 'Rotulado_paso2'] as $slot => $nombre) {
                $ruta = $mensajeria->localPathImagenConFallback($orgId, $slot);
                if ($ruta) {
                    $this->agregarArchivoLocal($zip, $ruta, $nombre . '.' . (pathinfo($ruta, PATHINFO_EXTENSION) ?: 'jpg'));
                }
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

    private function rutaTemplateRotulado(string $tipo): string
    {
        try {
            return app(ObjectStorageConnectorInterface::class)->localPath('templates/rotulado/' . $tipo . '.pdf');
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function agregarArchivoLocal(ZipArchive $zip, ?string $ruta, string $nombreEnZip): void
    {
        if ($ruta !== null && $ruta !== '' && is_file($ruta)) {
            $zip->addFile($ruta, $nombreEnZip);
        } else {
            Log::warning('[ROTULADO_DESCARGA] Archivo no encontrado, se omite del ZIP', ['archivo' => $nombreEnZip]);
        }
    }

    private function agregarMovilidadPersonal(ZipArchive $zip, string $carpeta, Cotizacion $cotizacion, CotizacionProveedor $proveedor, string $carga): void
    {
        $vim = app(MovilidadPersonalVimService::class);
        $result = $vim->generateForProveedor($cotizacion, $proveedor->toArray(), $carga);
        if ($result === null) {
            Log::error('[ROTULADO_DESCARGA] No se generaron códigos VIM', [
                'id_cotizacion' => $cotizacion->id,
                'code_supplier' => $proveedor->code_supplier,
            ]);

            return;
        }
        $this->agregarArchivoLocal($zip, $vim->ejemploPdfPath(), $carpeta . 'movilidad_personal_ejemplo.pdf');
        $this->agregarArchivoLocal($zip, $result['excel_path'] ?? null, $carpeta . 'Codigos_VIN.' . (pathinfo((string) ($result['excel_path'] ?? ''), PATHINFO_EXTENSION) ?: 'xlsx'));
    }
}
