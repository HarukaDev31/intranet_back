{{-- Cuerpo compartido del contrato (páginas 1-5 + bloque firmas labels). --}}
@php
    $esRuc = !empty($es_ruc);
    $razon = $cliente_razon_social ?? ($cliente_nombre ?? 'EL CLIENTE');
    $ruc = $cliente_ruc ?? ($cliente_documento ?? '');
    $domFiscal = $cliente_domicilio_fiscal ?? '';
    $coordNombre = $coordinador_operativo_nombre ?? '';
    $coordDni = $coordinador_operativo_dni ?? '';
@endphp

<!-- Page 1 -->
<div class="page">
    <h1>ACUERDO POR SERVICIO DE CARGA CONSOLIDADA</h1>
    <table class="meta-table small">
        <tr>
            <td><strong>FECHA:</strong> {{ $fecha ?? date('d-m-Y') }}</td>
            <td style="white-space: nowrap;"><strong>CONTRATO:</strong> {{ $cod_contract ?? '' }}{!! !empty($cod_contract_calculator) ? ' &nbsp;|&nbsp; <strong>Cotización:</strong> ' . e($cod_contract_calculator) : '' !!}</td>
        </tr>
    </table>
    <div class="section small">
        <p><strong>Partes:</strong> Este acuerdo se celebra entre:</p>
        <p><strong>PRO MUNDO COMEX SAC</strong>, con RUC 20612452432, con domicilio de oficina administrativa en Av. Nicolas de Arriola 314, piso 11 oficina #3, Santa Catalina, La Victoria, en adelante referido como <strong>"EL GESTOR"</strong>.</p>
        @if($esRuc)
            <p><strong>{{ $razon }}</strong>, con RUC {{ $ruc }}@if(!empty($domFiscal)), con domicilio fiscal en {{ $domFiscal }}@endif, participante del <strong>CONSOLIDADO {{ $carga ?? '' }}</strong>, en adelante referido como <strong>"EL CLIENTE"</strong>@if(!empty($coordNombre)); quien autoriza a <strong>{{ $coordNombre }}</strong>@if(!empty($coordDni)), con DNI {{ $coordDni }}@endif, para actuar como su Coordinador Operativo ante EL GESTOR durante la ejecución del presente servicio, con facultades para tomar decisiones sobre el proceso de importación, siendo sus actuaciones dentro de este ámbito vinculantes para EL CLIENTE@endif.</p>
        @else
            <p><strong>NOMBRES Y APELLIDOS / RAZÓN SOCIAL:</strong> {{ $cliente_nombre ?? 'EL CLIENTE' }}, con DNI {{ $cliente_documento ?? 'XXXXXXXX' }}, participante del <strong>CONSOLIDADO {{ $carga ?? '' }}</strong>, en adelante referido como <strong>"EL CLIENTE"</strong>.</p>
        @endif
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
            <p class="bullet">• Asistencia en los aforos físicos en caso de que la carga caiga en canal rojo.</p>
            <p class="bullet">• Seguimiento de la Declaración Aduanera de Mercancías (DAM) durante el proceso de desaduanaje.</p>
            <p class="bullet">• Coordinación de la logística interna (Almacén extraportuario – Almacén La Victoria) una vez se obtenga el levante de la DAM.</p>
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
            <p class="bullet">• Informar su participación al GESTOR antes de enviar la carga al almacén.</p>
            <p class="bullet">• Proporcionar de manera oportuna los datos del proveedor que solicita EL GESTOR.</p>
            <p class="bullet">• Coordinar con su proveedor para asegurar que la carga llegue en la fecha acordada con EL GESTOR.</p>
            <p class="bullet">• Revisar las imágenes proporcionadas por EL GESTOR y brindar aprobación del envío de su carga. (Una vez indicado el visto bueno, no podrá retractarse).</p>
            <p class="bullet">• Proporcionar a EL GESTOR toda la documentación requerida para el proceso de nacionalización dentro de los plazos establecidos.</p>
            <p class="bullet">• Pagar los costos acordados por los servicios prestados en las fechas estipuladas por EL GESTOR.</p>
        </div>
        <h3>4. Costos y pagos</h3>
        <div class="small bullets">
            <p class="bullet">• EL CLIENTE se compromete a pagar a EL GESTOR el importe correspondiente a la cotización final por los servicios prestados en dos momentos:</p>
            <p class="bullet">• <strong>Primer pago:</strong> por el servicio de importación, según la cotización preliminar entregada y aceptada por EL CLIENTE, teniendo como fecha de pago el día en que la carga llega al almacén en Yiwu. EL CLIENTE puede solicitar, de manera excepcional y por única vez, una prórroga sujeta a aprobación expresa de EL GESTOR, hasta la fecha de corte del consolidado en el que participa.</p>
            <p class="bullet">• <strong>Segundo pago:</strong> por el saldo resultante de restar el primer pago del monto total de la cotización final (incluyendo impuestos, cargos adicionales y cualquier otra diferencia pendiente), teniendo como fecha máxima de pago el día en que la carga llega al puerto del Callao. EL CLIENTE puede solicitar, de manera excepcional y por única vez, una prórroga sujeta a aprobación expresa de EL GESTOR, hasta la fecha de entrega de la carga.</p>
            <p class="bullet">• Las prórrogas son de carácter excepcional y se otorgan por única vez para cada uno de los pagos señalados, debiendo EL CLIENTE solicitarla antes del vencimiento de la fecha de pago regular. Cabe resaltar que EL GESTOR no está obligado a conceder solicitudes adicionales sobre el mismo pago.</p>
            <p class="bullet">• El pago se efectuará en dólares estadounidenses a través de transferencia o depósito bancario en las cuentas designadas por EL GESTOR.</p>
            <p class="bullet">• Asimismo, EL CLIENTE asumirá cualquier costo adicional que pudiera surgir durante el proceso de importación (mayor cubicaje, boletines químicos, variación en impuestos, cambios de partidas, ajustes de valor aplicado por aduana).</p>
            <p class="bullet">• Si el cliente no realizar el pago total del servicio prestado establecidos en la cotización final y costos relacionados a su importación, no podrá retirar su mercadería.</p>
        </div>
    </div>

    <div class="section">
        <h3>5. Criterios de Prioridad de Embarque</h3>
        <div class="small bullets">
            <p class="bullet">• EL GESTOR coordinará el llenado del contenedor en origen conforme al siguiente orden de prioridad:</p>
            <p class="bullet">• en primer lugar, los clientes que hayan efectuado el pago del CBM preliminar y cuya carga haya llegado dentro de la fecha de corte;</p>
            <p class="bullet">• en segundo lugar, los clientes que hayan efectuado el pago del CBM preliminar y cuya carga haya llegado fuera de la fecha de corte. En ambos casos, la prioridad dentro de cada grupo se determinará según el orden de llegada de la carga al almacén en Yiwu, China.</p>
            <p class="bullet">• Los clientes que no hayan efectuado el pago del CBM preliminar no tendrán prioridad de embarque en el consolidado correspondiente, y solo serán embarcados según disponibilidad de espacio.</p>
            <p class="bullet">• La carga que no logre embarcarse por falta de espacio será trasladada al siguiente consolidado disponible, previa notificación y aceptación de EL CLIENTE.</p>
        </div>
    </div>
