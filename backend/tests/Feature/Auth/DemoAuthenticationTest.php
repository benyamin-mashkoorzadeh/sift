<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\DemoSessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class DemoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_demo_entry_authenticates_the_configured_guest_and_returns_effective_context(): void
    {
        [$guest, $workspace] = $this->createDemoPrincipal();

        $this->statefulPostJson(route('auth.demo'))
            ->assertOk()
            ->assertJsonPath('data.user.id', $guest->id)
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspace.role', WorkspaceRole::Member->value)
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Demo->value)
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'knowledge.view',
                'assistant.use',
                'conversations.view',
                'review.view',
            ]);

        $this->assertAuthenticatedAs($guest);

        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Demo->value)
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'knowledge.view',
                'assistant.use',
                'conversations.view',
                'review.view',
            ]);
    }

    public function test_demo_entry_regenerates_the_session_identifier(): void
    {
        $this->createDemoPrincipal();
        $this->withSession(['pre_demo_marker' => 'present']);
        $originalSessionId = $this->app['session']->getId();

        $this->statefulPostJson(route('auth.demo'))->assertOk();

        $this->assertNotSame($originalSessionId, $this->app['session']->getId());
        $this->assertSame('present', session('pre_demo_marker'));
    }

    public function test_disabled_demo_fails_with_a_safe_error(): void
    {
        $this->createDemoPrincipal();
        config()->set('demo.enabled', false);

        $this->statefulPostJson(route('auth.demo'))
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'Guest Demo is unavailable right now.',
                'code' => 'demo_unavailable',
            ]);

        $this->assertGuest('web');
    }

    public function test_missing_demo_configuration_fails_with_the_same_safe_error(): void
    {
        config()->set('demo.enabled', true);
        config()->set('demo.user_id', null);
        config()->set('demo.workspace_id', null);

        $this->statefulPostJson(route('auth.demo'))
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'Guest Demo is unavailable right now.',
                'code' => 'demo_unavailable',
            ]);

        $this->assertGuest('web');
    }

    public function test_mismatched_demo_membership_fails_without_authenticating_the_guest(): void
    {
        [$guest] = $this->createDemoPrincipal();
        $otherWorkspace = Workspace::query()->create([
            'name' => 'Incorrect Demo Workspace',
            'slug' => 'incorrect-demo-workspace',
        ]);
        config()->set('demo.workspace_id', $otherWorkspace->id);

        $this->statefulPostJson(route('auth.demo'))
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'Guest Demo is unavailable right now.',
                'code' => 'demo_unavailable',
            ]);

        $this->assertGuest('web');
    }

    public function test_an_authenticated_normal_user_cannot_be_replaced_by_the_guest(): void
    {
        $this->createDemoPrincipal();
        [$normalUser] = $this->createUserWithWorkspace('normal@example.com');

        $this->actingAs($normalUser);

        $this->statefulPostJson(route('auth.demo'))
            ->assertConflict()
            ->assertExactJson([
                'message' => 'Log out before entering Guest Demo.',
                'code' => 'demo_session_conflict',
            ]);

        $this->assertAuthenticatedAs($normalUser);
    }

    public function test_the_configured_guest_cannot_use_ordinary_password_login(): void
    {
        [$guest] = $this->createDemoPrincipal();

        $this->statefulPostJson(route('auth.login'), [
            'email' => $guest->email,
            'password' => 'GuestPassword123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

        $this->assertGuest('web');
    }

    public function test_logout_clears_demo_context_and_invalidates_only_the_current_session(): void
    {
        $this->createDemoPrincipal();
        $this->statefulPostJson(route('auth.demo'))->assertOk();

        $this->assertSame(WorkspaceAccessMode::Demo->value, session(DemoSessionContext::ACCESS_MODE_KEY));

        $this->statefulPostJson(route('auth.logout'))->assertNoContent();

        $this->assertGuest('web');
        $this->assertNull(session(DemoSessionContext::ACCESS_MODE_KEY));
        $this->assertNull(session(DemoSessionContext::USER_ID_KEY));
        $this->assertNull(session(DemoSessionContext::WORKSPACE_ID_KEY));
        $this->assertNull(session(DemoSessionContext::RATE_LIMIT_ID_KEY));
        $this->app['auth']->forgetGuards();

        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson(route('auth.user'))
            ->assertUnauthorized();
    }

    public function test_demo_context_isolated_between_independent_sessions(): void
    {
        [$guest, $workspace] = $this->createDemoPrincipal();
        $first = $this->demoContextForSession('first-demo-session');
        $second = $this->demoContextForSession('second-demo-session');

        $first->establish($guest, $workspace);
        $second->establish($guest, $workspace);

        $this->assertTrue($first->isValidFor($guest, $workspace));
        $this->assertTrue($second->isValidFor($guest, $workspace));

        $first->clear();

        $this->assertFalse($first->isValidFor($guest, $workspace));
        $this->assertTrue($second->isValidFor($guest, $workspace));
    }

    public function test_normal_login_clears_stale_demo_context(): void
    {
        [$guest, $demoWorkspace] = $this->createDemoPrincipal();
        [$normalUser] = $this->createUserWithWorkspace('login@example.com');

        $this->withSession($this->demoSession($guest, $demoWorkspace));
        $this->statefulPostJson(route('auth.login'), [
            'email' => $normalUser->email,
            'password' => 'StrongPassword123',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $normalUser->id)
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Normal->value);

        $this->assertAuthenticatedAs($normalUser);
        $this->assertDemoContextCleared();
    }

    public function test_registration_clears_stale_demo_context(): void
    {
        [$guest, $demoWorkspace] = $this->createDemoPrincipal();

        $this->withSession($this->demoSession($guest, $demoWorkspace));
        $this->statefulPostJson(route('auth.register'), [
            'name' => 'New Owner',
            'email' => 'new-owner@example.com',
            'company_name' => 'New Company',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'new-owner@example.com')
            ->assertJsonPath('data.access_mode', WorkspaceAccessMode::Normal->value);

        $this->assertDemoContextCleared();
    }

    /**
     * @return array{User, Workspace}
     */
    private function createDemoPrincipal(): array
    {
        [$guest, $workspace] = $this->createUserWithWorkspace(
            'guest@example.com',
            'GuestPassword123',
            WorkspaceRole::Member,
        );
        config()->set('demo.enabled', true);
        config()->set('demo.user_id', $guest->id);
        config()->set('demo.workspace_id', $workspace->id);

        return [$guest, $workspace];
    }

    /**
     * @return array{User, Workspace}
     */
    private function createUserWithWorkspace(
        string $email,
        string $password = 'StrongPassword123',
        WorkspaceRole $role = WorkspaceRole::Owner,
    ): array {
        $user = User::query()->create([
            'name' => str($email)->before('@')->headline()->toString(),
            'email' => $email,
            'password' => $password,
        ]);
        $workspace = Workspace::query()->create([
            'name' => str($email)->before('@')->headline().' Workspace',
            'slug' => str($email)->before('@').'-'.fake()->unique()->uuid(),
        ]);
        $workspace->users()->attach($user->id, ['role' => $role->value]);

        return [$user, $workspace];
    }

    private function demoContextForSession(string $sessionId): DemoSessionContext
    {
        $session = new Store($sessionId, new ArraySessionHandler(120));
        $session->start();
        $request = Request::create('/');
        $request->setLaravelSession($session);

        return new DemoSessionContext($request);
    }

    /**
     * @return array<string, int|string>
     */
    private function demoSession(User $guest, Workspace $workspace): array
    {
        return [
            DemoSessionContext::ACCESS_MODE_KEY => WorkspaceAccessMode::Demo->value,
            DemoSessionContext::USER_ID_KEY => $guest->id,
            DemoSessionContext::WORKSPACE_ID_KEY => $workspace->id,
        ];
    }

    private function assertDemoContextCleared(): void
    {
        $this->assertNull(session(DemoSessionContext::ACCESS_MODE_KEY));
        $this->assertNull(session(DemoSessionContext::USER_ID_KEY));
        $this->assertNull(session(DemoSessionContext::WORKSPACE_ID_KEY));
        $this->assertNull(session(DemoSessionContext::RATE_LIMIT_ID_KEY));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statefulPostJson(string $uri, array $data = [])
    {
        return $this->withHeader('Origin', 'http://localhost:3000')->postJson($uri, $data);
    }
}
