<?php

namespace Tests\Feature\Api;

use App\Enums\DocumentStatus;
use App\Enums\RagAnswerStatus;
use App\Enums\ReviewItemStatus;
use App\Models\AssistantInteraction;
use App\Models\Document;
use App\Models\ReviewItem;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkspaceOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_workspace_returns_real_zero_counts_and_empty_activity(): void
    {
        $workspace = $this->createWorkspace('empty', 'Empty Workspace');

        $this->getJson(route('workspaces.overview.show', $workspace))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'workspace' => [
                        'id' => $workspace->id,
                        'name' => 'Empty Workspace',
                    ],
                    'documents' => [
                        'total' => 0,
                        'ready' => 0,
                        'processing' => 0,
                        'failed' => 0,
                    ],
                    'interactions' => [
                        'total' => 0,
                        'answered' => 0,
                        'needs_review' => 0,
                    ],
                    'reviews' => [
                        'pending' => 0,
                    ],
                    'recent_documents' => [],
                    'recent_interactions' => [],
                ],
            ]);
    }

    public function test_overview_counts_and_recent_activity_are_workspace_scoped(): void
    {
        $workspace = $this->createWorkspace('primary', 'Acme Support');
        $otherWorkspace = $this->createWorkspace('other', 'Private Workspace');

        $ready = $this->createDocument($workspace, DocumentStatus::Ready, 'ready.pdf', '2026-10-03 09:00:00');
        $processing = $this->createDocument($workspace, DocumentStatus::Processing, 'processing.pdf', '2026-10-03 10:00:00');
        $failed = $this->createDocument($workspace, DocumentStatus::Failed, 'failed.pdf', '2026-10-03 11:00:00');
        $answered = $this->createInteraction($workspace, RagAnswerStatus::Answered, 'Answered question', '2026-10-03 12:00:00');
        $needsReview = $this->createInteraction($workspace, RagAnswerStatus::NeedsReview, 'Unknown question', '2026-10-03 13:00:00');
        $this->createReviewItem($workspace, ReviewItemStatus::Resolved, 'Resolved question');
        $this->createDocument($otherWorkspace, DocumentStatus::Ready, 'private.pdf', '2026-10-03 14:00:00');
        $this->createInteraction($otherWorkspace, RagAnswerStatus::Answered, 'Private question', '2026-10-03 14:00:00');

        $response = $this->getJson(route('workspaces.overview.show', $workspace));

        $response->assertOk()
            ->assertJsonPath('data.workspace.name', 'Acme Support')
            ->assertJsonPath('data.documents', [
                'total' => 3,
                'ready' => 1,
                'processing' => 1,
                'failed' => 1,
            ])
            ->assertJsonPath('data.interactions', [
                'total' => 2,
                'answered' => 1,
                'needs_review' => 1,
            ])
            ->assertJsonPath('data.reviews.pending', 1)
            ->assertJsonPath('data.recent_documents.0.id', $failed->id)
            ->assertJsonPath('data.recent_documents.1.id', $processing->id)
            ->assertJsonPath('data.recent_documents.2.id', $ready->id)
            ->assertJsonPath('data.recent_interactions.0.id', $needsReview->id)
            ->assertJsonPath('data.recent_interactions.1.id', $answered->id)
            ->assertJsonMissing(['original_filename' => 'private.pdf'])
            ->assertJsonMissing(['question' => 'Private question']);
    }

    public function test_recent_sections_are_limited_to_five_and_hide_internal_fields(): void
    {
        $workspace = $this->createWorkspace('limits', 'Limited Workspace');

        foreach (range(1, 6) as $index) {
            $this->createDocument(
                $workspace,
                DocumentStatus::Ready,
                "document-{$index}.pdf",
                "2026-10-03 10:00:0{$index}",
            );
            $this->createInteraction(
                $workspace,
                RagAnswerStatus::Answered,
                "Question {$index}",
                "2026-10-03 11:00:0{$index}",
            );
        }

        $this->getJson(route('workspaces.overview.show', $workspace))
            ->assertOk()
            ->assertJsonCount(5, 'data.recent_documents')
            ->assertJsonCount(5, 'data.recent_interactions')
            ->assertJsonPath('data.recent_documents.0.original_filename', 'document-6.pdf')
            ->assertJsonPath('data.recent_interactions.0.question', 'Question 6')
            ->assertJsonMissing(['storage_path' => 'internal'])
            ->assertJsonMissing(['provider' => 'internal-provider'])
            ->assertJsonMissing(['model' => 'internal-model'])
            ->assertJsonMissing(['answer' => 'Internal answer']);
    }

    public function test_an_unknown_workspace_returns_not_found(): void
    {
        $this->createWorkspace('known', 'Known Workspace');

        $this->getJson('/api/workspaces/999999/overview')->assertNotFound();
    }

    private function createWorkspace(string $slug, string $name): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => $name,
            'slug' => "overview-{$slug}",
        ]);

        if (! $this->app['auth']->check()) {
            $this->actingAsWorkspaceMember($workspace);
        }

        return $workspace;
    }

    private function createDocument(
        Workspace $workspace,
        DocumentStatus $status,
        string $filename,
        string $createdAt,
    ): Document {
        Carbon::setTestNow($createdAt);
        $document = Document::query()->create([
            'workspace_id' => $workspace->id,
            'original_filename' => $filename,
            'storage_disk' => 'local',
            'storage_path' => 'internal/'.str()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => $status,
            'page_count' => $status === DocumentStatus::Ready ? 1 : null,
        ]);
        Carbon::setTestNow();

        return $document;
    }

    private function createInteraction(
        Workspace $workspace,
        RagAnswerStatus $status,
        string $question,
        string $createdAt,
    ): AssistantInteraction {
        $reviewItem = $status === RagAnswerStatus::NeedsReview
            ? $this->createReviewItem($workspace, ReviewItemStatus::Pending, $question)
            : null;
        Carbon::setTestNow($createdAt);
        $interaction = AssistantInteraction::query()->create([
            'workspace_id' => $workspace->id,
            'review_item_id' => $reviewItem?->id,
            'question' => $question,
            'status' => $status,
            'answer' => $status === RagAnswerStatus::Answered
                ? 'Internal answer'
                : 'Insufficient workspace knowledge.',
            'provider' => $status === RagAnswerStatus::Answered ? 'internal-provider' : null,
            'model' => $status === RagAnswerStatus::Answered ? 'internal-model' : null,
        ]);
        Carbon::setTestNow();

        return $interaction;
    }

    private function createReviewItem(
        Workspace $workspace,
        ReviewItemStatus $status,
        string $question,
    ): ReviewItem {
        return ReviewItem::query()->create([
            'workspace_id' => $workspace->id,
            'question' => $question,
            'deduplication_key' => $status === ReviewItemStatus::Pending
                ? hash('sha256', strtolower($question))
                : null,
            'status' => $status,
            'resolution' => $status === ReviewItemStatus::Resolved ? 'Resolved internally.' : null,
            'last_asked_at' => now(),
            'resolved_at' => $status === ReviewItemStatus::Resolved ? now() : null,
        ]);
    }
}
