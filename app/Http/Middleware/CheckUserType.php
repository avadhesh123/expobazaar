<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserType
{
    public function handle(Request $request, Closure $next, string ...$types)
    {
        if (!auth()->check()) return redirect()->route('auth.login');
        if (!in_array(auth()->user()->user_type, $types)) abort(403, 'Unauthorized.');
        return $next($request);
    }
}
