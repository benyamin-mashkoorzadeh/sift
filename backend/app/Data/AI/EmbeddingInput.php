<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class EmbeddingInput
{
    public function __construct(
        public string $key,
        public string $text,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('An embedding input key cannot be empty.');
        }

        if (trim($text) === '') {
            throw new InvalidArgumentException('Embedding input text cannot be empty.');
        }
    }
}
