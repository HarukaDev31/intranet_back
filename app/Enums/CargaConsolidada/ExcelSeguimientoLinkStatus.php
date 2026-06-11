<?php

namespace App\Enums\CargaConsolidada;

/**
 * Estados de vinculación del Excel de seguimiento consolidado → Google Drive.
 */
class ExcelSeguimientoLinkStatus
{
    const QUEUED = 'queued';
    const PROCESSING = 'processing';
    const COMPLETED = 'completed';
    const FAILED = 'failed';

    /** @var string[] */
    const ALL = [
        self::QUEUED,
        self::PROCESSING,
        self::COMPLETED,
        self::FAILED,
    ];

    /**
     * @param string|null $status
     * @return bool
     */
    public static function isProcessing($status)
    {
        return in_array($status, [self::QUEUED, self::PROCESSING], true);
    }

    /**
     * @param string|null $status
     * @return bool
     */
    public static function isFailed($status)
    {
        return $status === self::FAILED;
    }

    /**
     * @param string|null $status
     * @return bool
     */
    public static function isCompleted($status)
    {
        return $status === self::COMPLETED;
    }
}
