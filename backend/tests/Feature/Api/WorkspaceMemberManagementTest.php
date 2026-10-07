<?php

namespace Tests\Feature\Api;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_changes_admin_to_member_and_permissions_update_immediately(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('demote');
        $admin = $this->attachUser($workspace, WorkspaceRole::Admin, 'admin@example.com');
        $joinedAt = now()->subMonth()->startOfSecond();
        $this->setJoinedAt($workspace, $admin, $joinedAt);
        $this->createSession('admin-role-change', $admin);

        $this->actingAs($owner)
            ->patchJson(route('workspaces.team.members.update', [$workspace, $admin]), [
                'role' => 'member',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.joined_at', $joinedAt->toISOString());

        $this->assertSame($joinedAt->toISOString(), $admin->workspaces()->sole()->pivot->created_at->toISOString());
        $this->assertDatabaseHas('sessions', ['id' => 'admin-role-change', 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.workspace.role', 'member')
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'assistant.use',
                'review.view',
                'review.resolve',
            ]);
        $this->getJson(route('workspaces.documents.index', $workspace))->assertForbidden();
        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))->assertForbidden();
        $this->getJson(route('workspaces.review-items.index', $workspace))->assertOk();
        $this->getJson(route('workspaces.settings.show', $workspace))->assertForbidden();
        $this->getJson(route('workspaces.team.members.index', $workspace))->assertForbidden();
    }

    public function test_owner_changes_member_to_admin_and_permissions_update_immediately(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('promote');
        $member = $this->attachUser($workspace, WorkspaceRole::Member, 'member@example.com');

        $this->actingAs($owner)
            ->patchJson(route('workspaces.team.members.update', [$workspace, $member]), [
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        $this->actingAs($member)
            ->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('data.workspace.role', 'admin')
            ->assertJsonPath('data.permissions', [
                'overview.view',
                'assistant.use',
                'knowledge.view',
                'knowledge.manage',
                'conversations.view',
                'review.view',
                'review.resolve',
                'settings.view',
            ]);
        $this->getJson(route('workspaces.documents.index', $workspace))->assertOk();
        $this->getJson(route('workspaces.assistant-interactions.index', $workspace))->assertOk();
        $this->getJson(route('workspaces.review-items.index', $workspace))->assertOk();
        $this->getJson(route('workspaces.settings.show', $workspace))->assertOk();
        $this->getJson(route('workspaces.team.members.index', $workspace))->assertForbidden();
    }

    public function test_owner_role_payload_is_rejected_and_owner_cannot_change_their_own_role(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('owner-protection');
        $member = $this->attachUser($workspace, WorkspaceRole::Member, 'member-protection@example.com');

        $this->actingAs($owner)
            ->patchJson(route('workspaces.team.members.update', [$workspace, $member]), [
                'role' => 'owner',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->actingAs($owner)
            ->patchJson(route('workspaces.team.members.update', [$workspace, $owner]), [
                'role' => 'member',
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'workspace_owner_protected');

        $this->assertSame(WorkspaceRole::Owner, $owner->workspaces()->sole()->pivot->role);
        $this->assertSame(WorkspaceRole::Member, $member->workspaces()->sole()->pivot->role);
    }

    public function test_admin_member_and_cross_workspace_targets_cannot_have_roles_changed(): void
    {
        foreach ([WorkspaceRole::Admin, WorkspaceRole::Member] as $role) {
            $workspace = $this->createWorkspace('forbidden-'.$role->value);
            $actor = $this->attachUser($workspace, $role, $role->value.'-actor@example.com');
            $target = $this->attachUser($workspace, WorkspaceRole::Member, $role->value.'-target@example.com');

            $this->actingAs($actor)
                ->patchJson(route('workspaces.team.members.update', [$workspace, $target]), ['role' => 'admin'])
                ->assertForbidden();
        }

        [$workspace, $owner] = $this->createOwnedWorkspace('owned');
        $otherWorkspace = $this->createWorkspace('other');
        $otherMember = $this->attachUser($otherWorkspace, WorkspaceRole::Member, 'other-target@example.com');

        $this->actingAs($owner)
            ->patchJson(route('workspaces.team.members.update', [$workspace, $otherMember]), ['role' => 'admin'])
            ->assertNotFound();
    }

    /**
     * @return array<string, array{WorkspaceRole}>
     */
    public static function removableRoleProvider(): array
    {
        return [
            'admin' => [WorkspaceRole::Admin],
            'member' => [WorkspaceRole::Member],
        ];
    }

    #[DataProvider('removableRoleProvider')]
    public function test_owner_removes_non_owner_preserves_user_and_invalidates_database_sessions(WorkspaceRole $role): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('remove-'.$role->value);
        $target = $this->attachUser($workspace, $role, $role->value.'-remove@example.com');
        $this->createSession('target-one-'.$role->value, $target);
        $this->createSession('target-two-'.$role->value, $target);
        $this->createSession('owner-'.$role->value, $owner);

        $this->actingAs($owner)
            ->deleteJson(route('workspaces.team.members.destroy', [$workspace, $target]))
            ->assertNoContent();

        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'email' => $target->email,
        ]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $owner->id]);

        $this->actingAs($target)
            ->getJson(route('workspaces.overview.show', $workspace))
            ->assertNotFound();
    }

    public function test_owner_cannot_remove_themselves(): void
    {
        [$workspace, $owner] = $this->createOwnedWorkspace('remove-owner');
        $this->createSession('owner-protected', $owner);

        $this->actingAs($owner)
            ->deleteJson(route('workspaces.team.members.destroy', [$workspace, $owner]))
            ->assertConflict()
            ->assertJsonPath('code', 'workspace_owner_protected');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('sessions', ['user_id' => $owner->id]);
    }

    public function test_admin_member_and_cross_workspace_targets_cannot_be_removed(): void
    {
        foreach ([WorkspaceRole::Admin, WorkspaceRole::Member] as $role) {
            $workspace = $this->createWorkspace('remove-forbidden-'.$role->value);
            $actor = $this->attachUser($workspace, $role, $role->value.'-remove-actor@example.com');
            $target = $this->attachUser($workspace, WorkspaceRole::Member, $role->value.'-remove-target@example.com');

            $this->actingAs($actor)
                ->deleteJson(route('workspaces.team.members.destroy', [$workspace, $target]))
                ->assertForbidden();
        }

        [$workspace, $owner] = $this->createOwnedWorkspace('remove-owned');
        $otherWorkspace = $this->createWorkspace('remove-other');
        $otherMember = $this->attachUser($otherWorkspace, WorkspaceRole::Member, 'remove-other-target@example.com');

        $this->actingAs($owner)
            ->deleteJson(route('workspaces.team.members.destroy', [$workspace, $otherMember]))
            ->assertNotFound();
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $otherMember->id,
        ]);
    }

    public function test_member_management_routes_require_authentication(): void
    {
        [$workspace, , $member] = $this->createWorkspaceWithOwnerAndMember('unauthenticated');

        $this->patchJson(route('workspaces.team.members.update', [$workspace, $member]), ['role' => 'admin'])
            ->assertUnauthorized();
        $this->deleteJson(route('workspaces.team.members.destroy', [$workspace, $member]))
            ->assertUnauthorized();
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

    /**
     * @return array{Workspace, User, User}
     */
    private function createWorkspaceWithOwnerAndMember(string $suffix): array
    {
        [$workspace, $owner] = $this->createOwnedWorkspace($suffix);
        $member = $this->attachUser($workspace, WorkspaceRole::Member, $suffix.'-member@example.com');

        return [$workspace, $owner, $member];
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => str($suffix)->headline().' Workspace',
            'slug' => "member-management-{$suffix}-".Str::uuid(),
        ]);
    }

    private function attachUser(Workspace $workspace, WorkspaceRole $role, string $email): User
    {
        $user = User::query()->create([
            'name' => str($role->value)->headline().' User',
            'email' => $email,
            'password' => 'StrongPassword123',
        ]);
        $workspace->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    private function setJoinedAt(Workspace $workspace, User $user, $joinedAt): void
    {
        DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->update(['created_at' => $joinedAt, 'updated_at' => $joinedAt]);
    }

    private function createSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Sift test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
    }
}
