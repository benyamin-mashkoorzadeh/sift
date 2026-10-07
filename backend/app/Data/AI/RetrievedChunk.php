<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class RetrievedChunk
{
    public function __construct(
        public int $documentId,
        public string $originalFilename,
        public int $chunkId,
        public int $pageNumber,
        public int $chunkIndex,
        public string $content,
        public float $distance,
        public float $similarity,
    ) {
        if ($documentId < 1 || $chunkId < 1 || $pageNumber < 1 || $chunkIndex < 0) {
            throw new InvalidArgumentException('Retrieved source identifiers are invalid.');
        }

        if ($originalFilename === '' || trim($content) === '') {
            throw new InvalidArgumentException('Retrieved source content cannot be empty.');
        }

        if (! is_finite($distance) || ! is_finite($similarity)) {
            throw new InvalidArgumentException('Retrieval ranking metrics must be finite.');
        }
    }
}
