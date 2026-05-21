<?php

/**
 * Quita BOM y corrige mojibake línea a línea (sin romper UTF-8 ya correcto).
 */
$path = dirname(__DIR__) . '/app/Http/Controllers/CargaConsolidada/CotizacionFinal/CotizacionFinalController.php';

$raw = file_get_contents($path);
if ($raw === false) {
    fwrite(STDERR, "Cannot read file\n");
    exit(1);
}

if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
    $raw = substr($raw, 3);
    echo "BOM removed\n";
}

function countBad(string $s): int
{
    return substr_count($s, 'Ã')
        + substr_count($s, 'ðŸ')
        + substr_count($s, 'â˜')
        + substr_count($s, 'âœ');
}

$before = countBad($raw);
$eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
$lines = explode($eol, $raw);
$changed = 0;

foreach ($lines as $i => $line) {
    if (strpos($line, 'Ã') === false) {
        continue;
    }

    $prev = $line;
    for ($pass = 0; $pass < 3; $pass++) {
        if (strpos($line, 'Ã') === false) {
            break;
        }
        $next = @mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
        if ($next === false || $next === '' || $next === $line) {
            break;
        }
        $line = $next;
    }

    if ($line !== $prev) {
        $lines[$i] = $line;
        $changed++;
    }
}

$fixed = implode($eol, $lines);

$emojiMap = [
    'ðŸ“¦' => '📦',
    'ðŸ“‹' => '📋',
    'ðŸ˜' => '😁',
    'ðŸ™‹â€â™‚ï¸' => '🙋‍♂️',
    'ðŸ™‹â€â™‚ï¸' => '🙋‍♂️',
    'â˜‘ï¸' => '☑️',
    'â˜‘ï¸' => '☑️',
    'âœ…' => '✅',
    'ðŸ’°' => '💰',
    'ðŸš¢' => '🚢',
];
$fixed = str_replace(array_keys($emojiMap), array_values($emojiMap), $fixed);

$after = countBad($fixed);

echo "Lines fixed (Ã): {$changed}\n";
echo "Bad markers before: {$before}\n";
echo "Bad markers after: {$after}\n";

if (strpos($fixed, '<?php') !== 0 || strpos($fixed, 'class CotizacionFinalController') === false) {
    fwrite(STDERR, "Sanity check failed\n");
    exit(1);
}

if ($after > $before) {
    fwrite(STDERR, "Fix increased bad markers; aborting\n");
    exit(1);
}

file_put_contents($path, $fixed);
echo "Saved UTF-8 without BOM\n";