</div>

<!-- Page 3 -->
<div class="page">
    <div class="section">
        <h3>6. Duración del acuerdo</h3>
        <p class="small">Este acuerdo iniciará desde el momento en que EL GESTOR reciba la carga en su almacén en Yiwu, hasta la entrega de la carga en nuestro almacén ubicado en Av. Nicolás de Arriola 2000, La Victoria, Lima, Perú.</p>
    </div>

    <div class="section">
        <h3>7. Confidencialidad</h3>
        <p class="small">Ambas partes se comprometen a mantener la confidencialidad de la información relacionada con el proceso de importación, incluyendo información comercial, operativa, documental, precios, proveedores, datos personales y demás información que no sea de carácter público.</p>
        <p class="small">EL GESTOR comunicará la información relacionada con el servicio al CLIENTE a través de la persona que inició la negociación con EL GESTOR. Asimismo, EL GESTOR no estará obligado a compartir ninguna información con terceros.</p>
        <p class="small">EL GESTOR podrá utilizar fotografías y videos generales obtenidos durante la prestación de sus servicios para fines institucionales, comerciales y publicitarios, incluyendo redes sociales y otros medios de comunicación, siempre que estos no revelen información confidencial o sensible, como datos del proveedor, precios, documentos, datos personales u otra información que permita conocer aspectos específicos de su operación.</p>
        <p class="small">En caso de que el material audiovisual permita identificar directamente al CLIENTE o contenga información específica o sensible de su mercancía o importación, EL GESTOR solicitará previamente su autorización para su publicación o difusión.</p>
    </div>

    <div class="section">
        <h3>8. Términos y Condiciones Generales del Gestor</h3>
        <div class="small bullets">
            <p class="bullet">• Tipo de Cambio: El tipo de cambio de las cotizaciones brindadas es referencial y puede estar sujeto a variaciones.</p>
            <p class="bullet">• Emisión de Comprobante: La factura o boleta se emitirá sobre la base del valor de la carga y del servicio de importación. El comprobante será remitido a EL CLIENTE de forma electrónica al WhatsApp registrado, siendo EL CLIENTE responsable de revisarlo y conservarlo para sus fines.</p>
            <p class="bullet">• Documentación de importación: Si EL CLIENTE no entrega los documentos reales en el tiempo acordado, asumirá recargos, multas o sanciones aduaneras.</p>
            <p class="bullet">• Declaración de Mercancías: Las mercancías serán declaradas ante aduanas de Perú con el nombre de Pro Business (Pro Mundo Comex S.A.C.).</p>
            <p class="bullet">• Estado de Mercadería: EL GESTOR no será responsable ni de la calidad ni de las unidades faltantes que el proveedor entregue en el almacén de China; esa responsabilidad recae en el proveedor.</p>
            <p class="bullet">• Empaque y embalaje: El proveedor de EL CLIENTE deberá colocar el correcto embalaje y pictograma de acuerdo a la naturaleza del producto para un correcto manipulado del EL GESTOR.</p>
            <p class="bullet">• Mermas de importación: EL CLIENTE reconoce y acepta que, debido a la naturaleza del transporte internacional y operaciones, pueden presentarse daños leves o deformaciones; estos incidentes son considerados efectos normales del proceso logístico internacional.</p>
        </div>
    </div>
