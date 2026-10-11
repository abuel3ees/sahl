<?php

namespace App\Http\Requests\Api\V1\Backoffice;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RejectApplicationRequest extends FormRequest
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
            /** Why the application was rejected. Shown to the applicant. */
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
