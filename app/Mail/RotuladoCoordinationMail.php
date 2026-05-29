<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RotuladoCoordinationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $cliente;

    public $carga;

    public int $idCotizacion;

    public ?string $telefonoCliente;

    /** @var array<int, array{title: string|null, body: string}> */
    public array $sections;

    /** @var array<int, array{path: string, name: string, mime: string}> */
    public array $attachmentsMeta;

    /**
     * @param array<int, array{title: string|null, body: string}> $sections
     * @param array<int, array{path: string, name: string, mime: string}> $attachmentsMeta
     */
    public function __construct(
        string $cliente,
        $carga,
        int $idCotizacion,
        ?string $telefonoCliente,
        array $sections,
        array $attachmentsMeta
    ) {
        $this->cliente = $cliente;
        $this->carga = $carga;
        $this->idCotizacion = $idCotizacion;
        $this->telefonoCliente = $telefonoCliente;
        $this->sections = $sections;
        $this->attachmentsMeta = $attachmentsMeta;
    }

    public function build()
    {
        $mail = $this->subject('Rotulado enviado — Consolidado #' . $this->carga . ' — ' . $this->cliente)
            ->view('emails.rotulado_coordination')
            ->with([
                'cliente' => $this->cliente,
                'carga' => $this->carga,
                'idCotizacion' => $this->idCotizacion,
                'telefonoCliente' => $this->telefonoCliente,
                'sections' => $this->sections,
            ]);

        foreach ($this->attachmentsMeta as $attachment) {
            $path = $attachment['path'] ?? '';
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $mail->attach($path, [
                'as' => $attachment['name'] ?? basename($path),
                'mime' => $attachment['mime'] ?? 'application/octet-stream',
            ]);
        }

        return $mail;
    }
}
