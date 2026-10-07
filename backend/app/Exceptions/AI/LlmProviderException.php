<?php

namespace App\Exceptions\AI;

use RuntimeException;

class LlmProviderException extends RuntimeException
{
    public const USER_MESSAGE = 'The language model provider could not generate an answer.';
}
