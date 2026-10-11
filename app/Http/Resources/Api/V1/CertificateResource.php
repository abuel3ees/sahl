<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Certificate
 */
class CertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'serial_number' => $this->serial_number,
            'verification_code' => $this->verification_code,
            /** Public web page a third party (e.g. an employer) opens to check the certificate. Encoded in the QR code. Apps can call `GET /certificates/verify/{code}` instead. */
            'verification_url' => route('verify.show', $this->verification_code),
            'issued_at' => $this->issued_at,
            'expires_at' => $this->expires_at,
            'is_expired' => $this->isExpired(),
            'content' => $this->content,
        ];
    }
}
