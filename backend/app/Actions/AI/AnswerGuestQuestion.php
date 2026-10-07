<?php

namespace App\Actions\AI;

use App\Data\AI\RagAnswerResult;
use App\Exceptions\AI\DemoAssistantUnavailableException;
use App\Models\Workspace;
use App\Services\AI\RagService;
use App\Services\Auth\DemoConfiguration;

class AnswerGuestQuestion
{
    public function __construct(
        private readonly RagService $ragService,
        private readonly DemoConfiguration $demoConfiguration,
    ) {}

    public function handle(Workspace $workspace, string $question): RagAnswerResult
    {
        if (! $this->demoConfiguration->assistantEnabled()) {
            throw new DemoAssistantUnavailableException(
                DemoAssistantUnavailableException::USER_MESSAGE,
            );
        }

        return $this->ragService->answer($workspace, $question);
    }
}
