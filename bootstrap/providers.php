<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\StorageServiceProvider::class, // ← add this
];
