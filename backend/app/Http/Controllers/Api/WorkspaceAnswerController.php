<?php

namespace App\Http\Controllers\Api;

use App\Actions\AI\AnswerWorkspaceQuestionForCurrentAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\AskWorkspaceQuestionRequest;
use App\Http\Resources\RagAnswerResource;
use App\Models\User;
use App\Models\Workspace;

class WorkspaceAnswerController extends Controller
{
    public function __invoke(
        AskWorkspaceQuestionRequest $request,
        Workspace $workspace,
        AnswerWorkspaceQuestionForCurrentAccess $answerWorkspaceQuestion,
    ): RagAnswerResource {
        /** @var User $user */
        $user = $request->user();

        return new RagAnswerResource($answerWorkspaceQuestion->handle(
            $user,
            $workspace,
            $request->validated('question'),
        ));
    }
}
