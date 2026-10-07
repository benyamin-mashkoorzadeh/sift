<?php

namespace App\Actions\Widget;

use App\Actions\AI\AnswerWorkspaceQuestion;
use App\Data\AI\AssistantInteractionContext;
use App\Data\Widget\WidgetAnswerResult;
use App\Enums\RagAnswerStatus;
use App\Exceptions\Widget\WidgetAnswerUnavailableException;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use Throwable;

class AnswerWidgetQuestion
{
    public const NEEDS_REVIEW_ANSWER = "I couldn't find a reliable answer to that question. I've sent it for review.";

    public function __construct(
        private readonly AnswerWorkspaceQuestion $answerWorkspaceQuestion,
    ) {}

    public function handle(
        Workspace $workspace,
        WidgetConversation $conversation,
        string $question,
    ): WidgetAnswerResult {
        try {
            $result = $this->answerWorkspaceQuestion->handle(
                $workspace,
                $question,
                AssistantInteractionContext::widget($conversation),
            );

            return new WidgetAnswerResult(
                status: $result->status,
                answer: $result->status === RagAnswerStatus::NeedsReview
                    ? self::NEEDS_REVIEW_ANSWER
                    : $result->answer,
            );
        } catch (Throwable $exception) {
            throw new WidgetAnswerUnavailableException(
                WidgetAnswerUnavailableException::USER_MESSAGE,
                previous: $exception,
            );
        }
    }
}
