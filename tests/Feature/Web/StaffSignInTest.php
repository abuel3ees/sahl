<?php

use App\Enums\UserType;
use App\Models\GovEntity;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * A user as the Entra provider returns it: `id` is the Entra object ID and `roles` comes from the validated ID token.
 *
 * @param  array<int, string>  $roles
 */
function entraUser(array $roles = ['MOI.Reviewer'], string $objectId = '0b7f6c2e-4d1a-4f3e-9a51-2c8d7e6f5a41'): SocialiteUser
{
    return SocialiteUser::fake([
        'id' => $objectId,
        'name' => 'Mariam Al-Kuwari',
        'email' => 'reviewer@sahl-test.onmicrosoft.com',
        'roles' => $roles,
    ]);
}

beforeEach(function () {
    $this->interior = GovEntity::factory()->interior()->create();
});

it('sends staff to their Entra tenant to sign in', function () {
    config([
        'services.entra.tenant' => 'f3a1c2d4-0000-4000-8000-000000000001',
        'services.entra.client_id' => 'a9b8c7d6-0000-4000-8000-000000000002',
        'services.entra.redirect' => 'http://localhost:8123/auth/staff/callback',
    ]);

    $location = $this->get('/auth/staff/redirect')->assertRedirect()->headers->get('Location');

    expect($location)
        ->toStartWith('https://login.microsoftonline.com/f3a1c2d4-0000-4000-8000-000000000001/oauth2/v2.0/authorize?')
        ->toContain('client_id=a9b8c7d6-0000-4000-8000-000000000002')
        ->toContain('response_type=code')
        ->toContain('scope=openid+profile+User.Read');
});

it('signs in a reviewer and creates their employee account on first sign-in', function () {
    Socialite::fake('entra', entraUser());

    $this->get('/auth/staff/callback?code=one-time-code')->assertRedirect(route('staff.applications.index'));

    $user = User::firstWhere('entra_object_id', '0b7f6c2e-4d1a-4f3e-9a51-2c8d7e6f5a41');

    expect($user)
        ->type->toBe(UserType::Employee)
        ->gov_entity_id->toBe($this->interior->id)
        ->password->toBeNull()
        ->isEmployee()->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('reuses the same account on later sign-ins and refreshes the name', function () {
    Socialite::fake('entra', entraUser());
    $this->get('/auth/staff/callback?code=first');
    auth()->logout();

    Socialite::fake('entra', SocialiteUser::fake([...entraUser()->getRaw(), 'name' => 'Mariam K.']));
    $this->get('/auth/staff/callback?code=second');

    expect(User::where('entra_object_id', '0b7f6c2e-4d1a-4f3e-9a51-2c8d7e6f5a41')->sole()->name)->toBe('Mariam K.');
});

it('refuses an Entra user without a reviewer role', function (array $roles) {
    Socialite::fake('entra', entraUser($roles));

    $this->get('/auth/staff/callback?code=one-time-code')->assertRedirect(route('login', ['as' => 'staff']));

    $this->assertGuest();
    expect(User::whereNotNull('entra_object_id')->exists())->toBeFalse();
    $this->get('/login?as=staff')->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('signInError', 'no_role'));
})->with([
    'no roles' => [[]],
    'another role' => [['MOI.Auditor']],
    'unknown ministry' => [['XYZ.Reviewer']],
]);

it('explains when the person cancels at Microsoft', function () {
    $this->get('/auth/staff/callback?error=access_denied&error_description=User+cancelled')
        ->assertRedirect(route('login', ['as' => 'staff']));

    $this->assertGuest();
    $this->get('/login?as=staff')->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('signInError', 'cancelled'));
});

it('refuses when the token exchange or ID token validation fails', function () {
    Socialite::fake('entra', fn () => throw new RuntimeException('id_token signature invalid'));

    $this->get('/auth/staff/callback?code=tampered')->assertRedirect(route('login', ['as' => 'staff']));

    $this->assertGuest();
    $this->get('/login?as=staff')->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('signInError', 'failed'));
});

it('shows the back office home to a signed-in employee', function () {
    $employee = User::factory()->employee($this->interior)->create(['entra_object_id' => '0b7f6c2e-4d1a-4f3e-9a51-2c8d7e6f5a41']);

    $this->actingAs($employee)
        ->get('/staff')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('staff/applications/index')
            ->where('reviewer.gov_entity.code', 'MOI')
            ->where('reviewer.signed_in_with', 'entra')
            ->where('auth.user.type', 'employee'));
});

it('keeps the back office from guests and citizens', function () {
    $this->get('/staff')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/staff')->assertForbidden();
});

it('signs out', function () {
    $this->actingAs(User::factory()->employee($this->interior)->create())
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
