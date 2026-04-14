<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
      ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'user.type' => \App\Http\Middleware\CheckUserType::class,
            'department' => \App\Http\Middleware\CheckDepartment::class,
            'company.code' => \App\Http\Middleware\CheckCompanyCode::class,
            'vendor.kyc.approved' => \App\Http\Middleware\VendorKycApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
