<?php

namespace App\Services\PagoProveedor;

use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorDocumento;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Facades\Log;

/**
 * Emite los PDF del módulo una sola vez por estado.
 *
 * Cada documento guarda la huella de sus datos, la plantilla y el paso del flujo.
 * Si nada cambió se devuelve el PDF ya emitido; si el proceso avanza o retrocede
 * de etapa se genera otro y queda anotado en el historial.
 */
class PagoProveedorDocumentoService
{
    use UsesObjectStorage;

    public function __construct(private readonly PagoProveedorHistorialService $historial)
    {
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}|null  $actor
     * @param  callable(string $emitidoEl): string  $render
     * @return array{filename: string, base64: string, paso: int, paso_nombre: string, emitido_el: string|null, generado: bool, descargas: int}
     */
    public function emitir(
        string $entidadTipo,
        int $entidadId,
        string $documento,
        int $alcanceId,
        int $paso,
        string $huellaDatos,
        string $nombreArchivo,
        callable $render,
        ?array $actor = null,
        bool $contarDescarga = true
    ): array {
        $huella = $this->huella($documento, $paso, $huellaDatos);
        $registro = $this->registro($entidadTipo, $entidadId, $documento, $alcanceId);
        $binario = $registro !== null && $registro->fingerprint === $huella ? $this->leer($registro->path) : null;
        $generado = $binario === null;

        if ($generado) {
            $anterior = $registro !== null ? (int) $registro->step : null;
            $rutaAnterior = $registro?->path;
            $emitidoEl = now()->timezone(PagoProveedorVigenciaService::ZONA)->format('j/n/Y');
            $binario = $render($emitidoEl);
            $ruta = $this->guardar($documento, $entidadTipo, $entidadId, $alcanceId, $paso, $huella, $binario);
            $registro = $this->persistir(
                $registro,
                $entidadTipo,
                $entidadId,
                $documento,
                $alcanceId,
                $paso,
                $huella,
                $huellaDatos,
                $ruta,
                $nombreArchivo,
                $emitidoEl
            );
            // Solo queda el PDF vigente: el del paso anterior ya no sirve.
            $this->descartarArchivoAnterior($rutaAnterior, $ruta);
            $this->registrarEmision($registro, $actor, $anterior);
        }

        if ($contarDescarga) {
            $registro->increment('downloads');
        }

        return [
            'filename' => (string) (($registro->filename ?: '') !== '' ? $registro->filename : $nombreArchivo),
            'base64' => base64_encode($binario),
            'paso' => (int) $registro->step,
            'paso_nombre' => PagoProveedorDocumento::stepLabel((int) $registro->step),
            'emitido_el' => $registro->issued_on,
            'generado' => $generado,
            'descargas' => (int) $registro->downloads,
        ];
    }

    /**
     * Huella completa: datos + paso + plantilla. Es la que decide si hay que rehacer el PDF.
     */
    private function huella(string $documento, int $paso, string $huellaDatos): string
    {
        return sha1(implode('|', [$huellaDatos, (string) $paso, $this->plantillaHuella($documento)]));
    }

    /**
     * Dice si el PDF guardado sigue sirviendo para estos datos y este paso,
     * sin tocar el archivo ni contar descargas.
     */
    public function vigente(
        string $entidadTipo,
        int $entidadId,
        string $documento,
        int $alcanceId,
        int $paso,
        string $huellaDatos
    ): bool {
        $registro = $this->registro($entidadTipo, $entidadId, $documento, $alcanceId);
        if ($registro === null || $registro->path === '') {
            return false;
        }

        return hash_equals((string) $registro->fingerprint, $this->huella($documento, $paso, $huellaDatos));
    }

    /**
     * Estado del último PDF emitido (para mostrarlo en la ficha sin generarlo).
     *
     * @return array{paso: int, paso_nombre: string, generado_en: string|null, emitido_el: string|null, descargas: int, generado: bool}|null
     */
    public function estado(string $entidadTipo, int $entidadId, string $documento, int $alcanceId = 0): ?array
    {
        $registro = $this->registro($entidadTipo, $entidadId, $documento, $alcanceId);
        if ($registro === null) {
            return null;
        }

        return [
            'paso' => (int) $registro->step,
            'paso_nombre' => PagoProveedorDocumento::stepLabel((int) $registro->step),
            'generado_en' => optional($registro->generated_at)->toIso8601String(),
            'emitido_el' => $registro->issued_on,
            'descargas' => (int) $registro->downloads,
            'generado' => true,
        ];
    }

