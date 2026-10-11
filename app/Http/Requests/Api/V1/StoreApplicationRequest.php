<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ApplicationStatus;
use App\Enums\CertificateLanguage;
use App\Enums\CertificatePurpose;
use App\Models\Application;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Application::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Slug of the service to apply for. */
            'service' => ['required', 'string', Rule::exists('services', 'slug')->where('is_active', true)],
            'purpose' => ['required', Rule::enum(CertificatePurpose::class)],
            /** Who the certificate is addressed to. Leave empty for "To whom it may concern". */
            'addressed_to' => ['nullable', 'string', 'max:150'],
            'certificate_language' => ['required', Rule::enum(CertificateLanguage::class)],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $hasOpenApplication = $this->user()->applications()
                    ->where('service_id', $this->service()->id)
                    ->where('status', ApplicationStatus::Submitted)
                    ->exists();

                if ($hasOpenApplication) {
                    $validator->errors()->add('service', 'You already have an open application for this service.');
                }
            },
        ];
    }

    public function service(): Service
    {
        return Service::where('slug', $this->input('service'))->firstOrFail();
    }

    /**
     * @return array{purpose: string, addressed_to: string|null, certificate_language: string}
     */
    public function formData(): array
    {
        return [
            'purpose' => $this->input('purpose'),
            'addressed_to' => $this->input('addressed_to'),
            'certificate_language' => $this->input('certificate_language'),
        ];
    }
}
