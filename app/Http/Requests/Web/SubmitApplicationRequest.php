<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Api\V1\StoreApplicationRequest;
use App\Models\Service;

/**
 * The API's rules (service active, purpose, addressee, language, one open request per service),
 * plus the declaration a citizen ticks on the website.
 */
class SubmitApplicationRequest extends StoreApplicationRequest
{
    /**
     * The service comes from the URL on the website, not from the form body.
     */
    protected function prepareForValidation(): void
    {
        $service = $this->route('service');

        $this->merge(['service' => $service instanceof Service ? $service->slug : $service]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'declaration' => ['accepted'],
        ];
    }
}
