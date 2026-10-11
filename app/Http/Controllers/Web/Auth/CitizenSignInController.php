<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Auth\SignInCitizenWithEntra;
use App\Actions\Auth\SignInRefused;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Citizens sign up and sign in with Microsoft Entra External ID (OpenID Connect, authorization code flow).
 */
class CitizenSignInController extends Controller
{
    /**
     * Send the browser to the Sahl Citizens sign-up/sign-in pages.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('entra_citizen')->redirect();
    }

    public function callback(Request $request, SignInCitizenWithEntra $signIn): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->refuse('cancelled', $request->string('error_description')->toString());
        }

        try {
            $entraUser = Socialite::driver('entra_citizen')->user();
        } catch (Throwable $exception) {
            return $this->refuse('failed', $exception->getMessage());
        }

        // Claim names only (never values): handy when checking the token configuration in Entra.
        Log::debug('Citizen sign-in claims', ['claims' => array_keys($entraUser->getRaw())]);

        try {
            $citizen = $signIn->handle($entraUser);
        } catch (SignInRefused $refused) {
            return $this->refuse($refused->reason, $refused->getMessage());
        }

        Auth::login($citizen);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    private function refuse(string $reason, string $detail): RedirectResponse
    {
        Log::warning('Citizen sign-in refused', ['reason' => $reason, 'detail' => $detail]);

        Inertia::flash('signInError', $reason);

        return redirect()->route('login');
    }
}
