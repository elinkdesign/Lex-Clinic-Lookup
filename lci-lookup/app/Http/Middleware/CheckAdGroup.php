<?php

namespace App\Http\Middleware;

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
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Check if user has the required AD group
        if (!$this->userInGroup($user, 'g-app-webapp-cpdrlist')) {
            abort(403, 'Access denied. You must be a member of the g-app-webapp-cpdrlist group.');
        }

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
