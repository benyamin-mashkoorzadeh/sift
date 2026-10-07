<?php

namespace Tests\Feature\Api;

use App\Data\AI\RagAnswerResult;
use App\Data\AI\RagCitation;
use App\Enums\RagAnswerStatus;
use App\Enums\WorkspaceRole;
use App\Models\AssistantInteraction;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use App\Services\AI\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DemoWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_grounded_answer_uses_configured_workspace_without_persisting_or_exposing_internal_data(): void
    {
        [, $workspace] = $this->configureDemoEnvironment(widgetEnabled: true, dashboardDemoEnabled: false);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')
            ->once()
            ->withArgs(fn (Workspace $candidate, string $question): bool => $candidate->is($workspace)
                && $question === 'How long do I have to return an unused product?')
            ->andReturn($this->answeredResult());
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('demo.widget.messages.store'), [
            'question' => '  How long do I have to return an unused product?  ',
        ])->assertOk()
            ->assertExactJson([
                'data' => [
                    'status' => 'answered',
                    'answer' => 'Unused products can be returned within 30 days.',
                ],
            ])
            ->assertJsonMissing([
                'citations', 'provider', 'model', 'reason', 'document_id', 'chunk_id',
                'page_number', 'chunk_index', 'interaction_id', 'review_item_id',
            ])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertNoDemoWidgetPersistence();
    }

    public function test_unsupported_question_is_safe_and_non_persistent(): void
    {
        $this->configureDemoEnvironment(widgetEnabled: true);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('demo.widget.messages.store'), [
            'question' => "What is the company's CEO's name?",
        ])->assertOk()->assertExactJson([
            'data' => [
                'status' => 'needs_review',
                'answer' => "I couldn't find a reliable answer in the available knowledge.",
            ],
        ]);

        $this->assertNoDemoWidgetPersistence();
    }

    public function test_existing_prepared_history_and_review_records_are_unchanged(): void
    {
        [, $workspace] = $this->configureDemoEnvironment(widgetEnabled: true);
        $reviewItem = ReviewItem::query()->create([
            'workspace_id' => $workspace->id,
            'question' => 'Who is the CEO?',
            'deduplication_key' => hash('sha256', 'who is the ceo?'),
            'status' => 'pending',
            'last_asked_at' => now()->subDay(),
        ]);
        $interaction = AssistantInteraction::query()->create([
            'workspace_id' => $workspace->id,
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

        $this->postJson(route('demo.widget.messages.store'), [
            'question' => 'Who is the CEO?',
        ])->assertOk()->assertJsonPath('data.status', 'needs_review');

        $this->assertDatabaseCount('assistant_interactions', 1);
        $this->assertDatabaseCount('assistant_interaction_citations', 0);
        $this->assertDatabaseCount('review_items', 1);
        $this->assertDatabaseCount('widget_conversations', 0);
        $this->assertSame($reviewAttributes, $reviewItem->fresh()->getAttributes());
        $this->assertSame($interactionAttributes, $interaction->fresh()->getAttributes());
    }

    public function test_widget_kill_switch_is_independent_and_fails_before_rag(): void
    {
        $this->configureDemoEnvironment(widgetEnabled: false, dashboardDemoEnabled: true);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('demo.widget.messages.store'), [
            'question' => 'Can I return an item?',
        ])->assertServiceUnavailable()->assertExactJson([
            'message' => 'The Demo assistant is temporarily unavailable.',
            'code' => 'demo_widget_unavailable',
        ]);

        $this->assertNoDemoWidgetPersistence();
    }

    public function test_missing_or_mismatched_demo_environment_fails_closed_before_rag(): void
    {
        [$user] = $this->configureDemoEnvironment(widgetEnabled: true);
        $otherWorkspace = $this->createWorkspace('mismatched');
        config()->set('demo.workspace_id', $otherWorkspace->id);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('demo.widget.messages.store'), [
            'question' => 'Can I return an item?',
        ])->assertServiceUnavailable()->assertExactJson([
            'message' => 'The Demo assistant is temporarily unavailable.',
            'code' => 'demo_widget_unavailable',
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertNoDemoWidgetPersistence();
    }

    public function test_validation_rejects_invalid_questions_and_caller_controlled_fields_before_rag(): void
    {
        $this->configureDemoEnvironment(widgetEnabled: true);
        config()->set('demo.widget.max_question_length', 12);
        config()->set('demo.widget.requests_per_minute_per_ip', 100);
        config()->set('demo.widget.requests_per_hour_per_ip', 100);
        config()->set('demo.widget.requests_per_day', 100);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        foreach ([
            [],
            ['question' => '   '],
            ['question' => ['invalid']],
            ['question' => str_repeat('a', 13)],
            ['question' => 'Valid', 'workspace_id' => 999],
        ] as $payload) {
            $this->postJson(route('demo.widget.messages.store'), $payload)
                ->assertUnprocessable();
        }

        $this->assertNoDemoWidgetPersistence();
    }

    public function test_oversized_request_is_rejected_before_rag(): void
    {
        $this->configureDemoEnvironment(widgetEnabled: true);
        config()->set('demo.widget.maximum_request_bytes', 32);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('demo.widget.messages.store'), [
            'question' => str_repeat('a', 100),
        ])->assertStatus(413)->assertExactJson([
            'message' => 'Widget request is too large.',
            'code' => 'widget_request_too_large',
        ]);
    }

    public function test_public_rate_limits_run_before_rag(): void
    {
        $this->configureDemoEnvironment(widgetEnabled: true);
        config()->set('demo.widget.requests_per_minute_per_ip', 1);
        config()->set('demo.widget.requests_per_hour_per_ip', 10);
        config()->set('demo.widget.requests_per_day', 10);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson(route('demo.widget.messages.store'), ['question' => 'First question'])
            ->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson(route('demo.widget.messages.store'), ['question' => 'Second question'])
            ->assertTooManyRequests()
            ->assertExactJson([
                'message' => 'The Demo assistant is busy. Please try again later.',
                'code' => 'demo_widget_rate_limited',
            ])
            ->assertHeader('Retry-After');

        $this->assertNoDemoWidgetPersistence();
    }

    public function test_rag_failure_returns_only_a_safe_error(): void
    {
        $this->configureDemoEnvironment(widgetEnabled: true);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andThrow(new RuntimeException(
            'Sensitive provider failure with internal details.',
        ));
        $this->app->instance(RagService::class, $ragService);

        $this->postJson(route('demo.widget.messages.store'), [
            'question' => 'Can I return an item?',
        ])->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'The Demo assistant is temporarily unavailable.',
                'code' => 'demo_widget_unavailable',
            ])
            ->assertJsonMissing(['Sensitive provider failure with internal details.']);

        $this->assertNoDemoWidgetPersistence();
    }

    /**
     * @return array{User, Workspace}
     */
    private function configureDemoEnvironment(
        bool $widgetEnabled,
        bool $dashboardDemoEnabled = true,
    ): array {
        $workspace = $this->createWorkspace('configured');
        $user = User::query()->create([
            'name' => 'Demo Guest',
            'email' => Str::uuid().'@example.com',
            'password' => 'GuestPassword123',
        ]);
        $workspace->users()->attach($user->id, ['role' => WorkspaceRole::Member->value]);
        config()->set('demo.enabled', $dashboardDemoEnabled);
        config()->set('demo.widget.enabled', $widgetEnabled);
        config()->set('demo.user_id', $user->id);
        config()->set('demo.workspace_id', $workspace->id);

        return [$user, $workspace];
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Demo Widget Test',
            'slug' => 'demo-widget-'.$suffix.'-'.Str::lower((string) Str::ulid()),
        ]);
    }

    private function answeredResult(): RagAnswerResult
    {
        return new RagAnswerResult(
            status: RagAnswerStatus::Answered,
            answer: 'Unused products can be returned within 30 days.',
            citations: [new RagCitation(123, 'internal-returns.pdf', 1, 456, 0)],
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
            reason: 'internal-only-reason',
        );
    }

    private function assertNoDemoWidgetPersistence(): void
    {
        $this->assertSame(0, WidgetConversation::query()->count());
        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('assistant_interaction_citations', 0);
        $this->assertDatabaseCount('review_items', 0);
    }
}
