<?php

namespace App\Exceptions\AI;

use RuntimeException;

class AssistantInteractionPersistenceException extends RuntimeException
{
    public const USER_MESSAGE = 'Sift could not save this answer right now. Please try again.';
}
