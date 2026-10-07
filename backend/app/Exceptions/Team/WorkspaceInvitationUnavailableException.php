<?php

namespace App\Exceptions\Team;

use RuntimeException;

class WorkspaceInvitationUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'This invitation is unavailable.';

    public function __construct()
    {
        parent::__construct(self::USER_MESSAGE);
    }
}
