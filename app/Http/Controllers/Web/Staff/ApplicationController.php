<?php

namespace App\Http\Controllers\Web\Staff;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reviewer's back office: their own ministry's applications only.
 */
class ApplicationController extends Controller
{
    /**
     * The review queue. Waiting requests oldest first (work them in order); decided ones newest first.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
        ]);

        $status = ApplicationStatus::tryFrom($validated['status'] ?? '') ?? ApplicationStatus::Submitted;
        $reviewer = $request->user()->load('govEntity');
        $ministryApplications = Application::where('gov_entity_id', $reviewer->gov_entity_id);

        $applications = (clone $ministryApplications)
            ->where('status', $status)
            ->with(['service', 'applicant'])
            ->when(
                $status->isAwaitingDecision(),
                fn ($query) => $query->orderBy('submitted_at'),
                fn ($query) => $query->orderByDesc('decided_at'),
            )
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $counts = (clone $ministryApplications)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('staff/applications/index', [
            'reviewer' => [
                'name' => $reviewer->name,
                'signed_in_with' => $reviewer->entra_object_id ? 'entra' : 'password',
                'gov_entity' => [
                    'code' => $reviewer->govEntity->code,
                    'name' => ['ar' => $reviewer->govEntity->name_ar, 'en' => $reviewer->govEntity->name_en],
                ],
            ],
            'status' => $status,
            'counts' => collect(ApplicationStatus::cases())->mapWithKeys(fn (ApplicationStatus $case): array => [$case->value => (int) ($counts[$case->value] ?? 0)]),
            // Round-trip through JSON so the page gets exactly what the API returns (data, links, meta).
            'applications' => json_decode(ApplicationResource::collection($applications)->toResponse($request)->getContent(), true),
        ]);
    }

    /**
     * One request, with everything a reviewer needs to decide it. Another ministry's request is a 404.
     */
    public function show(Request $request, Application $application): Response
    {
        Gate::authorize('view', $application);

        $application->load(['service', 'applicant', 'statusChanges', 'certificate', 'decider']);

        return Inertia::render('staff/applications/show', [
            'application' => json_decode(ApplicationResource::make($application)->toJson(), true),
            // Internal only: the public API never names the reviewer, but colleagues in the back office may see it.
            'decidedBy' => $application->decider?->name,
            'canDecide' => $application->status->isAwaitingDecision() && Gate::allows('decide', $application),
        ]);
    }
}
