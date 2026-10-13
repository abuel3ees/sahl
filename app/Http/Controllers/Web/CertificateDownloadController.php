<?php

namespace App\Http\Controllers\Web;

use App\Certificates\CertificatePdf;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The certificate as a PDF, for the applicant and for reviewers of the issuing ministry (ApplicationPolicy::view).
 * Anyone else gets a 404, like every other application route.
 */
class CertificateDownloadController extends Controller
{
    public function __invoke(Application $application, CertificatePdf $pdf): Response
    {
        Gate::authorize('view', $application);

        $certificate = $application->certificate()->firstOrFail();

        return response($pdf->render($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$certificate->serial_number}.pdf\"",
            // It carries the full National ID: never cache it on shared machines or proxies.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
