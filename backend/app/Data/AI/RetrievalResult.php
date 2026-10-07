<?php

namespace App\Data\AI;

final readonly class RetrievalResult
{
    /**
     * @param  list<RetrievedChunk>  $chunks
     */
    public function __construct(public array $chunks) {}

    public function isEmpty(): bool
    {
        return $this->chunks === [];
    }
}
