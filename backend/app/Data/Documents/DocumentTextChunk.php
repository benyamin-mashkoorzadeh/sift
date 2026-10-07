<?php

namespace App\Data\Documents;

use InvalidArgumentException;

final readonly class DocumentTextChunk
{
    public function __construct(
        public int $pageNumber,
        public int $pageChunkIndex,
        public string $content,
    ) {
        if ($pageNumber < 1) {
            throw new InvalidArgumentException('A chunk page number must be positive.');
        }

        if ($pageChunkIndex < 0) {
            throw new InvalidArgumentException('A page chunk index cannot be negative.');
        }

        if (trim($content) === '') {
            throw new InvalidArgumentException('Chunk content cannot be empty.');
        }
    }
}
