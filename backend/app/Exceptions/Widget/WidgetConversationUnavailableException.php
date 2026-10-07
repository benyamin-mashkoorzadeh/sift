<?php

namespace App\Exceptions\Widget;

use RuntimeException;

class WidgetConversationUnavailableException extends RuntimeException
{
    public const USER_MESSAGE = 'Conversation unavailable.';
}
