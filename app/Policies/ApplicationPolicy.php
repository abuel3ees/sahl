<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApplicationPolicy
{
    /**
     * Citizens see their own applications; employees see their ministry's.
     * Anyone else gets a 404 so they cannot learn the application exists.
     */
    public function view(User $user, Application $application): Response
    {
        $canView = $user->isCitizen()
            ? $application->applicant_id === $user->id
            : $user->isEmployee() && $application->gov_entity_id === $user->gov_entity_id;

        return $canView ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isCitizen();
    }

    /**
     * Approve or reject: only employees of the owning ministry, never the applicant.
     */
    public function decide(User $user, Application $application): Response
    {
        if (! $user->isEmployee() || $application->gov_entity_id !== $user->gov_entity_id) {
            return Response::denyAsNotFound();
        }

        return $application->applicant_id === $user->id ? Response::deny('You cannot decide your own application.') : Response::allow();
    }
}
