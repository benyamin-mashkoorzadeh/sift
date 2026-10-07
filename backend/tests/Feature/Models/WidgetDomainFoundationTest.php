<?php

namespace Tests\Feature\Models;

use App\Enums\AssistantInteractionOrigin;
use App\Enums\RagAnswerStatus;
use App\Models\AssistantInteraction;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use App\Models\WorkspaceWidget;
use App\Services\Widget\WidgetConversationTokenGenerator;
use App\Services\Widget\WorkspaceWidgetKeyGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class WidgetDomainFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_schema_has_expected_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('workspace_widgets', [
            'id',
            'workspace_id',
            'public_key',
            'enabled',
            'key_rotated_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('widget_conversations', [
            'id',
            'workspace_id',
            'session_token_hash',
            'expires_at',
            'last_activity_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('assistant_interactions', [
            'origin',
            'widget_conversation_id',
        ]));

        $indexes = collect(DB::select(<<<'SQL'
            SELECT tablename, indexname
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename IN ('workspace_widgets', 'widget_conversations', 'assistant_interactions')
            SQL))->pluck('indexname');

        $this->assertContains('workspace_widgets_workspace_id_unique', $indexes);
        $this->assertContains('workspace_widgets_public_key_unique', $indexes);
        $this->assertContains('widget_conversations_session_token_hash_unique', $indexes);
        $this->assertContains('widget_conversations_workspace_id_last_activity_at_index', $indexes);
        $this->assertContains('assistant_interactions_workspace_id_origin_created_at_index', $indexes);
    }

    public function test_each_workspace_has_at_most_one_widget(): void
    {
        $workspace = $this->createWorkspace('single-widget');
        $this->createWidget($workspace);

        $this->expectException(QueryException::class);

        $this->createWidget($workspace);
    }

    public function test_public_keys_are_globally_unique(): void
    {
        $publicKey = $this->app->make(WorkspaceWidgetKeyGenerator::class)->generate();
        $this->createWidget($this->createWorkspace('first-key'), $publicKey);

        $this->expectException(QueryException::class);

        $this->createWidget($this->createWorkspace('second-key'), $publicKey);
    }

    public function test_public_key_must_use_the_expected_nonblank_format(): void
    {
        $this->expectException(QueryException::class);

        $this->createWidget($this->createWorkspace('invalid-key'), ' ');
    }

    public function test_public_key_generator_uses_the_expected_random_non_enumerable_format(): void
    {
        $generator = $this->app->make(WorkspaceWidgetKeyGenerator::class);
        $keys = array_map(static fn (): string => $generator->generate(), range(1, 100));

        $this->assertCount(100, array_unique($keys));

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^sift_w_[A-Za-z0-9_-]{43}$/', $key);
        }
    }

    public function test_conversation_token_generator_exposes_raw_token_but_only_hash_is_persisted(): void
    {
        $workspace = $this->createWorkspace('hashed-token');
        $token = $this->app->make(WidgetConversationTokenGenerator::class)->generate();
        $conversation = $this->createConversation($workspace, $token->hash);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token->rawToken);
        $this->assertSame(hash('sha256', $token->rawToken), $token->hash);
        $this->assertNotSame($token->rawToken, $conversation->session_token_hash);
        $this->assertDatabaseHas('widget_conversations', ['session_token_hash' => $token->hash]);
        $this->assertDatabaseMissing('widget_conversations', ['session_token_hash' => $token->rawToken]);
        $this->assertArrayNotHasKey('session_token_hash', $conversation->toArray());
    }

    public function test_session_token_hashes_are_globally_unique(): void
    {
        $hash = hash('sha256', 'same-session-token');
        $this->createConversation($this->createWorkspace('first-session'), $hash);

        $this->expectException(QueryException::class);

        $this->createConversation($this->createWorkspace('second-session'), $hash);
    }

    public function test_raw_conversation_token_cannot_be_persisted_as_its_hash(): void
    {
        $token = $this->app->make(WidgetConversationTokenGenerator::class)->generate();

        $this->expectException(QueryException::class);

        $this->createConversation($this->createWorkspace('raw-token'), $token->rawToken);
    }

    public function test_widget_relationships_and_casts_work(): void
    {
        $workspace = $this->createWorkspace('relationships');
        $rotatedAt = now()->subMinute()->startOfSecond();
        $widget = $this->createWidget($workspace, enabled: true, keyRotatedAt: $rotatedAt)->fresh();
        $conversation = $this->createConversation($workspace)->fresh();
        $interaction = $this->createWidgetInteraction($workspace, $conversation)->fresh();

        $this->assertTrue($widget->workspace->is($workspace));
        $this->assertTrue($workspace->fresh()->widget->is($widget));
        $this->assertTrue($conversation->workspace->is($workspace));
        $this->assertTrue($workspace->fresh()->widgetConversations->firstOrFail()->is($conversation));
        $this->assertTrue($interaction->widgetConversation->is($conversation));
        $this->assertTrue($conversation->assistantInteractions->firstOrFail()->is($interaction));
        $this->assertTrue($widget->enabled);
        $this->assertInstanceOf(Carbon::class, $widget->key_rotated_at);
        $this->assertInstanceOf(Carbon::class, $conversation->expires_at);
        $this->assertInstanceOf(Carbon::class, $conversation->last_activity_at);
        $this->assertSame(AssistantInteractionOrigin::Widget, $interaction->origin);
    }

    public function test_existing_assistant_interactions_default_to_assistant_origin(): void
    {
        $workspace = $this->createWorkspace('assistant-default');
        $interaction = $this->createAssistantInteraction($workspace)->fresh();

        $this->assertSame(AssistantInteractionOrigin::Assistant, $interaction->origin);
        $this->assertNull($interaction->widget_conversation_id);
    }

    public function test_assistant_interaction_cannot_reference_a_widget_conversation(): void
    {
        $workspace = $this->createWorkspace('assistant-context');
        $conversation = $this->createConversation($workspace);

        $this->expectException(LogicException::class);

        $this->createAssistantInteraction($workspace, [
            'widget_conversation_id' => $conversation->id,
        ]);
    }

    public function test_widget_interaction_requires_a_widget_conversation(): void
    {
        $workspace = $this->createWorkspace('widget-context');

        $this->expectException(LogicException::class);

        $this->createAssistantInteraction($workspace, [
            'origin' => AssistantInteractionOrigin::Widget,
        ]);
    }

    public function test_widget_interaction_cannot_reference_another_workspaces_conversation(): void
    {
        $workspace = $this->createWorkspace('interaction-workspace');
        $otherConversation = $this->createConversation($this->createWorkspace('conversation-workspace'));

        $this->expectException(LogicException::class);

        $this->createAssistantInteraction($workspace, [
            'origin' => AssistantInteractionOrigin::Widget,
            'widget_conversation_id' => $otherConversation->id,
        ]);
    }

    public function test_deleting_a_widget_conversation_preserves_interaction_history(): void
    {
        $workspace = $this->createWorkspace('conversation-deletion');
        $conversation = $this->createConversation($workspace);
        $interaction = $this->createWidgetInteraction($workspace, $conversation);

        $conversation->delete();
        $interaction->refresh();

        $this->assertSame(AssistantInteractionOrigin::Widget, $interaction->origin);
        $this->assertNull($interaction->widget_conversation_id);
        $this->assertDatabaseHas('assistant_interactions', ['id' => $interaction->id]);
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Widget Domain Test',
            'slug' => "widget-domain-{$suffix}-".Str::lower((string) Str::ulid()),
        ]);
    }

    private function createWidget(
        Workspace $workspace,
        ?string $publicKey = null,
        bool $enabled = false,
        ?Carbon $keyRotatedAt = null,
    ): WorkspaceWidget {
        return WorkspaceWidget::query()->create([
            'workspace_id' => $workspace->id,
            'public_key' => $publicKey ?? $this->app->make(WorkspaceWidgetKeyGenerator::class)->generate(),
            'enabled' => $enabled,
            'key_rotated_at' => $keyRotatedAt,
        ]);
    }

    private function createConversation(
        Workspace $workspace,
        ?string $tokenHash = null,
    ): WidgetConversation {
        return WidgetConversation::query()->create([
            'workspace_id' => $workspace->id,
            'session_token_hash' => $tokenHash ?? hash('sha256', (string) Str::uuid()),
            'expires_at' => now()->addDay(),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAssistantInteraction(
        Workspace $workspace,
        array $overrides = [],
    ): AssistantInteraction {
        return AssistantInteraction::query()->create(array_merge([
            'workspace_id' => $workspace->id,
            'question' => 'What is the return policy?',
            'status' => RagAnswerStatus::Answered,
            'answer' => 'Unused products can be returned within 30 days.',
            'provider' => 'test',
            'model' => 'test-model',
        ], $overrides));
    }

    private function createWidgetInteraction(
        Workspace $workspace,
        WidgetConversation $conversation,
    ): AssistantInteraction {
        return $this->createAssistantInteraction($workspace, [
            'origin' => AssistantInteractionOrigin::Widget,
            'widget_conversation_id' => $conversation->id,
        ]);
    }
}
