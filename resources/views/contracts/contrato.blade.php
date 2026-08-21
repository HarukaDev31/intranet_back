<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Acuerdo por Servicio de Carga Consolidada</title>
    <style>
    @page { size: A4; margin: 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; color:#222; font-size:15px; margin:0; }
    /* Removed left accent stripe */
         /* Header area (fixed) */
         /* Place header logo at the top-right corner (closer to edge) */
         .header { position:fixed; top:1mm; right:4mm; left:auto; height:26mm; display:flex; align-items:flex-start; justify-content:flex-end; padding:0; z-index:2 }
         /* Larger logo, positioned slightly outside to the right/top for the visual you provided */
         .header-logo { width:auto; max-height:40mm; margin-top:-1mm; transform: translateX(1mm) translateY(-10mm); }
            /* Use most of the page width: left/right margins are now controlled by @page
                Reserve header area with top margin so content starts below it. Make .page itself enforce breaks
                so we avoid scattered <div class="page-break"> elements which can produce blank pages in Dompdf. */
            .page { margin:20mm 8mm 0 8mm; padding:6px 6mm 10px 6mm; box-sizing:border-box; page-break-after:always; }
            /* Avoid adding a blank page after the last .page */
            .page:last-child { page-break-after: avoid; }
        h1 { font-size:18px; margin:6px 0 12px 0; text-align:center; }
        .meta { margin-bottom:10px; font-size:11px }
        .meta-table { width:100%; margin-bottom:10px; font-size:11px; border-collapse:collapse }
        .meta-table td { width:50%; padding:0 8px 0 0; vertical-align:top; box-sizing:border-box }
        .section { margin-bottom:0px; }
        .section h3 { margin:6px 0; font-size:13px }
        .bullets { margin-left:18px; }
        .small { font-size:11px; color:#222 }
        .bullet { margin:6px 0 }
        hr.sep { border:none; border-top:1px solid #eee; margin:12px 0 }
    .footer { position:fixed; bottom:12mm; left:10mm; right:10mm; font-size:10px; color:#666 }
        /* legacy page-break elements removed — .page now controls page breaks */
        /* .page-break { page-break-after: always; } // removed */
        /* Signature block alignment (horizontal layout compatible with Dompdf) */
     .signatures { display:flex; flex-direction:row; justify-content:space-between; align-items:flex-start; gap:20px; margin-top:18px; }
     /* Each signature box uses flexible width but stays side-by-side */
     .sig-box { display:block; flex:1 1 48%; max-width:48%; text-align:center; box-sizing:border-box; }
     /* Signature layout: use a single global dotted line across the whole table
         and make both signature containers the same fixed height so baselines match.
         Using explicit mm units reduces Dompdf rounding differences. */
    .signatures-wrap { position:relative; height:auto; }
    .signatures-table { width:100%; border-collapse:separate; border-spacing:12px 0; table-layout:fixed }
    .signatures-table td { vertical-align:bottom; position:relative; padding:0 10px; }
    /* Reserve space at the bottom of each signature cell for the printed name (we'll position names absolutely) */
    .sig-container { position:relative; height:auto; padding-bottom:0; box-sizing:border-box }
    /* The global dotted line spans full width and is positioned relative to .signatures-wrap.
       Use a repeating-linear-gradient to create spaced dots (gap visible). */
    /* SVG dotted line — more reliable in Dompdf than complex CSS backgrounds */
    .sig-line-svg { position:absolute; left:8mm; right:8mm; bottom:22mm; height:4mm; z-index:2; display:block }
    /* Fallback line using border-top in case SVG isn't rendered by Dompdf in some environments */
    .sig-line-fallback { position:absolute; left:8mm; right:8mm; bottom:22mm; height:0; border-top:1px dotted #000; z-index:2; }
    /* Printed names will be inline inside table cells (not absolutely positioned) */
    .sig-names { margin:0; padding:0; text-align:center }
    /* Patricia's signature placed absolutely inside .signatures-wrap and centered over the right column (~75%) */
    /* Signature image placed inside its column and centered there. Use absolute positioning
       relative to the .sig-container so it stays aligned with the printed name underneath. */
        /* Make the signature image centered within its table cell (no absolute positioning). */
        .firma { display:block; margin:0 auto; max-height:20mm; width:auto; z-index:5 }

        /* Table row heights and alignment: first row reserves space for the signature image;
           second row shows a dotted top border and contains the printed names. */
    /* Reduced vertical spacing so signature and names are closer */
    .signatures-table tr.sig-row-images td { height:12mm; vertical-align:bottom; padding-bottom:1mm }
    .signatures-table tr.sig-row-names td { border-top:1px dotted #000; padding-top:2px; vertical-align:top }
        /* Printed names inside the second row cells */
        .sig-names { margin:0; padding:0; text-align:center }
    </style>
</head>
<body>
    @php
        $filename = 'logo_contrato.png';
        $logoSrc = asset('storage/logo_icons/' . $filename); // default public URL fallback

        //If controller passed an absolute path, try it first
        if (!empty($logo_contrato_url)) {
            $p = $logo_contrato_url;
            $pNorm = str_replace('\\', DIRECTORY_SEPARATOR, $p);
            if (@file_exists($pNorm) && is_readable($pNorm)) {
                $ext = pathinfo($pNorm, PATHINFO_EXTENSION) ?: 'png';
                $data = @file_get_contents($pNorm);
                if ($data !== false && strlen($data) > 0) {
                    $logoSrc = 'data:image/' . $ext . ';base64,' . base64_encode($data);
                }
            }
        }

        if (strpos($logoSrc, 'data:') !== 0) {
            $p = public_path('storage/logo_icons/' . $filename);
            $pNorm = str_replace('\\', DIRECTORY_SEPARATOR, $p);
            if (@file_exists($pNorm) && is_readable($pNorm)) {
                $ext = pathinfo($pNorm, PATHINFO_EXTENSION) ?: 'png';
                $data = @file_get_contents($pNorm);
                if ($data !== false && strlen($data) > 0) {
                    $logoSrc = 'data:image/' . $ext . ';base64,' . base64_encode($data);
                }
            }
        }

        // base_path public/storage (alternative)
        if (strpos($logoSrc, 'data:') !== 0) {
            $p = base_path('public/storage/logo_icons/' . $filename);
            $pNorm = str_replace('\\', DIRECTORY_SEPARATOR, $p);
            if (@file_exists($pNorm) && is_readable($pNorm)) {
                $ext = pathinfo($pNorm, PATHINFO_EXTENSION) ?: 'png';
                $data = @file_get_contents($pNorm);
                if ($data !== false && strlen($data) > 0) {
                    $logoSrc = 'data:image/' . $ext . ';base64,' . base64_encode($data);
                }
            }
        }
    @endphp
    <div class="header">
        <img src="{{ $logoSrc }}" class="header-logo" alt="logo" onerror="this.style.display='none'" />
    </div>

    @include('contracts.partials.contrato_body', ['show_client_signature' => false])

</body>
</html>
