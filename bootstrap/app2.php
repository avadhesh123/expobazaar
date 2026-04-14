<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'user.type' => \App\Http\Middleware\CheckUserType::class,
            'department' => \App\Http\Middleware\CheckDepartment::class,
            'company.code' => \App\Http\Middleware\CheckCompanyCode::class,
            'vendor.kyc.approved' => \App\Http\Middleware\VendorKycApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 419 CSRF token expired → redirect to login
        $exceptions->renderable(function (TokenMismatchException $e, $request) {
            return redirect()->route('auth.login')->with('message', 'Session expired. Please login again.');
        });

        // 401 Unauthenticated → redirect to login
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('auth.login');
        });
    })->create();