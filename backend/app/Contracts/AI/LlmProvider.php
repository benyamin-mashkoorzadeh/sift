<?php

namespace App\Contracts\AI;

use App\Data\AI\GenerationRequest;
use App\Data\AI\GenerationResult;

interface LlmProvider
{
    public function generate(GenerationRequest $request): GenerationResult;
}
