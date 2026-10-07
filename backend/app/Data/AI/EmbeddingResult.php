<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class EmbeddingResult
{
    /**
     * @param  list<EmbeddingOutput>  $outputs
     */
    public function __construct(
        public string $provider,
        public string $model,
        public int $dimensions,
        public array $outputs,
    ) {
        if ($provider === '' || $model === '') {
            throw new InvalidArgumentException('Embedding provider metadata cannot be empty.');
        }

        if ($dimensions < 1) {
            throw new InvalidArgumentException('Embedding dimensions must be positive.');
        }

        if ($outputs === []) {
            throw new InvalidArgumentException('An embedding result must contain at least one output.');
        }

        $keys = [];

        foreach ($outputs as $output) {
            if (count($output->vector) !== $dimensions) {
                throw new InvalidArgumentException('An embedding output has an unexpected dimension.');
            }

            $keys[] = $output->key;
        }

        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Embedding output keys must be unique.');
        }
    }
}
