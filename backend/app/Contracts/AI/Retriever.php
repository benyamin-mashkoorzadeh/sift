<?php

namespace App\Contracts\AI;

use App\Data\AI\RetrievalRequest;
use App\Data\AI\RetrievalResult;

interface Retriever
{
    public function retrieve(RetrievalRequest $request): RetrievalResult;
}
