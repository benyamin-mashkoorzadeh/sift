<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class EmbeddingOutput
{
    /**
     * @param  list<float>  $vector
     */
    public function __construct(
        public string $key,
        public array $vector,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('An embedding output key cannot be empty.');
        }

        if ($vector === []) {
            throw new InvalidArgumentException('An embedding vector cannot be empty.');
        }

        foreach ($vector as $component) {
            if (! is_float($component) || ! is_finite($component)) {
                throw new InvalidArgumentException('Embedding vector components must be finite floats.');
            }
        }
    }
}
