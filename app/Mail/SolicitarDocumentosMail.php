<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitarDocumentosMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var string */
    public $clienteNombre;

    /** @var string */
    public $cargaCode;

    /** @var string */
    public $clienteTelefono;

    /** @var array<int, array<string, mixed>> */
    public $steps;

    /**
     * @param  array<int, array<string, mixed>>  $steps  Bloques en orden: type=text|file
     */
    public function __construct(string $clienteNombre, string $cargaCode, array $steps, string $clienteTelefono = '')
    {
        $this->clienteNombre = $clienteNombre;
        $this->cargaCode = $cargaCode;
        $this->clienteTelefono = $clienteTelefono;
        $this->steps = $steps;
    }

    public function build()
    {
        $mail = $this->subject('Solicitud de documentación - Consolidado #' . $this->cargaCode)
            ->view('emails.solicitar_documentos')
            ->with([
                'clienteNombre' => $this->clienteNombre,
                'clienteTelefono' => $this->clienteTelefono,
                'cargaCode' => $this->cargaCode,
                'steps' => $this->steps,
            ]);

        foreach ($this->steps as $step) {
            if (($step['type'] ?? '') !== 'file') {
                continue;
            }
            $path = $step['path'] ?? '';
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $filename = $step['filename'] ?? basename($path);
            $mail->attach($path, ['as' => $filename]);
        }

        return $mail;
    }
}
