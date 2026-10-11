<?php

namespace App\Providers;

use App\Auth\EntraExternalIdProvider;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-backoffice', fn (User $user): bool => $user->isEmployee());
        Gate::define('access-citizen-account', fn (User $user): bool => $user->isCitizen());

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by($request->input('login').'|'.$request->ip()));
        RateLimiter::for('verification', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));

        // Staff sign in with Entra ID (workforce), citizens with Entra External ID. Settings: config('services.entra*').
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('entra', MicrosoftProvider::class);
            $event->extendSocialite('entra_citizen', EntraExternalIdProvider::class);
        });

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }
}
