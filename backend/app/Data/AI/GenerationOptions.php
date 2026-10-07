<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class GenerationOptions
{
    public function __construct(
        public int $maxOutputTokens,
        public float $temperature,
    ) {
        if ($maxOutputTokens < 1) {
            throw new InvalidArgumentException('Maximum output tokens must be positive.');
        }

        if (! is_finite($temperature) || $temperature < 0 || $temperature > 2) {
            throw new InvalidArgumentException('Generation temperature must be between 0 and 2.');
        }
    }
}
