<?php

namespace App\Exceptions\Documents;

use RuntimeException;

class DocumentEmbeddingException extends RuntimeException
{
    public const USER_MESSAGE = 'We could not generate embeddings for this document.';
}
