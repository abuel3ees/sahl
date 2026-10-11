<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CertificateVerificationResource;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The public page behind the QR code: anyone (an employer, an embassy) checks that a certificate is genuine.
 * No sign-in. Shows the same masked data as the API and shares its rate limit.
 */
class CertificateVerificationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('verify/index');
    }

    public function show(Request $request, string $code): HttpResponse
    {
        $code = strtoupper($code);
        $certificate = Certificate::where('verification_code', $code)->first();

        return Inertia::render('verify/show', [
            'code' => $code,
            // Same shape as GET /api/v1/certificates/verify/{code}; null when no certificate has this code.
            'certificate' => $certificate ? json_decode(CertificateVerificationResource::make($certificate)->toJson(), true) : null,
        ])->toResponse($request)->setStatusCode($certificate ? 200 : 404);
    }
}
