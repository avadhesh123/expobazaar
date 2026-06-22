<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Set storage disk based on APP_URL or STORAGE_DISK env
        $disk = env('STORAGE_DISK');

        if (!$disk) {
            $appUrl = config('app.url', '');
            $disk = str_contains($appUrl, 'scm.expobazaar.com') ? 's3' : 'public';
        }

        config(['app.storage_disk' => $disk]);
    }
}
