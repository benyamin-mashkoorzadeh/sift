<?php

namespace App\Actions\AI;

use App\Actions\Review\RecordNeedsReviewQuestion;
use App\Data\AI\AssistantInteractionContext;
use App\Data\AI\RagAnswerResult;
use App\Enums\RagAnswerStatus;
use App\Exceptions\AI\AssistantInteractionPersistenceException;
use App\Exceptions\Review\ReviewPersistenceException;
use App\Models\AssistantInteraction;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PersistAssistantInteraction
{
    public function __construct(
        private readonly RecordNeedsReviewQuestion $recordNeedsReviewQuestion,
    ) {}

    public function handle(
        Workspace $workspace,
        string $question,
        RagAnswerResult $result,
        ?AssistantInteractionContext $context = null,
    ): AssistantInteraction {
        $context ??= AssistantInteractionContext::assistant();
        $context->assertValidFor($workspace);

        try {
            return DB::transaction(function () use ($workspace, $question, $result, $context): AssistantInteraction {
                $reviewItem = $result->status === RagAnswerStatus::NeedsReview
                    ? $this->recordNeedsReviewQuestion->handle($workspace, $question)
                    : null;

                $interaction = $workspace->assistantInteractions()->create([
                    'origin' => $context->origin,
                    'review_item_id' => $reviewItem?->getKey(),
                    'widget_conversation_id' => $context->widgetConversation?->getKey(),
                    'question' => Str::squish($question),
                    'status' => $result->status,
                    'answer' => $result->answer,
                    'provider' => $result->provider,
                    'model' => $result->model,
                ]);

                if ($result->citations !== []) {
                    $interaction->citations()->createMany(array_map(
                        static fn ($citation, int $position): array => [
                            'document_id' => $citation->documentId,
                            'document_chunk_id' => $citation->chunkId,
                            'original_filename' => $citation->originalFilename,
                            'page_number' => $citation->pageNumber,
                            'chunk_index' => $citation->chunkIndex,
                            'position' => $position,
                        ],
                        $result->citations,
                        array_keys($result->citations),
                    ));
                }

                if ($context->widgetConversation !== null) {
                    $context->widgetConversation->forceFill([
                        'last_activity_at' => now(),
                    ])->save();
                }

                return $interaction->load(['citations', 'reviewItem']);
            });
        } catch (ReviewPersistenceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new AssistantInteractionPersistenceException(
                AssistantInteractionPersistenceException::USER_MESSAGE,
                previous: $exception,
            );
        }
    }
}
