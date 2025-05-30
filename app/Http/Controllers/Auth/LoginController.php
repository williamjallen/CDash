<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AbstractController;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LdapRecord\Laravel\Auth\ListensForLdapBindFailure;
use Symfony\Component\HttpFoundation\Response;

final class LoginController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers {
        login as traitLogin;
        credentials as traitCredentials;
    }

    use ListensForLdapBindFailure;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->maxAttempts = config('cdash.login.max_attempts', 5);
        $this->decayMinutes = config('cdash.login.lockout.duration', 1);

        $this->listenForLdapBindFailure();
    }

    /**
     * Handle a login request to the application.
     *
     * @return RedirectResponse|\Illuminate\Http\Response|JsonResponse|Response
     *
     * @throws ValidationException
     */
    public function login(Request $request)
    {
        if (config('cdash.username_password_authentication_enabled') === false) {
            return $this->sendFailedLoginResponse($request);
        }
        return $this->traitLogin($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(Request $request): array
    {
        if ((bool) config('cdash.ldap_enabled')) {
            return [
                (string) env('LDAP_LOCATE_USERS_BY', 'mail') => $request->post('email'),
                'password' => $request->post('password'),
                'fallback' => $this->traitCredentials($request),
            ];
        } else {
            return $this->traitCredentials($request);
        }
    }

    /**
     * Get the failed login response instance.
     *
     * @throws ValidationException
     */
    protected function sendFailedLoginResponse(Request $request): Response
    {
        $e = ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);

        // Seems like this should be the default way to deal with failed response vs
        // Laravel's redirect with a status of 302 :(
        $e->status(401)
            ->response = response()
                ->view('auth.login', [
                    'errors' => $e->validator->getMessageBag(),
                    'title' => 'Login',
                ],
                    401
                );
        throw $e;
    }

    public function redirectTo(): string
    {
        return session('url.intended') ?? '/';
    }

    public static function saml2Login(): Response|RedirectResponse
    {
        if (config('saml2.enabled') === false) {
            return response('SAML2 login is not enabled.', 500);
        }

        // Check if the saml2 table exists.
        if (!Schema::hasTable('saml2_tenants')) {
            return response('saml2_tenants table not found.', 500);
        }

        // Check if there is a row in the saml2_tenants table.
        $saml2_tenants_row = DB::table('saml2_tenants')->first();
        if ($saml2_tenants_row === null) {
            return response('SAML2 tenant not found.', 500);
        }

        // Redirect to SAML2 login URL.
        // Note that we currently one support one SAML2 IdP
        // per CDash instance.
        return redirect(saml_url('/projects', $saml2_tenants_row->uuid));
    }
}
