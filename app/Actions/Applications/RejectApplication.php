<?php

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RejectApplication
{
    public function handle(Application $application, User $reviewer, string $reason): Application
    {
        return DB::transaction(function () use ($application, $reviewer, $reason): Application {
            $application = Application::whereKey($application->getKey())->lockForUpdate()->firstOrFail();

            if (! $application->status->isAwaitingDecision()) {
                throw new ConflictHttpException("Application is already {$application->status->value}.");
            }

            $application->update([
                'status' => ApplicationStatus::Rejected,
                'decided_at' => now(),
                'decided_by' => $reviewer->id,
                'decision_note' => $reason,
            ]);

            $application->statusChanges()->create([
                'from_status' => ApplicationStatus::Submitted,
                'to_status' => ApplicationStatus::Rejected,
                'actor_id' => $reviewer->id,
                'note' => $reason,
                'created_at' => now(),
            ]);

            return $application;
        });
    }
}
