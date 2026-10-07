<?php

namespace Tests\Feature\Api;

use App\Actions\AI\PersistAssistantInteraction;
use App\Actions\Review\RecordNeedsReviewQuestion;
use App\Data\AI\RagAnswerResult;
use App\Data\AI\RagCitation;
use App\Enums\DocumentStatus;
use App\Enums\RagAnswerStatus;
use App\Exceptions\AI\AssistantInteractionPersistenceException;
use App\Exceptions\AI\RagAnswerException;
use App\Exceptions\Review\ReviewPersistenceException;
use App\Models\AssistantInteraction;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ReviewItem;
use App\Models\Workspace;
use App\Services\AI\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WorkspaceAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_question_returns_a_grounded_answer_with_trusted_citations(): void
    {
        $workspace = $this->createWorkspace();
        [$document, $chunk] = $this->createSource($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')
            ->once()
            ->withArgs(fn (Workspace $candidate, string $question): bool => $candidate->is($workspace)
                && $question === 'How long do I have to return an unused product?')
            ->andReturn(new RagAnswerResult(
                status: RagAnswerStatus::Answered,
                answer: 'Customers may return unused products within 30 days of delivery.',
                citations: [new RagCitation(
                    documentId: $document->id,
                    originalFilename: 'sift-test-knowledge-base.pdf',
                    pageNumber: 1,
                    chunkId: $chunk->id,
                    chunkIndex: 0,
                )],
                provider: 'groq',
                model: 'openai/gpt-oss-20b',
                reason: 'internal-only',
            ));
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => '  How long do I have to return an unused product?  ',
        ])->assertOk()->assertExactJson([
            'data' => [
                'status' => 'answered',
                'answer' => 'Customers may return unused products within 30 days of delivery.',
                'citations' => [[
                    'document_id' => $document->id,
                    'original_filename' => 'sift-test-knowledge-base.pdf',
                    'page_number' => 1,
                    'chunk_id' => $chunk->id,
                    'chunk_index' => 0,
                ]],
            ],
        ]);

        $this->assertDatabaseCount('review_items', 0);
        $this->assertDatabaseCount('assistant_interactions', 1);
    }

    public function test_needs_review_is_a_successful_product_outcome_without_internal_metadata(): void
    {
        $workspace = $this->createWorkspace();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn(new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: 'groq',
            model: 'openai/gpt-oss-20b',
            reason: 'insufficient_evidence',
        ));
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'Does the company offer lifetime repairs?',
        ])->assertOk()->assertExactJson([
            'data' => [
                'status' => 'needs_review',
                'answer' => RagService::INSUFFICIENT_ANSWER,
                'citations' => [],
            ],
        ]);

        $reviewItem = ReviewItem::query()->sole();

        $this->assertTrue($reviewItem->workspace->is($workspace));
        $this->assertSame('Does the company offer lifetime repairs?', $reviewItem->question);
        $this->assertTrue(AssistantInteraction::query()->sole()->reviewItem->is($reviewItem));
    }

    public function test_invalid_questions_are_rejected_before_rag_runs(): void
    {
        $workspace = $this->createWorkspace();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        foreach ([
            [],
            ['question' => '   '],
            ['question' => ['not', 'a', 'string']],
            ['question' => str_repeat('a', 2001)],
        ] as $payload) {
            $this->postJson(route('workspaces.answers.store', $workspace), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('question');
        }
    }

    public function test_an_unknown_workspace_returns_not_found_without_running_rag(): void
    {
        $this->createWorkspace();

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        $this->postJson('/api/workspaces/999999/answers', [
            'question' => 'What is the return policy?',
        ])->assertNotFound();
    }

    public function test_rag_failures_return_only_the_safe_api_error(): void
    {
        $workspace = $this->createWorkspace();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andThrow(new RagAnswerException(
            RagAnswerException::USER_MESSAGE,
            previous: new RuntimeException('Sensitive provider response.'),
        ));
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'What is the return policy?',
        ])->assertServiceUnavailable()->assertExactJson([
            'message' => RagAnswerException::USER_MESSAGE,
            'code' => 'answer_unavailable',
        ])->assertJsonMissing(['Sensitive provider response.']);

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_a_review_persistence_failure_returns_only_a_safe_error(): void
    {
        $workspace = $this->createWorkspace();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn(new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: null,
            model: null,
            reason: 'no_retrieval_results',
        ));
        $recorder = Mockery::mock(RecordNeedsReviewQuestion::class);
        $recorder->shouldReceive('handle')->once()->andThrow(new ReviewPersistenceException(
            ReviewPersistenceException::USER_MESSAGE,
            previous: new RuntimeException('Sensitive database failure.'),
        ));
        $this->app->instance(RagService::class, $ragService);
        $this->app->instance(RecordNeedsReviewQuestion::class, $recorder);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'What is the return policy?',
        ])->assertServiceUnavailable()->assertExactJson([
            'message' => ReviewPersistenceException::USER_MESSAGE,
            'code' => 'review_unavailable',
        ])->assertJsonMissing(['Sensitive database failure.']);

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_a_history_persistence_failure_returns_only_a_safe_error(): void
    {
        $workspace = $this->createWorkspace();
        $ragService = Mockery::mock(RagService::class);
        $result = new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: null,
            model: null,
            reason: 'no_retrieval_results',
        );
        $ragService->shouldReceive('answer')->once()->andReturn($result);
        $persistence = Mockery::mock(PersistAssistantInteraction::class);
        $persistence->shouldReceive('handle')->once()->andThrow(new AssistantInteractionPersistenceException(
            AssistantInteractionPersistenceException::USER_MESSAGE,
            previous: new RuntimeException('Sensitive persistence failure.'),
        ));
        $this->app->instance(RagService::class, $ragService);
        $this->app->instance(PersistAssistantInteraction::class, $persistence);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'What is the return policy?',
        ])->assertServiceUnavailable()->assertExactJson([
            'message' => AssistantInteractionPersistenceException::USER_MESSAGE,
            'code' => 'history_unavailable',
        ])->assertJsonMissing(['Sensitive persistence failure.']);

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_the_temporary_route_throttle_limits_repeated_requests(): void
    {
        $workspace = $this->createWorkspace();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->times(10)->andReturn(new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: null,
            model: null,
            reason: 'no_retrieval_results',
        ));
        $this->app->instance(RagService::class, $ragService);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson(route('workspaces.answers.store', $workspace), [
                'question' => "Question {$attempt}",
            ])->assertOk();
        }

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'One request too many',
        ])->assertTooManyRequests();
    }

    private function createWorkspace(): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => 'Assistant API Test',
            'slug' => 'assistant-api-test-'.str()->random(8),
        ]);

        if (! $this->app['auth']->check()) {
            $this->actingAsWorkspaceMember($workspace);
        }

        return $workspace;
    }

    /**
     * @return array{Document, DocumentChunk}
     */
    private function createSource(Workspace $workspace): array
    {
        $document = Document::query()->create([
            'workspace_id' => $workspace->id,
            'original_filename' => 'sift-test-knowledge-base.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'documents/'.str()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Ready,
            'page_count' => 1,
            'processed_at' => now(),
        ]);
        $chunk = DocumentChunk::query()->create([
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
            'content' => 'Unused products may be returned within 30 days.',
            'page_number' => 1,
            'chunk_index' => 0,
        ]);

        return [$document, $chunk];
    }
}
