<?php

namespace App\Exceptions\Documents;

use RuntimeException;

class DocumentProcessingException extends RuntimeException
{
    public const USER_MESSAGE = 'We could not finish processing this document.';

    public const DISPATCH_USER_MESSAGE = 'We could not start processing this document.';
}
