<?php

namespace App\Jobs;

use App\Mail\SolicitarDocumentosMail;
use App\Traits\MailTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SolicitarDocumentosEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MailTrait;

    /** @var int */
    protected $idCotizacion;

    /** @var string */
    protected $clienteNombre;

    /** @var string */
    protected $cargaCode;

    /** @var string */
    protected $clienteTelefono;

    /** @var array */
    protected $steps;

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function __construct(int $idCotizacion, string $clienteNombre, string $cargaCode, array $steps, string $clienteTelefono = '')
    {
        $this->idCotizacion = $idCotizacion;
        $this->clienteNombre = $clienteNombre;
        $this->cargaCode = $cargaCode;
        $this->clienteTelefono = $clienteTelefono;
        $this->steps = $steps;
        $this->onQueue('notificaciones');
    }

    public function handle(): void
    {
        try {
            $recipients = $this->coordinationEmails();
            if ($recipients === []) {
                Log::warning('SolicitarDocumentosEmailJob: COORDINATION_EMAIL no configurado; se omite correo');

                return;
            }

            $this->sendMailToCoordination(
                new SolicitarDocumentosMail($this->clienteNombre, $this->cargaCode, $this->steps, $this->clienteTelefono)
            );

            Log::info('SolicitarDocumentosEmailJob: email enviado', [
                'id_cotizacion' => $this->idCotizacion,
                'correos' => $recipients,
            ]);
        } catch (\Throwable $e) {
            Log::error('SolicitarDocumentosEmailJob error: ' . $e->getMessage(), [
                'id_cotizacion' => $this->idCotizacion,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
