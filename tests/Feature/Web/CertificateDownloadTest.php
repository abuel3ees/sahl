<?php

use App\Actions\Applications\ApproveApplication;
use App\Enums\CertificatePurpose;
use App\Models\Application;
use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->interior = GovEntity::factory()->interior()->create();
    $this->goodConduct = Service::factory()->goodConduct()->for($this->interior)->create();
    $this->reviewer = User::factory()->employee($this->interior)->create();
    $this->citizen = User::factory()->create(['national_id' => '28463409215', 'name' => 'Khalid Al-Sayegh', 'name_ar' => 'خالد الصايغ']);
    $this->application = Application::factory()->for($this->goodConduct)->create([
        'applicant_id' => $this->citizen->id,
        'form_data' => ['purpose' => 'employment', 'addressed_to' => 'Al-Wahah National Bank', 'certificate_language' => 'ar'],
    ]);
    app(ApproveApplication::class)->handle($this->application, $this->reviewer);
    $this->certificate = $this->application->certificate()->first();
});

it('gives the applicant their certificate as a PDF', function () {
    $response = $this->actingAs($this->citizen)->get("/applications/{$this->application->id}/certificate.pdf");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "inline; filename=\"{$this->certificate->serial_number}.pdf\"");
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

it('lets reviewers of the issuing ministry open it', function () {
    $this->actingAs($this->reviewer)->get("/applications/{$this->application->id}/certificate.pdf")->assertOk();
});

it('hides it from everyone else', function () {
    $this->get("/applications/{$this->application->id}/certificate.pdf")->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get("/applications/{$this->application->id}/certificate.pdf")->assertNotFound();

    $otherMinistry = User::factory()->employee(GovEntity::factory()->create(['code' => 'MUN']))->create();
    $this->actingAs($otherMinistry)->get("/applications/{$this->application->id}/certificate.pdf")->assertNotFound();
});

it('has nothing to download before the certificate is issued', function () {
    $waiting = Application::factory()->for($this->goodConduct)->create(['applicant_id' => $this->citizen->id]);

    $this->actingAs($this->citizen)->get("/applications/{$waiting->id}/certificate.pdf")->assertNotFound();
});

it('prints the snapshot in the language the applicant chose, with the verification link', function (string $language, array $expected) {
    $content = [...$this->certificate->content, 'language' => $language];

    $html = view('certificates.good-conduct', [
        'certificate' => $this->certificate->forceFill(['content' => $content]),
        'language' => $language,
        'holderName' => $language === 'ar' ? 'خالد الصايغ' : 'Khalid Al-Sayegh',
        'purpose' => CertificatePurpose::Employment->label()[$language],
        'addressedTo' => 'Al-Wahah National Bank',
        'verificationUrl' => route('verify.show', $this->certificate->verification_code),
    ])->render();

    expect($html)
        ->toContain($this->certificate->serial_number)
        ->toContain('28463409215')
        ->toContain($this->certificate->verification_code)
        ->toContain('type="QR"')
        ->toContain(url("/verify/{$this->certificate->verification_code}"))
        ->toContain('Al-Wahah National Bank');

    foreach ($expected as $text) {
        expect($html)->toContain($text);
    }
})->with([
    'Arabic' => ['ar', ['dir="rtl"', 'شهادة حسن السيرة والسلوك', 'ليس لديه سجل جنائي', 'نموذج تجريبي']],
    'English' => ['en', ['dir="ltr"', 'Good Conduct Certificate', 'has no criminal record', 'Fictional demonstration']],
]);
