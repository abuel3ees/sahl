<?php

namespace App\Http\Controllers\Web\Citizen;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicationResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in citizen's home: who they are and their requests. Starting a new request comes next.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $citizen = $request->user();
        $applications = $citizen->applications()->with(['service.govEntity', 'certificate'])->latest('submitted_at')->get();

        return Inertia::render('citizen/account', [
            'citizen' => [
                'name' => $citizen->name,
                'name_ar' => $citizen->name_ar,
                'email' => $citizen->email,
                'national_id' => $citizen->national_id,
            ],
            'applications' => json_decode(ApplicationResource::collection($applications)->toJson(), true),
        ]);
    }
}
