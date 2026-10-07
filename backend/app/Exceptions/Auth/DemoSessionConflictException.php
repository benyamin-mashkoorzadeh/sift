<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class DemoSessionConflictException extends RuntimeException
{
    public const USER_MESSAGE = 'Log out before entering Guest Demo.';
}
