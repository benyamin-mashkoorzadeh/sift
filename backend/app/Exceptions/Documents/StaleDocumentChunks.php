<?php

namespace App\Exceptions\Documents;

class StaleDocumentChunks extends DocumentEmbeddingException
{
    public const USER_MESSAGE = 'The document content changed while embeddings were being generated.';
}
