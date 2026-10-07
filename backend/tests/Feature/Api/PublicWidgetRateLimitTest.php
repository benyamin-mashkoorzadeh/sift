<?php

namespace Tests\Feature\Api;

use App\Data\AI\RagAnswerResult;
use App\Enums\RagAnswerStatus;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use App\Models\WorkspaceWidget;
use App\Services\AI\RagService;
use App\Services\Widget\WidgetConversationTokenGenerator;
use App\Services\Widget\WorkspaceWidgetKeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class PublicWidgetRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_rate_limit_is_configurable_per_ip(): void
    {
        config()->set('widget.limits.bootstrap_requests_per_minute', 1);
        $widget = $this->createWidget();

        $this->getJson(route('widget.bootstrap', $widget->public_key))->assertOk();
        $this->getJson(route('widget.bootstrap', $widget->public_key))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_conversation_creation_rate_limit_is_configurable_per_ip_and_widget(): void
    {
        config()->set('widget.limits.session_creations_per_hour', 1);
        $widget = $this->createWidget();

        $this->postJson(route('widget.conversations.store', $widget->public_key))->assertCreated();
        $this->postJson(route('widget.conversations.store', $widget->public_key))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('widget_conversations', 1);
    }

    public function test_message_rate_limit_is_enforced_per_conversation_before_rag(): void
    {
        config()->set('widget.limits.messages_per_minute_per_conversation', 1);
        config()->set('widget.limits.messages_per_hour_per_ip_widget', 10);
        config()->set('widget.limits.messages_per_day_per_widget', 10);
        $widget = $this->createWidget();
        $token = $this->createConversation($widget->workspace);
        $this->mockRagForOneRequest();

        $this->message($widget, $token, 'First')->assertOk();
        $this->message($widget, $token, 'Second')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_message_rate_limit_is_enforced_per_ip_and_widget_before_rag(): void
    {
        config()->set('widget.limits.messages_per_minute_per_conversation', 10);
        config()->set('widget.limits.messages_per_hour_per_ip_widget', 1);
        config()->set('widget.limits.messages_per_day_per_widget', 10);
        $widget = $this->createWidget();
        $firstToken = $this->createConversation($widget->workspace);
        $secondToken = $this->createConversation($widget->workspace);
        $this->mockRagForOneRequest();

        $this->message($widget, $firstToken, 'First')->assertOk();
        $this->message($widget, $secondToken, 'Second')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_message_rate_limit_is_enforced_per_widget_per_day_before_rag(): void
    {
        config()->set('widget.limits.messages_per_minute_per_conversation', 10);
        config()->set('widget.limits.messages_per_hour_per_ip_widget', 10);
        config()->set('widget.limits.messages_per_day_per_widget', 1);
        $widget = $this->createWidget();
        $firstToken = $this->createConversation($widget->workspace);
        $secondToken = $this->createConversation($widget->workspace);
        $this->mockRagForOneRequest();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->message($widget, $firstToken, 'First')
            ->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
            ->message($widget, $secondToken, 'Second')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    private function createWidget(): WorkspaceWidget
    {
        $workspace = Workspace::query()->create([
            'name' => 'Widget Rate Limit Test',
            'slug' => 'widget-rate-limit-'.Str::lower((string) Str::ulid()),
        ]);

        return WorkspaceWidget::query()->create([
            'workspace_id' => $workspace->id,
            'public_key' => $this->app->make(WorkspaceWidgetKeyGenerator::class)->generate(),
            'enabled' => true,
        ])->load('workspace');
    }

    private function createConversation(Workspace $workspace): string
    {
        $token = $this->app->make(WidgetConversationTokenGenerator::class)->generate();
        WidgetConversation::query()->create([
            'workspace_id' => $workspace->id,
            'session_token_hash' => $token->hash,
            'expires_at' => now()->addDay(),
            'last_activity_at' => now(),
        ]);

        return $token->rawToken;
    }

    private function message(WorkspaceWidget $widget, string $token, string $question): TestResponse
    {
        return $this->withHeader('X-Sift-Conversation-Token', $token)
            ->postJson(route('widget.messages.store', $widget->public_key), [
                'question' => $question,
            ]);
    }

    private function mockRagForOneRequest(): void
    {
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->once()->andReturn(new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: null,
            model: null,
            reason: 'no_results',
        ));
        $this->app->instance(RagService::class, $ragService);
    }
}
