<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryFormLinkCoordinationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombreCliente;

    public $carga;

    public int $idCotizacion;

    public ?string $telefonoCliente;

    public ?string $destino;

    /** @var array<int, array{title: string|null, body: string}> */
    public array $sections;

    /**
     * @param array<int, array{title: string|null, body: string}> $sections
     */
    public function __construct(
        string $nombreCliente,
        $carga,
        int $idCotizacion,
        ?string $telefonoCliente,
        ?string $destino,
        array $sections
    ) {
        $this->nombreCliente = $nombreCliente;
        $this->carga = $carga;
        $this->idCotizacion = $idCotizacion;
        $this->telefonoCliente = $telefonoCliente;
        $this->destino = $destino;
        $this->sections = $sections;
    }

    public function build()
    {
        $destinoLabel = $this->destino ? ' — ' . $this->destino : '';

        return $this->subject('Link formulario de entrega enviado — Consolidado #' . $this->carga . $destinoLabel)
            ->view('emails.delivery_form_link_coordination')
            ->with([
                'nombreCliente' => $this->nombreCliente,
                'carga' => $this->carga,
                'idCotizacion' => $this->idCotizacion,
                'telefonoCliente' => $this->telefonoCliente,
                'destino' => $this->destino,
                'sections' => $this->sections,
            ]);
    }
}
