<?php

namespace App\Support\PhpSpreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Collection\Memory\SimpleCache3;
use PhpOffice\PhpSpreadsheet\Settings;

/**
 * Aísla la caché de celdas de PhpSpreadsheet por operación (workers Horizon reutilizan proceso).
 */
final class PhpSpreadsheetRuntime
{
    public static function begin(): void
    {
        class_exists(Cell::class);
        Settings::setCache(new SimpleCache3());
    }

    public static function end(): void
    {
        Settings::setCache(null);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(callable $callback)
    {
        self::begin();

        try {
            return $callback();
        } finally {
            self::end();
        }
    }
}
