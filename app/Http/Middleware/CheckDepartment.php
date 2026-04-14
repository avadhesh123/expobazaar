<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckDepartment
{
    public function handle(Request $request, Closure $next, string ...$departments)
    {
        if (!auth()->check()) return redirect()->route('auth.login');
        $user = auth()->user();
        if ($user->isAdmin()) return $next($request); // Admins bypass
        if (!in_array($user->department, $departments)) abort(403, 'Unauthorized department.');
        return $next($request);
    }
}
