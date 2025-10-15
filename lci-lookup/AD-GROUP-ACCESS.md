# Active Directory Group Access Control

## Overview

The LCI Lookup Tool uses Active Directory group membership to control access. Only users who are members of the designated AD group can use the application.

## Configuration

**Required AD Group:** `g-app-webapp-cpdrlist`

### How It Works

1. Users authenticate via LDAP (Windows Authentication)
2. After successful authentication, the `CheckAdGroup` middleware verifies group membership
3. If the user is a member of `g-app-webapp-cpdrlist`, they gain access
4. If not, they receive a 403 Forbidden error

### Changing the Required Group

To change the required AD group, edit the middleware:

**File:** `app/Http/Middleware/CheckAdGroup.php`

```php
// Change this line (line 23):
if (!$this->userInGroup($user, 'g-app-webapp-cpdrlist')) {
    
// To your desired group:
if (!$this->userInGroup($user, 'your-new-group-name')) {
```

### Adding Multiple Groups

To allow access for users in **any** of multiple groups:

```php
private function userInGroup($user, array $allowedGroups): bool
{
    // Check multiple groups
    foreach ($allowedGroups as $groupName) {
        // ... (existing group check logic)
        if (/* user in this group */) {
            return true;
        }
    }
    return false;
}

// In the handle method:
if (!$this->userInGroup($user, ['group1', 'group2', 'group3'])) {
    abort(403, 'Access denied.');
}
```

### Disabling Group Check (Not Recommended for Production)

To temporarily disable the group check for testing:

1. Open `routes/web.php`
2. Remove `'ad.group'` from the middleware array:

```php
// Before:
Route::middleware(['auth', 'ad.group'])->group(function () {

// After (only auth required):
Route::middleware(['auth'])->group(function () {
```

**⚠️ Warning:** This allows any authenticated user to access the app. Only use for testing!

## Troubleshooting

### User Gets 403 Error Despite Being in Group

1. **Check Group Membership:**
   - Verify the user is actually in the AD group
   - Check in Active Directory Users and Computers
   - Or run: `gpresult /user USERNAME /r` on the server

2. **Check Case Sensitivity:**
   - The middleware does case-insensitive matching
   - Both `g-app-webapp-cpdrlist` and `G-APP-WEBAPP-CPDRLIST` should work

3. **Check LDAP Connection:**
   - Ensure LDAP credentials in `.env` have permission to read group membership
   - Test LDAP connection: `php artisan tinker` then test the user model

4. **Enable Debug Logging:**
   Edit `CheckAdGroup.php` and add logging:

   ```php
   use Illuminate\Support\Facades\Log;

   private function userInGroup($user, string $groupName): bool
   {
       Log::info('Checking group membership', [
           'user' => $user->username ?? $user->name,
           'required_group' => $groupName,
           'user_groups' => $user->memberof ?? 'N/A'
       ]);
       
       // ... rest of the method
   }
   ```

   Then check `storage/logs/laravel.log` for details.

### All Users Get 403 Error

- The middleware might not be finding group data correctly
- Check if `memberof` attribute is populated
- You may need to adjust the LDAP query or user model

## Testing

### Test Group Access Locally

```php
php artisan tinker

// Get a user
$user = \App\Models\User::where('username', 'test.user')->first();

// Check their groups
$user->memberof;

// Or if using LDAP relationships:
$user->groups()->get();
```

### Test with Different Users

1. Log in as a user who IS in the group → should work
2. Log in as a user who IS NOT in the group → should get 403
3. Check `storage/logs/laravel.log` for any errors

