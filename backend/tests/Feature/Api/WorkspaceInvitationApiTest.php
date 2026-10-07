<?php

namespace Tests\Feature\Api;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('team.frontend_url', 'https://sift.example.test');
        config()->set('team.invitation_ttl_days', 7);
    }

    public function test_owner_can_list_only_active_pending_invitations_without_tokens(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('list');
        $active = $this->createInvitation($workspace, [
            'email' => 'active@example.com',
            'active_key' => $this->activeKey($workspace, 'active@example.com'),
        ]);
        $this->createInvitation($workspace, [
            'email' => 'accepted@example.com',
            'accepted_at' => now(),
            'active_key' => null,
        ]);
        $this->createInvitation($workspace, [
            'email' => 'cancelled@example.com',
            'cancelled_at' => now(),
            'active_key' => null,
        ]);
        $this->createInvitation($workspace, [
            'email' => 'expired@example.com',
            'expires_at' => now()->subMinute(),
            'active_key' => $this->activeKey($workspace, 'expired@example.com'),
        ]);

        $this->actingAs($owner)
            ->getJson(route('workspaces.team.invitations.index', $workspace))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.email', 'active@example.com')
            ->assertJsonMissingPath('data.0.token_hash')
            ->assertJsonMissingPath('data.0.active_key')
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('data.0.invitation_url');
    }

    public function test_owner_can_create_admin_and_member_invitations_with_normalized_email_and_hashed_tokens(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('create');
        $now = now()->startOfSecond();
        $this->travelTo($now);

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Member] as $index => $role) {
            $email = $index === 0 ? '  ADMIN@EXAMPLE.COM  ' : '  MEMBER@EXAMPLE.COM  ';
            $normalizedEmail = trim(strtolower($email));

            $response = $this->actingAs($owner)->postJson(
                route('workspaces.team.invitations.store', $workspace),
                ['email' => $email, 'role' => $role->value],
            );

            $response
                ->assertCreated()
                ->assertJsonPath('data.invitation.email', $normalizedEmail)
                ->assertJsonPath('data.invitation.role', $role->value)
                ->assertJsonPath('data.invitation.expires_at', $now->copy()->addDays(7)->toISOString())
                ->assertJsonMissingPath('data.invitation.token_hash')
                ->assertJsonMissingPath('data.invitation.active_key');

            $invitation = WorkspaceInvitation::query()->where('email', $normalizedEmail)->sole();
            $url = $response->json('data.invitation_url');
            $rawToken = basename((string) parse_url($url, PHP_URL_PATH));

            $this->assertStringStartsWith('https://sift.example.test/invite/', $url);
            $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);
            $this->assertNotSame($rawToken, $invitation->token_hash);
            $this->assertSame($owner->id, $invitation->invited_by_user_id);
            $this->assertSame($this->activeKey($workspace, $normalizedEmail), $invitation->active_key);
            $this->assertDatabaseMissing('workspace_invitations', ['token_hash' => $rawToken]);
        }
    }

    public function test_invitation_creation_validates_role_email_and_extra_fields(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('validation');

        foreach (['owner', 'viewer'] as $role) {
            $this->actingAs($owner)->postJson(
                route('workspaces.team.invitations.store', $workspace),
                ['email' => 'person@example.com', 'role' => $role],
            )->assertUnprocessable()->assertJsonValidationErrors('role');
        }

        $this->actingAs($owner)->postJson(
            route('workspaces.team.invitations.store', $workspace),
            [
                'email' => 'not-an-email',
                'role' => 'member',
                'workspace_id' => 999,
            ],
        )->assertUnprocessable()->assertJsonValidationErrors(['email', 'workspace_id']);

        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_existing_workspace_member_and_other_workspace_account_are_rejected_safely(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('accounts');
        $member = $this->attachUser($workspace, WorkspaceRole::Member, 'member@example.com');

        $this->actingAs($owner)->postJson(
            route('workspaces.team.invitations.store', $workspace),
            ['email' => $member->email, 'role' => 'member'],
        )
            ->assertConflict()
            ->assertJsonPath('code', 'already_workspace_member');

        $otherWorkspace = $this->createWorkspace('other-account');
        $otherUser = $this->attachUser($otherWorkspace, WorkspaceRole::Member, 'other@example.com');

        $this->actingAs($owner)->postJson(
            route('workspaces.team.invitations.store', $workspace),
            ['email' => $otherUser->email, 'role' => 'admin'],
        )
            ->assertConflict()
            ->assertExactJson([
                'message' => 'This account cannot be invited to this workspace.',
                'code' => 'invitation_account_unavailable',
            ]);

        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_existing_zero_workspace_account_can_be_invited(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('orphan');
        $user = $this->createUser('available@example.com');

        $this->actingAs($owner)->postJson(
            route('workspaces.team.invitations.store', $workspace),
            ['email' => strtoupper($user->email), 'role' => 'member'],
        )->assertCreated();

        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => $user->email,
        ]);
    }

    public function test_duplicate_active_invitation_returns_conflict_and_only_one_active_row_exists(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('duplicate');
        $payload = ['email' => 'person@example.com', 'role' => 'member'];

        $this->actingAs($owner)
            ->postJson(route('workspaces.team.invitations.store', $workspace), $payload)
            ->assertCreated();
        $this->actingAs($owner)
            ->postJson(route('workspaces.team.invitations.store', $workspace), $payload)
            ->assertConflict()
            ->assertJsonPath('code', 'active_invitation_exists')
            ->assertJsonMissingPath('invitation_url');

        $this->assertSame(1, WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('active_key')
            ->count());
    }

    public function test_expired_invitation_is_atomically_released_and_replaced(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('replace');
        $expired = $this->createInvitation($workspace, [
            'email' => 'person@example.com',
            'active_key' => $this->activeKey($workspace, 'person@example.com'),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($owner)->postJson(
            route('workspaces.team.invitations.store', $workspace),
            ['email' => 'PERSON@example.com', 'role' => 'admin'],
        )->assertCreated();

        $replacement = WorkspaceInvitation::query()->findOrFail($response->json('data.invitation.id'));
        $this->assertNotSame($expired->id, $replacement->id);
        $this->assertNull($expired->fresh()->active_key);
        $this->assertSame($this->activeKey($workspace, 'person@example.com'), $replacement->active_key);
    }

    public function test_owner_can_cancel_an_active_invitation_without_deleting_it(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('cancel');
        $invitation = $this->createActiveInvitation($workspace);

        $this->actingAs($owner)
            ->deleteJson(route('workspaces.team.invitations.destroy', [$workspace, $invitation]))
            ->assertNoContent();

        $invitation->refresh();
        $this->assertNotNull($invitation->cancelled_at);
        $this->assertNull($invitation->active_key);
        $this->assertDatabaseHas('workspace_invitations', ['id' => $invitation->id]);
    }

    public function test_inactive_and_expired_invitations_cannot_be_cancelled(): void
    {
        foreach (['accepted', 'cancelled', 'expired'] as $state) {
            [$workspace, $owner] = $this->createOwnedWorkspace('cancel-'.$state);
            $overrides = match ($state) {
                'accepted' => ['accepted_at' => now(), 'active_key' => null],
                'cancelled' => ['cancelled_at' => now(), 'active_key' => null],
                'expired' => ['expires_at' => now()->subMinute()],
            };
            $invitation = $this->createActiveInvitation($workspace, $overrides);

            $this->actingAs($owner)
                ->deleteJson(route('workspaces.team.invitations.destroy', [$workspace, $invitation]))
                ->assertConflict()
                ->assertJsonPath('code', $state === 'expired' ? 'invitation_expired' : 'invitation_inactive');
        }
    }

    public function test_owner_can_regenerate_a_link_on_the_same_invitation(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('regenerate');
        $invitation = $this->createActiveInvitation($workspace);
        $oldHash = $invitation->token_hash;
        $now = now()->startOfSecond();
        $this->travelTo($now);

        $response = $this->actingAs($owner)->postJson(
            route('workspaces.team.invitations.regenerate-link', [$workspace, $invitation]),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.invitation.id', $invitation->id)
            ->assertJsonPath('data.invitation.expires_at', $now->copy()->addDays(7)->toISOString());

        $url = $response->json('data.invitation_url');
        $rawToken = basename((string) parse_url($url, PHP_URL_PATH));
        $invitation->refresh();
        $this->assertNotSame($oldHash, $invitation->token_hash);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);
        $this->assertDatabaseMissing('workspace_invitations', ['token_hash' => $rawToken]);
        $this->assertDatabaseCount('workspace_invitations', 1);
    }

    public function test_inactive_and_expired_invitations_cannot_be_regenerated(): void
    {
        foreach (['accepted', 'cancelled', 'expired'] as $state) {
            [$workspace, $owner] = $this->createOwnedWorkspace('regenerate-'.$state);
            $overrides = match ($state) {
                'accepted' => ['accepted_at' => now(), 'active_key' => null],
                'cancelled' => ['cancelled_at' => now(), 'active_key' => null],
                'expired' => ['expires_at' => now()->subMinute()],
            };
            $invitation = $this->createActiveInvitation($workspace, $overrides);

            $this->actingAs($owner)->postJson(
                route('workspaces.team.invitations.regenerate-link', [$workspace, $invitation]),
            )
                ->assertConflict()
                ->assertJsonPath('code', $state === 'expired' ? 'invitation_expired' : 'invitation_inactive');
        }
    }

    public function test_admin_and_member_cannot_manage_invitations(): void
    {
        foreach ([WorkspaceRole::Admin, WorkspaceRole::Member] as $role) {
            $workspace = $this->createWorkspace('manage-'.$role->value);
            $user = $this->attachUser($workspace, $role, $role->value.'-manage@example.com');
            $invitation = $this->createActiveInvitation($workspace);

            $this->actingAs($user)
                ->getJson(route('workspaces.team.invitations.index', $workspace))
                ->assertForbidden();
            $this->actingAs($user)->postJson(
                route('workspaces.team.invitations.store', $workspace),
                ['email' => 'new@example.com', 'role' => 'member'],
            )->assertForbidden();
            $this->actingAs($user)
                ->deleteJson(route('workspaces.team.invitations.destroy', [$workspace, $invitation]))
                ->assertForbidden();
            $this->actingAs($user)->postJson(
                route('workspaces.team.invitations.regenerate-link', [$workspace, $invitation]),
            )->assertForbidden();
        }
    }

    public function test_invitation_routes_require_authentication(): void
    {
        $workspace = $this->createWorkspace('unauthenticated');
        $invitation = $this->createActiveInvitation($workspace);

        $this->getJson(route('workspaces.team.invitations.index', $workspace))->assertUnauthorized();
        $this->postJson(route('workspaces.team.invitations.store', $workspace), [])->assertUnauthorized();
        $this->deleteJson(route('workspaces.team.invitations.destroy', [$workspace, $invitation]))->assertUnauthorized();
        $this->postJson(route('workspaces.team.invitations.regenerate-link', [$workspace, $invitation]))->assertUnauthorized();
    }

    public function test_cross_workspace_invitation_access_is_not_found(): void
    {
        [$ownedWorkspace, $owner] = $this->createOwnedWorkspace('owned');
        $otherWorkspace = $this->createWorkspace('private');
        $invitation = $this->createActiveInvitation($otherWorkspace);

        $this->actingAs($owner)
            ->getJson(route('workspaces.team.invitations.index', $otherWorkspace))
            ->assertNotFound();
        $this->actingAs($owner)
            ->deleteJson(route('workspaces.team.invitations.destroy', [$ownedWorkspace, $invitation]))
            ->assertNotFound();
        $this->actingAs($owner)->postJson(
            route('workspaces.team.invitations.regenerate-link', [$ownedWorkspace, $invitation]),
        )->assertNotFound();

        $this->assertNull($invitation->fresh()->cancelled_at);
    }

    /**
     * @return array{Workspace, User}
     */
    private function createOwnedWorkspace(string $suffix): array
    {
        $workspace = $this->createWorkspace($suffix);
        $owner = $this->attachUser($workspace, WorkspaceRole::Owner, $suffix.'-owner@example.com');

        return [$workspace, $owner];
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => str($suffix)->headline().' Workspace',
            'slug' => "invitation-api-{$suffix}-".Str::uuid(),
        ]);
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Invitation User',
            'email' => $email,
            'password' => 'StrongPassword123',
        ]);
    }

    private function attachUser(Workspace $workspace, WorkspaceRole $role, string $email): User
    {
        $user = $this->createUser($email);
        $workspace->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createActiveInvitation(Workspace $workspace, array $overrides = []): WorkspaceInvitation
    {
        return $this->createInvitation($workspace, array_merge([
            'active_key' => $this->activeKey($workspace, 'invitee@example.com'),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvitation(Workspace $workspace, array $overrides = []): WorkspaceInvitation
    {
        return WorkspaceInvitation::query()->create(array_merge([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => WorkspaceRole::Member,
            'token_hash' => hash('sha256', Str::uuid()->toString()),
            'active_key' => null,
            'expires_at' => now()->addDays(7),
        ], $overrides));
    }

    private function activeKey(Workspace $workspace, string $email): string
    {
        return hash('sha256', $workspace->id.'|'.$email);
    }
}
