<?php

use App\Enums\UserType;
use App\Models\Application;
use App\Models\GovEntity;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;

const CITIZEN_OID = '6f1c2a3b-0000-4000-8000-0000000000aa';

/**
 * A citizen as the External ID driver returns them: claims from the validated ID token.
 *
 * @param  array<string, mixed>  $claims
 */
function citizenFromEntra(array $claims = []): SocialiteUser
{
    $claims = ['oid' => CITIZEN_OID, 'name' => 'Khalid Al-Sayegh', 'email' => 'khalid@example.com', 'national_id' => '27063408815', ...$claims];

    return (new SocialiteUser)->setRaw($claims)->map(['id' => $claims['oid'], 'name' => $claims['name'], 'email' => $claims['email']]);
}

function signInAsCitizen(array $claims = []): TestResponse
{
    Socialite::fake('entra_citizen', citizenFromEntra($claims));

    return test()->get('/auth/citizen/callback?code=one-time-code');
}

it('signs up a new citizen with the National ID from their Sahl ID', function () {
    signInAsCitizen()->assertRedirect(route('account'));

    $citizen = User::firstWhere('entra_object_id', CITIZEN_OID);
    expect($citizen)
        ->type->toBe(UserType::Citizen)
        ->national_id->toBe('27063408815')
        ->email->toBe('khalid@example.com')
        ->password->toBeNull();
    $this->assertAuthenticatedAs($citizen);
});

it('reuses the account on later sign-ins', function () {
    signInAsCitizen();
    auth()->logout();
    signInAsCitizen(['name' => 'Khalid S.']);

    expect(User::where('entra_object_id', CITIZEN_OID)->sole()->name)->toBe('Khalid S.');
});

it('lets the first Sahl ID with a seeded citizen’s National ID claim that record', function () {
    $seeded = User::factory()->create(['national_id' => '28763404821', 'name' => 'Ahmed Al-Mansouri']);
    $application = Application::factory()->create(['applicant_id' => $seeded->id]);

    signInAsCitizen(['national_id' => '28763404821', 'name' => 'Ahmed Al-Mansouri'])->assertRedirect(route('account'));

    expect($seeded->refresh()->entra_object_id)->toBe(CITIZEN_OID);
    $this->assertAuthenticatedAs($seeded);
    $this->get('/account')->assertInertia(fn (AssertableInertia $page) => $page->where('applications.0.id', $application->id));
});

it('refuses a National ID that another Sahl ID already holds', function () {
    User::factory()->create(['national_id' => '28763404821', 'entra_object_id' => '11111111-0000-4000-8000-000000000000']);

    signInAsCitizen(['national_id' => '28763404821'])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::where('entra_object_id', CITIZEN_OID)->exists())->toBeFalse();
    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('signInError', 'national_id_taken'));
});

it('refuses a Sahl ID without a valid National ID', function (?string $nationalId) {
    signInAsCitizen(['national_id' => $nationalId])->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('signInError', 'no_national_id'));
})->with([
    'missing' => [null],
    'too short' => ['2876340'],
    'not digits' => ['2876340482X'],
]);

it('signs in without the email when another account already uses it', function () {
    User::factory()->employee(GovEntity::factory()->interior()->create())->create(['email' => 'khalid@example.com']);

    signInAsCitizen()->assertRedirect(route('account'));

    expect(User::firstWhere('entra_object_id', CITIZEN_OID)->email)->toBeNull();
});

it('explains a cancelled or failed sign-in', function () {
    $this->get('/auth/citizen/callback?error=access_denied')->assertRedirect(route('login'));
    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('signInError', 'cancelled'));

    Socialite::fake('entra_citizen', fn () => throw new RuntimeException('ID token signature is invalid'));
    $this->get('/auth/citizen/callback?code=tampered')->assertRedirect(route('login'));
    $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('signInError', 'failed'));
});

it('shows a citizen only their own requests', function () {
    $citizen = User::factory()->create();
    $mine = Application::factory()->create(['applicant_id' => $citizen->id]);
    Application::factory()->create();

    $this->actingAs($citizen)
        ->get('/account')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('citizen/account')
            ->where('citizen.national_id', $citizen->national_id)
            ->has('applications', 1)
            ->where('applications.0.id', $mine->id));
});

it('keeps guests and staff out of the citizen account page', function () {
    $this->get('/account')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->employee()->create())->get('/account')->assertForbidden();
});
