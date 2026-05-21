<?php

$path = __DIR__ . '/app/Http/Controllers/CargaConsolidada/CotizacionFinal/CotizacionFinalController.php';
$original = file_get_contents($path);

$candidates = [
    'Windows-1252',
    'ISO-8859-1',
];

$best = null;
$bestScore = PHP_INT_MAX;

foreach ($candidates as $enc) {
    $fixed = @mb_convert_encoding($original, 'UTF-8', $enc);
    if ($fixed === false || $fixed === '') {
        continue;
    }
    $score = substr_count($fixed, 'Ã')
        + substr_count($fixed, 'ðŸ')
        + substr_count($fixed, 'â˜')
        + substr_count($fixed, 'âœ')
        + substr_count($fixed, 'Ã©')
        + substr_count($fixed, 'Ã³');
    if ($score < $bestScore) {
        $bestScore = $score;
        $best = $fixed;
    }
}

if ($best === null) {
    fwrite(STDERR, "No fix applied\n");
    exit(1);
}

$before = substr_count($original, 'Ã');
$after = substr_count($best, 'Ã');

echo "Mojibake markers: before={$before} after={$after}\n";

if ($after >= $before) {
    fwrite(STDERR, "Fix did not improve file; aborting\n");
    exit(1);
}

// Sanity: must remain valid PHP
if (strpos($best, '<?php') !== 0 || strpos($best, 'class CotizacionFinalController') === false) {
    fwrite(STDERR, "Sanity check failed\n");
    exit(1);
}

file_put_contents($path, $best);
echo "File rewritten with UTF-8 fix.\n";
