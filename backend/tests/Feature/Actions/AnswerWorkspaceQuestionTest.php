<?php

namespace Tests\Feature\Actions;

use App\Actions\AI\AnswerWorkspaceQuestion;
use App\Actions\Review\RecordNeedsReviewQuestion;
use App\Data\AI\AssistantInteractionContext;
use App\Data\AI\RagAnswerResult;
use App\Data\AI\RagCitation;
use App\Enums\AssistantInteractionOrigin;
use App\Enums\DocumentStatus;
use App\Enums\RagAnswerStatus;
use App\Enums\ReviewItemStatus;
use App\Exceptions\AI\AssistantInteractionPersistenceException;
use App\Models\AssistantInteraction;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ReviewItem;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use App\Services\AI\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AnswerWorkspaceQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_answered_results_do_not_create_review_items(): void
    {
        $workspace = $this->createWorkspace('answered');
        [$document, $chunk] = $this->createSource($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->answeredResult($document, $chunk));
        $this->app->instance(RagService::class, $ragService);

        $result = $this->app->make(AnswerWorkspaceQuestion::class)
            ->handle($workspace, 'What is the return policy?');

        $this->assertSame(RagAnswerStatus::Answered, $result->status);
        $this->assertDatabaseCount('review_items', 0);
        $this->assertDatabaseCount('assistant_interactions', 1);
        $this->assertDatabaseCount('assistant_interaction_citations', 1);
        $interaction = AssistantInteraction::query()->sole();
        $this->assertSame(AssistantInteractionOrigin::Assistant, $interaction->origin);
        $this->assertNull($interaction->widget_conversation_id);
    }

    public function test_widget_interaction_context_is_persisted_without_changing_rag_behavior(): void
    {
        $workspace = $this->createWorkspace('widget-context');
        $conversation = $this->createWidgetConversation($workspace);
        [$document, $chunk] = $this->createSource($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')
            ->once()
            ->with($workspace, 'What is the return policy?')
            ->andReturn($this->answeredResult($document, $chunk));
        $this->app->instance(RagService::class, $ragService);

        $this->app->make(AnswerWorkspaceQuestion::class)->handle(
            $workspace,
            'What is the return policy?',
            AssistantInteractionContext::widget($conversation),
        );

        $interaction = AssistantInteraction::query()->sole();
        $this->assertSame(AssistantInteractionOrigin::Widget, $interaction->origin);
        $this->assertTrue($interaction->widgetConversation->is($conversation));
        $this->assertDatabaseCount('assistant_interaction_citations', 1);
    }

    public function test_equivalent_pending_questions_are_deduplicated_and_refresh_last_asked_at(): void
    {
        $workspace = $this->createWorkspace('deduplication');
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->twice()->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);
        $action = $this->app->make(AnswerWorkspaceQuestion::class);

        Carbon::setTestNow('2026-10-03 10:00:00');
        $action->handle($workspace, "  What is the company's CEO's name?  ");
        $firstSeen = ReviewItem::query()->sole()->last_asked_at;

        Carbon::setTestNow('2026-10-03 10:15:00');
        $action->handle($workspace, "what   is the COMPANY'S ceo's name?");

        $reviewItem = ReviewItem::query()->sole();

        $this->assertSame("What is the company's CEO's name?", $reviewItem->question);
        $this->assertTrue($reviewItem->last_asked_at->greaterThan($firstSeen));
        $this->assertSame('2026-10-03 10:15:00', $reviewItem->last_asked_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('assistant_interactions', 2);
        $this->assertSame(1, AssistantInteraction::query()->distinct('review_item_id')->count('review_item_id'));

        Carbon::setTestNow();
    }

    public function test_deduplication_never_crosses_workspace_boundaries(): void
    {
        $firstWorkspace = $this->createWorkspace('first');
        $secondWorkspace = $this->createWorkspace('second');
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->twice()->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);
        $action = $this->app->make(AnswerWorkspaceQuestion::class);

        $action->handle($firstWorkspace, 'Who is the CEO?');
        $action->handle($secondWorkspace, 'Who is the CEO?');

        $this->assertDatabaseCount('review_items', 2);
        $this->assertSame(1, $firstWorkspace->reviewItems()->count());
        $this->assertSame(1, $secondWorkspace->reviewItems()->count());
        $this->assertSame(1, $firstWorkspace->assistantInteractions()->count());
        $this->assertSame(1, $secondWorkspace->assistantInteractions()->count());
    }

    public function test_a_resolved_question_can_create_a_new_pending_item(): void
    {
        $workspace = $this->createWorkspace('resolved-repeat');
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->twice()->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);
        $action = $this->app->make(AnswerWorkspaceQuestion::class);

        $action->handle($workspace, 'Who is the CEO?');
        $existing = ReviewItem::query()->sole();
        $existing->update([
            'status' => ReviewItemStatus::Resolved,
            'resolution' => 'A human supplied answer.',
            'deduplication_key' => null,
            'resolved_at' => now(),
        ]);

        $action->handle($workspace, 'Who is the CEO?');

        $this->assertDatabaseCount('review_items', 2);
        $this->assertSame(1, $workspace->reviewItems()->where('status', ReviewItemStatus::Pending)->count());
        $this->assertSame(1, $workspace->reviewItems()->where('status', ReviewItemStatus::Resolved)->count());
        $this->assertDatabaseCount('assistant_interactions', 2);
    }

    public function test_citation_snapshots_survive_source_deletion(): void
    {
        $workspace = $this->createWorkspace('source-deletion');
        [$document, $chunk] = $this->createSource($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->answeredResult($document, $chunk));
        $this->app->instance(RagService::class, $ragService);

        $this->app->make(AnswerWorkspaceQuestion::class)
            ->handle($workspace, 'What is the return policy?');

        $document->delete();
        $citation = AssistantInteraction::query()->sole()->citations()->sole();

        $this->assertNull($citation->document_id);
        $this->assertNull($citation->document_chunk_id);
        $this->assertSame('returns.pdf', $citation->original_filename);
        $this->assertSame(1, $citation->page_number);
        $this->assertSame(0, $citation->chunk_index);
    }

    public function test_persistence_failure_rolls_back_review_and_history(): void
    {
        $workspace = $this->createWorkspace('rollback');
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->needsReviewResult());
        $recorder = Mockery::mock(RecordNeedsReviewQuestion::class);
        $recorder->shouldReceive('handle')->once()->andReturnUsing(function (Workspace $workspace): never {
            ReviewItem::query()->create([
                'workspace_id' => $workspace->id,
                'question' => 'Who is the CEO?',
                'deduplication_key' => hash('sha256', 'who is the ceo?'),
                'status' => ReviewItemStatus::Pending,
                'last_asked_at' => now(),
            ]);

            throw new RuntimeException('Simulated persistence failure.');
        });
        $this->app->instance(RagService::class, $ragService);
        $this->app->instance(RecordNeedsReviewQuestion::class, $recorder);

        $this->expectException(AssistantInteractionPersistenceException::class);

        try {
            $this->app->make(AnswerWorkspaceQuestion::class)->handle($workspace, 'Who is the CEO?');
        } finally {
            $this->assertDatabaseCount('review_items', 0);
            $this->assertDatabaseCount('assistant_interactions', 0);
        }
    }

    private function answeredResult(Document $document, DocumentChunk $chunk): RagAnswerResult
    {
        return new RagAnswerResult(
            status: RagAnswerStatus::Answered,
            answer: 'Unused products can be returned within 30 days.',
            citations: [new RagCitation($document->id, 'returns.pdf', 1, $chunk->id, 0)],
            provider: 'test',
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

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Review Action Test',
            'slug' => "review-action-{$suffix}",
        ]);
    }

    private function createWidgetConversation(Workspace $workspace): WidgetConversation
    {
        return WidgetConversation::query()->create([
            'workspace_id' => $workspace->id,
            'session_token_hash' => hash('sha256', "widget-session-{$workspace->id}"),
            'expires_at' => now()->addDay(),
            'last_activity_at' => now(),
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
}
