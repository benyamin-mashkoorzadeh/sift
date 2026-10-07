<?php

namespace App\Actions\AI;

use App\Data\AI\AssistantInteractionContext;
use App\Data\AI\RagAnswerResult;
use App\Models\Workspace;
use App\Services\AI\RagService;

class AnswerWorkspaceQuestion
{
    public function __construct(
        private readonly RagService $ragService,
        private readonly PersistAssistantInteraction $persistAssistantInteraction,
    ) {}

    public function handle(
        Workspace $workspace,
        string $question,
        ?AssistantInteractionContext $context = null,
    ): RagAnswerResult {
        $context ??= AssistantInteractionContext::assistant();
        $context->assertValidFor($workspace);
        $result = $this->ragService->answer($workspace, $question);

        $this->persistAssistantInteraction->handle($workspace, $question, $result, $context);

        return $result;
    }
}
