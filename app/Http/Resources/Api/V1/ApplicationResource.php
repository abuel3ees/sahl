<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'service' => ServiceResource::make($this->whenLoaded('service')),
            'applicant' => UserResource::make($this->whenLoaded('applicant')),
            /** @var 'employment'|'travel'|'residency'|'education'|'other' */
            'purpose' => $this->form_data['purpose'],
            /** @var string|null */
            'addressed_to' => $this->form_data['addressed_to'] ?? null,
            /** @var 'ar'|'en' */
            'certificate_language' => $this->form_data['certificate_language'],
            'submitted_at' => $this->submitted_at,
            /** Service level: working days allowed, working days waited so far (Friday and Saturday excluded), and whether it is late. */
            'sla' => $this->whenLoaded('service', fn (): array => [
                'working_days' => $this->service->sla_working_days,
                'working_days_waiting' => $this->workingDaysWaiting(),
                'is_overdue' => $this->isOverdue(),
            ]),
            'decided_at' => $this->decided_at,
            'decision_note' => $this->decision_note,
            'timeline' => ApplicationStatusChangeResource::collection($this->whenLoaded('statusChanges')),
            'certificate' => CertificateResource::make($this->whenLoaded('certificate')),
        ];
    }
}
