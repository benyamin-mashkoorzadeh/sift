<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class RetrievalRequest
{
    /**
     * @param  list<float>  $queryVector
     */
    public function __construct(
        public int $workspaceId,
        public array $queryVector,
        public string $embeddingProvider,
        public string $embeddingModel,
        public RetrievalOptions $options,
    ) {
        if ($workspaceId < 1) {
            throw new InvalidArgumentException('A retrieval workspace ID must be positive.');
        }

        if ($queryVector === []) {
            throw new InvalidArgumentException('A retrieval query vector cannot be empty.');
        }

        foreach ($queryVector as $component) {
            if (! is_float($component) || ! is_finite($component)) {
                throw new InvalidArgumentException('Query vector components must be finite floats.');
            }
        }

        if ($embeddingProvider === '' || $embeddingModel === '') {
            throw new InvalidArgumentException('Retrieval embedding metadata cannot be empty.');
        }
    }
}
