<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Whether a reviewer can still approve or reject the application.
     */
    public function isAwaitingDecision(): bool
    {
        return $this === self::Submitted;
    }
}
