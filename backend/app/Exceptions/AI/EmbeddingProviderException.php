<?php

namespace App\Exceptions\AI;

use RuntimeException;

class EmbeddingProviderException extends RuntimeException
{
    public const USER_MESSAGE = 'The embedding provider could not process the requested content.';
}