</div>

<!-- Page 4 -->
<div class="page">
    <div class="section small bullets">
        <p class="bullet">• Plazo para Reclamos: EL CLIENTE tiene un plazo de 24 horas después de la entrega en Perú para presentar cualquier tipo de reclamo.</p>
        <p class="bullet">• Verificación de Marcas: Todas las marcas deben ser verificadas por EL CLIENTE; no se aceptarán marcas registradas en Indecopi o con medidas de frontera. En caso de que Aduanas o EL GESTOR detecten productos que infrinjan derechos de propiedad intelectual (marcas, patentes u otros derechos registrados ante Indecopi), EL CLIENTE asumirá íntegramente los gastos de almacenaje, multas, sanciones administrativas y demás costos que se generen, incluidos los que EL GESTOR deba afrontar para resolver dicha infracción.</p>
        <p class="bullet">• Productos Restringidos: Solo se aceptarán productos restringidos bajo previa evaluación y coordinación con EL GESTOR.</p>
        <p class="bullet">• No Hay Opción de Reembolso: EL CLIENTE reconoce que no existe opción de reembolso por los servicios prestados o mercadería adquirida.</p>
        <p class="bullet">• Contratación de operadores de comercio exterior: EL GESTOR podrá seleccionar operadores sin autorización previa del CLIENTE para garantizar importación exitosa.</p>
        <p class="bullet">• Tiempo de entrega: Las fechas de entrega son estimadas y sujetas a variaciones por factores externos (procesos aduaneros, condiciones climáticas, etc.).</p>
        <p class="bullet">• El traslado de la mercancía del CLIENTE a otro contenedor está sujeto a las condiciones operativas del servicio (llega de carga fuera de tiempo, falta de pago, mal rotulado, falta de documentos, etc).</p>
    </div>

    <div class="section">
        <h3>9. Servicios Adicionales y recargos</h3>
        <div class="small bullets">
            <p class="bullet">• Pago a Proveedor: Si EL CLIENTE solicita que EL GESTOR realice el pago al proveedor, deberá informar previamente y asumir comisiones bancarias y costos de gestión.</p>
            <p class="bullet">• Inspección de la Mercadería: Si EL CLIENTE solicita una inspección detallada, se cotizará un costo adicional según dimensiones y cantidad.</p>
            <p class="bullet">• Cargas Grandes y Manipuleo: Para cargas superiores a 100 kg por bulto o en pallets se aplicará un costo adicional por montaje (uso de montacarga).</p>
            <p class="bullet">• Envíos a Provincia: Los envíos a provincias se realizarán a través de la agencia Marvisur u otra agencia de transporte que se encuentre dentro de la cobertura de EL GESTOR según condiciones operativas. EL CLIENTE deberá consultar y confirmar previamente dicha cobertura con EL GESTOR.</p>
            <p class="bullet">• El costo base por el traslado de la mercancía desde el almacén del GESTOR hasta la agencia de transporte será desde $15, esto está condicionado a la cantidad de cajas, peso y CBM de EL CLIENTE; el envío se hará previa cancelación del flete interno.</p>
        </div>
    </div>

    <div class="section">
        <h3>10. Penalidades</h3>
        <div class="small bullets">
            <p class="bullet">• Retrasos en la Carga y Costos de Almacenaje: Si EL CLIENTE notifica a EL GESTOR, con al menos tres (3) días hábiles de anticipación a la fecha de corte, que su carga no llegará a tiempo al almacén de Yiwu, esta se trasladará al siguiente consolidado sin costo adicional. De lo contrario, se cobrará un cargo de almacenamiento de $30. En caso de reincidencia, es decir, si EL CLIENTE incurre en este mismo supuesto en más de una oportunidad, perderá también la prioridad de embarque señalada en la Cláusula 5 en los siguientes consolidados en los que participe.</p>
            <p class="bullet">• Retrasos en entrega de documentos: Si EL CLIENTE no entrega documentación en tiempo, se aplicará sanción administrativa de $50.00.</p>
            <p class="bullet">• Recargos por Pagos Fuera de Plazo: Recargo de $3 por día de retraso.</p>
            <p class="bullet">• Almacenamiento por No Retiro: Si EL CLIENTE no retira su mercancía dentro del plazo comunicado por EL GESTOR, se generará un cargo de $ 6 por día adicional de permanencia.</p>
        </div>
    </div>
