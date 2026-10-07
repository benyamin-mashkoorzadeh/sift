<?php

namespace App\Exceptions\AI;

use RuntimeException;

class KnowledgeRetrievalException extends RuntimeException
{
    public const USER_MESSAGE = 'We could not search this workspace knowledge.';
}
