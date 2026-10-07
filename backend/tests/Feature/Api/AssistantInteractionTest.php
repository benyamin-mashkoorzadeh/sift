<?php

namespace Tests\Feature\Api;

use App\Enums\AssistantInteractionOrigin;
use App\Enums\RagAnswerStatus;
use App\Enums\ReviewItemStatus;
use App\Models\AssistantInteraction;
use App\Models\ReviewItem;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssistantInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_newest_first_and_strictly_workspace_scoped(): void
    {
        $workspace = $this->createWorkspace('history');
        $otherWorkspace = $this->createWorkspace('other');

        Carbon::setTestNow('2026-10-03 10:00:00');
        $older = $this->createAnsweredInteraction($workspace, 'Older question');
        Carbon::setTestNow('2026-10-03 11:00:00');
        $newer = $this->createNeedsReviewInteraction($workspace, 'Newer question');
        $this->createAnsweredInteraction($otherWorkspace, 'Private question');
        Carbon::setTestNow();

        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonMissing(['question' => 'Private question']);
    }

    public function test_history_can_filter_both_supported_outcomes(): void
    {
        $workspace = $this->createWorkspace('filters');
        $answered = $this->createAnsweredInteraction($workspace, 'Answered question');
        $needsReview = $this->createNeedsReviewInteraction($workspace, 'Unknown question');

        $this->getJson(route('workspaces.assistant-interactions.index', [
            'workspace' => $workspace,
            'status' => 'answered',
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $answered->id);

        $this->getJson(route('workspaces.assistant-interactions.index', [
            'workspace' => $workspace,
            'status' => 'needs_review',
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $needsReview->id);
    }

    public function test_history_returns_citation_snapshots_without_internal_provider_metadata(): void
    {
        $workspace = $this->createWorkspace('citations');
        $interaction = $this->createAnsweredInteraction($workspace, 'What is the return policy?');
        $interaction->citations()->create([
            'document_id' => null,
            'document_chunk_id' => null,
            'original_filename' => 'deleted-return-policy.pdf',
            'page_number' => 3,
            'chunk_index' => 7,
            'position' => 0,
        ]);

        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))
            ->assertOk()
            ->assertJsonPath('data.0.citations.0.original_filename', 'deleted-return-policy.pdf')
            ->assertJsonPath('data.0.citations.0.page_number', 3)
            ->assertJsonPath('data.0.citations.0.chunk_index', 7)
            ->assertJsonPath('data.0.citations.0.document_id', null)
            ->assertJsonPath('data.0.citations.0.chunk_id', null)
            ->assertJsonPath('data.0.citations.0.source_available', false)
            ->assertJsonPath('data.0.origin', 'assistant')
            ->assertJsonMissing(['provider' => 'internal-provider'])
            ->assertJsonMissing(['model' => 'internal-model']);
    }

    public function test_history_exposes_widget_origin_without_conversation_credentials_or_session_details(): void
    {
        $workspace = $this->createWorkspace('widget-origin');
        $conversation = WidgetConversation::query()->create([
            'workspace_id' => $workspace->id,
            'session_token_hash' => hash('sha256', 'private-widget-token'),
            'expires_at' => now()->addDay(),
            'last_activity_at' => now(),
        ]);
        AssistantInteraction::query()->create([
            'workspace_id' => $workspace->id,
            'origin' => AssistantInteractionOrigin::Widget,
            'widget_conversation_id' => $conversation->id,
            'question' => 'Do you ship to Canada?',
            'status' => RagAnswerStatus::Answered,
            'answer' => 'Yes.',
            'provider' => 'internal-provider',
            'model' => 'internal-model',
        ]);

        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))
            ->assertOk()
            ->assertJsonPath('data.0.origin', 'widget')
            ->assertJsonMissingPath('data.0.widget_conversation_id')
            ->assertJsonMissingPath('data.0.conversation_token')
            ->assertJsonMissingPath('data.0.session_token_hash')
            ->assertJsonMissing(['provider' => 'internal-provider'])
            ->assertJsonMissing(['model' => 'internal-model']);
    }

    public function test_needs_review_history_reflects_the_current_human_resolution(): void
    {
        $workspace = $this->createWorkspace('resolution');
        $interaction = $this->createNeedsReviewInteraction($workspace, 'Who is the CEO?');
        $reviewItem = $interaction->reviewItem;
        $reviewItem->update([
            'status' => ReviewItemStatus::Resolved,
            'resolution' => 'The current CEO is Jane Example.',
            'deduplication_key' => null,
            'resolved_at' => now(),
        ]);

        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))
            ->assertOk()
            ->assertJsonPath('data.0.review.id', $reviewItem->id)
            ->assertJsonPath('data.0.review.status', 'resolved')
            ->assertJsonPath('data.0.review.resolution', 'The current CEO is Jane Example.');
    }

    public function test_history_is_paginated_and_validates_filters(): void
    {
        $workspace = $this->createWorkspace('pagination');

        foreach (range(1, 21) as $index) {
            $this->createAnsweredInteraction($workspace, "Question {$index}");
        }

        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson(route('workspaces.assistant-interactions.index', [
            'workspace' => $workspace,
            'status' => 'invalid',
        ]))->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_deleting_a_workspace_removes_its_history_and_review_items(): void
    {
        $workspace = $this->createWorkspace('workspace-deletion');
        $this->createNeedsReviewInteraction($workspace, 'Who is the CEO?');

        $workspace->delete();

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    private function createAnsweredInteraction(Workspace $workspace, string $question): AssistantInteraction
    {
        return AssistantInteraction::query()->create([
            'workspace_id' => $workspace->id,
            'question' => $question,
            'status' => RagAnswerStatus::Answered,
            'answer' => 'A grounded answer.',
            'provider' => 'internal-provider',
            'model' => 'internal-model',
        ]);
    }

    private function createNeedsReviewInteraction(Workspace $workspace, string $question): AssistantInteraction
    {
        $reviewItem = ReviewItem::query()->create([
            'workspace_id' => $workspace->id,
            'question' => $question,
            'deduplication_key' => hash('sha256', strtolower($question)),
            'status' => ReviewItemStatus::Pending,
            'last_asked_at' => now(),
        ]);

        return AssistantInteraction::query()->create([
            'workspace_id' => $workspace->id,
            'review_item_id' => $reviewItem->id,
            'question' => $question,
            'status' => RagAnswerStatus::NeedsReview,
            'answer' => 'I could not find enough information to answer reliably.',
            'provider' => null,
            'model' => null,
        ]);
    }

    private function createWorkspace(string $suffix): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => 'Assistant History Test',
            'slug' => "assistant-history-{$suffix}",
        ]);

        if (! $this->app['auth']->check()) {
            $this->actingAsWorkspaceMember($workspace);
        }

        return $workspace;
    }
}
