<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

/**
 * Simple in-memory user for session storage.
 * Doesn't require database queries or LDAP lookups.
 */
class SessionUser implements Authenticatable
{
    use AuthenticatableTrait;

    protected $attributes = [];

    public function __construct($attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }

    public function getAuthIdentifierName()
    {
        return 'objectguid';
    }

    public function getAuthIdentifier()
    {
        $guid = $this->attributes['objectguid'] ?? null;

        if (is_array($guid)) {
            $guid = $guid[0] ?? null;
        }

        if (is_string($guid) && $guid !== '') {
            return $guid;
        }

        $samaccount = $this->attributes['samaccountname'] ?? null;

        if (is_array($samaccount)) {
            $samaccount = $samaccount[0] ?? null;
        }

        return $samaccount ?? null;
    }

    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return '';
    }

    public function setRememberToken($value)
    {
    }

    public function getRememberTokenName()
    {
        return '';
    }
    
    /**
     * Methods required for session restoration
     */
    public function getKey()
    {
        return $this->getAuthIdentifier();
    }
    
    public static function find($id)
    {
        // This won't be called in file-based sessions since objects are directly serialized
        // But keeping it here in case it's ever needed
        return null;
    }
    
    public function getAttributes()
    {
        return $this->attributes;
    }
    
    /**
     * Serialize the user for session storage
     */
    public function __serialize(): array
    {
        return $this->attributes;
    }
    
    /**
     * Unserialize the user from session storage
     */
    public function __unserialize(array $data): void
    {
        $this->attributes = $data;
    }
}
