<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'slug' => $this->slug,
            'name' => ['ar' => $this->name_ar, 'en' => $this->name_en],
            'description' => ['ar' => $this->description_ar, 'en' => $this->description_en],
            /** Fee in minor units (dirhams). 5000 = QAR 50.00. */
            'fee' => ['amount_minor' => $this->fee_minor, 'currency' => $this->currency],
            'sla_working_days' => $this->sla_working_days,
            'validity_days' => $this->validity_days,
            'gov_entity' => GovEntityResource::make($this->whenLoaded('govEntity')),
        ];
    }
}
