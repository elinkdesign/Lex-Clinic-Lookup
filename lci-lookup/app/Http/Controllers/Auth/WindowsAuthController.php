<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use LdapRecord\Models\ActiveDirectory\User;
use App\Models\LdapUser;

class WindowsAuthController extends Controller
{
    /**
     * Handle the incoming Windows authentication request
     */
    public function authenticate(Request $request)
    {
        // Get authenticated Windows username from server variables
        $username = $request->server('AUTH_USER') ?? $request->server('REMOTE_USER');
        
        // Clean up the username (remove domain prefix if present)
        if ($username && str_contains($username, '\\')) {
            $parts = explode('\\', $username);
            $username = end($parts);
        }
        
        if (!$username) {
            Log::warning('Windows Authentication: No username provided in server variables');
            return redirect()->route('login')->withErrors(['auth' => 'Windows authentication failed. Please contact support.']);
        }
        
        try {
            // Find the user in LDAP
            $ldapUser = LdapUser::where('samaccountname', '=', $username)->first();
            
            if (!$ldapUser) {
                Log::warning("Windows Authentication: User {$username} not found in LDAP");
                return redirect()->route('login')->withErrors(['auth' => 'User not found in directory.']);
            }
            
            // Login the user (without password as they're authenticated by Windows)
            Auth::login($ldapUser);
            
            Log::info("Windows Authentication: User {$username} authenticated successfully");
            return redirect()->intended('/');
            
        } catch (\Exception $e) {
            Log::error('Windows Authentication error: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['auth' => 'Authentication error. Please contact support.']);
        }
    }
}
