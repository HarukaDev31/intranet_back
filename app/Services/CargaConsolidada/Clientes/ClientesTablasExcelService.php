<?php

namespace App\Services\CargaConsolidada\Clientes;

use App\Http\Controllers\CargaConsolidada\Clientes\EmbarcadosController;
use App\Http\Controllers\CargaConsolidada\Clientes\GeneralController;
use App\Http\Controllers\CargaConsolidada\Clientes\VariacionController;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\Usuario;
use App\Support\CargaConsolidada\DocumentStatusSync;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tymon\JWTAuth\Facades\JWTAuth;

class ClientesTablasExcelService
{
    const PAGE_SIZE = 2000;

    const ESTADO_CLIENTE_LABEL = [
        'RESERVADO' => 'Reservado',
        'NO RESERVADO' => 'No Reservado',
        'DOCUMENTACION' => 'Documentación',
    ];

    public function exportar(Request $request, $idContenedor)
    {
        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor) {
            throw new \Exception('Contenedor no encontrado');
        }

        $listRequest = $request->duplicate([
            'currentPage' => 1,
            'itemsPerPage' => self::PAGE_SIZE,
            'per_page' => self::PAGE_SIZE,
            'page' => 1,
            'search' => '',
        ]);

        $embarcadosRes = app(EmbarcadosController::class)->getEmbarcados($listRequest, $idContenedor);
        $generalRes = app(GeneralController::class)->index($listRequest, $idContenedor);
        $variacionRes = app(VariacionController::class)->index($listRequest, $idContenedor);

        $user = $this->authUser();
        $usaEstadosCoord2 = DocumentStatusSync::usesCoord2Statuses($user);
        $isOrgNoAdmin = $this->esUsuarioSocio($user);

        $spreadsheet = new Spreadsheet();
        $this->fillSheet(
            $spreadsheet->getActiveSheet(),
            'Seguimiento',
            $this->buildSeguimiento($this->listFromResponse($embarcadosRes), $usaEstadosCoord2)
        );
        $this->fillSheet(
            $spreadsheet->createSheet(),
            'Documentacion',
            $this->buildDocumentacion($this->listFromResponse($generalRes))
        );
        $this->fillSheet(
            $spreadsheet->createSheet(),
            'Variación',
            $this->buildVariacion($this->listFromResponse($variacionRes), $isOrgNoAdmin)
        );
        $spreadsheet->setActiveSheetIndex(0);

