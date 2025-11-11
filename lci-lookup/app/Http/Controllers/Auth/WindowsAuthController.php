<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SessionUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class WindowsAuthController extends Controller
{
    /**
     * Authenticate the user against Active Directory using the provided credentials.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Please enter your network username.',
            'password.required' => 'Please enter your password.',
        ]);

        $inputUsername = trim($validated['username']);
        $password = $validated['password'];
        $normalized = $this->normalizeUsername($inputUsername);
        $userDn = $this->buildUserDn($inputUsername);
        $logContext = [
            'input_username' => $inputUsername,
            'resolved_username' => $normalized['username'],
            'resolved_domain' => $normalized['domain'],
        ];

        Log::channel('windows_auth')->info('Login attempt started', $logContext);

        $ldapConfig = config('ldap.connections.default');
        $ldapServer = $ldapConfig['hosts'][0] ?? env('LDAP_HOST');
        $ldapPort = $ldapConfig['port'] ?? env('LDAP_PORT', 389);
        $baseDn = $ldapConfig['base_dn'] ?? env('LDAP_BASE_DN');

        if (!$ldapServer || !$baseDn) {
            Log::channel('windows_auth')->error('LDAP configuration missing host or base DN', $logContext);
            return back()->withErrors([
                'auth' => 'Directory service is not configured correctly. Please contact support.',
            ])->onlyInput('username');
        }

        $ldap = @ldap_connect($ldapServer, $ldapPort);

        if (!$ldap) {
            Log::channel('windows_auth')->error('Unable to connect to LDAP server', array_merge($logContext, [
                'server' => $ldapServer,
                'port' => $ldapPort,
            ]));

            return back()->withErrors([
                'auth' => 'Unable to reach the directory service. Please try again later.',
            ])->onlyInput('username');
        }

        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

        try {
            if (!@ldap_bind($ldap, $userDn, $password)) {
                $ldapError = ldap_error($ldap);
                Log::channel('windows_auth')->warning('User bind failed', array_merge($logContext, [
                    'bind_rdn' => $userDn,
                    'ldap_error' => $ldapError,
                ]));

                return back()->withErrors([
                    'auth' => 'Invalid username or password.',
                ])->onlyInput('username');
            }

            Log::channel('windows_auth')->info('User bind successful', array_merge($logContext, [
                'bind_rdn' => $userDn,
            ]));

            $filter = sprintf('(samAccountName=%s)', $this->escapeFilterValue($normalized['username']));
            $attributes = [
                'cn',
                'displayname',
                'samaccountname',
                'mail',
                'memberof',
                'userprincipalname',
                'givenname',
                'sn',
                'objectguid',
            ];

            $result = @ldap_search($ldap, $baseDn, $filter, $attributes);

            if (!$result) {
                Log::channel('windows_auth')->error('LDAP search failed', array_merge($logContext, [
                    'filter' => $filter,
                    'ldap_error' => ldap_error($ldap),
                ]));

                return back()->withErrors([
                    'auth' => 'We could not retrieve your directory information. Please try again.',
                ])->onlyInput('username');
            }

            $count = ldap_count_entries($ldap, $result);

            if ($count === 0) {
                Log::channel('windows_auth')->warning('No LDAP entries returned for user', array_merge($logContext, [
                    'filter' => $filter,
                ]));

                return back()->withErrors([
                    'auth' => 'We could not find your account in Active Directory.',
                ])->onlyInput('username');
            }

            $entries = ldap_get_entries($ldap, $result);
            $userEntry = $entries[0] ?? null;

            if (!$userEntry) {
                Log::channel('windows_auth')->error('LDAP entries were returned but the first entry is missing', array_merge($logContext, [
                    'filter' => $filter,
                ]));

                return back()->withErrors([
                    'auth' => 'We could not retrieve your directory information. Please try again.',
                ])->onlyInput('username');
            }

            Log::channel('windows_auth')->info('LDAP entry retrieved', array_merge($logContext, [
                'cn' => $userEntry['cn'][0] ?? null,
                'memberof_count' => $userEntry['memberof']['count'] ?? (isset($userEntry['memberof']) ? count(array_filter(array_keys($userEntry['memberof']), 'is_int')) : 0),
            ]));

            $requiredGroup = env('LDAP_REQUIRED_GROUP', 'g-app-webapp-cpdrlist');

            if (!$this->userInGroup($userEntry, $requiredGroup)) {
                Log::channel('windows_auth')->warning('User missing required group membership', array_merge($logContext, [
                    'required_group' => $requiredGroup,
                    'memberof' => $userEntry['memberof'] ?? null,
                ]));

                return back()->withErrors([
                    'auth' => 'You are not authorized to use this tool.',
                ])->onlyInput('username');
            }

            $sessionPayload = $this->mapLdapEntryToSessionUser($userEntry);
            $sessionUser = new SessionUser($sessionPayload);
            $request->session()->regenerate();
            $request->session()->put('auth.user', $sessionPayload);
            $request->session()->put('auth.username', $normalized['username']);
            $request->session()->put('auth.domain', $normalized['domain']);
            $request->session()->put('auth.guid', $sessionUser->getAuthIdentifier());
            Auth::setUser($sessionUser);

            Log::channel('windows_auth')->info('Authentication successful', array_merge($logContext, [
                'auth_identifier' => $sessionUser->getAuthIdentifier(),
            ]));

            return redirect()->intended(route('home'));
        } catch (Throwable $exception) {
            Log::channel('windows_auth')->error('Unexpected authentication error', array_merge($logContext, [
                'exception' => $exception->getMessage(),
            ]));

            return back()->withErrors([
                'auth' => 'An unexpected error occurred while attempting to sign you in.',
            ])->onlyInput('username');
        } finally {
            if (isset($ldap) && (is_resource($ldap) || $ldap instanceof \LDAP\Connection)) {
                @ldap_unbind($ldap);
            }
        }
    }

    /**
     * Determine whether the LDAP entry contains the required group membership.
     */
    private function userInGroup(array $entry, string $groupName): bool
    {
        if (!isset($entry['memberof'])) {
            return false;
        }

        $groupName = strtolower($groupName);
        $memberOf = $entry['memberof'];
        $groups = [];

        if (is_array($memberOf)) {
            if (isset($memberOf['count'])) {
                $count = (int) $memberOf['count'];
                for ($i = 0; $i < $count; $i++) {
                    if (isset($memberOf[$i]) && is_string($memberOf[$i])) {
                        $groups[] = $memberOf[$i];
                    }
                }
            } else {
                foreach ($memberOf as $value) {
                    if (is_string($value)) {
                        $groups[] = $value;
                    }
                }
            }
        } elseif (is_string($memberOf)) {
            $groups[] = $memberOf;
        }

        foreach ($groups as $groupDn) {
            if (stripos($groupDn, $groupName) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalise the provided username into domain + samAccountName parts.
     *
     * @return array{domain: string|null, username: string}
     */
    private function normalizeUsername(string $username): array
    {
        $username = trim($username);

        if (str_contains($username, '\\')) {
            [$domain, $name] = explode('\\', $username, 2);
            return [
                'domain' => $domain !== '' ? strtoupper($domain) : null,
                'username' => $name,
            ];
        }

        if (str_contains($username, '@')) {
            [$name, $domain] = explode('@', $username, 2);
            return [
                'domain' => $domain !== '' ? strtoupper(strtok($domain, '.')) : null,
                'username' => $name,
            ];
        }

        return [
            'domain' => null,
            'username' => $username,
        ];
    }

    /**
     * Build the bind DN (or UPN) for the incoming username.
     */
    private function buildUserDn(string $username): string
    {
        $username = trim($username);

        if (str_contains($username, '\\') || str_contains($username, '@')) {
            return $username;
        }

        $domain = env('LDAP_DOMAIN', 'LC');

        return "{$domain}\\{$username}";
    }

    /**
     * Escape a value for use inside an LDAP filter.
     */
    private function escapeFilterValue(string $value): string
    {
        if (function_exists('ldap_escape')) {
            return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
        }

        return strtr($value, [
            '\\' => '\\5c',
            '*' => '\\2a',
            '(' => '\\28',
            ')' => '\\29',
            "\0" => '\\00',
        ]);
    }

    /**
     * Reduce the raw LDAP entry to a session-friendly payload.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function mapLdapEntryToSessionUser(array $entry): array
    {
        return [
            'cn' => $this->extractFirstAttribute($entry, 'cn'),
            'displayname' => $this->extractFirstAttribute($entry, 'displayname'),
            'samaccountname' => $this->extractFirstAttribute($entry, 'samaccountname'),
            'mail' => $this->extractFirstAttribute($entry, 'mail'),
            'userprincipalname' => $this->extractFirstAttribute($entry, 'userprincipalname'),
            'givenname' => $this->extractFirstAttribute($entry, 'givenname'),
            'sn' => $this->extractFirstAttribute($entry, 'sn'),
            'objectguid' => $this->encodeGuid($this->extractFirstAttribute($entry, 'objectguid')),
            'memberof' => $this->normalizeMemberOf($entry['memberof'] ?? []),
        ];
    }

    /**
     * Safely extract the first value for a given attribute from an LDAP entry.
     *
     * @param  array<string, mixed>  $entry
     */
    private function extractFirstAttribute(array $entry, string $attribute): ?string
    {
        if (! isset($entry[$attribute])) {
            return null;
        }

        $value = $entry[$attribute];

        if (is_array($value)) {
            if (isset($value[0]) && is_string($value[0])) {
                return $value[0];
            }

            return null;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Normalise the memberOf attribute to an indexed array of strings.
     *
     * @param  mixed  $memberOf
     * @return array<int, string>
     */
    private function normalizeMemberOf(mixed $memberOf): array
    {
        if (is_array($memberOf)) {
            $values = [];

            if (isset($memberOf['count'])) {
                $count = (int) $memberOf['count'];

                for ($i = 0; $i < $count; $i++) {
                    if (isset($memberOf[$i]) && is_string($memberOf[$i]) && $memberOf[$i] !== '') {
                        $values[] = $memberOf[$i];
                    }
                }

                return $values;
            }

            foreach ($memberOf as $value) {
                if (is_string($value) && $value !== '') {
                    $values[] = $value;
                }
            }

            return $values;
        }

        if (is_string($memberOf) && $memberOf !== '') {
            return [$memberOf];
        }

        return [];
    }

    /**
     * Convert the binary GUID returned by LDAP to a hex string.
     */
    private function encodeGuid(?string $guid): ?string
    {
        if ($guid === null || $guid === '') {
            return null;
        }

        return bin2hex($guid);
    }
}
