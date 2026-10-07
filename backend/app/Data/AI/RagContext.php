<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class RagContext
{
    /**
     * @param  list<RagContextSource>  $sources
     */
    public function __construct(public array $sources)
    {
        $keys = array_map(
            static fn (RagContextSource $source): string => $source->key,
            $sources,
        );

        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('RAG context source keys must be unique.');
        }
    }

    public function isEmpty(): bool
    {
        return $this->sources === [];
    }
}
