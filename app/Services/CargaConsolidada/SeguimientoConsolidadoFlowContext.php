<?php

namespace App\Services\CargaConsolidada;

/**
 * Contexto de trazabilidad para un run de sync/vincular (Telescope / logs).
 */
final class SeguimientoConsolidadoFlowContext
{
    /** @var string */
    public $correlationId;

    /** @var int */
    public $idContenedor;

    /** @var string */
    public $origin;

    /** @var float */
    public $startedAt;

    /**
     * @param int $idContenedor
     * @param string $origin
     */
    public function __construct($idContenedor, $origin = 'unknown')
    {
        $this->correlationId = uniqid('seg_', true);
        $this->idContenedor = (int) $idContenedor;
        $this->origin = (string) $origin;
        $this->startedAt = microtime(true);
    }

    /**
     * @return array<string, mixed>
     */
    public function baseContext()
    {
        return [
            'flow' => 'seguimiento_drive',
            'correlation_id' => $this->correlationId,
            'id_contenedor' => $this->idContenedor,
            'origin' => $this->origin,
        ];
    }

    /**
     * @return int
     */
    public function elapsedMs()
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }
}
