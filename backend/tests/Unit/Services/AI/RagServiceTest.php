<?php

namespace Tests\Unit\Services\AI;

use App\Actions\AI\RetrieveWorkspaceKnowledge;
use App\Contracts\AI\LlmProvider;
use App\Data\AI\GenerationRequest;
use App\Data\AI\GenerationResult;
use App\Data\AI\RetrievalResult;
use App\Data\AI\RetrievedChunk;
use App\Enums\GenerationDecision;
use App\Enums\RagAnswerStatus;
use App\Exceptions\AI\KnowledgeRetrievalException;
use App\Exceptions\AI\LlmProviderException;
use App\Exceptions\AI\RagAnswerException;
use App\Models\Workspace;
use App\Services\AI\GroundedAnswerPrompt;
use App\Services\AI\RagContextBuilder;
use App\Services\AI\RagService;
use Mockery;
use Tests\TestCase;

class RagServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.context.max_chunks', 5);
        config()->set('ai.context.max_characters', 12000);
        config()->set('ai.llm.max_output_tokens', 600);
        config()->set('ai.llm.temperature', 0);
    }

    public function test_it_reuses_retrieval_and_constructs_citations_from_trusted_chunk_metadata(): void
    {
        $workspace = $this->workspace();
        $retrieval = Mockery::mock(RetrieveWorkspaceKnowledge::class);
        $provider = Mockery::mock(LlmProvider::class);
        $results = new RetrievalResult([
            $this->chunk(22, 6, 3, 2, 0.82, 'Unused products can be returned within 30 days.'),
            $this->chunk(11, 5, 1, 0, 0.94, 'Products must be unused and in their original packaging.'),
        ]);

        $retrieval->shouldReceive('handle')
            ->once()
            ->withArgs(fn (Workspace $candidate, string $question): bool => $candidate === $workspace
                && $question === 'How long do I have to return an unused product?')
            ->andReturn($results);
        $provider->shouldReceive('generate')
            ->once()
            ->withArgs(function (GenerationRequest $request): bool {
                $payload = json_decode($request->userPrompt, true, flags: JSON_THROW_ON_ERROR);

                return $request->allowedSourceKeys === ['source_1', 'source_2']
                    && array_column($payload['untrusted_sources'], 'content') === [
                        'Products must be unused and in their original packaging.',
                        'Unused products can be returned within 30 days.',
                    ]
                    && ! str_contains($request->userPrompt, 'Returns-5.pdf')
                    && ! str_contains($request->userPrompt, 'Returns-6.pdf');
            })
            ->andReturn(new GenerationResult(
                decision: GenerationDecision::Answered,
                answer: 'Unused products can be returned within 30 days when they remain in their original packaging.',
                sourceKeys: ['source_2', 'source_1'],
                provider: 'groq',
                model: 'openai/gpt-oss-20b',
            ));

        $answer = $this->service($retrieval, $provider)->answer(
            $workspace,
            '  How long do I have to return an unused product?  ',
        );

        $this->assertSame(RagAnswerStatus::Answered, $answer->status);
        $this->assertSame('groq', $answer->provider);
        $this->assertSame('openai/gpt-oss-20b', $answer->model);
        $this->assertSame([5, 6], array_column($answer->citations, 'documentId'));
        $this->assertSame(['Returns-5.pdf', 'Returns-6.pdf'], array_column($answer->citations, 'originalFilename'));
        $this->assertSame([1, 3], array_column($answer->citations, 'pageNumber'));
        $this->assertSame([11, 22], array_column($answer->citations, 'chunkId'));
        $this->assertSame([0, 2], array_column($answer->citations, 'chunkIndex'));
    }

    public function test_empty_retrieval_returns_needs_review_without_calling_the_llm(): void
    {
        $workspace = $this->workspace();
        $retrieval = Mockery::mock(RetrieveWorkspaceKnowledge::class);
        $provider = Mockery::mock(LlmProvider::class);
        $retrieval->shouldReceive('handle')->once()->andReturn(new RetrievalResult([]));
        $provider->shouldNotReceive('generate');

        $answer = $this->service($retrieval, $provider)->answer($workspace, 'Unknown policy?');

        $this->assertSame(RagAnswerStatus::NeedsReview, $answer->status);
        $this->assertSame(RagService::INSUFFICIENT_ANSWER, $answer->answer);
        $this->assertSame('no_retrieval_results', $answer->reason);
        $this->assertSame([], $answer->citations);
        $this->assertNull($answer->provider);
    }

    public function test_model_declared_insufficiency_returns_needs_review_without_citations(): void
    {
        $workspace = $this->workspace();
        $retrieval = Mockery::mock(RetrieveWorkspaceKnowledge::class);
        $provider = Mockery::mock(LlmProvider::class);
        $retrieval->shouldReceive('handle')->once()->andReturn(new RetrievalResult([
            $this->chunk(1, 5, 1, 0, 0.2, 'Unrelated warranty information.'),
        ]));
        $provider->shouldReceive('generate')->once()->andReturn(new GenerationResult(
            decision: GenerationDecision::Insufficient,
            answer: null,
            sourceKeys: [],
            provider: 'groq',
            model: 'openai/gpt-oss-20b',
        ));

        $answer = $this->service($retrieval, $provider)->answer($workspace, 'What is the return period?');

        $this->assertSame(RagAnswerStatus::NeedsReview, $answer->status);
        $this->assertSame('insufficient_evidence', $answer->reason);
        $this->assertSame([], $answer->citations);
        $this->assertSame('groq', $answer->provider);
    }

    public function test_an_untrusted_source_key_never_becomes_a_citation(): void
    {
        $workspace = $this->workspace();
        $retrieval = Mockery::mock(RetrieveWorkspaceKnowledge::class);
        $provider = Mockery::mock(LlmProvider::class);
        $retrieval->shouldReceive('handle')->once()->andReturn(new RetrievalResult([
            $this->chunk(1, 5, 1, 0, 0.9, 'Return policy evidence.'),
        ]));
        $provider->shouldReceive('generate')->once()->andReturn(new GenerationResult(
            decision: GenerationDecision::Answered,
            answer: 'An unsupported answer.',
            sourceKeys: ['source_99'],
            provider: 'groq',
            model: 'openai/gpt-oss-20b',
        ));

        $this->expectException(RagAnswerException::class);
        $this->expectExceptionMessage(RagAnswerException::USER_MESSAGE);

        $this->service($retrieval, $provider)->answer($workspace, 'What is the policy?');
    }

    public function test_retrieval_and_provider_failures_are_exposed_as_safe_rag_errors(): void
    {
        foreach ([
            new KnowledgeRetrievalException(KnowledgeRetrievalException::USER_MESSAGE),
            new LlmProviderException(LlmProviderException::USER_MESSAGE),
        ] as $failure) {
            $workspace = $this->workspace();
            $retrieval = Mockery::mock(RetrieveWorkspaceKnowledge::class);
            $provider = Mockery::mock(LlmProvider::class);

            if ($failure instanceof KnowledgeRetrievalException) {
                $retrieval->shouldReceive('handle')->once()->andThrow($failure);
                $provider->shouldNotReceive('generate');
            } else {
                $retrieval->shouldReceive('handle')->once()->andReturn(new RetrievalResult([
                    $this->chunk(1, 5, 1, 0, 0.9, 'Return policy evidence.'),
                ]));
                $provider->shouldReceive('generate')->once()->andThrow($failure);
            }

            try {
                $this->service($retrieval, $provider)->answer($workspace, 'What is the policy?');
                $this->fail('The RAG service should expose a safe application error.');
            } catch (RagAnswerException $exception) {
                $this->assertSame(RagAnswerException::USER_MESSAGE, $exception->getMessage());
            }
        }
    }

    private function service(RetrieveWorkspaceKnowledge $retrieval, LlmProvider $provider): RagService
    {
        return new RagService(
            retrieveWorkspaceKnowledge: $retrieval,
            contextBuilder: new RagContextBuilder,
            prompt: new GroundedAnswerPrompt,
            llmProvider: $provider,
        );
    }

    private function workspace(): Workspace
    {
        return (new Workspace)->forceFill(['id' => 2, 'name' => 'Test Workspace']);
    }

    private function chunk(
        int $chunkId,
        int $documentId,
        int $pageNumber,
        int $chunkIndex,
        float $similarity,
        string $content,
    ): RetrievedChunk {
        return new RetrievedChunk(
            documentId: $documentId,
            originalFilename: "Returns-{$documentId}.pdf",
            chunkId: $chunkId,
            pageNumber: $pageNumber,
            chunkIndex: $chunkIndex,
            content: $content,
            distance: 1 - $similarity,
            similarity: $similarity,
        );
    }
}
