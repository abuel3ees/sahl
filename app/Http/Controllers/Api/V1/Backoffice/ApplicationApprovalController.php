<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Actions\Applications\ApproveApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Backoffice\ApproveApplicationRequest;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Models\Application;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;

#[Group('Back office', 'Ministry employees reviewing applications. Scoped to the employee’s own ministry.', weight: 4)]
class ApplicationApprovalController extends Controller
{
    /**
     * Approve an application.
     *
     * Approves the application and issues its certificate. Returns 409 if it was already decided.
     */
    #[Response(409, 'The application was already approved or rejected.', type: 'array{message: string}')]
    public function store(ApproveApplicationRequest $request, Application $application, ApproveApplication $approveApplication): ApplicationResource
    {
        $application = $approveApplication->handle($application, $request->user(), $request->input('note'));

        return ApplicationResource::make($application->load(['service', 'applicant', 'statusChanges', 'certificate']));
    }
}
