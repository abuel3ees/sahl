<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Applications\SubmitApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreApplicationRequest;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Models\Application;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Applications', 'Applications submitted by the signed-in citizen.', weight: 2)]
class ApplicationController extends Controller
{
    /**
     * List my applications.
     *
     * The signed-in citizen's applications, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $applications = $request->user()->applications()
            ->with('service')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(20);

        return ApplicationResource::collection($applications);
    }

    /**
     * Submit an application.
     *
     * Only citizens can apply, and only one open application per service is allowed.
     */
    public function store(StoreApplicationRequest $request, SubmitApplication $submitApplication): JsonResponse
    {
        $application = $submitApplication->handle($request->user(), $request->service(), $request->formData());

        return ApplicationResource::make($application->load(['service', 'statusChanges']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an application.
     *
     * Includes the status timeline and, once approved, the certificate.
     */
    public function show(Application $application): ApplicationResource
    {
        Gate::authorize('view', $application);

        return ApplicationResource::make($application->load(['service', 'statusChanges', 'certificate']));
    }
}
