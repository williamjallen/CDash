<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LdapUtils
{
    public static function syncUser(User $user): void
    {
//        if ($user->ldapguid === null) {
//            $ldap_user = null;
//        } else {
        Log::error('marker1');
        $ldap_user = \App\Ldap\Models\User::findBy('uid', $user->email);
//        }

        $projects = Project::with('users')->get();

        foreach ($projects as $project) {
            if ($project->ldapfilter === null) {
                continue;
            }

//            $matches_ldap_filter = $ldap_user !== null && $ldap_user->members()
//                ->recursive()
//                ->exists(\LdapRecord\Models\Entry::find($project->ldapfilter));

            Log::error('marker2');
            $matches_ldap_filter = \App\Ldap\Models\Group::find($project->ldapfilter);

            if (!($matches_ldap_filter instanceof \App\Ldap\Models\Group)) {
                continue;
            }
            Log::error('marker3');
            $matches_ldap_filter = $matches_ldap_filter
                ->members()
                ->recursive()
                ->exists($ldap_user);

            Log::error('marker4');

            $relationship_already_exists = $project->users->contains($user);

            if ($matches_ldap_filter && !$relationship_already_exists) {
                $project->users()->attach($user->id, ['role' => Project::PROJECT_USER]);
                Log::info("Added user $user->email to project $project->name.");
            } elseif (!$matches_ldap_filter && $relationship_already_exists) {
                $project->users()->detach($user->id);
                Log::info("Removed user $user->email from project $project->name.");
            }
        }
    }
}
