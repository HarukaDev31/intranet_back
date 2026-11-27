<?php

namespace Tests\Unit;

use App\Services\CargaConsolidada\GeminiService;
use App\Services\ChinaCaja\ChinaBlDraftReader;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ChinaBlDraftReaderTest extends TestCase
{
    /** @var array<int, string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $cells  coordenada => valor
     */
    private function excel(array $cells): UploadedFile
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        foreach ($cells as $coordinate => $value) {
            $sheet->setCellValue($coordinate, $value);
        }
        $path = tempnam(sys_get_temp_dir(), 'bl').'.xlsx';
        (new Xlsx($book))->save($path);
        $this->files[] = $path;

        return new UploadedFile($path, 'BL.xlsx', null, null, true);
    }

    private function gemini(?string $answer): GeminiService
    {
        return new class($answer) extends GeminiService {
            public int $calls = 0;

            public function __construct(private readonly ?string $answer)
            {
            }

            public function analyzeTextAsJson($prompt, $maxOutputTokens = 2048, $temperature = 0.2, $responseSchema = null)
            {
                $this->calls++;

                return ['success' => true, 'data' => ['bl_number' => $this->answer], 'error' => null, 'finish_reason' => null];
            }
        };
    }

    public function test_toma_el_numero_de_la_celda_debajo_de_el_rotulo()
    {
        $gemini = $this->gemini(null);
        $reader = new ChinaBlDraftReader($gemini);

        $number = $reader->blNumber($this->excel(['B4' => 'SHIPPER', 'I4' => 'B/L. NO.', 'I5' => '177FGKGKN1D329A']));

        $this->assertSame('177FGKGKN1D329A', $number);
        $this->assertSame(0, $gemini->calls, 'Con el rótulo a la vista no hace falta Gemini');
    }

    public function test_toma_el_numero_a_la_derecha_y_normaliza()
    {
        $reader = new ChinaBlDraftReader($this->gemini(null));

        $number = $reader->blNumber($this->excel(['A1' => 'B/L NO:', 'B1' => ' meduq 123456 ']));

        $this->assertSame('MEDUQ123456', $number);
    }

    public function test_no_confunde_un_texto_sin_digitos_con_el_numero()
    {
        $reader = new ChinaBlDraftReader($this->gemini(null));

        $this->assertNull($reader->blNumber($this->excel(['I4' => 'B/L. NO.', 'I5' => 'SHIPPER'])));
    }

    public function test_sin_rotulo_pregunta_a_gemini()
    {
        $gemini = $this->gemini('abc-12345');
        $reader = new ChinaBlDraftReader($gemini);

        $number = $reader->blNumber($this->excel(['A1' => 'Booking', 'B1' => 'ABC-12345']));

        $this->assertSame('ABC-12345', $number);
        $this->assertSame(1, $gemini->calls);
    }
}
