<?php

namespace App\Actions\Auth;

use RuntimeException;

/**
 * Identity was verified by Entra, but Sahl won't sign the person in. `reason` is shown on the login page.
 */
class SignInRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $detail)
    {
        parent::__construct($detail);
    }
}
