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
            \Log::info('RestoreSessionUser: No session available', [
                'path' => $request->path(),
            ]);
            return $next($request);
        }

        // Check if user already authenticated in session
        if (Auth::check()) {
            \Log::info('RestoreSessionUser: User already authenticated', [
                'path' => $request->path(),
                'user' => Auth::user()->samaccountname ?? 'unknown',
            ]);
            return $next($request);
        }

        $userData = $request->session()->get('auth.user');

        \Log::info('RestoreSessionUser: Attempting to restore user', [
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'session_id' => $request->session()->getId(),
            'has_auth_user' => is_array($userData),
            'auth_user_keys' => is_array($userData) ? array_keys($userData) : null,
        ]);

        if (is_array($userData)) {
            $sessionUser = new SessionUser($userData);
            Auth::setUser($sessionUser);
            \Log::info('RestoreSessionUser: User restored successfully', [
                'path' => $request->path(),
                'user' => Auth::user()->samaccountname ?? 'unknown',
            ]);
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
