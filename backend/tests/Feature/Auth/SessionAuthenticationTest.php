<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_and_receive_their_workspace_context(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace(WorkspaceRole::Admin);

        $response = $this->statefulPostJson(route('auth.login'), [
            'email' => '  USER@EXAMPLE.COM ',
            'password' => 'StrongPassword123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'user@example.com')
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspace.role', WorkspaceRole::Admin->value)
            ->assertJsonPath('data.access_mode', 'normal')
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'assistant.use',
                'knowledge.view',
                'knowledge.manage',
                'conversations.view',
                'review.view',
                'review.resolve',
                'settings.view',
            ])
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.workspace.slug');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_return_a_generic_validation_error(): void
    {
        $this->createUserWithWorkspace();

        $this->statefulPostJson(route('auth.login'), [
            'email' => 'user@example.com',
            'password' => 'IncorrectPassword123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

        $this->assertGuest('web');
    }

    public function test_the_current_user_endpoint_requires_authentication(): void
    {
        $this->withHeaders([
            'Accept' => 'text/html',
            'Origin' => 'http://localhost:3000',
        ])->get(route('auth.user'))
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_an_authenticated_user_can_load_their_current_context_and_log_out(): void
    {
        config()->set('session.driver', 'database');
        [$user, $workspace] = $this->createUserWithWorkspace(WorkspaceRole::Member);

        $this->statefulPostJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'StrongPassword123',
        ])->assertOk();

        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspace.role', WorkspaceRole::Member->value)
            ->assertJsonPath('data.access_mode', 'normal')
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'assistant.use',
                'review.view',
                'review.resolve',
            ]);

        $this->statefulPostJson(route('auth.logout'))->assertNoContent();
        $this->assertGuest('web');
        $this->app['auth']->forgetGuards();

        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson(route('auth.user'))
            ->assertUnauthorized();
    }

    public function test_the_current_user_endpoint_includes_owner_team_permissions(): void
    {
        [$user] = $this->createUserWithWorkspace(WorkspaceRole::Owner);

        $this->actingAs($user)
            ->withHeader('Origin', 'http://localhost:3000')
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.access_mode', 'normal')
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'assistant.use',
                'knowledge.view',
                'knowledge.manage',
                'conversations.view',
                'review.view',
                'review.resolve',
                'settings.view',
                'settings.update',
                'team.view',
                'team.manage',
            ]);
    }

    public function test_login_fails_closed_when_the_user_has_no_single_workspace_context(): void
    {
        $user = User::query()->create([
            'name' => 'No Workspace',
            'email' => 'orphan@example.com',
            'password' => 'StrongPassword123',
        ]);

        $this->statefulPostJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'StrongPassword123',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'workspace_context_unavailable');

        $this->assertGuest('web');
    }

    public function test_login_fails_closed_when_the_user_has_multiple_workspaces(): void
    {
        [$user] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::query()->create([
            'name' => 'Second Workspace',
            'slug' => 'second-workspace',
        ]);
        $secondWorkspace->users()->attach($user->id, ['role' => WorkspaceRole::Member->value]);

        $this->statefulPostJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'StrongPassword123',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'workspace_context_unavailable');

        $this->assertGuest('web');
    }

    /**
     * @return array{User, Workspace}
     */
    private function createUserWithWorkspace(WorkspaceRole $role = WorkspaceRole::Owner): array
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'StrongPassword123',
        ]);
        $workspace = Workspace::query()->create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);
        $workspace->users()->attach($user->id, ['role' => $role->value]);

        return [$user, $workspace];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statefulPostJson(string $uri, array $data = [])
    {
        return $this->withHeader('Origin', 'http://localhost:3000')->postJson($uri, $data);
    }
}
