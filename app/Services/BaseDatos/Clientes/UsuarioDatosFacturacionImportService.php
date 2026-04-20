<?php

namespace App\Services\BaseDatos\Clientes;

use App\Events\UsuarioDatosFacturacionImportFinished;
use App\Helpers\UserLookupHelper;
use App\Jobs\ImportUsuarioDatosFacturacionExcelJob;
use App\Models\ImportUsuarioDatosFacturacion;
use App\Models\UsuarioDatosFacturacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UsuarioDatosFacturacionImportService
{
    public function enqueueImport($file, $usuarioId = null)
    {
        $usuarioId = $this->resolveValidUsuarioId($usuarioId);

        $storedPath = $file->storeAs(
            'imports/usuario-datos-facturacion',
            time() . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            'public'
        );

        $import = ImportUsuarioDatosFacturacion::create([
            'nombre_archivo' => $file->getClientOriginalName(),
            'ruta_archivo' => $storedPath,
            'cantidad_rows' => 0,
            'usuario_id' => $usuarioId,
            'estadisticas' => $this->buildInitialStats(),
            'estado' => 'PENDIENTE',
        ]);

        ImportUsuarioDatosFacturacionExcelJob::dispatch((int) $import->id);

        return [
            'import_id' => (int) $import->id,
            'estado' => $import->estado,
        ];
    }

    /**
     * Garantiza que usuario_id cumpla FK a users.id; si no, retorna null.
     *
     * @param mixed $usuarioId
     * @return int|null
     */
    private function resolveValidUsuarioId($usuarioId)
    {
        if (!is_numeric($usuarioId)) {
            return null;
        }

        $id = (int) $usuarioId;
        if ($id <= 0) {
            return null;
        }

        $exists = DB::table('users')->where('id', $id)->exists();
        return $exists ? $id : null;
    }

    public function processImportById($idImport)
    {
        $import = ImportUsuarioDatosFacturacion::find((int) $idImport);
        if (!$import) {
            throw new \InvalidArgumentException('Importacion no encontrada.');
        }

        if ($import->estado === 'ROLLBACK') {
            Log::info('Importacion omitida por estar en rollback', ['id_import' => $import->id]);
            return;
        }

        if ($import->estado === 'COMPLETADO') {
            Log::info('Importacion ya completada, no se reprocesa', ['id_import' => $import->id]);
            return;
        }

        $stats = $this->buildInitialStats();

        $import->update([
            'estado' => 'PROCESANDO',
            'estadisticas' => $stats,
        ]);

        $fullPath = storage_path('app/public/' . $import->ruta_archivo);
        if (!file_exists($fullPath)) {
            $stats['errores'] = 1;
            $stats['detalles'][] = 'Archivo no encontrado en storage.';
            $import->update([
                'estado' => 'ERROR',
                'estadisticas' => $stats,
            ]);
            $this->dispatchImportFinishedNotificationEvent($import, 'ERROR', 'No se pudo procesar la importacion: archivo no encontrado.', $stats);
            return;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();

            $headerRow = $this->detectHeaderRow($worksheet);
            $headersMap = $this->extractHeadersMap($worksheet, $headerRow);

            $highestRow = $worksheet->getHighestRow();
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                try {
                    $rowData = $this->extractRowData($worksheet, $row, $headersMap);
                    if ($this->isEmptyRow($rowData)) {
                        continue;
                    }

                    $stats['total_filas']++;

                    $correo = $this->firstValue($rowData, ['correo', 'email', 'column3']);
                    $telefono = $this->firstValue($rowData, ['celular', 'telefono', 'whatsapp', 'phone']);
                    $dni = $this->sanitizeDigits($this->firstValue($rowData, ['dni']));
                    $documento = $this->sanitizeDigits($this->firstValue($rowData, ['documento', 'doc', 'dni_ruc', 'ruc']));
                    $lookupDocumento = $dni ?: $documento;

                    $user = UserLookupHelper::findUserByContact(
                        $this->nullIfEmpty($correo),
                        $this->nullIfEmpty($telefono),
                        $this->nullIfEmpty($lookupDocumento)
                    );

                    if (!$user || empty($user->id)) {
                        $stats['omitidos']++;
                        $stats['sin_usuario']++;
                        $stats['detalles'][] = 'Fila ' . $row . ': no se encontro usuario por contacto.';
                        continue;
                    }

                    $destino = $this->normalizeDestino($this->firstValue($rowData, ['destino_entrega', 'destino']));
                    $titular = $this->firstValue($rowData, ['titular', 'razon_social']);
                    $nombre = $this->firstValue($rowData, ['nombre', 'nombre_completo']) ?: $titular;
                    $domicilioFiscal = $this->firstValue($rowData, ['domicilio_fiscal', 'domiciliofiscal', 'direccion_fiscal']);

                    $ruc = null;
                    if (strlen((string) $documento) === 11) {
                        $ruc = $documento;
                    } else {
                        $ruc = $this->sanitizeDigits($this->firstValue($rowData, ['ruc']));
                    }

                    $dniFinal = $dni;
                    if (empty($dniFinal) && strlen((string) $documento) === 8) {
                        $dniFinal = $documento;
                    }

                    UsuarioDatosFacturacion::create([
                        'id_user' => (int) $user->id,
                        'id_import' => (int) $import->id,
                        'destino' => $destino,
                        'nombre_completo' => $this->nullIfEmpty($nombre),
                        'dni' => $this->nullIfEmpty($dniFinal),
                        'ruc' => $this->nullIfEmpty($ruc),
                        'razon_social' => $this->nullIfEmpty($titular),
                        'domicilio_fiscal' => $this->nullIfEmpty($domicilioFiscal),
                    ]);

                    $stats['creados']++;
                } catch (\Exception $e) {
                    $stats['errores']++;
                    $stats['detalles'][] = 'Fila ' . $row . ': ' . $e->getMessage();
                    Log::warning('Import usuario_datos_facturacion, fila con error', [
                        'row' => $row,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $import->update([
                'cantidad_rows' => (int) $stats['creados'],
                'estadisticas' => $stats,
                'estado' => 'COMPLETADO',
            ]);
            $this->dispatchImportFinishedNotificationEvent(
                $import,
                'COMPLETADO',
                'Importacion finalizada correctamente.',
                $stats
            );
        } catch (\Throwable $e) {
            $stats['errores']++;
            $stats['detalles'][] = 'Error general del archivo: ' . $e->getMessage();

            $import->update([
                'estadisticas' => $stats,
                'estado' => 'ERROR',
            ]);
            $this->dispatchImportFinishedNotificationEvent(
                $import,
                'ERROR',
                'No se pudo procesar la importacion: ' . $e->getMessage(),
                $stats
            );

            throw $e;
        }
    }

    public function listImports($limit = 100)
    {
        return ImportUsuarioDatosFacturacion::query()
            ->where('estado', '!=', 'ROLLBACK')
            ->orderByDesc('id')
            ->limit((int) $limit)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'nombre_archivo' => $row->nombre_archivo,
                    'cantidad_rows' => (int) $row->cantidad_rows,
                    'estado' => $row->estado,
                    'rollback_at' => $row->rollback_at,
                    'created_at' => $row->created_at,
                    'estadisticas' => $row->estadisticas,
                    'ruta_archivo' => $this->generateFileUrl($row->ruta_archivo),
                ];
            })
            ->values();
    }

    public function rollbackImport($idImport)
    {
        $import = ImportUsuarioDatosFacturacion::find($idImport);
        if (!$import) {
            throw new \InvalidArgumentException('Importacion no encontrada.');
        }

        if ($import->estado === 'PROCESANDO') {
            throw new \InvalidArgumentException('La importacion se encuentra en proceso y no puede revertirse aun.');
        }

        if ($import->estado === 'ROLLBACK') {
            return [
                'deleted_rows' => 0,
                'already_rolled_back' => true,
            ];
        }

        $deletedRows = 0;
        DB::transaction(function () use ($import, &$deletedRows) {
            $deletedRows = UsuarioDatosFacturacion::where('id_import', (int) $import->id)->delete();

            $import->estado = 'ROLLBACK';
            $import->rollback_at = now();
            $import->save();
        });

        return [
            'deleted_rows' => (int) $deletedRows,
            'already_rolled_back' => false,
        ];
    }

    private function buildInitialStats()
    {
        return [
            'total_filas' => 0,
            'creados' => 0,
            'omitidos' => 0,
            'sin_usuario' => 0,
            'errores' => 0,
            'detalles' => [],
        ];
    }

    private function detectHeaderRow($worksheet)
    {
        $maxRow = min(5, (int) $worksheet->getHighestRow());
        for ($row = 1; $row <= $maxRow; $row++) {
            $values = $this->extractRowRawValues($worksheet, $row);
            $normalized = array_map([$this, 'normalizeHeader'], $values);
            if (in_array('destinoentrega', $normalized, true) || in_array('domiciliofiscal', $normalized, true)) {
                return $row;
            }
        }

        return 1;
    }

    private function extractHeadersMap($worksheet, $headerRow)
    {
        $headers = $this->extractRowRawValues($worksheet, $headerRow);
        $map = [];
        foreach ($headers as $idx => $header) {
            $key = $this->normalizeHeader($header);
            if ($key === '') {
                $key = 'column' . ($idx + 1);
            }
            $map[$idx + 1] = $key;
        }

        return $map;
    }

    private function extractRowData($worksheet, $row, $headersMap)
    {
        $data = [];
        foreach ($headersMap as $columnIndex => $header) {
            $value = trim((string) ($worksheet->getCellByColumnAndRow($columnIndex, $row)->getFormattedValue() ?? ''));
            $data[$header] = $value;
        }
        return $data;
    }

    private function extractRowRawValues($worksheet, $row)
    {
        $highestColumn = $worksheet->getHighestColumn($row);
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        $values = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $values[] = (string) ($worksheet->getCellByColumnAndRow($col, $row)->getFormattedValue() ?? '');
        }
        return $values;
    }

    private function isEmptyRow(array $rowData)
    {
        foreach ($rowData as $value) {
            if ($this->nullIfEmpty($value) !== null) {
                return false;
            }
        }
        return true;
    }

    private function firstValue(array $rowData, array $aliases)
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);
            if (array_key_exists($key, $rowData)) {
                $value = $this->nullIfEmpty($rowData[$key]);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function normalizeHeader($value)
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace([' ', '_', '-', '.', '/'], '', $value);
        return preg_replace('/[^a-z0-9]/', '', $value);
    }

    private function normalizeDestino($destino)
    {
        $value = strtolower(trim((string) $destino));
        if ($value === 'lima') {
            return 'Lima';
        }
        if ($value === 'provincia') {
            return 'Provincia';
        }
        return null;
    }

    private function sanitizeDigits($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value);
        return $digits !== '' ? $digits : null;
    }

    private function nullIfEmpty($value)
    {
        $value = is_string($value) ? trim($value) : $value;
        return ($value === '' || $value === null) ? null : $value;
    }

    private function generateFileUrl($path)
    {
        if (!$path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function dispatchImportFinishedNotificationEvent(
        ImportUsuarioDatosFacturacion $import,
        $status,
        $message,
        array $stats
    )
    {
        event(new UsuarioDatosFacturacionImportFinished($import, (string) $status, (string) $message, $stats));
    }
}
