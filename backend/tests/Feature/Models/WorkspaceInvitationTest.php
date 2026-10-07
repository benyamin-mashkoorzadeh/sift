<?php

namespace Tests\Feature\Models;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Policies\WorkspacePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_invitations_schema_has_the_expected_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasTable('workspace_invitations'));
        $this->assertTrue(Schema::hasColumns('workspace_invitations', [
            'id',
            'workspace_id',
            'invited_by_user_id',
            'accepted_by_user_id',
            'email',
            'role',
            'token_hash',
            'active_key',
            'expires_at',
            'accepted_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(DB::select(<<<'SQL'
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'workspace_invitations'
            SQL))->pluck('indexname');

        $this->assertContains('workspace_invitations_token_hash_unique', $indexes);
        $this->assertContains('workspace_invitations_active_key_unique', $indexes);
        $this->assertContains('workspace_invitations_workspace_id_created_at_index', $indexes);
        $this->assertContains('workspace_invitations_workspace_id_expires_at_index', $indexes);
    }

    public function test_invitation_relationships_and_timestamp_casts_work(): void
    {
        $workspace = $this->createWorkspace();
        $inviter = $this->createUser('inviter@example.com');
        $accepter = $this->createUser('accepter@example.com');
        $acceptedAt = now()->subMinute()->startOfSecond();
        $cancelledAt = now()->startOfSecond();

        $invitation = $workspace->invitations()->create([
            'invited_by_user_id' => $inviter->id,
            'accepted_by_user_id' => $accepter->id,
            'email' => 'new-member@example.com',
            'role' => WorkspaceRole::Admin,
            'token_hash' => hash('sha256', 'relationship-token'),
            'expires_at' => now()->addDays(7),
            'accepted_at' => $acceptedAt,
            'cancelled_at' => $cancelledAt,
        ])->fresh();

        $this->assertTrue($invitation->workspace->is($workspace));
        $this->assertTrue($invitation->invitedBy->is($inviter));
        $this->assertTrue($invitation->acceptedBy->is($accepter));
        $this->assertTrue($workspace->fresh()->invitations->firstOrFail()->is($invitation));
        $this->assertSame(WorkspaceRole::Admin, $invitation->role);
        $this->assertInstanceOf(Carbon::class, $invitation->expires_at);
        $this->assertInstanceOf(Carbon::class, $invitation->accepted_at);
        $this->assertInstanceOf(Carbon::class, $invitation->cancelled_at);
        $this->assertTrue($invitation->accepted_at->equalTo($acceptedAt));
        $this->assertTrue($invitation->cancelled_at->equalTo($cancelledAt));
    }

    public function test_inviter_and_accepter_are_set_to_null_when_the_users_are_deleted(): void
    {
        $workspace = $this->createWorkspace();
        $inviter = $this->createUser('inviter-null@example.com');
        $accepter = $this->createUser('accepter-null@example.com');
        $invitation = $this->createInvitation($workspace, [
            'invited_by_user_id' => $inviter->id,
            'accepted_by_user_id' => $accepter->id,
        ]);

        $inviter->delete();
        $accepter->delete();

        $invitation->refresh();
        $this->assertNull($invitation->invited_by_user_id);
        $this->assertNull($invitation->accepted_by_user_id);
    }

    public function test_admin_and_member_are_supported_invitation_roles(): void
    {
        $workspace = $this->createWorkspace();

        $admin = $this->createInvitation($workspace, [
            'role' => WorkspaceRole::Admin,
            'token_hash' => hash('sha256', 'admin-token'),
        ])->fresh();
        $member = $this->createInvitation($workspace, [
            'role' => WorkspaceRole::Member,
            'token_hash' => hash('sha256', 'member-token'),
        ])->fresh();

        $this->assertSame(WorkspaceRole::Admin, $admin->role);
        $this->assertSame(WorkspaceRole::Member, $member->role);
    }

    public function test_owner_is_rejected_as_an_invitation_role_by_the_database(): void
    {
        $workspace = $this->createWorkspace();

        $this->expectException(QueryException::class);

        $this->createInvitation($workspace, [
            'role' => WorkspaceRole::Owner,
            'token_hash' => hash('sha256', 'owner-token'),
        ]);
    }

    public function test_token_hash_must_be_unique(): void
    {
        $workspace = $this->createWorkspace();
        $tokenHash = hash('sha256', 'duplicate-token');
        $this->createInvitation($workspace, ['token_hash' => $tokenHash]);

        $this->expectException(QueryException::class);

        $this->createInvitation($workspace, [
            'email' => 'another@example.com',
            'token_hash' => $tokenHash,
        ]);
    }

    public function test_active_key_must_be_unique_when_populated(): void
    {
        $workspace = $this->createWorkspace();
        $activeKey = hash('sha256', 'active-invitation');
        $this->createInvitation($workspace, ['active_key' => $activeKey]);

        $this->expectException(QueryException::class);

        $this->createInvitation($workspace, [
            'email' => 'another@example.com',
            'token_hash' => hash('sha256', 'another-token'),
            'active_key' => $activeKey,
        ]);
    }

    public function test_multiple_null_active_keys_are_allowed(): void
    {
        $workspace = $this->createWorkspace();

        $first = $this->createInvitation($workspace);
        $second = $this->createInvitation($workspace, [
            'email' => 'second@example.com',
            'token_hash' => hash('sha256', 'second-null-active-key'),
        ]);

        $this->assertNull($first->active_key);
        $this->assertNull($second->active_key);
        $this->assertDatabaseCount('workspace_invitations', 2);
    }

    public function test_only_owner_has_team_permissions_and_policy_access(): void
    {
        $workspace = $this->createWorkspace();
        $owner = $this->attachUser($workspace, WorkspaceRole::Owner, 'owner@example.com');
        $admin = $this->attachUser($workspace, WorkspaceRole::Admin, 'admin@example.com');
        $member = $this->attachUser($workspace, WorkspaceRole::Member, 'member@example.com');
        $policy = $this->app->make(WorkspacePolicy::class);

        $this->assertTrue(WorkspaceRole::Owner->allows(WorkspacePermission::ViewTeam));
        $this->assertTrue(WorkspaceRole::Owner->allows(WorkspacePermission::ManageTeam));
        $this->assertFalse(WorkspaceRole::Admin->allows(WorkspacePermission::ViewTeam));
        $this->assertFalse(WorkspaceRole::Admin->allows(WorkspacePermission::ManageTeam));
        $this->assertFalse(WorkspaceRole::Member->allows(WorkspacePermission::ViewTeam));
        $this->assertFalse(WorkspaceRole::Member->allows(WorkspacePermission::ManageTeam));

        $this->assertTrue($policy->viewTeam($owner, $workspace));
        $this->assertTrue($policy->manageTeam($owner, $workspace));
        $this->assertFalse($policy->viewTeam($admin, $workspace));
        $this->assertFalse($policy->manageTeam($admin, $workspace));
        $this->assertFalse($policy->viewTeam($member, $workspace));
        $this->assertFalse($policy->manageTeam($member, $workspace));
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

    private function createWorkspace(): Workspace
    {
        return Workspace::query()->create([
            'name' => 'Invitation Workspace',
            'slug' => 'invitation-'.Str::uuid(),
        ]);
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'name' => 'Team User',
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
}
