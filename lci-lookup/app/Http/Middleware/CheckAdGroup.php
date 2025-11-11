<?php

namespace App\Http\Middleware;

use App\Models\SessionUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdGroup
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info("CheckAdGroup Middleware - Entry", [
            'path' => $request->path(),
            'session_id' => $request->session()->getId(),
        ]);

        $user = Auth::user();

        if (!$user && $request->session()->has('auth.user')) {
            $restoredUser = new SessionUser($request->session()->get('auth.user'));
            Auth::setUser($restoredUser);
            $user = $restoredUser;
            \Log::info("CheckAdGroup Middleware - Session user restored from payload", [
                'session_id' => $request->session()->getId(),
            ]);
        }

        \Log::info("CheckAdGroup Middleware - User Check", [
            'path' => $request->path(),
            'auth_check' => Auth::check(),
            'user' => $user ? ($user->samaccountname ?? null) : null,
            'has_memberof' => $user ? isset($user->memberof) : false,
        ]);

        if (!$user) {
            \Log::warning("CheckAdGroup Middleware - No user found after restore attempt, redirecting to login", [
                'session_has_auth_user' => $request->session()->has('auth.user'),
                'session_id' => $request->session()->getId(),
            ]);

            Auth::logout();

            $request->session()->forget([
                'auth.user',
                'auth.username',
                'auth.domain',
                'auth.guid',
            ]);

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'auth' => 'Your session expired. Please sign in again.',
                ]);
        }

        // Check if user has the required AD group
        if (!$this->userInGroup($user, 'g-app-webapp-cpdrlist')) {
            \Log::warning("Access denied for user", [
                'user' => $user->samaccountname ?? $user,
                'memberof' => $user->memberof ?? null,
            ]);
            abort(403, 'Access denied. You must be a member of the g-app-webapp-cpdrlist group.');
        }
        
        \Log::info("CheckAdGroup Middleware - Access granted for user", [
            'user' => $user->samaccountname ?? $user,
        ]);

        return $next($request);
    }

    /**
     * Check if user belongs to the specified AD group
     */
    private function userInGroup($user, string $groupName): bool
    {
        // If the user has a groups() relationship method (LDAP users typically do)
        if (method_exists($user, 'groups')) {
            $groups = $user->groups()->get();
            
            foreach ($groups as $group) {
                $cn = $group->getFirstAttribute('cn');
                if (strtolower($cn) === strtolower($groupName)) {
                    return true;
                }
            }
        }

        // Alternative: check if groups are stored as an attribute array
        if (isset($user->memberof)) {
            $memberOf = is_array($user->memberof) ? $user->memberof : [$user->memberof];
            
            foreach ($memberOf as $groupDn) {
                if (stripos($groupDn, "CN={$groupName},") !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
