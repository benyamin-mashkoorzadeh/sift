<?php

namespace App\Services\AI;

use App\Actions\AI\RetrieveWorkspaceKnowledge;
use App\Contracts\AI\LlmProvider;
use App\Data\AI\RagAnswerResult;
use App\Data\AI\RagCitation;
use App\Data\AI\RagContext;
use App\Enums\GenerationDecision;
use App\Enums\RagAnswerStatus;
use App\Exceptions\AI\RagAnswerException;
use App\Models\Workspace;
use Throwable;

class RagService
{
    public const INSUFFICIENT_ANSWER = 'I could not find enough information in this workspace knowledge base to answer that reliably.';

    public function __construct(
        private readonly RetrieveWorkspaceKnowledge $retrieveWorkspaceKnowledge,
        private readonly RagContextBuilder $contextBuilder,
        private readonly GroundedAnswerPrompt $prompt,
        private readonly LlmProvider $llmProvider,
    ) {}

    public function answer(Workspace $workspace, string $question): RagAnswerResult
    {
        try {
            $question = trim($question);

            if ($question === '') {
                throw new RagAnswerException(RagAnswerException::USER_MESSAGE);
            }

            $retrieval = $this->retrieveWorkspaceKnowledge->handle($workspace, $question);
            $context = $this->contextBuilder->build($retrieval);

            if ($context->isEmpty()) {
                return $this->needsReview('no_retrieval_results');
            }

            $generation = $this->llmProvider->generate(
                $this->prompt->build($question, $context),
            );

            if ($generation->decision === GenerationDecision::Insufficient) {
                return $this->needsReview(
                    reason: 'insufficient_evidence',
                    provider: $generation->provider,
                    model: $generation->model,
                );
            }

            $citations = $this->citationsFor($generation->sourceKeys, $context);

            if ($citations === []) {
                throw new RagAnswerException(RagAnswerException::USER_MESSAGE);
            }

            return new RagAnswerResult(
                status: RagAnswerStatus::Answered,
                answer: (string) $generation->answer,
                citations: $citations,
                provider: $generation->provider,
                model: $generation->model,
            );
        } catch (RagAnswerException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new RagAnswerException(
                RagAnswerException::USER_MESSAGE,
                previous: $exception,
            );
        }
    }

    /**
     * @param  list<string>  $selectedKeys
     * @return list<RagCitation>
     */
    private function citationsFor(array $selectedKeys, RagContext $context): array
    {
        $selected = array_fill_keys($selectedKeys, true);
        $citations = [];

        foreach ($context->sources as $source) {
            if (! isset($selected[$source->key])) {
                continue;
            }

            $citations[] = new RagCitation(
                documentId: $source->documentId,
                originalFilename: $source->originalFilename,
                pageNumber: $source->pageNumber,
                chunkId: $source->chunkId,
                chunkIndex: $source->chunkIndex,
            );

            unset($selected[$source->key]);
        }

        if ($selected !== []) {
            throw new RagAnswerException(RagAnswerException::USER_MESSAGE);
        }

        return $citations;
    }

    private function needsReview(
        string $reason,
        ?string $provider = null,
        ?string $model = null,
    ): RagAnswerResult {
        return new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: self::INSUFFICIENT_ANSWER,
            citations: [],
            provider: $provider,
            model: $model,
            reason: $reason,
        );
    }
}
