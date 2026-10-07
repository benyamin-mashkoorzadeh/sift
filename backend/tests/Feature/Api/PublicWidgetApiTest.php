<?php

namespace Tests\Feature\Api;

use App\Actions\AI\AnswerWorkspaceQuestion;
use App\Actions\AI\PersistAssistantInteraction;
use App\Data\AI\RagAnswerResult;
use App\Data\AI\RagCitation;
use App\Enums\AssistantInteractionOrigin;
use App\Enums\DocumentStatus;
use App\Enums\RagAnswerStatus;
use App\Exceptions\AI\AssistantInteractionPersistenceException;
use App\Exceptions\AI\RagAnswerException;
use App\Models\AssistantInteraction;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ReviewItem;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use App\Models\WorkspaceWidget;
use App\Services\AI\RagService;
use App\Services\Widget\WidgetConversationTokenGenerator;
use App\Services\Widget\WorkspaceWidgetKeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PublicWidgetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_widget_bootstrap_returns_only_public_workspace_information_and_security_headers(): void
    {
        $workspace = $this->createWorkspace('Bootstrap Company');
        $widget = $this->createWidget($workspace);

        $this->getJson(route('widget.bootstrap', $widget->public_key))
            ->assertOk()
            ->assertExactJson(['data' => ['workspace_name' => 'Bootstrap Company']])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_unknown_and_disabled_widgets_have_the_same_generic_response(): void
    {
        $widget = $this->createWidget($this->createWorkspace(), enabled: false);
        $expected = [
            'message' => 'Widget unavailable.',
            'code' => 'widget_unavailable',
        ];

        $this->getJson(route('widget.bootstrap', $widget->public_key))
            ->assertNotFound()
            ->assertExactJson($expected);
        $this->getJson(route('widget.bootstrap', 'sift_w_'.str_repeat('x', 43)))
            ->assertNotFound()
            ->assertExactJson($expected);
    }

    public function test_conversation_creation_returns_the_raw_token_once_and_persists_only_its_hash(): void
    {
        Carbon::setTestNow('2026-10-04 12:00:00');
        config()->set('widget.conversation_lifetime_minutes', 45);
        $widget = $this->createWidget($this->createWorkspace());

        $response = $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson(route('widget.conversations.store', $widget->public_key), [])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['conversation_token', 'expires_at']])
            ->assertJsonPath('data.expires_at', '2026-10-04T12:45:00.000000Z');

        $rawToken = $response->json('data.conversation_token');
        $conversation = WidgetConversation::query()->sole();

        $this->assertIsString($rawToken);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $rawToken);
        $this->assertSame(hash('sha256', $rawToken), $conversation->session_token_hash);
        $this->assertDatabaseMissing('widget_conversations', ['session_token_hash' => $rawToken]);
        $this->assertArrayNotHasKey('session_token_hash', $conversation->toArray());
    }

    public function test_disabled_widget_cannot_create_a_conversation(): void
    {
        $widget = $this->createWidget($this->createWorkspace(), enabled: false);

        $this->postJson(route('widget.conversations.store', $widget->public_key))
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Widget unavailable.',
                'code' => 'widget_unavailable',
            ]);

        $this->assertDatabaseCount('widget_conversations', 0);
    }

    public function test_grounded_widget_answer_is_minimal_while_internal_context_and_citations_are_persisted(): void
    {
        Carbon::setTestNow('2026-10-04 12:00:00');
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace);
        [$rawToken, $conversation] = $this->createConversation($workspace, lastActivityAt: now()->subHour());
        [$document, $chunk] = $this->createSource($workspace);
        $this->mockGroundedRag($workspace, $document, $chunk);

        $this->postWidgetMessage($widget, $rawToken, 'What is the return policy?')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'status' => 'answered',
                    'answer' => 'Unused products can be returned within 30 days.',
                ],
            ])
            ->assertJsonMissing([
                'citations', 'provider', 'model', 'reason', 'document_id', 'chunk_id',
                'page_number', 'chunk_index', 'interaction_id', 'review_item_id',
            ]);

        $interaction = AssistantInteraction::query()->with('citations')->sole();
        $this->assertSame(AssistantInteractionOrigin::Widget, $interaction->origin);
        $this->assertTrue($interaction->widgetConversation->is($conversation));
        $this->assertCount(1, $interaction->citations);
        $this->assertSame($document->id, $interaction->citations->first()->document_id);
        $this->assertSame($chunk->id, $interaction->citations->first()->document_chunk_id);
        $this->assertTrue($conversation->fresh()->last_activity_at->equalTo(now()));
    }

    public function test_needs_review_returns_the_safe_fallback_and_reuses_an_equivalent_pending_internal_review(): void
    {
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace);
        [$rawToken] = $this->createConversation($workspace);
        $result = new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: null,
            model: null,
            reason: 'internal-only-reason',
        );
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->twice()->andReturn($result);
        $this->app->instance(RagService::class, $ragService);

        $this->app->make(AnswerWorkspaceQuestion::class)->handle(
            $workspace,
            'What is the CEO name?',
        );

        $this->postWidgetMessage($widget, $rawToken, '  what is the CEO name?  ')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'status' => 'needs_review',
                    'answer' => "I couldn't find a reliable answer to that question. I've sent it for review.",
                ],
            ]);

        $reviewItem = ReviewItem::query()->sole();
        $interactions = AssistantInteraction::query()->orderBy('id')->get();
        $this->assertCount(2, $interactions);
        $this->assertSame($reviewItem->id, $interactions[0]->review_item_id);
        $this->assertSame($reviewItem->id, $interactions[1]->review_item_id);
        $this->assertSame(AssistantInteractionOrigin::Assistant, $interactions[0]->origin);
        $this->assertSame(AssistantInteractionOrigin::Widget, $interactions[1]->origin);
    }

    public function test_invalid_expired_cross_workspace_and_pre_rotation_conversations_are_generic_not_found(): void
    {
        Carbon::setTestNow('2026-10-04 12:00:00');
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace, keyRotatedAt: now()->subHour());
        [$expiredToken] = $this->createConversation($workspace, expiresAt: now()->subSecond());
        [$otherToken] = $this->createConversation($this->createWorkspace('Other'));
        [$oldToken] = $this->createConversation($workspace, createdAt: now()->subHours(2));
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);
        $expected = [
            'message' => 'Conversation unavailable.',
            'code' => 'conversation_unavailable',
        ];

        foreach ([
            'invalid' => 'invalid-token',
            'expired' => $expiredToken,
            'cross-workspace' => $otherToken,
            'pre-rotation' => $oldToken,
        ] as $case => $token) {
            $response = $this->postWidgetMessage($widget, $token, 'Can you help?');
            $this->assertSame(404, $response->status(), "{$case} conversation was accepted.");
            $response->assertExactJson($expected);
        }
    }

    public function test_conversation_created_after_key_rotation_is_accepted(): void
    {
        Carbon::setTestNow('2026-10-04 12:00:00');
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace, keyRotatedAt: now()->subHour());
        [$rawToken] = $this->createConversation($workspace, createdAt: now()->subMinute());
        $this->mockNeedsReviewRag();

        $this->postWidgetMessage($widget, $rawToken, 'Can you help?')
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_review');
    }

    public function test_disabled_widget_rejects_messages_before_rag_runs(): void
    {
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace, enabled: false);
        [$rawToken] = $this->createConversation($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        $this->postWidgetMessage($widget, $rawToken, 'Can you help?')
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Widget unavailable.',
                'code' => 'widget_unavailable',
            ]);
    }

    public function test_question_validation_uses_configured_limit_and_rejects_unexpected_fields_before_rag(): void
    {
        config()->set('widget.limits.maximum_question_length', 12);
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace);
        [$rawToken] = $this->createConversation($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        foreach ([
            [],
            ['question' => '   '],
            ['question' => ['invalid']],
            ['question' => str_repeat('a', 13)],
            ['question' => 'Valid', 'workspace_id' => $workspace->id],
        ] as $payload) {
            $this->withHeader('X-Sift-Conversation-Token', $rawToken)
                ->postJson(route('widget.messages.store', $widget->public_key), $payload)
                ->assertUnprocessable();
        }
    }

    public function test_oversized_payload_is_rejected_before_rag_runs(): void
    {
        config()->set('widget.limits.maximum_request_bytes', 32);
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace);
        [$rawToken] = $this->createConversation($workspace);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);

        $this->postWidgetMessage($widget, $rawToken, str_repeat('a', 100))
            ->assertStatus(413)
            ->assertExactJson([
                'message' => 'Widget request is too large.',
                'code' => 'widget_request_too_large',
            ]);
    }

    public function test_rag_and_persistence_failures_return_the_same_safe_widget_error_without_history(): void
    {
        $workspace = $this->createWorkspace();
        $widget = $this->createWidget($workspace);
        [$firstToken] = $this->createConversation($workspace);
        [$secondToken] = $this->createConversation($workspace);

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andThrow(new RagAnswerException(
            RagAnswerException::USER_MESSAGE,
            previous: new RuntimeException('Sensitive provider failure.'),
        ));
        $this->app->instance(RagService::class, $ragService);

        $expected = [
            'message' => 'Answer temporarily unavailable.',
            'code' => 'widget_answer_unavailable',
        ];
        $this->postWidgetMessage($widget, $firstToken, 'First question')
            ->assertServiceUnavailable()
            ->assertExactJson($expected)
            ->assertJsonMissing(['Sensitive provider failure.']);

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->needsReviewResult());
        $persistence = Mockery::mock(PersistAssistantInteraction::class);
        $persistence->shouldReceive('handle')->once()->andThrow(new AssistantInteractionPersistenceException(
            AssistantInteractionPersistenceException::USER_MESSAGE,
            previous: new RuntimeException('Sensitive persistence failure.'),
        ));
        $this->app->instance(RagService::class, $ragService);
        $this->app->instance(PersistAssistantInteraction::class, $persistence);

        $this->postWidgetMessage($widget, $secondToken, 'Second question')
            ->assertServiceUnavailable()
            ->assertExactJson($expected)
            ->assertJsonMissing(['Sensitive persistence failure.']);

        $this->assertDatabaseCount('assistant_interactions', 0);
        $this->assertDatabaseCount('review_items', 0);
    }

    public function test_dashboard_assistant_remains_authenticated_and_unchanged(): void
    {
        $workspace = $this->createWorkspace();

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'What is the return policy?',
        ])->assertUnauthorized();
    }

    private function createWorkspace(string $name = 'Widget API Test'): Workspace
    {
        return Workspace::query()->create([
            'name' => $name,
            'slug' => 'widget-api-'.Str::lower((string) Str::ulid()),
        ]);
    }

    private function createWidget(
        Workspace $workspace,
        bool $enabled = true,
        ?Carbon $keyRotatedAt = null,
    ): WorkspaceWidget {
        return WorkspaceWidget::query()->create([
            'workspace_id' => $workspace->id,
            'public_key' => $this->app->make(WorkspaceWidgetKeyGenerator::class)->generate(),
            'enabled' => $enabled,
            'key_rotated_at' => $keyRotatedAt,
        ]);
    }

    /**
     * @return array{string, WidgetConversation}
     */
    private function createConversation(
        Workspace $workspace,
        ?Carbon $expiresAt = null,
        ?Carbon $lastActivityAt = null,
        ?Carbon $createdAt = null,
    ): array {
        $token = $this->app->make(WidgetConversationTokenGenerator::class)->generate();
        $conversation = WidgetConversation::query()->create([
            'workspace_id' => $workspace->id,
            'session_token_hash' => $token->hash,
            'expires_at' => $expiresAt ?? now()->addDay(),
            'last_activity_at' => $lastActivityAt ?? now(),
        ]);
        if ($createdAt !== null) {
            $conversation->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        return [$token->rawToken, $conversation];
    }

    private function postWidgetMessage(WorkspaceWidget $widget, string $rawToken, string $question): TestResponse
    {
        return $this->withHeader('X-Sift-Conversation-Token', $rawToken)
            ->postJson(route('widget.messages.store', $widget->public_key), [
                'question' => $question,
            ]);
    }

    /**
     * @return array{Document, DocumentChunk}
     */
    private function createSource(Workspace $workspace): array
    {
        $document = Document::query()->create([
            'workspace_id' => $workspace->id,
            'original_filename' => 'private-policy.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'documents/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Ready,
            'page_count' => 3,
            'processed_at' => now(),
        ]);
        $chunk = DocumentChunk::query()->create([
            'workspace_id' => $workspace->id,
            'document_id' => $document->id,
            'content' => 'Unused products can be returned within 30 days.',
            'page_number' => 2,
            'chunk_index' => 4,
        ]);

        return [$document, $chunk];
    }

    private function mockGroundedRag(Workspace $workspace, Document $document, DocumentChunk $chunk): void
    {
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')
            ->once()
            ->withArgs(fn (Workspace $candidate, string $question): bool => $candidate->is($workspace)
                && $question === 'What is the return policy?')
            ->andReturn(new RagAnswerResult(
                status: RagAnswerStatus::Answered,
                answer: 'Unused products can be returned within 30 days.',
                citations: [new RagCitation(
                    documentId: $document->id,
                    originalFilename: $document->original_filename,
                    pageNumber: 2,
                    chunkId: $chunk->id,
                    chunkIndex: 4,
                )],
                provider: 'secret-provider',
                model: 'secret-model',
                reason: 'internal-only-reason',
            ));
        $this->app->instance(RagService::class, $ragService);
    }

    private function mockNeedsReviewRag(): void
    {
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn($this->needsReviewResult());
        $this->app->instance(RagService::class, $ragService);
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
}
