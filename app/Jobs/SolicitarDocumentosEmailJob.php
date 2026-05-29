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

    /** @var array */
    protected $steps;

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function __construct(int $idCotizacion, string $clienteNombre, string $cargaCode, array $steps)
    {
        $this->idCotizacion = $idCotizacion;
        $this->clienteNombre = $clienteNombre;
        $this->cargaCode = $cargaCode;
        $this->steps = $steps;
        $this->onQueue('notificaciones');
    }

    public function handle(): void
    {
        try {
            $this->sendMailTo(
                env('COORDINATION_EMAIL'),
                new SolicitarDocumentosMail($this->clienteNombre, $this->cargaCode, $this->steps)
            );

            Log::info('SolicitarDocumentosEmailJob: email enviado', [
                'id_cotizacion' => $this->idCotizacion,
                'correo' => env('COORDINATION_EMAIL'),
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
