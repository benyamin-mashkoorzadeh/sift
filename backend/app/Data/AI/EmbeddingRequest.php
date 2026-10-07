<?php

namespace App\Data\AI;

use App\Enums\EmbeddingInputType;
use InvalidArgumentException;

final readonly class EmbeddingRequest
{
    /**
     * @param  list<EmbeddingInput>  $inputs
     */
    public function __construct(
        public array $inputs,
        public EmbeddingInputType $inputType,
    ) {
        if ($inputs === []) {
            throw new InvalidArgumentException('An embedding request must contain at least one input.');
        }

        $keys = array_map(
            static fn (EmbeddingInput $input): string => $input->key,
            $inputs,
        );

        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Embedding input keys must be unique.');
        }
    }
}
