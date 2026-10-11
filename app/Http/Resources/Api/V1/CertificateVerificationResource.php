<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * The public view of a certificate: just enough to confirm it is genuine.
 *
 * @mixin Certificate
 */
class CertificateVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** "valid" or "expired". */
            'status' => $this->isExpired() ? 'expired' : 'valid',
            'serial_number' => $this->serial_number,
            'service' => ['ar' => $this->content['service_name_ar'], 'en' => $this->content['service_name_en']],
            'issuer' => ['ar' => $this->content['issuer_name_ar'], 'en' => $this->content['issuer_name_en']],
            'holder_name' => ['ar' => $this->content['holder_name_ar'], 'en' => $this->content['holder_name_en']],
            /** Only the last 4 digits are shown, e.g. "*******4821". */
            'holder_national_id_masked' => Str::mask((string) $this->content['holder_national_id'], '*', 0, -4),
            'issued_at' => $this->issued_at,
            'expires_at' => $this->expires_at,
        ];
    }
}
