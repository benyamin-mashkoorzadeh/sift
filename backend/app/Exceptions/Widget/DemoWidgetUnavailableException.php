<?php

namespace App\Exceptions\Widget;

use RuntimeException;

class DemoWidgetUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'The Demo assistant is temporarily unavailable.';
}
