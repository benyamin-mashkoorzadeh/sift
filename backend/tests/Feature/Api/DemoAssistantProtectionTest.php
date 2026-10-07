<?php

namespace Tests\Feature\Api;

use App\Data\AI\RagAnswerResult;
use App\Enums\RagAnswerStatus;
use App\Enums\WorkspaceRole;
use App\Exceptions\AI\DemoAssistantUnavailableException;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DemoAssistantProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_assistant_kill_switch_returns_a_safe_error_without_provider_work(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        config()->set('demo.assistant.enabled', false);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);
        $this->enterDemo();

        $this->ask($workspace, 'What is the return policy?')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => DemoAssistantUnavailableException::USER_MESSAGE,
                'code' => 'demo_assistant_unavailable',
            ]);
    }

    public function test_normal_assistant_remains_available_when_demo_assistant_is_disabled(): void
    {
        $workspace = $this->createWorkspace('normal-kill-switch');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);
        config()->set('demo.assistant.enabled', false);
        $this->mockSuccessfulRag(1);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'What is the return policy?',
        ])->assertOk();

        $this->assertDatabaseCount('assistant_interactions', 1);
    }

    public function test_demo_assistant_enforces_the_per_session_minute_limit(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        config()->set('demo.assistant.requests_per_minute', 2);
        $this->mockSuccessfulRag(2);
        $this->enterDemo();

        $this->ask($workspace, 'First question')->assertOk();
        $this->ask($workspace, 'Second question')->assertOk();
        $this->assertSafeRateLimited($this->ask($workspace, 'Third question'));
    }

    public function test_demo_assistant_enforces_the_per_ip_and_workspace_hourly_limit(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        config()->set('demo.assistant.requests_per_minute', 100);
        config()->set('demo.assistant.requests_per_hour_per_ip', 2);
        $this->mockSuccessfulRag(2);
        $this->enterDemo();

        $this->ask($workspace, 'First IP question')->assertOk();
        $this->ask($workspace, 'Second IP question')->assertOk();
        $this->assertSafeRateLimited($this->ask($workspace, 'Third IP question'));
    }

    public function test_demo_assistant_enforces_the_global_workspace_daily_limit(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        config()->set('demo.assistant.requests_per_minute', 100);
        config()->set('demo.assistant.requests_per_hour_per_ip', 100);
        config()->set('demo.assistant.requests_per_day', 2);
        $this->mockSuccessfulRag(2);
        $this->enterDemo();

        $this->ask($workspace, 'First global question')->assertOk();
        $this->ask($workspace, 'Second global question')->assertOk();
        $this->assertSafeRateLimited($this->ask($workspace, 'Third global question'));
    }

    public function test_a_new_guest_session_cannot_bypass_the_global_daily_limit(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        config()->set('demo.assistant.requests_per_minute', 100);
        config()->set('demo.assistant.requests_per_hour_per_ip', 100);
        config()->set('demo.assistant.requests_per_day', 1);
        $this->mockSuccessfulRag(1);
        $this->enterDemo();

        $this->ask($workspace, 'Question from the first session')->assertOk();
        $this->statefulPostJson(route('auth.logout'))->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->enterDemo();

        $this->assertSafeRateLimited(
            $this->ask($workspace, 'Question from the replacement session'),
        );
    }

    public function test_demo_question_length_is_limited_without_provider_work(): void
    {
        [, $workspace] = $this->createDemoPrincipal();
        config()->set('demo.assistant.max_question_length', 10);
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('answer');
        $this->app->instance(RagService::class, $ragService);
        $this->enterDemo();

        $this->ask($workspace, str_repeat('a', 11))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    public function test_normal_assistant_keeps_its_existing_question_length_limit(): void
    {
        $workspace = $this->createWorkspace('normal-length');
        $this->actingAsWorkspaceMember($workspace, WorkspaceRole::Owner);
        config()->set('demo.assistant.max_question_length', 10);
        $this->mockSuccessfulRag(1);

        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => str_repeat('a', 500),
        ])->assertOk();
    }

    public function test_demo_entry_is_rate_limited_by_hashed_ip_identity(): void
    {
        config()->set('demo.entry.requests_per_hour_per_ip', 2);
        config()->set('demo.enabled', false);

        $this->statefulPostJson(route('auth.demo'))->assertServiceUnavailable();
        $this->statefulPostJson(route('auth.demo'))->assertServiceUnavailable();
        $this->assertSafeRateLimited($this->statefulPostJson(route('auth.demo')));
    }

    private function createDemoPrincipal(): array
    {
        $workspace = $this->createWorkspace('protected-demo');
        $guest = User::query()->create([
            'name' => 'Guest',
            'email' => 'protected-guest@example.com',
            'password' => 'GuestPassword123',
        ]);
        $workspace->users()->attach($guest->id, ['role' => WorkspaceRole::Member->value]);
        config()->set('demo.enabled', true);
        config()->set('demo.user_id', $guest->id);
        config()->set('demo.workspace_id', $workspace->id);
        config()->set('demo.assistant.enabled', true);
        config()->set('demo.assistant.requests_per_minute', 100);
        config()->set('demo.assistant.requests_per_hour_per_ip', 100);
        config()->set('demo.assistant.requests_per_day', 100);
        config()->set('demo.entry.requests_per_hour_per_ip', 100);

        return [$guest, $workspace];
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Demo Protection Test',
            'slug' => $suffix.'-'.str()->random(8),
        ]);
    }

    private function enterDemo(): void
    {
        $this->statefulPostJson(route('auth.demo'))->assertOk();
    }

    private function ask(Workspace $workspace, string $question)
    {
        return $this->statefulPostJson(route('workspaces.answers.store', $workspace), [
            'question' => $question,
        ]);
    }

    private function statefulPostJson(string $uri, array $data = [])
    {
        return $this->withHeader('Origin', 'http://localhost:3000')->postJson($uri, $data);
    }

    private function mockSuccessfulRag(int $times): void
    {
        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('answer')->times($times)->andReturn(new RagAnswerResult(
            status: RagAnswerStatus::NeedsReview,
            answer: RagService::INSUFFICIENT_ANSWER,
            citations: [],
            provider: null,
            model: null,
            reason: 'no_retrieval_results',
        ));
        $this->app->instance(RagService::class, $ragService);
    }

    private function assertSafeRateLimited($response): void
    {
        $response->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertExactJson([
                'message' => 'Too many Demo requests. Please try again later.',
                'code' => 'demo_rate_limited',
            ]);
    }
}
