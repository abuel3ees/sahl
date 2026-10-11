<?php

namespace App\Http\Controllers\Web\Citizen;

use App\Actions\Applications\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SubmitApplicationRequest;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Http\Resources\Api\V1\ServiceResource;
use App\Models\Application;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A citizen requests a certificate and follows it. Same rules and action as POST /api/v1/applications.
 */
class ApplicationController extends Controller
{
    public function create(Request $request, Service $service): Response
    {
        abort_unless($service->is_active, 404);

        $openApplication = $request->user()->applications()
            ->where('service_id', $service->id)
            ->where('status', ApplicationStatus::Submitted)
            ->first(['id', 'reference_number']);

        return Inertia::render('citizen/apply', [
            'service' => json_decode(ServiceResource::make($service->load('govEntity'))->toJson(), true),
            'openApplication' => $openApplication?->only(['id', 'reference_number']),
        ]);
    }

    public function store(SubmitApplicationRequest $request, Service $service, SubmitApplication $submitApplication): RedirectResponse
    {
        $application = $submitApplication->handle($request->user(), $service, $request->formData());

        Inertia::flash('submitted', true);

        return redirect()->route('account.applications.show', $application);
    }

    /**
     * One of the citizen's own requests. Anyone else's is a 404.
     */
    public function show(Application $application): Response
    {
        Gate::authorize('view', $application);

        $application->load(['service.govEntity', 'statusChanges', 'certificate']);

        return Inertia::render('citizen/application', [
            'application' => json_decode(ApplicationResource::make($application)->toJson(), true),
            'decisionDueBy' => $application->status->isAwaitingDecision() ? $application->decisionDueBy()->toIso8601String() : null,
        ]);
    }
}
