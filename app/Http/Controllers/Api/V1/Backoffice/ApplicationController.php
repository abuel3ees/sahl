<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Models\Application;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

#[Group('Back office', 'Ministry employees reviewing applications. Scoped to the employee’s own ministry.', weight: 4)]
class ApplicationController extends Controller
{
    /**
     * List the ministry's applications (review queue).
     *
     * Only applications belonging to the employee’s own ministry. Oldest first, so the
     * queue is worked in order.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            /** Filter by status. Defaults to all. */
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
        ]);

        $applications = Application::query()
            ->where('gov_entity_id', $request->user()->gov_entity_id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['service', 'applicant'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(20);

        return ApplicationResource::collection($applications);
    }

    /**
     * Show an application (back office).
     *
     * Includes the applicant's details for review.
     */
    public function show(Application $application): ApplicationResource
    {
        Gate::authorize('view', $application);

        return ApplicationResource::make($application->load(['service', 'applicant', 'statusChanges', 'certificate']));
    }
}
