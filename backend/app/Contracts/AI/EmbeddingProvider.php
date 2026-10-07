<?php

namespace App\Contracts\AI;

use App\Data\AI\EmbeddingRequest;
use App\Data\AI\EmbeddingResult;

interface EmbeddingProvider
{
    public function embed(EmbeddingRequest $request): EmbeddingResult;
}
