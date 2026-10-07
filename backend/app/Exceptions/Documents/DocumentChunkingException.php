<?php

namespace App\Exceptions\Documents;

use RuntimeException;

class DocumentChunkingException extends RuntimeException
{
    public const USER_MESSAGE = 'We could not prepare this document for search.';
}
