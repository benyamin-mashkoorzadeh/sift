<?php

namespace Tests\Feature\Api;

use App\Enums\ReviewItemStatus;
use App\Models\ReviewItem;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_defaults_to_pending_orders_by_last_asked_and_is_workspace_scoped(): void
    {
        $workspace = $this->createWorkspace('listing');
        $otherWorkspace = $this->createWorkspace('other');
        $older = $this->createPendingItem($workspace, 'Older question', '2026-10-03 09:00:00');
        $newer = $this->createPendingItem($workspace, 'Newer question', '2026-10-03 11:00:00');
        $this->createResolvedItem($workspace, 'Resolved question');
        $this->createPendingItem($otherWorkspace, 'Other workspace question');

        $response = $this->getJson(route('workspaces.review-items.index', $workspace));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonMissing(['deduplication_key']);
    }

    public function test_listing_can_filter_resolved_items(): void
    {
        $workspace = $this->createWorkspace('resolved-list');
        $this->createPendingItem($workspace, 'Pending question');
        $resolved = $this->createResolvedItem($workspace, 'Resolved question');

        $this->getJson(route('workspaces.review-items.index', [
            'workspace' => $workspace,
            'status' => 'resolved',
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $resolved->id)
            ->assertJsonPath('data.0.status', 'resolved')
            ->assertJsonPath('data.0.resolution', 'Human resolution');
    }

    public function test_listing_is_paginated_and_validates_filters(): void
    {
        $workspace = $this->createWorkspace('pagination');

        foreach (range(1, 21) as $index) {
            $this->createPendingItem($workspace, "Question {$index}", "2026-10-03 10:00:{$index}");
        }

        $this->getJson(route('workspaces.review-items.index', $workspace))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson(route('workspaces.review-items.index', [
            'workspace' => $workspace,
            'status' => 'invalid',
        ]))->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_a_pending_item_can_be_resolved_with_a_human_answer(): void
    {
        $workspace = $this->createWorkspace('resolve');
        $reviewItem = $this->createPendingItem($workspace, 'Who is the CEO?');

        $this->patchJson(route('workspaces.review-items.resolve', [$workspace, $reviewItem]), [
            'resolution' => '  The current CEO is Jane Example.  ',
        ])->assertOk()->assertExactJson([
            'data' => [
                'id' => $reviewItem->id,
                'status' => 'resolved',
                'question' => 'Who is the CEO?',
                'resolution' => 'The current CEO is Jane Example.',
                'created_at' => $reviewItem->created_at->toISOString(),
                'last_asked_at' => $reviewItem->last_asked_at->toISOString(),
                'resolved_at' => ReviewItem::query()->findOrFail($reviewItem->id)->resolved_at->toISOString(),
            ],
        ]);

        $reviewItem->refresh();
        $this->assertSame(ReviewItemStatus::Resolved, $reviewItem->status);
        $this->assertNull($reviewItem->deduplication_key);
        $this->assertNotNull($reviewItem->resolved_at);
    }

    public function test_resolution_is_required_and_limited(): void
    {
        $workspace = $this->createWorkspace('validation');
        $reviewItem = $this->createPendingItem($workspace, 'Who is the CEO?');

        foreach ([null, '   ', str_repeat('a', 4001)] as $resolution) {
            $this->patchJson(route('workspaces.review-items.resolve', [$workspace, $reviewItem]), [
                'resolution' => $resolution,
            ])->assertUnprocessable()->assertJsonValidationErrors('resolution');
        }

        $this->assertSame(ReviewItemStatus::Pending, $reviewItem->fresh()->status);
    }

    public function test_a_resolved_item_cannot_be_overwritten(): void
    {
        $workspace = $this->createWorkspace('conflict');
        $reviewItem = $this->createResolvedItem($workspace, 'Who is the CEO?');

        $this->patchJson(route('workspaces.review-items.resolve', [$workspace, $reviewItem]), [
            'resolution' => 'Replacement answer',
        ])->assertConflict()->assertExactJson([
            'message' => 'This review item has already been resolved.',
            'code' => 'review_item_already_resolved',
        ]);

        $this->assertSame('Human resolution', $reviewItem->fresh()->resolution);
    }

    public function test_nested_binding_prevents_cross_workspace_resolution(): void
    {
        $workspace = $this->createWorkspace('binding');
        $otherWorkspace = $this->createWorkspace('binding-other');
        $reviewItem = $this->createPendingItem($otherWorkspace, 'Other workspace question');

        $this->patchJson(route('workspaces.review-items.resolve', [$workspace, $reviewItem]), [
            'resolution' => 'Must not be applied',
        ])->assertNotFound();

        $this->assertSame(ReviewItemStatus::Pending, $reviewItem->fresh()->status);
    }

    private function createPendingItem(
        Workspace $workspace,
        string $question,
        string $lastAskedAt = '2026-10-03 10:00:00',
    ): ReviewItem {
        return ReviewItem::query()->create([
            'workspace_id' => $workspace->id,
            'question' => $question,
            'deduplication_key' => hash('sha256', strtolower($question)),
            'status' => ReviewItemStatus::Pending,
            'last_asked_at' => $lastAskedAt,
        ]);
    }

    private function createResolvedItem(Workspace $workspace, string $question): ReviewItem
    {
        return ReviewItem::query()->create([
            'workspace_id' => $workspace->id,
            'question' => $question,
            'deduplication_key' => null,
            'status' => ReviewItemStatus::Resolved,
            'resolution' => 'Human resolution',
            'last_asked_at' => '2026-10-03 10:00:00',
            'resolved_at' => '2026-10-03 12:00:00',
        ]);
    }

    private function createWorkspace(string $suffix): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => 'Review API Test',
            'slug' => "review-api-{$suffix}",
        ]);

        if (! $this->app['auth']->check()) {
            $this->actingAsWorkspaceMember($workspace);
        }

        return $workspace;
    }
}
