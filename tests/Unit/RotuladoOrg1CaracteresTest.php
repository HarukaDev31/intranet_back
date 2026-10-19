<?php

namespace Tests\Unit;

use App\Services\CargaConsolidada\RotuladoPdfService;
use Tests\TestCase;

class RotuladoOrg1CaracteresTest extends TestCase
{
    /**
     * Caracteres que en la etiqueta salían como recuadros cuando DomPDF
     * pedía negrita y caía a DejaVu.
     *
     * @return array<int, string>
     */
    private function frases()
    {
        return array(
            '秘鲁',
            '公司',
            '联系人',
            '目的国',
            '货号',
            '这里写产品货号（多款式写每款货号和数量）',
            '装箱数',
            '净重',
            '毛重',
            '箱子规格',
            '供应商编号',
            'PERÚ',
            'PROBUSINESS',
            'HECHO EN CHINA',
            'PRO MUNDO COMEX S.A.C',
            'MAYS19-1',
        );
    }

    public function test_el_pdf_de_org1_contiene_los_caracteres_de_la_etiqueta()
    {
        $this->assertTrue(
            RotuladoPdfService::fontInstalled(),
            'Falta public/assets/fonts/NotoSansSC-Regular.ttf'
        );

        $service = new RotuladoPdfService();
        $html = $service->buildHtml('MARIA YSABEL CUBA ORE', 'MAYS19-1', '20', array(
            'organizacion_id' => 1,
            'company' => 'PRO MUNDO COMEX S.A.C',
            'pais_destino' => 'PERU',
            'iso2' => 'pe',
        ));
        $pdf = $service->renderPdf($html);

        $this->assertStringContainsString('NotoSansSC', $pdf);
        $text = $this->textoDelPdf($pdf);
        foreach ($this->frases() as $frase) {
            $this->assertTrue(mb_strpos($text, $frase) !== false, 'Falta en el PDF: ' . $frase);
        }
    }

    /**
     * DomPDF escribe el texto como UTF-16BE dentro de literales del content stream.
     *
     * @param string $pdf
     * @return string
     */
    private function textoDelPdf($pdf)
    {
        $decoded = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams)) {
            foreach ($streams[1] as $raw) {
                $try = @gzuncompress($raw);
                if ($try === false) {
                    $try = @gzinflate($raw);
                }
                $decoded .= ($try === false ? $raw : $try);
            }
        }

        $text = '';
        $len = strlen($decoded);
        for ($i = 0; $i < $len; $i++) {
            if ($decoded[$i] !== '(') {
                continue;
            }
            $bin = '';
            $i++;
            while ($i < $len) {
                $c = $decoded[$i];
                if ($c === '\\') {
                    $i++;
                    if ($i >= $len) {
                        break;
                    }
                    $n = $decoded[$i];
                    if ($n >= '0' && $n <= '7') {
                        $oct = $n;
                        for ($k = 0; $k < 2 && ($i + 1) < $len && $decoded[$i + 1] >= '0' && $decoded[$i + 1] <= '7'; $k++) {
                            $i++;
                            $oct .= $decoded[$i];
                        }
                        $bin .= chr(octdec($oct));
                    } else {
                        $esc = array('n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C");
                        $bin .= isset($esc[$n]) ? $esc[$n] : $n;
                    }
                } elseif ($c === ')') {
                    break;
                } else {
                    $bin .= $c;
                }
                $i++;
            }
            if ($bin === '' || strlen($bin) > 400 || strlen($bin) % 2 !== 0) {
                continue;
            }
            $text .= mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE') . "\n";
        }

        return $text;
    }
}