</div>

<!-- Page 5 -->
<div class="page">
    <div class="section">
        <h3>11. Procedimiento de Abandono Convencional de Mercancía</h3>
        <div class="small bullets">
            <p class="bullet">• Esta cláusula aplica exclusivamente a la mercadería nacionalizada (con levante de la DAM) que se encuentra bajo custodia de EL GESTOR en su almacén privado.</p>
            <p class="bullet">• Si EL CLIENTE mantiene una deuda pendiente y/o no retira su mercadería, EL GESTOR notificará dicha situación mediante al menos tres (3) intentos de comunicación por WhatsApp, correo electrónico y/o llamada telefónica a los datos de contacto consignados en este contrato, dejando constancia de fecha, hora y medio empleado.</p>
            <p class="bullet">• Transcurridos treinta (30) días calendario desde la primera notificación, sin pago, retiro ni respuesta de EL CLIENTE, EL GESTOR remitirá un aviso final otorgando cinco (5) días calendario adicionales. Vencido dicho plazo, EL CLIENTE otorga a EL GESTOR mandato expreso e irrevocable para considerar la mercadería en abandono convencional y venderla extrajudicialmente, procurando obtener el mejor valor razonable de mercado.</p>
            <p class="bullet">• El producto de la venta se aplicará a la deuda, penalidades y gastos de almacenaje y venta; el saldo remanente, de existir, será devuelto a EL CLIENTE. Si el producto no cubre la deuda, EL GESTOR conserva el derecho de reclamar el saldo por las vías legales correspondientes. Este procedimiento no exime a EL CLIENTE del pago de penalidades u otros costos pendientes.</p>
        </div>
    </div>

    <div class="section">
        <h3>12. Declaración de Buena Fe y Veracidad de la Información</h3>
        <div class="small bullets">
            <p class="bullet">• EL CLIENTE declara que toda la información y documentación proporcionada es veraz y completa.</p>
            <p class="bullet">• EL GESTOR actuará bajo el principio de buena fe contractual.</p>
        </div>
    </div>

    <div class="section">
        <h3>13. Cláusula Anticorrupción</h3>
        <p class="small">• Ambas partes declaran que rechazan todo acto de corrupción, soborno, colusión u otros actos contrarios a la legalidad vigente. EL CLIENTE se obliga a que sus proveedores, agentes o representantes no incurran en prácticas de corrupción relacionadas directa o indirectamente con el servicio prestado por EL GESTOR. Cualquier incumplimiento de esta cláusula facultará a EL GESTOR a resolver el contrato de manera inmediata, sin perjuicio de las acciones legales correspondientes.</p>
    </div>

    <div class="section">
        <h3>14. Limitación de Responsabilidad sobre la Mercadería</h3>
        <p class="small">• EL GESTOR actúa exclusivamente como intermediario logístico en el proceso de importación, y no tiene responsabilidad alguna respecto a la calidad, seguridad, funcionamiento, legalidad o estado físico de los productos que EL CLIENTE adquiere del proveedor. Cualquier daño, defecto, infracción de derechos de propiedad intelectual, responsabilidad por productos o perjuicio derivado de dichos bienes será de exclusiva responsabilidad del proveedor y/o de EL CLIENTE.</p>
        <p class="small">• Transcurridas 48 horas desde la entrega de la carga sin reclamo documentado, se entenderá que la carga fue recibida conforme, extinguiéndose cualquier responsabilidad posterior del GESTOR.</p>
    </div>

    <div class="section">
        <h3>15. Exoneración de Responsabilidad por Información Errónea</h3>
        <p class="small">• EL GESTOR no será responsable por retrasos, sanciones, observaciones aduaneras, decomisos u otros perjuicios que resulten de errores, omisiones o falsedades en la documentación o declaraciones brindadas por EL CLIENTE, incluyendo la incorrecta determinación del valor de la mercancía, su naturaleza, origen o clasificación arancelaria.</p>
    </div>
