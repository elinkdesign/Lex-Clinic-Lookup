<?php

namespace App\Models;

use LdapRecord\Models\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;

class LdapUser extends Model implements Authenticatable
{
    use CanAuthenticate;

    // The object classes of the LDAP model
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'user',
    ];

    // The LDAP connection to use for this model
    protected ?string $connection = 'default';

    // The attributes that should be hidden for arrays
    protected array $hidden = [
        'userpassword',
        'unicodepwd',
    ];

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'objectguid';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->getFirstAttribute($this->getAuthIdentifierName());
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Get the "remember me" token value.
     */
    public function getRememberToken(): string
    {
        return '';
    }

    /**
     * Set the "remember me" token value.
     */
    public function setRememberToken($value): void
    {
        // Not implemented
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}
