<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class RagCitation
{
    public function __construct(
        public int $documentId,
        public string $originalFilename,
        public int $pageNumber,
        public int $chunkId,
        public int $chunkIndex,
    ) {
        if ($documentId < 1 || $pageNumber < 1 || $chunkId < 1 || $chunkIndex < 0) {
            throw new InvalidArgumentException('RAG citation identifiers are invalid.');
        }

        if (trim($originalFilename) === '') {
            throw new InvalidArgumentException('RAG citation filename cannot be empty.');
        }
    }
}
