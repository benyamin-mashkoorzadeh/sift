<?php

namespace App\Exceptions\Auth;

use RuntimeException;

class DemoUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'Guest Demo is unavailable right now.';
}
