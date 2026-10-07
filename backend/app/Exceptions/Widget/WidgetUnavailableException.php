<?php

namespace App\Exceptions\Widget;

use RuntimeException;

class WidgetUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'Widget unavailable.';
}
