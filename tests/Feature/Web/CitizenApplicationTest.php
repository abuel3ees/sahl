<?php

use App\Actions\Applications\ApproveApplication;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->interior = GovEntity::factory()->interior()->create();
    $this->goodConduct = Service::factory()->goodConduct()->for($this->interior)->create();
    $this->citizen = User::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function requestForm(array $overrides = []): array
{
    return ['purpose' => 'employment', 'addressed_to' => 'Oasis Energy Company', 'certificate_language' => 'en', 'declaration' => true, ...$overrides];
}

it('shows the request form with the service facts', function () {
    $this->actingAs($this->citizen)
        ->get('/services/good-conduct-certificate/apply')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('citizen/apply')
            ->where('service.slug', 'good-conduct-certificate')
            ->where('service.sla_working_days', 3)
            ->where('openApplication', null));
});

it('sends guests to sign in first and brings them back to the form', function () {
    $this->get('/services/good-conduct-certificate/apply')->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(url('/services/good-conduct-certificate/apply'));
});

it('keeps staff away from the citizen form', function () {
    $this->actingAs(User::factory()->employee($this->interior)->create())
        ->get('/services/good-conduct-certificate/apply')
        ->assertForbidden();
});

it('hides inactive services', function () {
    $this->goodConduct->update(['is_active' => false]);

    $this->actingAs($this->citizen)->get('/services/good-conduct-certificate/apply')->assertNotFound();
});

it('submits a request and opens it for the citizen to follow', function () {
    $response = $this->actingAs($this->citizen)->post('/services/good-conduct-certificate/applications', requestForm());

    $application = Application::sole();
    $response->assertRedirect(route('account.applications.show', $application));

    expect($application)
        ->applicant_id->toBe($this->citizen->id)
        ->status->toBe(ApplicationStatus::Submitted)
        ->form_data->toBe(['purpose' => 'employment', 'addressed_to' => 'Oasis Energy Company', 'certificate_language' => 'en']);

    $this->get(route('account.applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('citizen/application')
            ->hasFlash('submitted', true)
            ->where('application.reference_number', $application->reference_number)
            ->has('application.timeline', 1)
            ->where('decisionDueBy', fn (?string $date) => $date !== null));
});

it('treats an empty addressee as “to whom it may concern”', function () {
    $this->actingAs($this->citizen)->post('/services/good-conduct-certificate/applications', requestForm(['addressed_to' => null]));

    expect(Application::sole()->form_data['addressed_to'])->toBeNull();
});

it('validates the form', function (array $overrides, string $field) {
    $this->actingAs($this->citizen)
        ->post('/services/good-conduct-certificate/applications', requestForm($overrides))
        ->assertSessionHasErrors($field);

    expect(Application::count())->toBe(0);
})->with([
    'no declaration' => [['declaration' => false], 'declaration'],
    'no purpose' => [['purpose' => null], 'purpose'],
    'unknown purpose' => [['purpose' => 'holiday'], 'purpose'],
    'unknown language' => [['certificate_language' => 'fr'], 'certificate_language'],
    'addressee too long' => [['addressed_to' => str_repeat('a', 151)], 'addressed_to'],
]);

it('allows one open request per service', function () {
    $open = Application::factory()->for($this->goodConduct)->create(['applicant_id' => $this->citizen->id]);

    $this->actingAs($this->citizen)
        ->post('/services/good-conduct-certificate/applications', requestForm())
        ->assertSessionHasErrors('service');

    $this->get('/services/good-conduct-certificate/apply')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('openApplication.id', $open->id));
});

it('shows the certificate once the request is approved', function () {
    $application = Application::factory()->for($this->goodConduct)->create(['applicant_id' => $this->citizen->id]);
    app(ApproveApplication::class)->handle($application, User::factory()->employee($this->interior)->create());

    $this->actingAs($this->citizen)
        ->get(route('account.applications.show', $application))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('application.status', 'approved')
            ->where('application.certificate.verification_url', fn (string $url) => str_contains($url, '/verify/'))
            ->where('decisionDueBy', null));
});

it('hides other citizens’ requests behind a 404', function () {
    $someoneElses = Application::factory()->for($this->goodConduct)->create();

    $this->actingAs($this->citizen)->get(route('account.applications.show', $someoneElses))->assertNotFound();
});

it('gives the decision deadline in working days, skipping Friday and Saturday', function () {
    $application = Application::factory()->for($this->goodConduct)->create(['submitted_at' => '2026-10-08 09:00']); // a Thursday

    expect($application->decisionDueBy()->toDateString())->toBe('2026-10-13'); // Sun, Mon, Tue
});
