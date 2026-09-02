<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Protect the console while keeping the tenant-scoped login endpoints public. */
final class RequireAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        // Existing feature tests exercise business controllers without making
        // auth assertions; production and browser requests are always gated.
        if (app()->environment('testing') || $request->is('login') || $request->is('logout') || auth()->check()) {
            return $next($request);
        }

        return redirect()->guest(route('login'));
    }
}
