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
        // We know the filename is exactly 'logo_contrato.png'. Check common locations sequentially and embed the first found file as base64 (Dompdf-friendly).
        $filename = 'logo_contrato.png';
        $logoSrc = asset('storage/' . $filename); // default public URL fallback

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

        // public/storage/logo_contrato.png
        if (strpos($logoSrc, 'data:') !== 0) {
            $p = public_path('storage/' . $filename);
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
            $p = base_path('public/storage/' . $filename);
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

    <!-- Page 1 -->
    <div class="page">
        <h1>ACUERDO POR SERVICIO DE CARGA CONSOLIDADA</h1>
        <div class="meta small"><strong>FECHA:</strong> {{ $fecha ?? date('d-m-Y') }}</div>

        <div class="section small">
            <p><strong>Partes:</strong> Este acuerdo se celebra entre:</p>
            <p><strong>PRO MUNDO COMEX SAC</strong>, con RUC 20612452432, con domicilio de oficina administrativa en Av. Nicolas de Arriola 314, piso 11 oficina #3, Santa Catalina, La Victoria, en adelante referido como <strong>"EL GESTOR"</strong>.</p>
            <p><strong>NOMBRES Y APELLIDOS / RAZÓN SOCIAL:</strong> {{ $cliente_nombre ?? 'EL CLIENTE' }}, con DNI {{ $cliente_documento ?? 'XXXXXXXX' }}, participante del <strong>CONSOLIDADO {{ $carga ?? '' }}</strong>, en adelante referido como <strong>"EL CLIENTE"</strong>.</p>
        </div>

        <div class="section">
            <h3>1. Objeto del Acuerdo:</h3>
            <p class="small">El GESTOR de Importación brindará el SERVICIO DE CARGA CONSOLIDADA, que consiste en gestionar la exportación de mercancías desde el momento en que la carga llegue a su almacén en Yiwu, China, hasta la descarga de la mercancía en el almacén de EL GESTOR en La Victoria, Lima, Perú.</p>
        </div>

        <div class="section">
            <h3>2. Servicios Incluidos: por parte del GESTOR</h3>
            <div class="bullets small">
                <p class="bullet">• Coordinación con el almacén en China para la recepción de la carga.</p>
                <p class="bullet">• Verificación aleatoria y superficial de la carga recepcionada (se envían fotos).</p>
                <p class="bullet">• Presentación de la documentación necesaria para los trámites de exportación desde China.</p>
                <p class="bullet">• Contratación y coordinación con el agente de carga o shipping (incluyen costos en origen y flete internacional).</p>
                <p class="bullet">• Contratación y coordinación con el agente de aduana en Perú (incluyen costos en destino y gestión aduanera).</p>
                <p class="bullet">• Gestión de los certificados de origen para los productos que lo requieran, previa evaluación de EL GESTOR.</p>
                <p class="bullet">• Presentación de la documentación necesaria para los trámites de importación en Perú.</p>
                <p class="bullet">•	Asistencia en los aforos físicos en caso de que la carga caiga en canal rojo.</p>
                <p class="bullet">•	Seguimiento de la Declaración Aduanera de Mercancías (DAM) durante el proceso de desaduanaje.</p>
                <p class="bullet">•	Coordinación de la logística interna (Almacén extraportuario – Almacén La Victoria) una vez se obtenga el levante de la DAM.</p>
            </div>
        </div>

        <div class="section">
            <h3>3. Obligaciones de las partes:</h3>
            <div class="small bullets">
                <p class="bullet"><strong>Obligaciones del gestor:</strong></p>
                <p class="bullet">• Brindar la proforma del servicio basada en la documentación preliminar que presente EL CLIENTE.</p>
                <p class="bullet">• Confirmar la recepción de la carga en cuanto llegue al almacén en Yiwu, China.</p>
                <p class="bullet">• Realizar las gestiones necesarias para el transporte internacional de la carga, así como para el proceso aduanal en Perú.</p>
                <p class="bullet">• Brindar una cotización final del servicio en base al volumen recibido y a la declaración realizada por EL GESTOR.</p>
                <p class="bullet">• Mantener a EL CLIENTE informado sobre el estado de los envíos a través de un grupo de comunicación creado con todos los participantes involucrados.</p>
            </div>
        </div>
    </div>

    <!-- Page 2 -->
    <div class="page">
        <div class="section">
            <div class="small bullets">
                <p class="bullet"><strong>Obligaciones del cliente:</strong></p>
                <p class="bullet">•	Informar su participación al GESTOR antes de enviar la carga al almacén. </p>
                <p class="bullet">•	Proporcionar de manera oportuna los datos del proveedor que solicita EL GESTOR.</p>
                <p class="bullet">•	Coordinar con su proveedor para asegurar que la carga llegue en la fecha acordada con EL GESTOR.</p>
                <p class="bullet">•	Revisar las imágenes proporcionadas por EL GESTOR y brindar aprobación del envío de su carga. (Una vez indicado el visto bueno, no podrá retractarse).</p>
                <p class="bullet">•	Proporcionar a EL GESTOR toda la documentación requerida para el proceso de nacionalización dentro de los plazos establecidos.</p>
                <p class="bullet">•	Pagar los costos acordados por los servicios prestados en las fechas estipuladas por EL GESTOR.</p>
            </div>
            <h3>4. Costos y pagos</h3>
            <div class="small bullets">
                <p class="bullet">• EL CLIENTE se compromete a pagar a EL GESTOR el importe especificado en la cotización final por los servicios prestados que incluye (Servicio de Importación y Tributos).</p>
                <p class="bullet">• El plazo máximo para realizar el pago será de 5 días antes de la llegada de la carga al puerto del Callao.</p>
                <p class="bullet">• El pago se efectuará en dólares estadounidenses a través de transferencia o depósito bancario en las cuentas designadas por EL GESTOR.</p>
                <p class="bullet">•	Asimismo, EL CLIENTE asumirá cualquier costo adicional que pudiera surgir durante el proceso de importación (mayor cubicaje, boletines químicos, variación en impuestos, cambios de partidas, ajustes de valor aplicado por aduana).</p>
                <p class="bullet">•	Si el cliente no realizar el pago total del servicio prestado establecidos en la cotización final y costos relacionados a su importación, no podrá retirar su mercadería.</p>
            </div>
        </div>

        <div class="section">
            <h3>5. Duración del acuerdo</h3>
            <p class="small">Este acuerdo iniciará desde el momento en que EL GESTOR reciba la carga en su almacén en Yiwu, hasta la entrega de la carga en nuestro almacén ubicado en Av. Nicolás de Arriola 2000, La Victoria, Lima, Perú.</p>
        </div>

        <div class="section">
            <h3>6. Confidencialidad</h3>
            <p class="small">Ambas partes se comprometen a mantener la confidencialidad de la información relacionada con el proceso de importación. Esta información solo será divulgada a la persona que inició la negociación con EL GESTOR. Asimismo, EL GESTOR no estará obligado a compartir ninguna información con terceros.</p>
            <p class="small">El GESTOR no debe compartir información sobre el proveedor, modelos, precios y demás datos relacionados a la importación de EL CLIENTE a empresas terceras. </p>
        </div>

        <div class="section">
            <h3>7. Términos y Condiciones Generales del Gestor</h3>
            <div class="small bullets">
                <p class="bullet">• Tipo de Cambio: El tipo de cambio de las cotizaciones brindadas es referencial y puede estar sujeto a variaciones.</p>
                <p class="bullet">• Emisión de Comprobante: La factura o boleta se emitirá en base al valor CIF de la cotización final.</p>
                <p class="bullet">• Documentación de importación: Si EL CLIENTE no entrega los documentos reales en el tiempo acordado, asumirá recargos, multas o sanciones aduaneras.</p>
                <p class="bullet">• Declaración de Mercancías: Las mercancías serán declaradas ante aduanas de Perú con el nombre de Pro Business (Pro Mundo Comex S.A.C.).</p>
            </div>
        </div>
    </div>

    <!-- Page 3 -->
    <div class="page">
        <div class="section small bullets">
            <p class="bullet">• Estado de Mercadería: EL GESTOR no será responsable ni de la calidad ni de las unidades faltantes que el proveedor entregue en el almacén de China; esa responsabilidad recae en el proveedor.</p>
            <p class="bullet">• Empaque y embalaje: El proveedor de EL CLIENTE deberá colocar el correcto embalaje y pictograma de acuerdo a la naturaleza del producto para un correcto manipulado del EL GESTOR.</p>
            <p class="bullet">• Mermas de importación: EL CLIENTE reconoce y acepta que, debido a la naturaleza del transporte internacional y operaciones, pueden presentarse daños leves o deformaciones; estos incidentes son considerados efectos normales del proceso logístico internacional.</p>
            <p class="bullet">• Plazo para Reclamos: EL CLIENTE tiene un plazo de 24 horas después de la entrega en Perú para presentar cualquier tipo de reclamo.</p>
            <p class="bullet">• Verificación de Marcas: Todas las marcas deben ser verificadas por EL CLIENTE; no se aceptarán marcas patentadas en Indecopi sin medidas de frontera.</p>
            <p class="bullet">• Productos Restringidos: Solo se aceptarán productos restringidos bajo previa evaluación y coordinación con EL GESTOR.</p>
            <p class="bullet">• No Hay Opción de Reembolso: EL CLIENTE reconoce que no existe opción de reembolso por los servicios prestados o mercadería adquirida.</p>
            <p class="bullet">• Contratación de operadores de comercio exterior: EL GESTOR podrá seleccionar operadores sin autorización previa del CLIENTE para garantizar importación exitosa.</p>
            <p class="bullet">• Tiempo de entrega: Las fechas de entrega son estimadas y sujetas a variaciones por factores externos (procesos aduaneros, condiciones climáticas, etc.).</p>
        </div>

        <div class="section">
            <h3>8. Servicios Adicionales y recargos</h3>
            <div class="small bullets">
                <p class="bullet">• Pago a Proveedor: Si EL CLIENTE solicita que EL GESTOR realice el pago al proveedor, deberá informar previamente y asumir comisiones bancarias y costos de gestión.</p>
                <p class="bullet">• Inspección de la Mercadería: Si EL CLIENTE solicita una inspección detallada, se cotizará un costo adicional según dimensiones y cantidad.</p>
                <p class="bullet">• Cargas Grandes y Manipuleo: Para cargas superiores a 100 kg por bulto o en pallets se aplicará un costo adicional por montaje.</p>
                <p class="bullet">• Envíos a Provincia: Los envíos a provincias se realizarán a través de la agencia Marvisur u otra agencia según cobertura.</p>
                <p class="bullet">El costo base para enviar la carga desde el almacén del GESTOR a la agencia de transporte es de S/.30, esto está condicionado a la cantidad de cajas, peso y CBM de EL CLIENTE; el envío se hará previa cancelación del flete interno.</p>
            </div>
        </div>
        <div class="section">
            <h3>9. Penalidades</h3>
            <div class="small bullets">
                <p class="bullet">• Retrasos en la Carga y Costos de Almacenaje: Se cobrará un cargo de almacenamiento de S/.30 si la carga no llega en el rango de fechas indicado.</p>
                <p class="bullet">• Retrasos en entrega de documentos: Si EL CLIENTE no entrega documentación en tiempo, se aplicará sanción administrativa de $50.00.</p>
                <p class="bullet">• Recargos por Pagos Fuera de Plazo: Recargo de S/.10 por día de retraso.</p>
                <p class="bullet">• Almacenamiento por No Retiro: Si EL CLIENTE no retira en plazo, se cobrará S/.20 por día adicional.</p>
            </div>
        </div>
    </div>

    <!-- Page 4 -->
    <div class="page">
        <div class="section">
            <h3>10. Declaración de Buena Fe y Veracidad de la Información</h3>
            <div class="small bullets">
                <p class="bullet">• EL CLIENTE declara que toda la información y documentación proporcionada es veraz y completa.</p>
                <p class="bullet">• EL GESTOR actuará bajo el principio de buena fe contractual.</p>
            </div>
        </div>
        <div class="section">
            <h3>11. Cláusula Anticorrupción</h3>
            <p class="small">• Ambas partes declaran que rechazan todo acto de corrupción, soborno, colusión u otros actos contrarios a la legalidad vigente. EL CLIENTE se obliga a que sus proveedores, agentes o representantes no incurran en prácticas de corrupción relacionadas directa o indirectamente con el servicio prestado por EL GESTOR. Cualquier incumplimiento de esta cláusula facultará a EL GESTOR a resolver el contrato de manera inmediata, sin perjuicio de las acciones legales correspondientes.</p>
        </div>
        <div class="section">
            <h3>12. Limitación de Responsabilidad sobre la Mercadería</h3>
            <p class="small">• EL GESTOR actúa exclusivamente como intermediario logístico en el proceso de importación, y no tiene responsabilidad alguna respecto a la calidad, seguridad, funcionamiento, legalidad o estado físico de los productos que EL CLIENTE adquiere del proveedor. Cualquier daño, defecto, infracción de derechos de propiedad intelectual, responsabilidad por productos o perjuicio derivado de dichos bienes será de exclusiva responsabilidad del proveedor y/o de EL CLIENTE.</p>
            <p class="small">• Transcurridas 48 horas desde la entrega de la carga sin reclamo documentado, se entenderá que la carga fue recibida conforme, extinguiéndose cualquier responsabilidad posterior del GESTOR.</p>
        </div>
        <div class="section">
            <h3>13. Exoneración de Responsabilidad por Información Errónea</h3>
            <p class="small">• EL GESTOR no será responsable por retrasos, sanciones, observaciones aduaneras, decomisos u otros perjuicios que resulten de errores, omisiones o falsedades en la documentación o declaraciones brindadas por EL CLIENTE, incluyendo la incorrecta determinación del valor de la mercancía, su naturaleza, origen o clasificación arancelaria.</p>
        </div>

        <div class="section">
            <h3>14. Propiedad intelectual</h3>
            <p class="small">• Las partes reconocen que los logos, marcas, manuales, diseños, contenidos, software, y demás elementos entregados o utilizados en ejecución del presente contrato son propiedad exclusiva de sus titulares. Ninguna de las partes adquiere, por este contrato, derecho alguno sobre la propiedad intelectual de la otra parte o de terceros.</p>
        </div>
        <div class="section">
            <h3>15. Protección de datos personales</h3>
            <p class="small">• EL CLIENTE autoriza expresamente a EL GESTOR al tratamiento de los datos personales entregados para la adecuada ejecución del presente contrato, conforme a la Ley N.º 29733 - Ley de Protección de Datos Personales y su Reglamento. La empresa garantiza la confidencialidad, seguridad y uso limitado de los datos personales a los fines contractuales.</p>
        </div>
    </div>

    <!-- Page 5 -->
    <div class="page">
        <div class="section">
            <h3>16. Fuerza mayor</h3>
            <p class="small">• Ninguna de las partes será responsable por el incumplimiento de sus obligaciones contractuales si este se debe a causas de fuerza mayor o caso fortuito, tales como desastres naturales, actos de autoridad, pandemias, conflictos armados, entre otros.</p>
        </div>

        <div class="section">
            <h3>17. Resolución del contrato por incumplimiento grave</h3>
            <p class="small">• Cualquiera de las partes podrá resolver el contrato en caso de incumplimiento grave de la otra parte, previa notificación escrita y sin que se requiera pronunciamiento judicial, siempre que no se subsane dicho incumplimiento dentro del plazo de quince (15) días calendario.</p>
        </div>
        <div class="section">
            <h3>18. Solución de controversias</h3>
            <p class="small">• Las partes acuerdan que cualquier controversia surgida con ocasión del presente contrato será resuelta mediante arbitraje de derecho administrado por el Centro de Arbitraje de la Cámara de Comercio de Lima. El laudo será definitivo e inapelable.</p>
            <p class="small">• En caso de discrepancia entre este contrato y cualquier otro documento, prevalecerá el presente acuerdo. Las comunicaciones electrónicas complementan, pero no sustituyen lo pactado en este contrato.</p>
        </div>
        @php
            // Try to load Patricia's signature image from public/storage and embed as base64 for Dompdf
            $firmaPatSrc = null;
            $firmaPath = public_path('storage/firma_patricia.png');
            $firmaPathNorm = str_replace('\\', DIRECTORY_SEPARATOR, $firmaPath);
            if (@file_exists($firmaPathNorm) && is_readable($firmaPathNorm)) {
                $ext = pathinfo($firmaPathNorm, PATHINFO_EXTENSION) ?: 'png';
                $data = @file_get_contents($firmaPathNorm);
                if ($data !== false && strlen($data) > 0) {
                    $firmaPatSrc = 'data:image/' . $ext . ';base64,' . base64_encode($data);
                }
            }
        @endphp

        <div class="signatures-wrap" style="margin-top:36px;">
                <table class="signatures-table" style="width:100%; margin-top:6mm">
                    <tr class="sig-row-images">
                        <td style="width:50%; text-align:center;">
                            <div class="sig-container">
                                {{-- left side: no signature image for client by default (keeps space) --}}
                            </div>
                        </td>
                        <td style="width:50%; text-align:center;">
                            <div class="sig-container">
                                {{-- Patricia signature image centered in this cell --}}
                                @if(!empty($firmaPatSrc))
                                    <img src="{{ $firmaPatSrc }}" alt="firma" class="firma" />
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="sig-row-names">
                        <td style="width:50%; text-align:center;">
                            <div class="sig-names">{{ $cliente_nombre ?? 'NOMBRE APELLIDOS' }}<br>{{ $cliente_documento ?? 'DNI' }}<br><strong>EL CLIENTE</strong></div>
                        </td>
                        <td style="width:50%; text-align:center;">
                            <div class="sig-names">PATRICIA ALBAN HIDALGO<br>PRO MUNDO COMEX S.A.C.<br>20612452432<br>(Gerente General)<br><strong>EL GESTOR</strong></div>
                        </td>
                    </tr>
                </table>
        </div>
    </div>

</body>
</html>