</div>

<!-- Page 6 -->
<div class="page">
    <div class="section">
        <h3>16. Propiedad intelectual</h3>
        <p class="small">• Las partes reconocen que los logos, marcas, manuales, diseños, contenidos, software, y demás elementos entregados o utilizados en ejecución del presente contrato son propiedad exclusiva de sus titulares. Ninguna de las partes adquiere, por este contrato, derecho alguno sobre la propiedad intelectual de la otra parte o de terceros.</p>
    </div>
    <div class="section">
        <h3>17. Protección de datos personales</h3>
        <p class="small">• EL CLIENTE autoriza expresamente a EL GESTOR al tratamiento de los datos personales entregados para la adecuada ejecución del presente contrato, conforme a la Ley N.º 29733 - Ley de Protección de Datos Personales y su Reglamento. La empresa garantiza la confidencialidad, seguridad y uso limitado de los datos personales a los fines contractuales.</p>
    </div>
    <div class="section">
        <h3>18. Fuerza mayor</h3>
        <p class="small">• Ninguna de las partes será responsable por el incumplimiento de sus obligaciones contractuales si este se debe a causas de fuerza mayor o caso fortuito, tales como desastres naturales, actos de autoridad, pandemias, conflictos armados, entre otros.</p>
    </div>
    <div class="section">
        <h3>19. Resolución del contrato por incumplimiento grave</h3>
        <p class="small">• Cualquiera de las partes podrá resolver el contrato en caso de incumplimiento grave de la otra parte, previa notificación escrita y sin que se requiera pronunciamiento judicial, siempre que no se subsane dicho incumplimiento dentro del plazo de quince (15) días calendario.</p>
    </div>
    <div class="section">
        <h3>20. Solución de controversias</h3>
        <p class="small">• Las partes acuerdan que cualquier controversia surgida con ocasión del presente contrato será resuelta mediante arbitraje de derecho administrado por el Centro de Arbitraje de la Cámara de Comercio de Lima. El laudo será definitivo e inapelable.</p>
        <p class="small">• En caso de discrepancia entre este contrato y cualquier otro documento, prevalecerá el presente acuerdo. Las comunicaciones electrónicas complementan, pero no sustituyen lo pactado en este contrato.</p>
    </div>

    @php
        $firmaPatSrc = null;
        $firmaPath = public_path('storage/social_icons/firma_patricia.png');
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
                        @if(!empty($show_client_signature) && !empty($signature_base64))
                            <img src="{{ $signature_base64 }}" alt="firma cliente" class="firma" />
                        @endif
                    </div>
                </td>
                <td style="width:50%; text-align:center;">
                    <div class="sig-container">
                        @if(!empty($firmaPatSrc))
                            <img src="{{ $firmaPatSrc }}" alt="firma" class="firma" />
                        @endif
                    </div>
                </td>
            </tr>
            <tr class="sig-row-names">
                <td style="width:50%; text-align:center;">
                    @if($esRuc)
                        <div class="sig-names">
                            {{ strtoupper($coordNombre ?: ($cliente_nombre ?? 'COORDINADOR OPERATIVO')) }}<br>
                            {{ $razon }}<br>
                            {{ $ruc }}<br>
                            (Coordinador Operativo)<br>
                            <strong>EL CLIENTE</strong>
                        </div>
                    @else
                        <div class="sig-names">{{ $cliente_nombre ?? 'NOMBRE APELLIDOS' }}<br>{{ $cliente_documento ?? 'DNI' }}<br><strong>EL CLIENTE</strong></div>
                    @endif
                </td>
                <td style="width:50%; text-align:center;">
                    <div class="sig-names">PATRICIA ALBAN HIDALGO<br>PRO MUNDO COMEX S.A.C.<br>20612452432<br>(Gerente General)<br><strong>EL GESTOR</strong></div>
                </td>
            </tr>
        </table>
    </div>
</div>