    /**
     * Documentos emitidos de una solicitud: resumen general y balances por proveedor.
     *
     * @return array<int, array{alcance_id: int, documento: string, paso: int, paso_nombre: string, generado_en: string|null, emitido_el: string|null, descargas: int}>
     */
    public function estadoDeSolicitud(int $entidadId): array
    {
        return PagoProveedorDocumento::query()
            ->where('entity_type', PagoProveedorDocumento::ENTITY_REQUEST)
            ->where('entity_id', $entidadId)
            ->orderBy('scope_id')
            ->get()
            ->map(function (PagoProveedorDocumento $registro) {
                return [
                    'alcance_id' => (int) $registro->scope_id,
                    'documento' => (string) $registro->document_type,
                    'paso' => (int) $registro->step,
                    'paso_nombre' => PagoProveedorDocumento::stepLabel((int) $registro->step),
                    'generado_en' => optional($registro->generated_at)->toIso8601String(),
                    'emitido_el' => $registro->issued_on,
                    'descargas' => (int) $registro->downloads,
                ];
            })
            ->all();
    }

    /**
     * Paso del flujo que corresponde al resumen de la cotización.
     * 0 = propuesta enviada; 1..4 = etapa de la solicitud asociada.
     */
    public function pasoDeCotizacion(PagoProveedorCotizacion $cotizacion): int
    {
        $cotizacion->loadMissing(['proveedores', 'solicitud.proveedores']);
        if ($cotizacion->solicitud === null) {
            return PagoProveedorDocumento::STEP_QUOTE;
        }

        return self::pasoDeProveedores($cotizacion->solicitud->proveedores);
    }

    public function pasoDeSolicitudProveedor(PagoProveedorSolicitudProveedor $proveedor): int
    {
        if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_COMPLETED) {
            return PagoProveedorDocumento::STEP_CONFIRMATION;
        }

