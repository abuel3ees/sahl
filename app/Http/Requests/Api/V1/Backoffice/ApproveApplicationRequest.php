<?php

namespace App\Http\Requests\Api\V1\Backoffice;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApproveApplicationRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('decide', $this->route('application'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Optional internal note, visible to the applicant. */
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
