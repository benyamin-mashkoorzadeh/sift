<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class RetrievalOptions
{
    public const MAX_TOP_K = 50;

    public function __construct(
        public int $topK,
        public ?float $minimumSimilarity = null,
    ) {
        if ($topK < 1 || $topK > self::MAX_TOP_K) {
            throw new InvalidArgumentException('Retrieval top-K must be between 1 and 50.');
        }

        if ($minimumSimilarity !== null
            && (! is_finite($minimumSimilarity) || $minimumSimilarity < -1 || $minimumSimilarity > 1)) {
            throw new InvalidArgumentException('Minimum similarity must be between -1 and 1.');
        }
    }
}
