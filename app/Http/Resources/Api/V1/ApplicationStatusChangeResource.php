<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ApplicationStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationStatusChange
 */
class ApplicationStatusChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            /** Who acted. Reviewer identities are never exposed, only "applicant" or "ministry". */
            'actor' => $this->actor_id === $this->application->applicant_id ? 'applicant' : 'ministry',
            'note' => $this->note,
            'at' => $this->created_at,
        ];
    }
}
