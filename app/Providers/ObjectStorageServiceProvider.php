<?php

namespace App\Providers;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Services\Storage\S3ObjectStorageConnector;
use Illuminate\Support\ServiceProvider;

class ObjectStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ObjectStorageConnectorInterface::class, S3ObjectStorageConnector::class);
        $this->app->alias(ObjectStorageConnectorInterface::class, 'object.storage');
    }
}
