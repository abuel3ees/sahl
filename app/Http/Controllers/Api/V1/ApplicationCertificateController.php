<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CertificateResource;
use App\Models\Application;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Support\Facades\Gate;

#[Group('Applications', 'Applications submitted by the signed-in citizen.', weight: 2)]
class ApplicationCertificateController extends Controller
{
    /**
     * Show an application's certificate.
     *
     * Returns 404 until the application is approved.
     */
    #[Response(404, 'Not approved yet, or not your application.', type: 'array{message: string}')]
    public function show(Application $application): CertificateResource
    {
        Gate::authorize('view', $application);

        $certificate = $application->certificate()->firstOrFail();

        return CertificateResource::make($certificate);
    }
}
