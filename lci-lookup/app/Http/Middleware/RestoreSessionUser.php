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

        // Skip guest routes to prevent redirect loops
        if ($this->isGuestRoute($request)) {
            return $next($request);
        }

        // Check if user already authenticated in session
        if (Auth::check()) {
            return $next($request);
        }

        $userData = $request->session()->get('auth.user');

        if (is_array($userData)) {
            $sessionUser = new SessionUser($userData);
            Auth::setUser($sessionUser);
        }

        return $next($request);
    }

    /**
     * Determine if the current request is for a guest route.
     */
    private function isGuestRoute(Request $request): bool
    {
        $guestRoutes = [
            'login',
            'register',
            'windows.auth',
            'password.request',
            'password.email',
            'password.reset',
            'password.store',
        ];

        $currentRoute = $request->route();

        if (!$currentRoute) {
            return false;
        }

        $routeName = $currentRoute->getName();

        return in_array($routeName, $guestRoutes, true);
    }
}
