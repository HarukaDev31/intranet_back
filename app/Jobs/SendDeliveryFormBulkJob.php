<?php

namespace App\Jobs;

use App\Models\CargaConsolidada\Contenedor;
use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendDeliveryFormBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    public $cotizaciones = [];

    public function __construct(array $cotizaciones)
    {
        $indexed = [];
        foreach ($cotizaciones as $item) {
            $idCotizacion = 0;
            $typeForm = null;

            if (is_array($item)) {
                $idCotizacion = isset($item['id_cotizacion']) ? (int) $item['id_cotizacion'] : 0;
                if (array_key_exists('type_form', $item) && $item['type_form'] !== null && $item['type_form'] !== '') {
                    $typeForm = ((int) $item['type_form'] === 1) ? 1 : 0;
                }
            } else {
                $idCotizacion = (int) $item;
            }

            if ($idCotizacion <= 0) {
                continue;
            }

            if (!isset($indexed[$idCotizacion])) {
                $indexed[$idCotizacion] = [
                    'id_cotizacion' => $idCotizacion,
                    'type_form' => $typeForm,
                ];
                continue;
            }

            // Si ya existía y ahora llega type_form explícito, priorizarlo.
            if ($indexed[$idCotizacion]['type_form'] === null && $typeForm !== null) {
                $indexed[$idCotizacion]['type_form'] = $typeForm;
            }
        }

        $this->cotizaciones = array_values($indexed);
        $this->onQueue('notificaciones');
    }

    public function handle(): void
    {
        foreach ($this->cotizaciones as $cotizacionData) {
            try {
                $numeroWhatsapp = null;
                $resultPrincipal = null;
                $resultSecundario = null;
                $idCotizacion = isset($cotizacionData['id_cotizacion']) ? (int) $cotizacionData['id_cotizacion'] : 0;
                if ($idCotizacion <= 0) {
                    continue;
                }
                $row = DB::table('contenedor_consolidado_cotizacion as C')
                    ->leftJoin('consolidado_delivery_form_lima as L', function ($join) {
                        $join->on('L.id_contenedor', '=', 'C.id_contenedor')
                            ->on('L.id_cotizacion', '=', 'C.id');
                    })
                    ->leftJoin('consolidado_delivery_form_province as P', function ($join) {
                        $join->on('P.id_contenedor', '=', 'C.id_contenedor')
                            ->on('P.id_cotizacion', '=', 'C.id');
                    })
                    ->leftJoin('consolidado_comprobante_forms as CF', function ($join) {
                        $join->on('CF.id_contenedor', '=', 'C.id_contenedor')
                            ->on('CF.id_cotizacion', '=', 'C.id');
                    })
                    ->select([
                        'C.id',
                        'C.id_contenedor',
                        'C.telefono',
                        'C.nombre as nombre_cliente',
                        DB::raw($this->sqlCaseTypeFormNullable() . ' as type_form'),
                    ])
                    ->where('C.id', (int) $idCotizacion)
                    ->whereNull('C.deleted_at')
                    ->first();

                if (!$row || !$row->id_contenedor) {
                    Log::warning('SendDeliveryFormBulkJob: cotización no encontrada', ['id_cotizacion' => $idCotizacion]);
                    continue;
                }

                $contenedor = Contenedor::find($row->id_contenedor);
                if (!$contenedor) {
                    Log::warning('SendDeliveryFormBulkJob: contenedor no encontrado', ['id_cotizacion' => $idCotizacion]);
                    continue;
                }

                $urlClientes = env('APP_URL_CLIENTES');
                $urlProvincia = $urlClientes . '/formulario-entrega/provincia/' . $row->id_contenedor;
                $urlLima = $urlClientes . '/formulario-entrega/lima/' . $row->id_contenedor;

                $typeFormPayload = isset($cotizacionData['type_form']) && $cotizacionData['type_form'] !== null
                    ? (int) $cotizacionData['type_form']
                    : null;
                $typeFormDb = isset($row->type_form) ? (int) $row->type_form : null;
                $typeForm = ($typeFormPayload === 0 || $typeFormPayload === 1) ? $typeFormPayload : $typeFormDb;
                [$messagePrincipal, $messageSecundario] = $this->buildDeliveryFormsMessages(
                    (string) $contenedor->carga,
                    (string) ($row->nombre_cliente ?? ''),
                    $typeForm,
                    $urlLima,
                    $urlProvincia
                );

                $telefono = preg_replace('/\D+/', '', (string) $row->telefono);
                if (!$telefono) {
                    Log::warning('SendDeliveryFormBulkJob: cotización sin teléfono', ['id_cotizacion' => $idCotizacion]);
                    continue;
                }
                if (strlen($telefono) < 9) {
                    $telefono = '51' . $telefono;
                }
                $numeroWhatsapp = $telefono . '@c.us';

                $resultPrincipal = $this->sendMessage($messagePrincipal, $numeroWhatsapp);
                if (!$resultPrincipal['status']) {
                    Log::warning('SendDeliveryFormBulkJob: error enviando WhatsApp', [
                        'id_cotizacion' => $idCotizacion,
                        'tipo_mensaje' => 'principal',
                        'response' => isset($resultPrincipal['response']) ? $resultPrincipal['response'] : null,
                    ]);
                    continue;
                }

                $resultSecundario = $this->sendMessage($messageSecundario, $numeroWhatsapp,5);
                if (!$resultSecundario['status']) {
                    Log::warning('SendDeliveryFormBulkJob: error enviando WhatsApp', [
                        'id_cotizacion' => $idCotizacion,
                        'tipo_mensaje' => 'secundario',
                        'response' => isset($resultSecundario['response']) ? $resultSecundario['response'] : null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('SendDeliveryFormBulkJob: excepción por cotización', [
                    'id_cotizacion' => $idCotizacion,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sqlComprobanteFormTieneDestino(): string
    {
        return '(CF.id IS NOT NULL AND CF.destino_entrega IS NOT NULL AND TRIM(CF.destino_entrega) <> \'\')';
    }

    private function sqlCaseTypeFormNullable(): string
    {
        $cf = $this->sqlComprobanteFormTieneDestino();
        return 'CASE '
            . 'WHEN ' . $cf . ' AND (UPPER(TRIM(CF.destino_entrega)) IN (\'PROVINCIA\',\'PROVINCE\') OR UPPER(TRIM(CF.destino_entrega)) LIKE \'%PROVIN%\') THEN 0 '
            . 'WHEN ' . $cf . ' AND (UPPER(TRIM(CF.destino_entrega)) IN (\'LIMA\') OR UPPER(TRIM(CF.destino_entrega)) LIKE \'%LIMA%\') THEN 1 '
            . 'WHEN P.id IS NOT NULL THEN 0 '
            . 'WHEN L.id IS NOT NULL THEN 1 '
            . 'ELSE NULL END';
    }

    private function buildDeliveryFormsMessages(string $carga, string $nombreCliente, ?int $typeForm, string $urlLima, string $urlProvincia): array
    {
        $isLima = ($typeForm === 1);
        $forms = $isLima ? $urlLima : $urlProvincia;
        $destinoCliente = $isLima ? 'Lima' : 'Provincia';
        $nombreCliente = trim($nombreCliente) !== '' ? trim($nombreCliente) : 'cliente';
        $saludoInicial = "🙋🏻‍♀️Hola {$nombreCliente} te saluda área de Coordinación.\n\n"
            . "Cliente: {$destinoCliente}\n\n";

        $messagePrincipal = $isLima
            ? "# Consolidado " . $carga . "\n\n"
                . $saludoInicial
                . "✅ *Registrarse*, en el siguiente link.\n"
                . "✅ *Reservar su horario* de recojo lo antes posible.\n"
                . "✅ *Plazo máximo* para el registro: 48 horas\n"
                . "✅ Tener los pagos al día.\n"
                . "✅ *FORMS:* " . $forms . "\n\n"
                . "⚠ Enviar movilidad acorde al volumen de su carga (auto, camioneta, furgón o camión)."
            : "# Consolidado " . $carga . "\n"
                . $saludoInicial
                . "✅ *Registrarse*, en el siguiente link.\n"
                . "✅ *Plazo máximo* para el registro: 48 horas\n"
                . "✅ *Organizaremos los envíos* una vez liberado el contenedor.\n"
                . "✅ *FORMS:* " . $forms . "\n \n"
                . "⚠  De no llenar el formulario no se programará el envío de sus productos.";
        // intval of carga <5 use El *costo de flete* Almacén – Agencia se cotizará y será informado por interno. instead ➡ El *costo de flete* Almacén – Agencia detalla en su cotización final.
        $messageSecundario = $isLima
            ? "❌ Tiempo máximo de recojo: *30 minutos* según horario reservado\n"
                . "❌ La movilidad debe retirar toda la mercadería en un solo viaje.\n"
                . "❌ No se permite recojo parcial ni múltiples viajes.\n"
                . "❌ No está permitido seleccionar, separar, armar o desarmar productos dentro del almacén.\n"
                . "❌ No dejar pallets, etiquetas ni bolsas en el almacén.\n\n"
                . "📍 Agradecemos su apoyo para mantener un proceso de entrega ordenado."
            : "Importante:\n\n"
                . "➡ La información registrada será utilizada para la *emisión de guías de remisión*.\n"
                . "➡ *Validar* que sus datos estén correctos y completos.\n"
                . (
                    intval($carga) < 5
                        ? "➡ El *costo de flete* Almacén – Agencia se cotizará y será informado por interno.\n"
                        : "➡ El *costo de flete* Almacén – Agencia detalla en su cotización final.\n"
                )
                . "➡ Los envíos se realizan con *Marvisur*.\n"
                . "➡ Si desea trabajar con otra agencia de transporte, se aplicará un *costo adicional* y previa coordinación.\n"
                . "➡ En ese caso, no asumimos responsabilidad por incidencias en la entrega con la agencia elegida.";

        return [$messagePrincipal, $messageSecundario];
    }
}
