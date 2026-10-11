<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CertificateVerificationResource;
use App\Models\Certificate;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;

#[Group('Certificate verification', 'Public checks behind the QR code on every certificate.', weight: 3)]
class CertificateVerificationController extends Controller
{
    /**
     * Verify a certificate.
     *
     * Public endpoint behind the QR code. Anyone holding the code can confirm the
     * certificate is genuine. Only minimal, masked personal data is returned.
     *
     * @unauthenticated
     */
    #[Response(404, 'No certificate has this code.', type: 'array{message: string}')]
    #[Response(429, 'More than 30 checks per minute from this IP.', type: 'array{message: string}')]
    public function show(string $code): CertificateVerificationResource
    {
        $certificate = Certificate::where('verification_code', strtoupper($code))->firstOrFail();

        return CertificateVerificationResource::make($certificate);
    }
}
