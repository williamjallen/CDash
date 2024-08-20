<?php

namespace App\Ldap\Models;

class User extends \LdapRecord\Models\OpenLDAP\User
{
    protected string $guidKey = 'uid';
}
