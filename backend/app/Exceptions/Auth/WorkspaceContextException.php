<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class WorkspaceContextException extends RuntimeException
{
    public const USER_MESSAGE = 'This account does not have an available workspace.';
}
