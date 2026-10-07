<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspaceRole;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\DemoSessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EffectiveWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_demo_context_receives_only_the_guest_permission_whitelist(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace(WorkspaceRole::Owner);
        $reviewItem = $this->createReviewItem($workspace);
        $this->configureDemoPrincipal($user, $workspace);
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->actingAs($user)
            ->withSession($this->validDemoSession($user, $workspace))
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.workspace.role', WorkspaceRole::Owner->value)
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Demo->value)
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'knowledge.view',
                'assistant.use',
                'conversations.view',
                'review.view',
            ]);

        $this->getJson(route('workspaces.overview.show', $workspace))->assertOk();
        $this->getJson(route('workspaces.documents.index', $workspace))->assertOk();
        $this->postJson(route('workspaces.answers.store', $workspace), [])->assertUnprocessable();
        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))->assertOk();
        $this->getJson(route('workspaces.review-items.index', $workspace))->assertOk();

        $this->postJson(route('workspaces.documents.store', $workspace), [])->assertForbidden();
        $this->patchJson(route('workspaces.review-items.resolve', [$workspace, $reviewItem]), [
            'resolution' => 'A forbidden demo resolution.',
        ])->assertForbidden();
        $this->getJson(route('workspaces.settings.show', $workspace))->assertForbidden();
        $this->patchJson(route('workspaces.settings.update', $workspace), [
            'name' => 'Forbidden Demo Rename',
        ])->assertForbidden();
        $this->getJson(route('workspaces.team.members.index', $workspace))->assertForbidden();
    }

    public function test_a_configured_demo_principal_without_demo_session_context_fails_closed(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace(WorkspaceRole::Owner);
        $this->configureDemoPrincipal($user, $workspace);
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->actingAs($user)
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Demo->value)
            ->assertJsonPath('data.permissions', []);

        $this->getJson(route('workspaces.overview.show', $workspace))->assertForbidden();
        $this->getJson(route('workspaces.documents.index', $workspace))->assertForbidden();
        $this->postJson(route('workspaces.answers.store', $workspace), [
            'question' => 'May I access the assistant?',
        ])->assertForbidden();
    }

    public function test_an_incomplete_demo_session_context_fails_closed(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace(WorkspaceRole::Admin);
        $this->configureDemoPrincipal($user, $workspace);
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->actingAs($user)
            ->withSession([
                DemoSessionContext::ACCESS_MODE_KEY => WorkspaceAccessMode::Demo->value,
                DemoSessionContext::USER_ID_KEY => $user->id,
            ])
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Demo->value)
            ->assertJsonPath('data.permissions', []);

        $this->getJson(route('workspaces.review-items.index', $workspace))->assertForbidden();
    }

    public function test_unconfigured_users_continue_to_receive_normal_role_permissions(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace(WorkspaceRole::Member);
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->actingAs($user)
            ->withSession([
                DemoSessionContext::ACCESS_MODE_KEY => WorkspaceAccessMode::Demo->value,
                DemoSessionContext::USER_ID_KEY => $user->id,
                DemoSessionContext::WORKSPACE_ID_KEY => $workspace->id,
            ])
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Normal->value)
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'assistant.use',
                'review.view',
                'review.resolve',
            ]);
    }

    /**
     * @return array{User, Workspace}
     */
    private function createUserWithWorkspace(WorkspaceRole $role): array
    {
        $user = User::query()->create([
            'name' => 'Effective Access User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'StrongPassword123',
        ]);
        $workspace = Workspace::query()->create([
            'name' => 'Effective Access Workspace',
            'slug' => fake()->unique()->slug(),
        ]);
        $workspace->users()->attach($user->id, ['role' => $role->value]);

        return [$user, $workspace];
    }

    private function createReviewItem(Workspace $workspace): ReviewItem
    {
        return $workspace->reviewItems()->create([
            'question' => 'What should a guest not resolve?',
            'deduplication_key' => hash('sha256', 'guest-review-item'),
            'status' => 'pending',
            'last_asked_at' => now(),
        ]);
    }

    private function configureDemoPrincipal(User $user, Workspace $workspace): void
    {
        config()->set('demo.enabled', true);
        config()->set('demo.user_id', $user->id);
        config()->set('demo.workspace_id', $workspace->id);
    }

    /**
     * @return array<string, int|string>
     */
    private function validDemoSession(User $user, Workspace $workspace): array
    {
        return [
            DemoSessionContext::ACCESS_MODE_KEY => WorkspaceAccessMode::Demo->value,
            DemoSessionContext::USER_ID_KEY => $user->id,
            DemoSessionContext::WORKSPACE_ID_KEY => $workspace->id,
            DemoSessionContext::RATE_LIMIT_ID_KEY => 'effective-access-test-session',
        ];
    }
}
