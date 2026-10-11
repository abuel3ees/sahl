<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IssueTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** National ID (citizens) or work email (employees). */
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            /** A label for the token, e.g. "postman" or "iphone". */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
