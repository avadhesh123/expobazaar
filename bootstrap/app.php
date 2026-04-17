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

        // Tell the 'auth' middleware where to redirect unauthenticated users
        // (default tries route('login') which doesn't exist in this app)
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->ajax()) {
                return null; // Returns 401 JSON for AJAX
            }
            return route('auth.login');
        });

        // Tell the 'guest' middleware where to redirect authenticated users
        $middleware->redirectUsersTo(function ($request) {
            $user = $request->user();
            if (!$user) {
                return '/';
            }
            if ($user->user_type === 'vendor') {
                return route('vendor.dashboard');
            }
            return match ($user->department) {
                'admin'       => route('admin.dashboard'),
                'sourcing'    => route('sourcing.dashboard'),
                'logistics'   => route('logistics.dashboard'),
                'finance'     => route('finance.dashboard'),
                'sales'       => route('sales.dashboard'),
                'hod'         => route('hod.dashboard'),
                'cataloguing' => route('cataloguing.dashboard'),
                default       => '/',
            };
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ── 419: CSRF token expired / session timed out ───────────────────────
        $exceptions->renderable(function (TokenMismatchException $e, $request) {
            // AJAX / fetch() calls expect JSON — return 419 so the global
            // fetch() patch in app.blade.php can handle the redirect client-side
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Session expired. Please refresh the page and login again.',
                    'expired' => true,
                ], 419);
            }
            // Normal page request → redirect to login with a flash message
            return redirect()->route('auth.login')
                ->with('error', 'Your session has expired. Please login again.');
        });

        // ── 401: Unauthenticated ──────────────────────────────────────────────
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('auth.login')
                ->with('error', 'Please login to continue.');
        });

        // ── 403: Unauthorised ─────────────────────────────────────────────────
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($e->getStatusCode() === 403) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['message' => 'Forbidden.'], 403);
                }
                return redirect()->back()
                    ->with('error', 'You do not have permission to perform this action.');
            }
        });

    })->create();
