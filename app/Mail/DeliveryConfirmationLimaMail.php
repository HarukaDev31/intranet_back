<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Services\Delivery\LimaRecojoNotificacionService;

class DeliveryConfirmationLimaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $carga;
    public $mensaje;
    public $deliveryForm;
    public $cotizacion;
    public $user;
    public $primerNombre;
    public $pickName;
    public $pickDoc;
    public $pickPhone;
    public $fechaTextual;
    public $fechaRecojo;
    public $horaInicio;
    public $horaFin;
    public $horaRecojo;
    public $direccion;
    public $referencia;
    public $mapsUrl;
    public $logo_header_white;
    public $logo_footer;

    /**
     * @param mixed $deliveryForm
     * @param mixed $cotizacion
     * @param mixed $user
     * @param string|int $carga
     * @param string $logo_header
     * @param string $logo_footer
     * @param array|null $notificacion Salida de LimaRecojoNotificacionService::datosVistaCorreo (opcional; se recalcula si es null)
     */
    public function __construct($deliveryForm, $cotizacion, $user, $carga, $logo_header, $logo_footer, $notificacion = null)
    {
        $this->deliveryForm = $deliveryForm;
        $this->cotizacion = $cotizacion;
        $this->user = $user;
        $this->carga = $carga;
        $this->logo_header_white = $logo_header;
        $this->logo_footer = $logo_footer;

        if ($notificacion === null) {
            $notificacion = LimaRecojoNotificacionService::datosVistaCorreo($deliveryForm, $carga, $user);
        }

        $this->mensaje = $notificacion['whatsapp'];
        $this->primerNombre = $notificacion['primerNombre'];
        $this->pickName = $notificacion['pickName'];
        $this->pickDoc = $notificacion['pickDoc'];
        $this->pickPhone = $notificacion['pickPhone'];
        $this->fechaTextual = $notificacion['fechaTextual'];
        $this->fechaRecojo = $notificacion['fechaRecojo'];
        $this->horaInicio = $notificacion['horaInicio'];
        $this->horaFin = $notificacion['horaFin'];
        $this->horaRecojo = $notificacion['horaRecojo'];
        $this->direccion = $notificacion['direccion'];
        $this->referencia = $notificacion['referencia'];
        $this->mapsUrl = $notificacion['mapsUrl'];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Confirmación de Recojo - Lima - Consolidado #' . $this->carga)
            ->view('emails.delivery_confirmation_lima')
            ->with([
                'carga' => $this->carga,
                'mensaje' => $this->mensaje,
                'deliveryForm' => $this->deliveryForm,
                'cotizacion' => $this->cotizacion,
                'user' => $this->user,
                'primerNombre' => $this->primerNombre,
                'pickName' => $this->pickName,
                'pickDoc' => $this->pickDoc,
                'pickPhone' => $this->pickPhone,
                'fechaTextual' => $this->fechaTextual,
                'fechaRecojo' => $this->fechaRecojo,
                'horaInicio' => $this->horaInicio,
                'horaFin' => $this->horaFin,
                'horaRecojo' => $this->horaRecojo,
                'direccion' => $this->direccion,
                'referencia' => $this->referencia,
                'mapsUrl' => $this->mapsUrl,
                'logo_header' => $this->logo_header_white,
                'logo_footer' => $this->logo_footer,
            ]);
    }
}
