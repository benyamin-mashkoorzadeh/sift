<?php

namespace App\Actions\AI;

use App\Contracts\AI\EmbeddingProvider;
use App\Contracts\AI\Retriever;
use App\Data\AI\EmbeddingInput;
use App\Data\AI\EmbeddingRequest;
use App\Data\AI\RetrievalOptions;
use App\Data\AI\RetrievalRequest;
use App\Data\AI\RetrievalResult;
use App\Enums\EmbeddingInputType;
use App\Exceptions\AI\KnowledgeRetrievalException;
use App\Models\Workspace;
use Throwable;

class RetrieveWorkspaceKnowledge
{
    private const QUERY_KEY = 'query';

    public function __construct(
        private readonly EmbeddingProvider $embeddingProvider,
        private readonly Retriever $retriever,
    ) {}

    public function handle(
        Workspace $workspace,
        string $question,
        ?RetrievalOptions $options = null,
    ): RetrievalResult {
        try {
            $question = trim($question);

            if ($question === '') {
                throw new KnowledgeRetrievalException(KnowledgeRetrievalException::USER_MESSAGE);
            }

            $embedding = $this->embeddingProvider->embed(new EmbeddingRequest(
                inputs: [new EmbeddingInput(self::QUERY_KEY, $question)],
                inputType: EmbeddingInputType::Query,
            ));

            if ($embedding->dimensions !== (int) config('ai.embeddings.dimensions')
                || count($embedding->outputs) !== 1
                || $embedding->outputs[0]->key !== self::QUERY_KEY) {
                throw new KnowledgeRetrievalException(KnowledgeRetrievalException::USER_MESSAGE);
            }

            $options ??= new RetrievalOptions(
                topK: (int) config('ai.retrieval.top_k'),
                minimumSimilarity: config('ai.retrieval.minimum_similarity'),
            );

            return $this->retriever->retrieve(new RetrievalRequest(
                workspaceId: (int) $workspace->getKey(),
                queryVector: $embedding->outputs[0]->vector,
                embeddingProvider: $embedding->provider,
                embeddingModel: $embedding->model,
                options: $options,
            ));
        } catch (Throwable $exception) {
            $failure = $exception instanceof KnowledgeRetrievalException
                ? $exception
                : new KnowledgeRetrievalException(
                    KnowledgeRetrievalException::USER_MESSAGE,
                    previous: $exception,
                );

            report($exception);

            throw $failure;
        }
    }
}
