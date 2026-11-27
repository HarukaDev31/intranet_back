<?php

namespace App\Services\ChinaCaja;

use App\Services\CargaConsolidada\GeminiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee el N.° de BL del Excel del BL draft: el valor va en la celda de «B/L. NO.» (debajo del rótulo o a su derecha).
 * Si el rótulo no aparece tal cual, se lo pide al servicio Gemini del backend con el contenido de la hoja.
 */
class ChinaBlDraftReader
{
    private const LABEL = '/^B\s*\/?\s*L\.?\s*(NO|NUMBER|N[°º])\.?\s*[:：]?$/iu';

    /** Un N.° de BL tiene letras/dígitos (y a lo sumo - o /) y al menos un dígito. */
    private const NUMBER = '/^(?=.*\d)[A-Z0-9][A-Z0-9\-\/]{4,59}$/';

    private const SCAN_ROWS = 60;

    private const NEIGHBOURS = 3;

    public function __construct(private readonly GeminiService $gemini)
    {
    }

    public function blNumber(UploadedFile $file): ?string
    {
        try {
            $book = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            Log::warning('ChinaBlDraftReader: no se pudo abrir el Excel', ['error' => $e->getMessage()]);

            return null;
        }

        foreach ($book->getWorksheetIterator() as $sheet) {
            $number = $this->fromLabel($sheet);
            if ($number !== null) {
                return $number;
            }
        }

        return $this->fromGemini($book);
    }

    private function fromLabel(Worksheet $sheet): ?string
    {
        $maxRow = min($sheet->getHighestRow(), self::SCAN_ROWS);
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($row = 1; $row <= $maxRow; $row++) {
            for ($col = 1; $col <= $maxCol; $col++) {
                if (!preg_match(self::LABEL, $this->text($sheet, $col, $row))) {
                    continue;
                }
                $number = $this->firstNeighbour($sheet, $col, $row, 0, 1) ?? $this->firstNeighbour($sheet, $col, $row, 1, 0);
                if ($number !== null) {
                    return $number;
                }
            }
        }

        return null;
    }

    /** Primera celda con texto debajo (0, 1) o a la derecha (1, 0) del rótulo; las combinadas dejan vacías las siguientes. */
    private function firstNeighbour(Worksheet $sheet, int $col, int $row, int $stepCol, int $stepRow): ?string
    {
        for ($i = 1; $i <= self::NEIGHBOURS; $i++) {
            $text = $this->text($sheet, $col + $i * $stepCol, $row + $i * $stepRow);
            if ($text !== '') {
                return $this->clean($text);
            }
        }

        return null;
    }

    private function fromGemini(Spreadsheet $book): ?string
    {
        $content = $this->dump($book->getActiveSheet());
        if ($content === '') {
            return null;
        }
        try {
            $result = $this->gemini->analyzeTextAsJson(
                'Este es el contenido (celda: valor) de un BL draft en Excel. Devuelve bl_number: el número que figura en la celda '
                . '"B/L. NO." (o "BL No."), sin espacios. Si no aparece, null. No inventes datos.' . "\nContenido:\n" . $content,
                256,
                0.0,
                ['type' => 'OBJECT', 'properties' => ['bl_number' => ['type' => 'STRING', 'nullable' => true]]]
            );
        } catch (\Throwable $e) {
            Log::warning('ChinaBlDraftReader: falló Gemini', ['error' => $e->getMessage()]);

            return null;
        }

        return $result['success'] ? $this->clean((string) ($result['data']['bl_number'] ?? '')) : null;
    }

    private function dump(Worksheet $sheet): string
    {
        $lines = [];
        $maxRow = min($sheet->getHighestRow(), self::SCAN_ROWS);
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($row = 1; $row <= $maxRow; $row++) {
            for ($col = 1; $col <= $maxCol; $col++) {
                $text = $this->text($sheet, $col, $row);
                if ($text !== '') {
                    $lines[] = Coordinate::stringFromColumnIndex($col).$row.': '.mb_substr(str_replace("\n", ' ', $text), 0, 120);
                }
            }
        }

        return mb_substr(implode("\n", $lines), 0, 8000);
    }

    private function text(Worksheet $sheet, int $col, int $row): string
    {
        $coordinate = Coordinate::stringFromColumnIndex($col).$row;

        return $sheet->cellExists($coordinate) ? trim((string) $sheet->getCell($coordinate)->getCalculatedValue()) : '';
    }

    private function clean(string $value): ?string
    {
        $value = mb_strtoupper(preg_replace('/\s+/u', '', $value) ?? '');

        return preg_match(self::NUMBER, $value) ? $value : null;
    }
}
