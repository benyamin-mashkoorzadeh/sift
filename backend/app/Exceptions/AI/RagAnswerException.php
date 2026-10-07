<?php

namespace App\Exceptions\AI;

use RuntimeException;

class RagAnswerException extends RuntimeException
{
    public const USER_MESSAGE = 'Sift could not generate an answer right now.';
}
