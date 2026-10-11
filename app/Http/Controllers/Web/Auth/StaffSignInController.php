<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Auth\SignInStaffWithEntra;
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
 * Ministry staff sign in with Microsoft Entra ID (OpenID Connect, authorization code flow).
 */
class StaffSignInController extends Controller
{
    /**
     * Send the browser to Microsoft's sign-in page.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('entra')->redirect();
    }

    /**
     * Microsoft sends the browser back here with a one-time code. Socialite swaps it for tokens
     * server to server and validates the ID token; we then require a reviewer app role.
     */
    public function callback(Request $request, SignInStaffWithEntra $signIn): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->refuse('cancelled', $request->string('error_description')->toString());
        }

        try {
            $entraUser = Socialite::driver('entra')->user();
        } catch (Throwable $exception) {
            return $this->refuse('failed', $exception->getMessage());
        }

        $user = $signIn->handle($entraUser);

        if ($user === null) {
            return $this->refuse('no_role', "Entra user {$entraUser->getId()} has no reviewer role for a known ministry.");
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('staff.applications.index'));
    }

    private function refuse(string $reason, string $detail): RedirectResponse
    {
        Log::warning('Staff sign-in refused', ['reason' => $reason, 'detail' => $detail]);

        Inertia::flash('signInError', $reason);

        return redirect()->route('login', ['as' => 'staff']);
    }
}
