<?php

namespace App\Exceptions\AI;

use RuntimeException;

class RetrievalException extends RuntimeException
{
    public const USER_MESSAGE = 'The knowledge search could not be completed.';
}