        $carga = preg_replace('/[\\\\\\/:\*\?"<>\|]/', '-', (string) ($contenedor->carga ?: $idContenedor));
        $fileName = 'clientes_#' . $carga . '_' . date('Y-m-d') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function authUser()
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function esUsuarioSocio($user)
    {
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'getNombreGrupo') && $user->getNombreGrupo() === Usuario::ROL_SOCIO) {
            return true;
        }
        return (int) $user->getAttribute('ID_Organizacion') !== 1;
    }

    private function listFromResponse($response)
    {
        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            if (!empty($payload['data']) && is_array($payload['data'])) {
                return $payload['data'];
            }
        }
        return [];
    }

    private function pick($row, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return (string) $row[$key];
            }
            if (!empty($row['cliente'][$key]) && trim((string) $row['cliente'][$key]) !== '') {
                return (string) $row['cliente'][$key];
            }
        }
        return '';
    }

    private function contactoText($row, $sinCorreo = false, array $extra = [])
    {
        $nombre = $this->pick($row, ['nombre', 'razon_social', 'name', 'cliente_nombre', 'clienteName']);
        $documento = $this->pick($row, ['documento', 'dni', 'ruc', 'numero_documento']);
        $telefono = $this->pick($row, ['telefono', 'whatsapp', 'celular', 'phone']);
        $correo = $this->pick($row, ['correo', 'email', 'mail']);
        $lines = array_filter([$nombre, $documento, $telefono]);
        if ($correo !== '') {
            $lines[] = $correo;
        } elseif ($sinCorreo) {
            $lines[] = 'Sin correo';
        }
        foreach ($extra as $line) {
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return implode("\n", $lines);
    }

    private function proveedoresOf($row)
    {
        return !empty($row['proveedores']) && is_array($row['proveedores']) ? $row['proveedores'] : [];
    }

    private function joinProveedorField($row, $field, $format = null)
    {
        $values = [];
        foreach ($this->proveedoresOf($row) as $proveedor) {
            $raw = $proveedor[$field] ?? null;
            $values[] = $format ? $format($raw) : ($raw === null ? '' : (string) $raw);
        }
        return implode("\n", $values);
    }

    private function formatMoney($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        return '$' . number_format((float) $value, 2, '.', ',');
    }

    private function formatOptionalDate($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    private function estadoClienteLabel($value)
    {
        $key = trim((string) $value);
        return self::ESTADO_CLIENTE_LABEL[$key] ?? $key;
    }

    private function statusOrPendiente($value)
    {
        return $value ? (string) $value : 'Pendiente';
    }

    private function variacionLabel($row, $isOrgNoAdmin)
    {
        $volCot = (float) ($row['volumen'] ?? 0);
        $volChina = (float) ($row['volumen_china'] ?? 0);
        $volDoc = (float) ($row['volumen_doc'] ?? 0);
        $valorCot = (float) ($row['valor_cot'] ?? 0);
        $valorDoc = (float) ($row['valor_doc'] ?? 0);
        $hayVariacion = $isOrgNoAdmin
            ? $volCot !== $volChina
            : ($volCot !== $volChina || $volCot !== $volDoc || $valorCot !== $valorDoc);
        return $hayVariacion ? 'SI' : 'NO';
    }

    private function buildSeguimiento(array $items, $usaEstadosCoord2)
    {
        $invoiceField = $usaEstadosCoord2 ? 'invoice_status' : 'invoice_status_final';
        $packingField = $usaEstadosCoord2 ? 'packing_status' : 'packing_status_final';
        $excelField = $usaEstadosCoord2 ? 'excel_conf_status' : 'excel_conf_status_final';
        $headers = [
            'N°', 'Contacto', 'T. Cliente', 'Productos', 'Code Supplier', 'Inspección',
            'Invoice', 'Packing list', 'Excel Conf.', 'Canal', 'Fecha de Entrega', 'Observaciones',
        ];
        $rows = [];
        foreach (array_values($items) as $index => $row) {
            $rows[] = [
                $index + 1,
                $this->contactoText($row),
                $row['tipo_cliente'] ?? '',
                $this->joinProveedorField($row, 'products'),
                $this->joinProveedorField($row, 'code_supplier'),
                $this->joinProveedorField($row, 'arrive_date_china', function ($v) {
                    return $this->formatOptionalDate($v);
                }),
                $this->joinProveedorField($row, $invoiceField, function ($v) {
                    return $this->statusOrPendiente($v);
                }),
                $this->joinProveedorField($row, $packingField, function ($v) {
                    return $this->statusOrPendiente($v);
                }),
                $this->joinProveedorField($row, $excelField, function ($v) {
                    return $this->statusOrPendiente($v);
                }),
                $this->joinProveedorField($row, 'canal'),
                $this->joinProveedorField($row, 'fecha_entrega', function ($v) {
                    return $this->formatOptionalDate($v);
                }),
                $this->joinProveedorField($row, 'observaciones_seguimiento'),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private function buildDocumentacion(array $items)
    {
        $headers = [
            'N°', 'Fecha', 'Contacto', 'T. Cliente', 'Volumen', 'Qty Item',
            'Fob', 'Logistica', 'Impuesto', 'Tarifa', 'Estados',
        ];
        $rows = [];
        foreach (array_values($items) as $index => $row) {
            $contrato = !empty($row['cod_contract']) ? 'Contrato: ' . $row['cod_contract'] : '';
            $rows[] = [
                $index + 1,
                $this->formatOptionalDate($row['fecha'] ?? null),
                $this->contactoText($row, true, [$contrato]),
                $row['name'] ?? '',
                $row['volumen'] ?? '',
                $row['qty_item'] ?? '',
                $this->formatMoney($row['fob'] ?? null),
                $this->formatMoney($row['monto'] ?? null),
                $this->formatMoney($row['impuestos'] ?? null),
                $this->formatMoney($row['tarifa'] ?? null),
                $this->estadoClienteLabel($row['estado_cliente'] ?? ''),
            ];
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private function buildVariacion(array $items, $isOrgNoAdmin)
    {
        $headers = $isOrgNoAdmin
            ? ['N°', 'Asesor', 'Contacto', 'T. Cliente', 'Tarifa', 'Vol. Cot', 'Vol. China', 'Variación']
            : ['N°', 'Asesor', 'Contacto', 'T. Cliente', 'Tarifa', 'Vol. Cot', 'Vol. China', 'Vol. Doc', 'Valor Cot', 'Valor Doc', 'Variación'];
        $rows = [];
        foreach (array_values($items) as $index => $row) {
            $line = [
                $index + 1,
                $row['asesor'] ?? '',
                $this->contactoText($row),
                $row['name'] ?? '',
                $row['tarifa'] ?? '',
                $row['volumen'] ?? '',
                $row['volumen_china'] ?? '',
            ];
            if (!$isOrgNoAdmin) {
                $line[] = $row['volumen_doc'] ?? '';
                $line[] = $row['valor_cot'] ?? '';
                $line[] = $row['valor_doc'] ?? '';
            }
            $line[] = $this->variacionLabel($row, $isOrgNoAdmin);
            $rows[] = $line;
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private function fillSheet($sheet, $name, array $payload)
    {
        $sheet->setTitle($name);
        $headers = $payload['headers'];
        $rows = $payload['rows'];
        foreach ($headers as $col => $header) {
            $coord = Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($coord, $header);
            $sheet->getStyle($coord)->getFont()->setBold(true);
        }
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $col => $value) {
                $coord = Coordinate::stringFromColumnIndex($col + 1) . ($rowIndex + 2);
                $sheet->setCellValue($coord, $value);
                if (is_string($value) && strpos($value, "\n") !== false) {
                    $sheet->getStyle($coord)
                        ->getAlignment()
                        ->setWrapText(true)
                        ->setVertical(Alignment::VERTICAL_TOP);
                }
            }
        }
        foreach ($headers as $col => $header) {
            $width = 16;
            if (in_array($header, ['Contacto', 'Productos', 'Observaciones'], true)) {
                $width = 32;
            } elseif ($header === 'Code Supplier') {
                $width = 18;
            } elseif (mb_strlen($header) <= 8) {
                $width = 12;
            }
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col + 1))->setWidth($width);
        }
    }
}
