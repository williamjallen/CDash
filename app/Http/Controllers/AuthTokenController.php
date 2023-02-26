<?php
namespace App\Http\Controllers;

require_once 'include/common.php';
require_once 'include/defines.php';

use App\Services\AuthTokenService;
use Symfony\Component\HttpFoundation\Response;

class AuthTokenController extends AbstractController {
    public function manage() {
        if (!\Auth::check()) {
            return $this->redirectToLogin();
        }

        return response()->view('admin.manage-authtokens', [
            'title' => 'CDash - Manage Authentication Tokens'
        ]);
    }

    /**
     * Get all of the authentication tokens available across the entire system.
     * This method is only available to administrators.
     */
    public function fetchAll() {
        $user = \Auth::user();
        if ($user === null || !$user->IsAdmin()) {
            return response()->json('Permissions error', status: Response::HTTP_FORBIDDEN);
        }

        $token_array = AuthTokenService::getAllTokens();
        $token_map = [];
        foreach ($token_array as $token) {
            $token_map[$token['hash']] = $token;
        }

        return response()->json([
            'tokens' => $token_map
        ]);
    }

    /**
     *
     */
    public function deleteToken(string $token_hash) {
        if (!AuthTokenService::deleteToken($token_hash, \Auth::id())) {
            return response()->json('Permissions error', status: Response::HTTP_FORBIDDEN);
        }
        return response()->json('Token deleted');
    }
}
