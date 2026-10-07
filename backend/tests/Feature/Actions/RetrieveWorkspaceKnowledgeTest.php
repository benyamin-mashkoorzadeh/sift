<?php

namespace Tests\Feature\Actions;

use App\Actions\AI\RetrieveWorkspaceKnowledge;
use App\Contracts\AI\EmbeddingProvider;
use App\Contracts\AI\Retriever;
use App\Data\AI\EmbeddingOutput;
use App\Data\AI\EmbeddingRequest;
use App\Data\AI\EmbeddingResult;
use App\Data\AI\RetrievalRequest;
use App\Data\AI\RetrievalResult;
use App\Enums\EmbeddingInputType;
use App\Exceptions\AI\EmbeddingProviderException;
use App\Exceptions\AI\KnowledgeRetrievalException;
use App\Exceptions\AI\RetrievalException;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RetrieveWorkspaceKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_embeds_the_question_as_a_query_and_passes_scoped_options_to_the_retriever(): void
    {
        config()->set('ai.embeddings.dimensions', 1024);
        config()->set('ai.retrieval.top_k', 5);
        config()->set('ai.retrieval.minimum_similarity', null);
        $workspace = $this->createWorkspace();
        $vector = array_fill(0, 1024, 0.1);
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')
            ->once()
            ->withArgs(function (EmbeddingRequest $request): bool {
                return $request->inputType === EmbeddingInputType::Query
                    && count($request->inputs) === 1
                    && $request->inputs[0]->key === 'query'
                    && $request->inputs[0]->text === 'What is the return policy?';
            })
            ->andReturn(new EmbeddingResult(
                provider: 'cohere',
                model: 'embed-v4.0',
                dimensions: 1024,
                outputs: [new EmbeddingOutput('query', $vector)],
            ));
        $retriever = Mockery::mock(Retriever::class);
        $retriever->shouldReceive('retrieve')
            ->once()
            ->withArgs(function (RetrievalRequest $request) use ($workspace, $vector): bool {
                return $request->workspaceId === $workspace->id
                    && $request->queryVector === $vector
                    && $request->embeddingProvider === 'cohere'
                    && $request->embeddingModel === 'embed-v4.0'
                    && $request->options->topK === 5
                    && $request->options->minimumSimilarity === null;
            })
            ->andReturn(new RetrievalResult([]));
        $this->app->instance(EmbeddingProvider::class, $embeddingProvider);
        $this->app->instance(Retriever::class, $retriever);

        $result = $this->app->make(RetrieveWorkspaceKnowledge::class)->handle(
            $workspace,
            '  What is the return policy?  ',
        );

        $this->assertTrue($result->isEmpty());
    }

    public function test_query_embedding_failures_do_not_call_the_retriever(): void
    {
        $workspace = $this->createWorkspace();
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->once()->andThrow(
            new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE),
        );
        $retriever = Mockery::mock(Retriever::class);
        $retriever->shouldNotReceive('retrieve');
        $this->app->instance(EmbeddingProvider::class, $embeddingProvider);
        $this->app->instance(Retriever::class, $retriever);

        $this->expectException(KnowledgeRetrievalException::class);
        $this->expectExceptionMessage(KnowledgeRetrievalException::USER_MESSAGE);

        $this->app->make(RetrieveWorkspaceKnowledge::class)->handle($workspace, 'A question');
    }

    public function test_an_unexpected_query_embedding_mapping_is_rejected(): void
    {
        $workspace = $this->createWorkspace();
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->once()->andReturn(new EmbeddingResult(
            provider: 'cohere',
            model: 'embed-v4.0',
            dimensions: 1024,
            outputs: [new EmbeddingOutput('wrong-key', array_fill(0, 1024, 0.1))],
        ));
        $retriever = Mockery::mock(Retriever::class);
        $retriever->shouldNotReceive('retrieve');
        $this->app->instance(EmbeddingProvider::class, $embeddingProvider);
        $this->app->instance(Retriever::class, $retriever);

        $this->expectException(KnowledgeRetrievalException::class);

        $this->app->make(RetrieveWorkspaceKnowledge::class)->handle($workspace, 'A question');
    }

    public function test_retriever_failures_are_exposed_as_safe_application_errors(): void
    {
        $workspace = $this->createWorkspace();
        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->once()->andReturn(new EmbeddingResult(
            provider: 'cohere',
            model: 'embed-v4.0',
            dimensions: 1024,
            outputs: [new EmbeddingOutput('query', array_fill(0, 1024, 0.1))],
        ));
        $retriever = Mockery::mock(Retriever::class);
        $retriever->shouldReceive('retrieve')->once()->andThrow(
            new RetrievalException(RetrievalException::USER_MESSAGE),
        );
        $this->app->instance(EmbeddingProvider::class, $embeddingProvider);
        $this->app->instance(Retriever::class, $retriever);

        $this->expectException(KnowledgeRetrievalException::class);
        $this->expectExceptionMessage(KnowledgeRetrievalException::USER_MESSAGE);

        $this->app->make(RetrieveWorkspaceKnowledge::class)->handle($workspace, 'A question');
    }

    private function createWorkspace(): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Retrieval Action Test',
            'slug' => 'retrieval-action-'.str()->random(8),
        ]);
    }
}
