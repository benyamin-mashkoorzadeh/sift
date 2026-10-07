<?php

namespace App\Exceptions\Team;

use RuntimeException;

class WorkspaceInvitationWrongAccountException extends RuntimeException
{
    public const USER_MESSAGE = 'This invitation belongs to a different account.';

    public function __construct()
    {
        parent::__construct(self::USER_MESSAGE);
    }
}
