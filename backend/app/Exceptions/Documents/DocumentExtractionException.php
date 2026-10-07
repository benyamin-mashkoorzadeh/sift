<?php

namespace App\Exceptions\Documents;

use RuntimeException;

class DocumentExtractionException extends RuntimeException
{
    public const USER_MESSAGE = 'We could not extract text from this PDF.';
}
