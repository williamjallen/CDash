<?php

namespace App\Ldap\Models;

use LdapRecord\Models\Relations\HasManyIn;

class Group extends \LdapRecord\Models\OpenLDAP\Group
{
    protected string $guidKey = 'uid';

    public function members(): HasManyIn
    {
        return $this->hasManyIn([static::class, \App\Ldap\Models\User::class], 'uniquemember')->using($this, 'uniquemember');
    }
}
