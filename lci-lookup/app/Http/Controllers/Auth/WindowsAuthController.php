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
        $password = $request->server('PHP_AUTH_PW'); // not used for binding, but kept to ensure header exists
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
            Log::channel('windows_auth')->info('WindowsAuthController.authenticate invoked', [
                'username' => $username,
                'session_id' => session()->getId(),
                'request_id' => $request->headers->get('X-Request-ID'),
            ]);

            // Configure LDAP connection
            $ldapConfig = config('ldap.connections.default');
            $ldapServer = $ldapConfig['hosts'][0];
            $ldapPort = $ldapConfig['port'] ?? 389;
            $serviceUser = env('LDAP_USERNAME', $ldapConfig['username'] ?? null);
            $servicePass = env('LDAP_PASSWORD', $ldapConfig['password'] ?? null);
            
            // Connect to LDAP server
            $ldap = ldap_connect($ldapServer, $ldapPort);
            if (!$ldap) {
                Log::channel('windows_auth')->error('Failed to connect to LDAP server', [
                    'username' => $username,
                    'server' => $ldapServer,
                    'port' => $ldapPort,
                ]);
                throw new \Exception('Failed to connect to LDAP server');
            }
            
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
            
            if (!$serviceUser || !$servicePass) {
                Log::channel('windows_auth')->error('LDAP service account credentials not configured', [
                    'username' => $username,
                ]);
                throw new \Exception('LDAP service account credentials not configured');
            }
            
            // Bind using service account credentials (Apache already validated the user)
            $bind = @ldap_bind($ldap, $serviceUser, $servicePass);
            
            if (!$bind) {
                Log::warning("LDAP service bind failed for account: {$serviceUser}");
                ldap_close($ldap);
                return redirect()->route('login')->withErrors(['auth' => 'Invalid credentials.']);
            }
            
            Log::channel('windows_auth')->info('LDAP service bind successful', [
                'username' => $username,
                'service_user' => $serviceUser,
            ]);

            Log::info("LDAP service bind successful");
            
            // User authenticated successfully! Search for their full info using the authenticated connection
            $baseDn = config('ldap.connections.default.base_dn');
            $filter = "(samAccountName={$username})";
            
            $result = @ldap_search($ldap, $baseDn, $filter, ['*', 'memberof']);

            Log::channel('windows_auth')->info('LDAP search executed', [
                'username' => $username,
                'filter' => $filter,
                'result' => $result !== false,
            ]);
            
            if ($result && ldap_count_entries($ldap, $result) > 0) {
                Log::channel('windows_auth')->info('LDAP entries found', [
                    'username' => $username,
                    'entry_count' => ldap_count_entries($ldap, $result),
                ]);

                $entries = ldap_get_entries($ldap, $result);
                $userEntry = $entries[0];
                
                // Log what we got
                Log::info("LDAP User Entry", [
                    'samaccountname' => $userEntry['samaccountname'] ?? null,
                    'memberof_count' => isset($userEntry['memberof']) ? (is_array($userEntry['memberof']) ? count($userEntry['memberof']) : 1) : 0,
                    'memberof' => $userEntry['memberof'] ?? null,
                ]);

                Log::channel('windows_auth')->info('LDAP user entry retrieved', [
                    'username' => $username,
                    'raw_entry' => $userEntry,
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
                    Log::channel('windows_auth')->warning('User not in required AD group', [
                        'username' => $username,
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

                Log::channel('windows_auth')->info('Authentication successful and user logged in', [
                    'username' => $username,
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
            Log::channel('windows_auth')->error('Windows Authentication Exception', [
                'username' => $username ?? null,
                'exception' => $e,
            ]);
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
            if (!is_string($group)) {
                continue;
            }

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
