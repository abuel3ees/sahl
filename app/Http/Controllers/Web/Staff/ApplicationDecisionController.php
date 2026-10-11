<?php

namespace App\Http\Controllers\Web\Staff;

use App\Actions\Applications\ApproveApplication;
use App\Actions\Applications\RejectApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Backoffice\ApproveApplicationRequest;
use App\Http\Requests\Api\V1\Backoffice\RejectApplicationRequest;
use App\Models\Application;
use Closure;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Approve or reject from the back office. Same form requests (policy + validation) and actions as the API.
 */
class ApplicationDecisionController extends Controller
{
    public function approve(ApproveApplicationRequest $request, Application $application, ApproveApplication $approveApplication): RedirectResponse
    {
        return $this->decide($application, 'approved', fn () => $approveApplication->handle($application, $request->user(), $request->input('note')));
    }

    public function reject(RejectApplicationRequest $request, Application $application, RejectApplication $rejectApplication): RedirectResponse
    {
        return $this->decide($application, 'rejected', fn () => $rejectApplication->handle($application, $request->user(), $request->string('reason')->toString()));
    }

    /**
     * Run the decision and come back to the request. If a colleague decided it first, say so instead of failing.
     */
    private function decide(Application $application, string $outcome, Closure $decision): RedirectResponse
    {
        try {
            $decision();
            Inertia::flash('decision', $outcome);
        } catch (ConflictHttpException) {
            Inertia::flash('decision', 'already_decided');
        }

        return redirect()->route('staff.applications.show', $application);
    }
}
