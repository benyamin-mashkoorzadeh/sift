<?php

namespace App\Exceptions\AI;

use RuntimeException;

class DemoAssistantUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'Guest Assistant is unavailable right now.';
}
