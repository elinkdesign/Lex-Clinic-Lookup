<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SessionUser;

class RestoreSessionUser
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->hasSession()) {
            return $next($request);
        }

        // Check if user already authenticated in session
        if (Auth::check()) {
            return $next($request);
        }

        $userData = $request->session()->get('auth.user');

        if (is_array($userData)) {
            Auth::setUser(new SessionUser($userData));
        }

        return $next($request);
    }
}
