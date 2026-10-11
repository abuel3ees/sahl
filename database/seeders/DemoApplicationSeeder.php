<?php

namespace Database\Seeders;

use App\Actions\Applications\ApproveApplication;
use App\Actions\Applications\RejectApplication;
use App\Actions\Applications\SubmitApplication;
use App\Enums\CertificateLanguage;
use App\Enums\CertificatePurpose;
use App\Models\Application;
use App\Models\GovEntity;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A realistic MOI review queue: requests of different ages (some past the SLA), plus one approved and one rejected.
 * Goes through the real actions so timelines and certificates exist. Safe to run again: it skips if MOI already has requests.
 */
class DemoApplicationSeeder extends Seeder
{
    /**
     * Fictional applicants: [national ID, English name, Arabic name].
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const APPLICANTS = [
        ['28763404821', 'Ahmed Al-Mansouri', 'أحمد المنصوري'],
        ['29163401177', 'Noora Al-Rashed', 'نورة الراشد'],
        ['28463409215', 'Khalid Al-Sayegh', 'خالد الصايغ'],
        ['29963402268', 'Fatima Al-Najjar', 'فاطمة النجار'],
        ['28863405530', 'Yousef Al-Harbi', 'يوسف الحربي'],
        ['30063407749', 'Maryam Al-Fardan', 'مريم الفردان'],
        ['28163403381', 'Hamad Al-Baker', 'حمد الباكر'],
    ];

    /**
     * [applicant index, days ago, purpose, addressed to, certificate language, outcome].
     *
     * @var array<int, array{0: int, 1: int, 2: CertificatePurpose, 3: string|null, 4: CertificateLanguage, 5: 'approve'|'reject'|null}>
     */
    private const REQUESTS = [
        [2, 9, CertificatePurpose::Employment, 'Al-Wahah National Bank', CertificateLanguage::Arabic, 'approve'],
        [3, 8, CertificatePurpose::Travel, 'Embassy of Andoria', CertificateLanguage::English, 'reject'],
        [4, 7, CertificatePurpose::Employment, 'Oasis Energy Company', CertificateLanguage::Arabic, null],
        [5, 6, CertificatePurpose::Education, 'Al-Wahah University', CertificateLanguage::English, null],
        [6, 4, CertificatePurpose::Residency, null, CertificateLanguage::Arabic, null],
        [0, 2, CertificatePurpose::Employment, 'Ministry of Finance', CertificateLanguage::Arabic, null],
        [1, 1, CertificatePurpose::Travel, null, CertificateLanguage::English, null],
        [2, 0, CertificatePurpose::Other, 'Al-Wahah Sports Club', CertificateLanguage::Arabic, null],
    ];

    public function run(SubmitApplication $submit, ApproveApplication $approve, RejectApplication $reject): void
    {
        $interior = GovEntity::where('code', 'MOI')->firstOrFail();

        if (Application::where('gov_entity_id', $interior->id)->exists()) {
            return;
        }

        $service = Service::where('code', Service::GOOD_CONDUCT)->firstOrFail();
        $reviewer = User::where('email', 'reviewer@moi.sahl.test')->firstOrFail();

        $applicants = collect(self::APPLICANTS)->map(fn (array $applicant): User => User::firstWhere('national_id', $applicant[0])
            ?? User::factory()->create([
                'national_id' => $applicant[0],
                'name' => $applicant[1],
                'name_ar' => $applicant[2],
                'email' => strtolower(explode(' ', $applicant[1])[0]).'.'.substr($applicant[0], -4).'@example.test',
            ]));

        foreach (self::REQUESTS as [$applicantIndex, $daysAgo, $purpose, $addressedTo, $language, $outcome]) {
            Carbon::setTestNow(now()->subDays($daysAgo)->setTime(8 + $applicantIndex, 15));

            $application = $submit->handle($applicants[$applicantIndex], $service, [
                'purpose' => $purpose->value,
                'addressed_to' => $addressedTo,
                'certificate_language' => $language->value,
            ]);

            Carbon::setTestNow(now()->addDay()->setTime(11, 30));

            match ($outcome) {
                'approve' => $approve->handle($application, $reviewer),
                'reject' => $reject->handle($application, $reviewer, 'The travel purpose needs the destination embassy’s request letter. Please apply again and attach it.'),
                null => null,
            };

            Carbon::setTestNow();
        }
    }
}
