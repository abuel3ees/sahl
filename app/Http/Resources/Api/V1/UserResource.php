<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => ['ar' => $this->name_ar, 'en' => $this->name],
            'email' => $this->email,
            'national_id' => $this->national_id,
            'gov_entity' => GovEntityResource::make($this->whenLoaded('govEntity')),
        ];
    }
}
