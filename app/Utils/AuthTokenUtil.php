<?php

declare(strict_types=1);

namespace App\Utils;

use App\Models\AuthToken;
use App\Models\User;
use CDash\Model\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AuthTokenUtil
{
    /**
     * Contract: we assume that $user_id has already been validated and blindly create a token
     * for the user specified.  It is the responsibility of anyone who uses this function to
     * ensure that the $user_id has been properly authenticated.
     *
     * @return array{raw_token: string, token: AuthToken}
     *
     * @throws InvalidArgumentException
     */
    public static function generateToken(int $user_id, int $project_id, string $scope, string $description): array
    {
        // 86 characters generates more than 512 bits of entropy (and is thus limited by the entropy of the hash)
        $token = Str::password(86, true, true, false);
        $params['hash'] = hash('sha512', $token);

        $params['userid'] = $user_id;

        $duration = Config::get('cdash.token_duration');
        $now = time();
        $params['created'] = gmdate(FMT_DATETIME, $now);

        if (!is_numeric($duration) || intval($duration) < 0) {
            Log::error("Invalid token_duration configuration {$duration}");
            throw new InvalidArgumentException('Invalid token_duration configuration');
        }

        if (intval($duration) === 0) {
            // this token "never" expires
            $params['expires'] = '9999-01-01 00:00:00';
        } else {
            $params['expires'] = gmdate(FMT_DATETIME, $now + $duration);
        }

        $params['description'] = $description;

        if (!self::validScope($scope)) {
            Log::error("Invalid token scope {$scope}");
            throw new InvalidArgumentException("Invalid token scope {$scope}");
        }
        if ($scope === AuthToken::SCOPE_FULL_ACCESS && Config::get('cdash.allow_full_access_tokens') !== true) {
            Log::error('Full-access tokens are prohibited by config');
            throw new InvalidArgumentException('Full-access tokens are prohibited by config');
        }
        if ($scope === AuthToken::SCOPE_SUBMIT_ONLY && $project_id < 0
                && Config::get('cdash.allow_submit_only_tokens') !== true) {
            Log::error('Only project-specific submit-only tokens allowed by config');
            throw new InvalidArgumentException('Only project-specific submit-only tokens allowed by config');
        }
        $params['scope'] = $scope;

        $params['projectid'] = $scope === AuthToken::SCOPE_SUBMIT_ONLY && $project_id > -1 ? $project_id : null;

        $project = new Project();
        $project->Id = $project_id;
        $project->Fill();
        if ($project_id >= 0 && !Gate::allows('view-project', $project)) {
            Log::error('Permissions error');
            throw new InvalidArgumentException('Permissions error');
        }

        $auth_token = AuthToken::create($params);
        return [
            'raw_token' => $token,
            'token' => $auth_token,
        ];
    }

    /**
     * Accepts a hashed token and a project, and decides whether the token is valid for the
     * specified project and associated user.
     */
    public static function checkToken(string $token_hash, int $project_id): bool
    {
        $auth_token = AuthToken::find($token_hash);
        if ($auth_token === null) {
            Log::error('Invalid Token');
            return false;
        }

        $user = User::find($auth_token['userid']);
        if ($user === null) {
            Log::error('Invalid UserId');
            return false;
        }

        // Check for token expiration, deleting the token if expired
        if (self::isTokenExpired($auth_token)) {
            Log::error('Invalid Token');
            return false;
        }

        $project = \App\Models\Project::find($project_id);
        if ($project === null || Gate::forUser($user)->denies('view', $project)) {
            Log::error('Invalid Project');
            return false;
        }

        switch ($auth_token['scope']) {
            case AuthToken::SCOPE_SUBMIT_ONLY:
                // If a token is submit-only and is project-specific, make sure it matches the right project
                if ($auth_token['projectid'] !== null && $project_id !== $auth_token['projectid']) {
                    Log::error('Invalid Project');
                    return false;
                }
                if (($auth_token['projectid'] === null || $project_id !== $auth_token['projectid'])
                        && Config::get('cdash.allow_submit_only_tokens') !== true) {
                    Log::error('Submit-only token used when disallowed by config');
                    return false;
                }
                break;
            case AuthToken::SCOPE_FULL_ACCESS:
                if (Config::get('cdash.allow_full_access_tokens') !== true) {
                    Log::error('Full-access token used when disallowed by config');
                    return false;
                }
                break;
            default:
                // In theory, this case should never be possible
                Log::error("Invalid scope listed for auth token with hash {$token_hash}");
                throw new RuntimeException("Invalid scope listed for auth token with hash {$token_hash}");
        }

        return true;
    }

    /**
     * Accepts the hash of a token and the user we expect it be associated with (ID obtained for
     * the current user via the Auth class).  The function returns a boolean indicating whether
     * The token was successfully deleted.
     */
    public static function deleteToken(string $token_hash, int $expected_user_id): bool
    {
        $auth_token = AuthToken::find($token_hash);
        if ($auth_token === null) {
            Log::error('Invalid Token');
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        switch ($auth_token['scope']) {
            case AuthToken::SCOPE_SUBMIT_ONLY:
                if ($auth_token['projectid'] !== null) {
                    // Project-scoped submit-only tokens can be deleted by:
                    // 1. The user who created them
                    // 2. A project administrator
                    // 3. A system administrator

                    $project = \App\Models\Project::findOrFail((int) $auth_token['projectid']);

                    if (
                        $expected_user_id !== $auth_token['userid']
                        && $project->administrators()->find($user->id) === null
                        && !$user->admin
                    ) {
                        return false;
                    }
                    break;
                }
                // Submit-only tokens with access to all projects have the same deletion requirements
                // as full-access tokens (thus we continue into the next case without breaking).
                // no break
            case AuthToken::SCOPE_FULL_ACCESS:
                // Full-access tokens can be deleted by:
                // 1. The user who created them
                // 2. A system administrator
                if ($expected_user_id !== $auth_token['userid'] && !$user->admin) {
                    return false;
                }
                break;
            default:
                // In theory, this case should never be possible
                Log::error("Invalid scope listed for auth token with hash {$token_hash}");
                throw new RuntimeException("Invalid scope listed for auth token with hash {$token_hash}");
        }
        return $auth_token->delete() > 0;
    }

    /**
     * Contract: we assume that $user_id has already been validated and blindly return a list of
     * auth tokens for the user requested.  It is your responsibility as a user of this method
     * to ensure that $user_id is validated appropriately.
     *
     * @return Collection<int,AuthToken>
     */
    public static function getTokensForUser(int $user_id): Collection
    {
        return AuthToken::select('authtoken.*', 'project.name AS projectname')
            ->leftJoin('project', 'project.id', '=', 'authtoken.projectid')
            ->where('authtoken.userid', '=', $user_id)
            ->get();
    }

    /**
     * Contract: we assume that the user has already been validated and blindly return a list of
     * all auth tokens.  It is your responsibility as a user of this method to ensure that only
     * administrators can access it.
     *
     * @return Collection<int,AuthToken>
     */
    public static function getAllTokens(): Collection
    {
        return AuthToken::select('authtoken.*', 'project.name AS projectname', 'users.firstname AS owner_firstname', 'users.lastname AS owner_lastname')
            ->leftJoin('project', 'project.id', '=', 'authtoken.projectid')
            ->leftJoin('users', 'users.id', '=', 'authtoken.userid')
            ->get();
    }

    public static function hashToken(?string $unhashed_token): string
    {
        if ($unhashed_token === null || $unhashed_token === '') {
            return '';
        }

        return hash('sha512', $unhashed_token);
    }

    /**
     * Check if the specified AuthToken is expired and delete it if so
     */
    public static function isTokenExpired(AuthToken $auth_token): bool
    {
        if ($auth_token['expires'] < Carbon::now()) {
            $auth_token->delete();
            return true;
        }
        return false;
    }

    public static function validScope(string $scope): bool
    {
        return $scope === AuthToken::SCOPE_FULL_ACCESS || $scope === AuthToken::SCOPE_SUBMIT_ONLY;
    }

    /**
     * Checks for the presence of a bearer token and returns the user ID associated with
     * that token if applicable.  Returns null if no bearer token or invalid bearer token observed.
     */
    public static function getUserIdFromRequest(): ?int
    {
        $token_hash = self::hashToken(self::getBearerToken());
        if ($token_hash === '') {
            return null;
        }

        $auth_token = AuthToken::find($token_hash);
        if ($auth_token === null
            || self::isTokenExpired($auth_token)
            || $auth_token['scope'] !== AuthToken::SCOPE_FULL_ACCESS
        ) {
            return null;
        }

        return $auth_token['userid'];
    }

    /**
     * Get access token from header.
     */
    public static function getBearerToken(): ?string
    {
        return request()->bearerToken();
    }
}
