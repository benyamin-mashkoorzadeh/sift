<?php

namespace Tests\Feature\Api;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_only_the_requested_workspace_members_with_joined_at(): void
    {
        $workspace = $this->createWorkspace('team');
        $owner = $this->attachUser($workspace, WorkspaceRole::Owner, 'owner@example.com');
        $admin = $this->attachUser($workspace, WorkspaceRole::Admin, 'admin@example.com');
        $otherWorkspace = $this->createWorkspace('other');
        $this->attachUser($otherWorkspace, WorkspaceRole::Member, 'outsider@example.com');
        $joinedAt = now()->subDays(2)->startOfSecond();
        DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $admin->id)
            ->update(['created_at' => $joinedAt, 'updated_at' => $joinedAt]);

        $this->actingAs($owner)
            ->getJson(route('workspaces.team.members.index', $workspace))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => WorkspaceRole::Admin->value,
                'joined_at' => $joinedAt->toISOString(),
            ])
            ->assertJsonMissing(['email' => 'outsider@example.com'])
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_admin_and_member_cannot_list_team_members(): void
    {
        foreach ([WorkspaceRole::Admin, WorkspaceRole::Member] as $role) {
            $workspace = $this->createWorkspace('forbidden-'.$role->value);
            $user = $this->attachUser($workspace, $role, $role->value.'@example.com');

            $this->actingAs($user)
                ->getJson(route('workspaces.team.members.index', $workspace))
                ->assertForbidden();
        }
    }

    public function test_team_member_listing_requires_authentication_and_conceals_other_workspaces(): void
    {
        $workspace = $this->createWorkspace('protected');

        $this->getJson(route('workspaces.team.members.index', $workspace))
            ->assertUnauthorized();

        $ownedWorkspace = $this->createWorkspace('owned');
        $owner = $this->attachUser($ownedWorkspace, WorkspaceRole::Owner, 'owner-owned@example.com');

        $this->actingAs($owner)
            ->getJson(route('workspaces.team.members.index', $workspace))
            ->assertNotFound();
    }

    private function createWorkspace(string $suffix): Workspace
    {
        return Workspace::query()->create([
            'name' => str($suffix)->headline().' Workspace',
            'slug' => "team-{$suffix}-".Str::uuid(),
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
}
