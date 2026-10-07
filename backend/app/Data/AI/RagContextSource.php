<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class RagContextSource
{
    public function __construct(
        public string $key,
        public int $documentId,
        public string $originalFilename,
        public int $chunkId,
        public int $pageNumber,
        public int $chunkIndex,
        public string $content,
        public float $distance,
        public float $similarity,
    ) {
        if (preg_match('/^source_[1-9][0-9]*$/', $key) !== 1
            || $documentId < 1
            || $chunkId < 1
            || $pageNumber < 1
            || $chunkIndex < 0) {
            throw new InvalidArgumentException('RAG context source identifiers are invalid.');
        }

        if (trim($originalFilename) === '' || trim($content) === '') {
            throw new InvalidArgumentException('RAG context source content cannot be empty.');
        }

        if (! is_finite($distance) || ! is_finite($similarity)) {
            throw new InvalidArgumentException('RAG context ranking metrics must be finite.');
        }
    }
}
