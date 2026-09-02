<?php

/**
 * Genera iconos PNG del rotulado (48x48, estilo sólido #111).
 * Ejecutar: php scripts/generate_rotulado_icons.php
 */

declare(strict_types=1);

$outDir = __DIR__ . '/../public/assets/templates/rotulado_icons';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "No se pudo crear $outDir\n");
    exit(1);
}

$size = 48;
$icons = array(
    'company' => 'drawCompany',
    'contact' => 'drawContact',
    'location' => 'drawLocation',
    'box' => 'drawBox',
    'qty' => 'drawQty',
    'weight' => 'drawWeight',
    'measure' => 'drawMeasure',
    'barcode' => 'drawBarcode',
);

foreach ($icons as $name => $drawFn) {
    $img = canvas($size);
    $drawFn($img, $size);
    $path = $outDir . '/' . $name . '.png';
    imagepng($img, $path);
    imagedestroy($img);
    echo $name . '.png (' . filesize($path) . " bytes)\n";
}

$peruFlag = base64_decode('iVBORw0KGgoAAAANSUhEUgAAADAAAAAgCAIAAADbtmxLAAAAXklEQVR4nM3OsRVAQADA0MgEzMC7/dfh2cEKet1V8usUWc51Z8Z47qn+2o6pXmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkRmIkxr8Hvl7pxARMaCeglQAAAABJRU5ErkJggg==');
if ($peruFlag !== false) {
    file_put_contents($outDir . '/peru_flag.png', $peruFlag);
    echo 'peru_flag.png (' . strlen($peruFlag) . " bytes)\n";
}

/**
 * @return resource
 */
function canvas(int $size)
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, color($img, 0, 0, 0, 127));

    return $img;
}

/**
 * @param resource $img
 */
function color($img, int $r, int $g, int $b, int $a = 0): int
{
    return imagecolorallocatealpha($img, $r, $g, $b, $a);
}

/**
 * @param resource $img
 */
function fill($img): int
{
    return color($img, 17, 17, 17);
}

/**
 * @param resource $img
 */
function drawCompany($img, int $size): void
{
    $c = fill($img);
    filledRect($img, $c, 10, 8, 28, 34);
    filledRect($img, color($img, 255, 255, 255), 14, 12, 6, 6);
    filledRect($img, color($img, 255, 255, 255), 22, 12, 6, 6);
    filledRect($img, color($img, 255, 255, 255), 30, 12, 6, 6);
    filledRect($img, color($img, 255, 255, 255), 14, 22, 6, 6);
    filledRect($img, color($img, 255, 255, 255), 22, 22, 6, 6);
    filledRect($img, color($img, 255, 255, 255), 30, 22, 6, 6);
    filledRect($img, $c, 8, 6, 32, 4);
    filledRect($img, $c, 22, 2, 4, 6);
}

/**
 * @param resource $img
 */
function drawContact($img, int $size): void
{
    $c = fill($img);
    imagefilledellipse($img, 24, 16, 12, 12, $c);
    filledRect($img, $c, 14, 24, 20, 14);
}

/**
 * @param resource $img
 */
function drawLocation($img, int $size): void
{
    $c = fill($img);
    imagefilledellipse($img, 24, 20, 16, 16, $c);
    imagefilledellipse($img, 24, 20, 7, 7, color($img, 255, 255, 255));
    imagefilledpolygon($img, array(24, 38, 16, 28, 32, 28), $c);
}

/**
 * @param resource $img
 */
function drawBox($img, int $size): void
{
    $c = fill($img);
    filledRect($img, $c, 10, 16, 28, 18);
    filledRect($img, color($img, 255, 255, 255), 14, 20, 20, 3);
    imagefilledpolygon($img, array(10, 16, 24, 8, 38, 16), $c);
}

/**
 * @param resource $img
 */
function drawQty($img, int $size): void
{
    $c = fill($img);
    filledRect($img, $c, 8, 22, 12, 12);
    filledRect($img, $c, 18, 14, 12, 12);
    filledRect($img, $c, 28, 22, 12, 12);
}

/**
 * @param resource $img
 */
function drawWeight($img, int $size): void
{
    $c = fill($img);
    filledRect($img, $c, 8, 28, 32, 4);
    filledRect($img, $c, 22, 10, 4, 18);
    filledRect($img, $c, 12, 10, 24, 4);
    filledRect($img, $c, 10, 14, 6, 10);
    filledRect($img, $c, 32, 14, 6, 10);
}

/**
 * @param resource $img
 */
function drawMeasure($img, int $size): void
{
    $c = fill($img);
    filledRect($img, $c, 8, 20, 32, 8);
    for ($x = 12; $x <= 36; $x += 4) {
        filledRect($img, $c, $x, 16, 2, 4);
    }
}

/**
 * @param resource $img
 */
function drawBarcode($img, int $size): void
{
    $c = fill($img);
    $widths = array(2, 1, 3, 1, 2, 1, 3, 1, 2);
    $x = 10;
    foreach ($widths as $w) {
        filledRect($img, $c, $x, 12, $w, 24);
        $x += $w + 2;
    }
}

/**
 * @param resource $img
 */
function filledRect($img, int $color, int $x, int $y, int $w, int $h): void
{
    imagefilledrectangle($img, $x, $y, $x + $w - 1, $y + $h - 1, $color);
}
