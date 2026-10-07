<?php

namespace Tests\Feature\Api;

use App\Data\AI\RagAnswerResult;
use App\Data\AI\RagCitation;
use App\Enums\DocumentStatus;
use App\Enums\RagAnswerStatus;
use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\RagService;
use App\Services\Auth\DemoSessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class GuestAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_answered_question_uses_rag_and_returns_citations_without_persisting(): void
    {
        [$guest, $workspace] = $this->createDemoPrincipal();
        [$document, $chunk] = $this->createSource($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')
            ->once()
            ->withArgs(fn (Workspace $candidate, string $question): bool => $candidate->is($workspace)
                && $question === 'How long do I have to return an unused product?')
            ->andReturn($this->answeredResult($document, $chunk));
        $this->app->instance(RagService::class, $ragService);
        $this->enterDemo();

        $this->statefulPostJson(route('workspaces.answers.store', $workspace), [
            'question' => '  How long do I have to return an unused product?  ',
        ])->assertOk()->assertExactJson([
            'data' => [
                'status' => 'answered',
                'answer' => 'Unused products may be returned within 30 days.',
                'citations' => [[
                    'document_id' => $document->id,
                    'original_filename' => 'returns.pdf',
                    'page_number' => 1,
                    'chunk_id' => $chunk->id,
                    'chunk_index' => 0,
                ]],
            ],
        ]);

        $this->assertAuthenticatedAs($guest);
        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('assistant_interaction_citations', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_demo_unsupported_question_returns_needs_review_without_persisting(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')
            ->once()
            ->withArgs(fn (Workspace $candidate, string $question): bool => $candidate->is($workspace)
                && $question === "What is the company's CEO's name?")
            ->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);
        $this->enterDemo();

        $this->statefulPostJson(route('workspaces.answers.store', $workspace), [
            'question' => "What is the company's CEO's name?",
        ])->assertOk()->assertExactJson([
            'data' => [
                'status' => 'needs_review',
                'answer' => RagService::INSUFFICIENT_ANSWER,
                'citations' => [],
            ],
        ]);

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('assistant_interaction_citations', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_prepared_history_and_review_items_remain_unchanged_after_demo_question(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        $reviewItem = $workspace->reviewItems()->create([
            'question' => 'Who is the CEO?',
            'deduplication_key' => hash('sha256', 'who is the ceo?'),
            'status' => 'pending',
            'last_asked_at' => now()->subDay(),
        ]);
        $interaction = $workspace->assistantInteractions()->create([
            'review_item_id' => $reviewItem->id,
            'question' => 'Who is the CEO?',
            'status' => 'needs_review',
            'answer' => RagService::INSUFFICIENT_ANSWER,
            'provider' => null,
            'model' => null,
        ]);
        $reviewAttributes = $reviewItem->fresh()->getAttributes();
        $interactionAttributes = $interaction->fresh()->getAttributes();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);
        $this->enterDemo();
        Carbon::setTestNow(now()->addHour());

        try {
            $this->statefulPostJson(route('workspaces.answers.store', $workspace), [
                'question' => 'Who is the CEO?',
            ])->assertOk()->assertJsonPath('data.status', 'needs_review');
        } finally {
            Carbon::setTestNow();
        }

        $this->assertDatabaseCount('assistant_interactions', 1);
        $this->assertDatabaseCount('assistant_interaction_citations', 0);
        $this->assertDatabaseCount('review_items', 1);
        $this->assertSame($reviewAttributes, $reviewItem->fresh()->getAttributes());
        $this->assertSame($interactionAttributes, $interaction->fresh()->getAttributes());
    }

    public function test_normal_authenticated_question_still_uses_the_persistent_path(): void
    {
        $workspace = $this->createWorkspace('normal-assistant');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);
        [$document, $chunk] = $this->createSource($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->answeredResult($document, $chunk));
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'What is the return policy?',
        ])->assertOk()->assertJsonPath('data.status', 'answered');

        $this->assertDatabaseCount('assistant_interactions', 1);
        $this->assertDatabaseCount('assistant_interaction_citations', 1);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_cross_workspace_demo_question_remains_concealed_and_does_not_run_rag(): void
    {
        $this->createDemoPrincipal();
        $otherWorkspace = $this->createWorkspace('private-workspace');
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);
        $this->enterDemo();

        $this->statefulPostJson(route('workspaces.answers.store', $otherWorkspace), [
            'question' => 'What is private?',
        ])->assertNotFound();

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_incomplete_demo_context_cannot_reach_guest_rag_or_persistence(): void
    {
        [$guest, $workspace] = $this->createDemoPrincipal();
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);
        $this->withHeader('Origin', 'http://localhost:3000')
            ->actingAs($guest)
            ->withSession([
                DemoSessionContext::ACCESS_MODE_KEY => WorkspaceAccessMode::Demo->value,
                DemoSessionContext::USER_ID_KEY => $guest->id,
            ])
            ->postJson(route('workspaces.answers.store', $workspace), [
                'question' => 'Can incomplete context use the Assistant?',
            ])->assertForbidden();

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    /**
     * @return array{User, Workspace}
     */
    private function createDemoPrincipal(): array
    {
        $workspace = $this->createWorkspace('guest-assistant');
        $guest = User::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'password' => 'GuestPassword123',
        ]);
        $workspace->users()->attach($guest->id, ['role' => WorkspaceRole::Member->value]);
        config()->set('demo.enabled', true);
        config()->set('demo.user_id', $guest->id);
        config()->set('demo.workspace_id', $workspace->id);
        config()->set('demo.assistant.enabled', true);

        return [$guest, $workspace];
    }

    private function enterDemo(): void
    {
        $this->statefulPostJson(route('auth.demo'))
            ->assertOk()
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Demo->value);
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Guest Assistant Test',
            'slug' => $suffix.'-'.str()->random(8),
        ]);
    }

    /**
     * @return array{Document, DocumentChunk}
     */
    private function createSource(Workspace $workspace): array
    {
        $document = Document::query()->create([
            'workspace_id' => $workspace->id,
            'original_filename' => 'returns.pdf',
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

    private function answeredResult(Document $document, DocumentChunk $chunk): RagAnswerResult
    {
        return new RagAnswerResult(
            status: RagAnswerStatus::Answered,
            answer: 'Unused products may be returned within 30 days.',
            citations: [new RagCitation($document->id, 'returns.pdf', 1, $chunk->id, 0)],
            provider: 'test-provider',
            model: 'test-model',
        );
    }

    private function needsReviewResult(): RagAnswerResult
    {
        return new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: null,
            model: null,
            reason: 'no_retrieval_results',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statefulPostJson(string $uri, array $data = [])
    {
        return $this->withHeader('Origin', 'http://localhost:3000')->postJson($uri, $data);
    }
}
