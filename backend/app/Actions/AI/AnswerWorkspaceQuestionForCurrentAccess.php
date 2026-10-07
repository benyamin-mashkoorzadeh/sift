<?php

namespace App\Actions\AI;

use App\Data\AI\RagAnswerResult;
use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\ResolveEffectiveWorkspaceAccess;
use Illuminate\Auth\Access\AuthorizationException;

class AnswerWorkspaceQuestionForCurrentAccess
{
    public function __construct(
        private readonly ResolveEffectiveWorkspaceAccess $resolveEffectiveWorkspaceAccess,
        private readonly AnswerWorkspaceQuestion $answerWorkspaceQuestion,
        private readonly AnswerGuestQuestion $answerGuestQuestion,
    ) {}

    public function handle(User $user, Workspace $workspace, string $question): RagAnswerResult
    {
        $access = $this->resolveEffectiveWorkspaceAccess->forWorkspace($user, $workspace);

        if ($access === null || ! $access->allows(WorkspacePermission::UseAssistant)) {
            throw new AuthorizationException;
        }

        return match ($access->accessMode) {
            WorkspaceAccessMode::Normal => $this->answerWorkspaceQuestion->handle($workspace, $question),
            WorkspaceAccessMode::Demo => $this->answerGuestQuestion->handle($workspace, $question),
        };
    }
}
