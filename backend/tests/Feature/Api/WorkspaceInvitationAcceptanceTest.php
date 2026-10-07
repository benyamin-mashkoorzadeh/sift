<?php

namespace Tests\Feature\Api;

use App\Actions\Team\RegenerateWorkspaceInvitationLink;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceInvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('team.frontend_url', 'https://sift.example.test');
        config()->set('team.invitation_ttl_days', 7);
    }

    public function test_valid_invitation_preview_is_safe_non_cacheable_and_read_only(): void
    {
        $workspace = $this->createWorkspace('Preview Company');
        [$invitation, $rawToken] = $this->createInvitation($workspace, 'NEW@EXAMPLE.COM', WorkspaceRole::Admin);
        $original = $invitation->fresh()->getAttributes();

        $this->getJson(route('invitations.show', $rawToken))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'workspace' => ['name' => 'Preview Company'],
                    'email' => 'new@example.com',
                    'role' => 'admin',
                    'expires_at' => $invitation->expires_at->toISOString(),
                    'account_state' => 'new_account',
                ],
            ])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertJsonMissingPath('data.workspace.id')
            ->assertJsonMissingPath('data.token_hash')
            ->assertJsonMissingPath('data.active_key')
            ->assertJsonMissingPath('data.id');

        $this->assertSame($original, $invitation->fresh()->getAttributes());
    }

    public function test_preview_reports_existing_account_without_exposing_internal_account_data(): void
    {
        $workspace = $this->createWorkspace('Existing Account Preview');
        $this->createUser('person@example.com');
        [, $rawToken] = $this->createInvitation($workspace, 'person@example.com');

        $this->getJson(route('invitations.show', $rawToken))
            ->assertOk()
            ->assertJsonPath('data.account_state', 'existing_account')
            ->assertJsonMissingPath('data.user');
    }

    public function test_invalid_and_inactive_invitation_previews_return_the_same_safe_not_found(): void
    {
        $workspace = $this->createWorkspace('Unavailable Preview');
        $cases = [];

        [, $cases['expired']] = $this->createInvitation($workspace, 'expired@example.com', overrides: [
            'expires_at' => now()->subMinute(),
        ]);
        [, $cases['cancelled']] = $this->createInvitation($workspace, 'cancelled@example.com', overrides: [
            'cancelled_at' => now(),
            'active_key' => null,
        ]);
        [, $cases['accepted']] = $this->createInvitation($workspace, 'accepted@example.com', overrides: [
            'accepted_at' => now(),
            'active_key' => null,
        ]);
        $cases['invalid'] = 'not-a-real-invitation-token';

        foreach ($cases as $rawToken) {
            $this->getJson(route('invitations.show', $rawToken))
                ->assertNotFound()
                ->assertExactJson([
                    'message' => 'This invitation is unavailable.',
                    'code' => 'invitation_unavailable',
                ])
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('Referrer-Policy', 'no-referrer');
        }
    }

    public function test_regenerated_old_token_cannot_be_previewed_or_accepted(): void
    {
        $workspace = $this->createWorkspace('Regenerated Token');
        [$invitation, $oldToken] = $this->createInvitation($workspace, 'person@example.com');
        app(RegenerateWorkspaceInvitationLink::class)->handle($workspace, $invitation);

        $this->getJson(route('invitations.show', $oldToken))->assertNotFound();
        $this->statefulPostJson(route('invitations.accept', $oldToken), [
            'name' => 'Person Name',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ])->assertNotFound();

        $this->assertDatabaseCount('workspace_user', 0);
    }

    /**
     * @return array<string, array{WorkspaceRole, list<string>}>
     */
    public static function invitationRoleProvider(): array
    {
        return [
            'admin' => [WorkspaceRole::Admin, [
                'overview.view',
                'assistant.use',
                'knowledge.view',
                'knowledge.manage',
                'conversations.view',
                'review.view',
                'review.resolve',
                'settings.view',
            ]],
            'member' => [WorkspaceRole::Member, [
                'overview.view',
                'assistant.use',
                'review.view',
                'review.resolve',
            ]],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('invitationRoleProvider')]
    public function test_new_user_acceptance_creates_only_the_membership_and_establishes_a_session(
        WorkspaceRole $role,
        array $permissions,
    ): void {
        $workspace = $this->createWorkspace('New User '.$role->value);
        [$invitation, $rawToken] = $this->createInvitation($workspace, 'JANE@EXAMPLE.COM', $role);
        $workspaceCount = Workspace::query()->count();

        $response = $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'name' => '  Jane Smith  ',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ]);

        $user = User::query()->where('email', 'jane@example.com')->sole();
        $membership = $user->workspaces()->sole();

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Jane Smith')
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspace.role', $role->value)
            ->assertJsonPath('data.permissions', $permissions)
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertTrue(Hash::check('Secure123', $user->password));
        $this->assertNotSame('Secure123', $user->password);
        $this->assertSame($role, $membership->pivot->role);
        $this->assertSame($workspaceCount, Workspace::query()->count());
        $this->assertAuthenticatedAs($user);

        $invitation->refresh();
        $this->assertSame($user->id, $invitation->accepted_by_user_id);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertNull($invitation->active_key);

        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspace.role', $role->value)
            ->assertJsonPath('data.permissions', $permissions);
    }

    public function test_new_user_acceptance_reuses_registration_password_requirements(): void
    {
        $workspace = $this->createWorkspace('Password Rules');
        [$invitation, $rawToken] = $this->createInvitation($workspace, 'new@example.com');

        $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'name' => 'New User',
            'password' => 'lowercase',
            'password_confirmation' => 'lowercase',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_acceptance_prohibits_identity_workspace_and_role_overrides(): void
    {
        $workspace = $this->createWorkspace('Protected Fields');
        [$invitation, $rawToken] = $this->createInvitation($workspace, 'invited@example.com', WorkspaceRole::Member);

        $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'name' => 'Injected User',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
            'email' => 'attacker@example.com',
            'role' => 'owner',
            'workspace_id' => 999,
            'invited_by' => 999,
            'accepted_by' => 999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role', 'workspace_id', 'invited_by', 'accepted_by']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workspace_user', 0);
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_existing_zero_workspace_user_can_accept_with_their_password_without_recreation(): void
    {
        $workspace = $this->createWorkspace('Existing User');
        $user = $this->createUser('existing@example.com');
        [, $rawToken] = $this->createInvitation($workspace, $user->email, WorkspaceRole::Admin);
        $userCount = User::query()->count();
        $workspaceCount = Workspace::query()->count();

        $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'password' => 'ExistingPassword123',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.workspace.role', 'admin');

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($workspaceCount, Workspace::query()->count());
        $this->assertSame(WorkspaceRole::Admin, $user->workspaces()->sole()->pivot->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_existing_user_password_does_not_consume_invitation(): void
    {
        $workspace = $this->createWorkspace('Wrong Password');
        $user = $this->createUser('existing@example.com');
        [$invitation, $rawToken] = $this->createInvitation($workspace, $user->email);

        $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'password' => 'WrongPassword123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password')
            ->assertJsonPath('errors.password.0', 'The provided credentials are incorrect.');

        $invitation->refresh();
        $this->assertNull($invitation->accepted_at);
        $this->assertNull($invitation->cancelled_at);
        $this->assertNotNull($invitation->active_key);
        $this->assertDatabaseCount('workspace_user', 0);
        $this->assertGuest('web');
    }

    public function test_matching_authenticated_zero_workspace_user_accepts_without_password(): void
    {
        $workspace = $this->createWorkspace('Authenticated Match');
        $user = $this->createUser('matching@example.com');
        [, $rawToken] = $this->createInvitation($workspace, $user->email, WorkspaceRole::Member);

        $this->actingAs($user)
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson(route('invitations.accept', $rawToken), [])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.workspace.role', 'member');

        $this->assertSame(WorkspaceRole::Member, $user->workspaces()->sole()->pivot->role);
        $this->assertAuthenticatedAs($user);
    }

    public function test_mismatched_authenticated_user_is_forbidden_without_consuming_invitation(): void
    {
        $workspace = $this->createWorkspace('Authenticated Mismatch');
        $user = $this->createUser('wrong@example.com');
        [$invitation, $rawToken] = $this->createInvitation($workspace, 'invited@example.com');

        $this->actingAs($user)
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson(route('invitations.accept', $rawToken), [])
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'This invitation belongs to a different account.',
                'code' => 'invitation_wrong_account',
            ]);

        $invitation->refresh();
        $this->assertNull($invitation->accepted_at);
        $this->assertNull($invitation->cancelled_at);
        $this->assertNotNull($invitation->active_key);
        $this->assertDatabaseCount('workspace_user', 0);
    }

    public function test_existing_same_workspace_member_is_rejected_invalidated_and_role_is_preserved(): void
    {
        $workspace = $this->createWorkspace('Same Workspace');
        $user = $this->createUser('member@example.com');
        $workspace->users()->attach($user->id, ['role' => WorkspaceRole::Member->value]);
        [$invitation, $rawToken] = $this->createInvitation($workspace, $user->email, WorkspaceRole::Admin);

        $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'password' => 'ExistingPassword123',
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'already_workspace_member');

        $invitation->refresh();
        $this->assertNotNull($invitation->cancelled_at);
        $this->assertNull($invitation->active_key);
        $this->assertNull($invitation->accepted_at);
        $this->assertSame(WorkspaceRole::Member, $user->workspaces()->sole()->pivot->role);
        $this->assertDatabaseCount('workspace_user', 1);

        $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'password' => 'ExistingPassword123',
        ])->assertNotFound();
    }

    public function test_other_workspace_user_is_rejected_without_a_second_membership_and_invitation_is_invalidated(): void
    {
        $targetWorkspace = $this->createWorkspace('Target Workspace');
        $existingWorkspace = $this->createWorkspace('Existing Workspace');
        $user = $this->createUser('member@example.com');
        $existingWorkspace->users()->attach($user->id, ['role' => WorkspaceRole::Admin->value]);
        [$invitation, $rawToken] = $this->createInvitation($targetWorkspace, $user->email);

        $this->statefulPostJson(route('invitations.accept', $rawToken), [
            'password' => 'ExistingPassword123',
        ])
            ->assertConflict()
            ->assertExactJson([
                'message' => 'This account cannot be invited to this workspace.',
                'code' => 'invitation_account_unavailable',
            ]);

        $invitation->refresh();
        $this->assertNotNull($invitation->cancelled_at);
        $this->assertNull($invitation->active_key);
        $this->assertDatabaseCount('workspace_user', 1);
        $this->assertTrue($user->workspaces()->sole()->is($existingWorkspace));
    }

    public function test_consumed_token_cannot_create_a_second_membership(): void
    {
        $workspace = $this->createWorkspace('Single Use');
        [, $rawToken] = $this->createInvitation($workspace, 'person@example.com');
        $payload = [
            'name' => 'Person Name',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ];

        $this->statefulPostJson(route('invitations.accept', $rawToken), $payload)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->statefulPostJson(route('invitations.accept', $rawToken), $payload)
            ->assertNotFound()
            ->assertJsonMissingPath('exception');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workspace_user', 1);
        $this->assertDatabaseCount('workspace_invitations', 1);
    }

    public function test_cancelled_and_expired_invitations_cannot_be_accepted(): void
    {
        $workspace = $this->createWorkspace('Inactive Acceptance');
        $cases = [];
        [, $cases['cancelled']] = $this->createInvitation($workspace, 'cancelled@example.com', overrides: [
            'cancelled_at' => now(),
            'active_key' => null,
        ]);
        [, $cases['expired']] = $this->createInvitation($workspace, 'expired@example.com', overrides: [
            'expires_at' => now()->subMinute(),
        ]);

        foreach ($cases as $rawToken) {
            $this->statefulPostJson(route('invitations.accept', $rawToken), [
                'name' => 'Person Name',
                'password' => 'Secure123',
                'password_confirmation' => 'Secure123',
            ])->assertNotFound()->assertJsonPath('code', 'invitation_unavailable');
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workspace_user', 0);
    }

    public function test_normal_registration_still_creates_a_new_workspace_owner(): void
    {
        $this->statefulPostJson(route('auth.register'), [
            'name' => 'Company Owner',
            'email' => 'owner@example.com',
            'company_name' => 'Owner Company',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.workspace.role', 'owner');

        $this->assertDatabaseCount('workspaces', 1);
        $this->assertSame(WorkspaceRole::Owner, User::query()->sole()->workspaces()->sole()->pivot->role);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{WorkspaceInvitation, string}
     */
    private function createInvitation(
        Workspace $workspace,
        string $email,
        WorkspaceRole $role = WorkspaceRole::Member,
        array $overrides = [],
    ): array {
        $normalizedEmail = strtolower(trim($email));
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $invitation = WorkspaceInvitation::query()->create(array_merge([
            'workspace_id' => $workspace->id,
            'email' => $normalizedEmail,
            'role' => $role,
            'token_hash' => hash('sha256', $rawToken),
            'active_key' => hash('sha256', $workspace->id.'|'.$normalizedEmail),
            'expires_at' => now()->addDays(7),
        ], $overrides));

        return [$invitation, $rawToken];
    }

    private function createWorkspace(string $name): Workspace
    {
        return Workspace::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::uuid(),
        ]);
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Existing User',
            'email' => $email,
            'password' => 'ExistingPassword123',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statefulPostJson(string $uri, array $data = [])
    {
        return $this->withHeader('Origin', 'http://localhost:3000')->postJson($uri, $data);
    }
}