        return max(
            PagoProveedorDocumento::STEP_DATA,
            min(PagoProveedorDocumento::STEP_CONFIRMATION, (int) $proveedor->stage)
        );
    }

    /**
     * Etapa común de la solicitud: la del proveedor menos avanzado que sigue vigente.
     *
     * @param  iterable<PagoProveedorSolicitudProveedor>  $proveedores
     */
    public static function pasoDeProveedores($proveedores): int
    {
        $etapas = [];
        foreach ($proveedores as $proveedor) {
            if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
                continue;
            }
            $etapas[] = (int) $proveedor->stage;
        }
        if ($etapas === []) {
            return PagoProveedorDocumento::STEP_DATA;
        }

        return max(
            PagoProveedorDocumento::STEP_DATA,
            min(PagoProveedorDocumento::STEP_CONFIRMATION, min($etapas))
        );
    }

    /**
     * Sello de la plantilla: si se edita el PDF se rehacen los documentos.
     */
    public function plantillaHuella(string $documento): string
    {
        $vistas = $documento === PagoProveedorDocumento::TYPE_BALANCE
            ? ['pago-proveedor/balance.blade.php']
            : ['pago-proveedor/resumen.blade.php', 'PagoProveedorResumenPdfVista.php'];

        $sellos = [];
        foreach ($vistas as $vista) {
            $ruta = strpos($vista, 'pago-proveedor/') === 0
                ? resource_path('views/' . $vista)
                : app_path('Services/PagoProveedor/' . $vista);
            $sellos[] = is_file($ruta) ? (string) filemtime($ruta) : '0';
        }

        return implode(':', $sellos);
    }

    private function registro(string $entidadTipo, int $entidadId, string $documento, int $alcanceId): ?PagoProveedorDocumento
    {
        return PagoProveedorDocumento::query()
            ->where('entity_type', $entidadTipo)
            ->where('entity_id', $entidadId)
            ->where('document_type', $documento)
            ->where('scope_id', $alcanceId)
            ->first();
    }

    private function persistir(
        ?PagoProveedorDocumento $registro,
        string $entidadTipo,
        int $entidadId,
        string $documento,
        int $alcanceId,
        int $paso,
        string $huella,
        string $huellaDatos,
        string $ruta,
        string $nombre,
        string $emitidoEl
    ): PagoProveedorDocumento {
        if ($registro === null) {
            $registro = new PagoProveedorDocumento([
                'entity_type' => $entidadTipo,
                'entity_id' => $entidadId,
                'document_type' => $documento,
                'scope_id' => $alcanceId,
            ]);
        }

        $registro->fill([
            'step' => $paso,
            'fingerprint' => $huella,
            'data_fingerprint' => $huellaDatos,
            'path' => $ruta,
            'filename' => $nombre,
            'issued_on' => $emitidoEl,
            'generated_at' => now(),
        ]);
        $registro->save();

        return $registro;
    }

    /**
     * Deja rastro de cada emisión, diciendo si el PDF se rehízo porque cambió el paso
     * del flujo o porque cambiaron los datos del documento.
     *
     * @param  array{id: int|null, nombre: string, area: string}|null  $actor
     */
    private function registrarEmision(PagoProveedorDocumento $registro, ?array $actor, ?int $pasoAnterior): void
    {
        if ($actor === null) {
            return;
        }

        // Se distingue el motivo para poder auditar cuándo cambió el estado del PDF.
        $accion = match (true) {
            $pasoAnterior === null => 'Documento generado',
            $pasoAnterior !== (int) $registro->step => 'Documento regenerado por cambio de paso',
            default => 'Documento regenerado por cambio de datos',
        };
        $detalle = 'Paso ' . $registro->step . ' · ' . PagoProveedorDocumento::stepLabel((int) $registro->step);
        if ($pasoAnterior !== null) {
            $detalle = $pasoAnterior !== (int) $registro->step
                ? 'Paso ' . $pasoAnterior . ' → ' . $detalle
                : $detalle . ' · datos actualizados';
        }
        $this->historial->registrar(
            $registro->entity_type,
            $registro->entity_id,
            $accion,
            $actor,
            $detalle
        );
    }

    private function guardar(
        string $documento,
        string $entidadTipo,
        int $entidadId,
        int $alcanceId,
        int $paso,
        string $huella,
        string $binario
    ): string {
        $ruta = implode('/', [
            'pago-proveedor',
            'documentos',
            $documento,
            $entidadTipo,
            $entidadId,
            ($alcanceId > 0 ? $alcanceId . '-' : '') . 'paso-' . $paso . '-' . substr($huella, 0, 10) . '.pdf',
        ]);

        try {
            return $this->storagePutContents($ruta, $binario);
        } catch (\Throwable $e) {
            Log::warning('Pago proveedor: no se pudo guardar el PDF emitido', [
                'ruta' => $ruta,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function leer(string $ruta): ?string
    {
        if ($ruta === '') {
            return null;
        }

        try {
            if (!$this->objectStorage()->exists($ruta)) {
                return null;
            }
            $stream = $this->objectStorage()->readStream($ruta);
            if (!is_resource($stream)) {
                return null;
            }
            $contenido = stream_get_contents($stream);
            fclose($stream);

            return is_string($contenido) && $contenido !== '' ? $contenido : null;
        } catch (\Throwable $e) {
            Log::warning('Pago proveedor: no se pudo leer el PDF emitido', [
                'ruta' => $ruta,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Borra el PDF del paso anterior cuando se emite uno nuevo.
     * Si falla, se ignora: solo es limpieza de almacenamiento.
     */
    private function descartarArchivoAnterior(?string $anterior, string $actual): void
    {
        if ($anterior === null || $anterior === '' || $anterior === $actual) {
            return;
        }

        try {
            $this->objectStorage()->delete($anterior);
        } catch (\Throwable $e) {
            Log::warning('Pago proveedor: no se pudo borrar el PDF anterior', [
                'ruta' => $anterior,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
