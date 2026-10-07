<?php

namespace App\Exceptions\Widget;

use RuntimeException;

class WidgetAnswerUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'Answer temporarily unavailable.';
}
