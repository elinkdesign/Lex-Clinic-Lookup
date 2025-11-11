<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\SessionUser;
use App\Models\LdapUser;
use LdapRecord\Connection;

class RestoreSessionUser
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user already authenticated in session
        if (Auth::check()) {
            \Log::info('✅ User already authenticated in session');
            return $next($request);
        }
        
        // Try to get authenticated user from Apache/Windows Auth headers
        $rawUsername = $request->server('PHP_AUTH_USER');
        $password = $request->server('PHP_AUTH_PW');
        $parsed = $this->normalizeUsername($rawUsername);
        $username = $parsed['username'];
        $domain = $parsed['domain'] ?? env('LDAP_DOMAIN', 'LC');
        
        if ($username && $password) {
            \Log::info('🔵 Found Windows Auth credentials in headers, restoring user', [
                'username' => $username,
                'path' => $request->path(),
            ]);
            
            try {
                // Connect to LDAP and get fresh user data
                $ldapConfig = config('ldap.connections.default');
                $ldapServer = $ldapConfig['hosts'][0];
                $ldapPort = $ldapConfig['port'] ?? 389;
                
                $ldap = ldap_connect($ldapServer, $ldapPort);
                if (!$ldap) {
                    throw new \Exception('Failed to connect to LDAP server');
                }
                
                ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
                
                // Verify credentials
                $userDn = "{$domain}\\{$username}";
                $bind = @ldap_bind($ldap, $userDn, $password);
                
                if (!$bind) {
                    \Log::warning("LDAP verification failed for user: {$userDn}");
                    ldap_close($ldap);
                    return $next($request);
                }
                
                // Get user info
                $baseDn = config('ldap.connections.default.base_dn');
                $filter = "(samAccountName={$username})";
                $result = @ldap_search($ldap, $baseDn, $filter, ['*', 'memberof']);
                
                if ($result && ldap_count_entries($ldap, $result) > 0) {
                    $entries = ldap_get_entries($ldap, $result);
                    $userEntry = $entries[0];
                    
                    ldap_close($ldap);
                    
                    // Create SessionUser from LDAP data
                    $sessionUser = new SessionUser($userEntry);
                    
                    // Set the user in auth without storing in session
                    Auth::guard('web')->setUser($sessionUser);
                    
                    \Log::info('✅ User restored from Windows Auth headers', [
                        'user' => $username,
                        'path' => $request->path(),
                    ]);
                }
                
            } catch (\Exception $e) {
                \Log::error('Error restoring user from Windows Auth: ' . $e->getMessage());
            }
        }
        
        return $next($request);
    }

    /**
     * Normalize username forms such as LC\user or user@domain.
     */
    private function normalizeUsername(?string $username): array
    {
        if (!$username) {
            return ['domain' => null, 'username' => null];
        }

        if (str_contains($username, '\\')) {
            [$domain, $name] = explode('\\', $username, 2);
            return ['domain' => strtoupper($domain), 'username' => $name];
        }

        if (str_contains($username, '@')) {
            [$name, $domain] = explode('@', $username, 2);
            return ['domain' => strtoupper(strtok($domain, '.')), 'username' => $name];
        }

        return ['domain' => null, 'username' => $username];
    }
}
