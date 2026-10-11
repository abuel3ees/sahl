<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Actions\Applications\RejectApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Backoffice\RejectApplicationRequest;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Models\Application;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;

#[Group('Back office', 'Ministry employees reviewing applications. Scoped to the employee’s own ministry.', weight: 4)]
class ApplicationRejectionController extends Controller
{
    /**
     * Reject an application.
     *
     * The reason is shown to the applicant. Returns 409 if it was already decided.
     */
    #[Response(409, 'The application was already approved or rejected.', type: 'array{message: string}')]
    public function store(RejectApplicationRequest $request, Application $application, RejectApplication $rejectApplication): ApplicationResource
    {
        $application = $rejectApplication->handle($application, $request->user(), $request->string('reason')->toString());

        return ApplicationResource::make($application->load(['service', 'applicant', 'statusChanges']));
    }
}
