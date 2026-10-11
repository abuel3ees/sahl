<?php

namespace App\Http\Resources\Api\V1;

use App\Models\GovEntity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GovEntity
 */
class GovEntityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => ['ar' => $this->name_ar, 'en' => $this->name_en],
        ];
    }
}
