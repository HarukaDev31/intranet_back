<?php

declare(strict_types=1);

/**
 * Laravel 13.8+ usa SortDirection (PHP 8.6) como default en orderBy().
 * En PHP 8.3 el stub de symfony/polyfill-php86 no siempre entra por classmap.
 */
if (PHP_VERSION_ID < 80600 && ! enum_exists('SortDirection', false)) {
    $stub = dirname(__DIR__) . '/vendor/symfony/polyfill-php86/Resources/stubs/SortDirection.php';

    if (is_file($stub)) {
        require $stub;
    }
}
