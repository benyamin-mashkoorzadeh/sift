<?php

namespace App\Exceptions\Widget;

use RuntimeException;

class WidgetApiUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'Widget temporarily unavailable.';
}
