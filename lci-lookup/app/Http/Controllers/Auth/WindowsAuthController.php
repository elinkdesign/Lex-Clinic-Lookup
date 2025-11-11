<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use LdapRecord\Models\ActiveDirectory\User;
use App\Models\LdapUser;
use App\Models\SessionUser;

class WindowsAuthController extends Controller
{
    /**
     * Handle the incoming Windows authentication request
     */
    public function authenticate(Request $request)
    {
        // Get authenticated username and password from Apache basic auth
        $rawUsername = $request->server('PHP_AUTH_USER');
        $password = $request->server('PHP_AUTH_PW');
        $parsed = $this->normalizeUsername($rawUsername);
        $username = $parsed['username'];
        $domain = $parsed['domain'] ?? env('LDAP_DOMAIN', 'LC');
        
        Log::info('=== Windows Auth Debug ===', [
            'AUTH_USER' => $request->server('AUTH_USER'),
            'REMOTE_USER' => $request->server('REMOTE_USER'),
            'PHP_AUTH_USER' => $rawUsername,
            'resolved_username' => $username,
        ]);
        
        if (!$username || !$password) {
            Log::warning('Windows Authentication: No credentials provided');
            return redirect()->route('login')->withErrors(['auth' => 'Authentication failed.']);
        }
        
        try {
            // Configure LDAP connection
            $ldapConfig = config('ldap.connections.default');
            $ldapServer = $ldapConfig['hosts'][0];
            $ldapPort = $ldapConfig['port'] ?? 389;
            
            // Connect to LDAP server
            $ldap = ldap_connect($ldapServer, $ldapPort);
            if (!$ldap) {
                throw new \Exception('Failed to connect to LDAP server');
            }
            
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            
            // Attempt to bind as the user with their credentials
            $userDn = "{$domain}\\{$username}";
            $bind = @ldap_bind($ldap, $userDn, $password);
            
            if (!$bind) {
                Log::warning("LDAP Authentication Failed for user: {$userDn}");
                ldap_close($ldap);
                return redirect()->route('login')->withErrors(['auth' => 'Invalid credentials.']);
            }
            
            Log::info("LDAP Bind Successful for user: {$userDn}");
            
            // User authenticated successfully! Search for their full info using the authenticated connection
            $baseDn = config('ldap.connections.default.base_dn');
            $filter = "(samAccountName={$username})";
            
            $result = @ldap_search($ldap, $baseDn, $filter, ['*', 'memberof']);
            
            if ($result && ldap_count_entries($ldap, $result) > 0) {
                $entries = ldap_get_entries($ldap, $result);
                $userEntry = $entries[0];
                
                // Log what we got
                Log::info("LDAP User Entry", [
                    'samaccountname' => $userEntry['samaccountname'] ?? null,
                    'memberof_count' => isset($userEntry['memberof']) ? (is_array($userEntry['memberof']) ? count($userEntry['memberof']) : 1) : 0,
                    'memberof' => $userEntry['memberof'] ?? null,
                ]);
                
                ldap_close($ldap);
                
                // Create an LdapUser from the LDAP entry
                $ldapUser = new LdapUser();
                $ldapUser->setRawAttributes($userEntry);
                
                Log::info("Before login - User object created", [
                    'user_class' => get_class($ldapUser),
                    'user_attrs' => $ldapUser->getAttributes(),
                ]);
                
                // Check if user is in the required AD group BEFORE logging them in
                if (!$this->userInGroup($ldapUser)) {
                    Log::warning("User not in required AD group", [
                        'user' => $username,
                        'memberof' => $ldapUser->memberof ?? null,
                    ]);
                    abort(403, 'Access denied. You must be a member of the g-app-webapp-cpdrlist group.');
                }
                
                // Create SessionUser and log them in
                $sessionUser = new SessionUser($ldapUser->getAttributes());
                Auth::guard('web')->login($sessionUser);
                
                Log::info("Authentication successful and user logged in", [
                    'user' => $username,
                    'auth_check' => Auth::check(),
                ]);
                
                return response()->json(
                    [
                        'success' => true,
                        'message' => 'Authentication successful',
                        'redirect' => route('home'),
                    ],
                    200
                );
            }
            
            ldap_close($ldap);
            
            // Fallback: User authenticated but no LDAP record - that's OK, still logged in
            Log::info("Authentication successful for user: {$username} (minimal record)");
            return response()->json(
                [
                    'success' => true,
                    'message' => 'Authentication successful',
                    'redirect' => route('home'),
                ],
                200
            );
            
        } catch (\Exception $e) {
            Log::error('Windows Authentication Exception: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['auth' => 'Authentication error.']);
        }
    }

    /**
     * Helper to check if a user is in a specific AD group.
     *
     * @param LdapUser $user
     * @param string $groupName The name of the group to check (e.g., 'g-app-webapp-cpdrlist')
     * @return bool
     */
    private function userInGroup(LdapUser $user, string $groupName = 'g-app-webapp-cpdrlist')
    {
        if (!isset($user->memberof) || !is_array($user->memberof)) {
            return false;
        }

        foreach ($user->memberof as $group) {
            if (stripos($group, $groupName) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalizes a username coming from Basic Auth headers. Handles forms:
     *  - LC\username
     *  - username@domain
     *  - username
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
