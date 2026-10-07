<?php

namespace Tests\Feature\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaimWorkspaceOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_creates_the_first_owner_only_after_explicit_confirmation(): void
    {
        $workspace = $this->createWorkspace('unclaimed');

        $this->artisan('sift:claim-workspace', ['workspace' => $workspace->id])
            ->expectsQuestion('Owner name', 'Legacy Owner')
            ->expectsQuestion('Owner email', 'OWNER@EXAMPLE.COM')
            ->expectsQuestion('Owner password', 'Sift123a')
            ->expectsQuestion('Confirm owner password', 'Sift123a')
            ->expectsConfirmation("Create this owner for workspace {$workspace->id}?", 'yes')
            ->expectsOutput("Workspace {$workspace->id} now has an owner.")
            ->assertSuccessful();

        $user = User::query()->sole();
        $membership = $workspace->fresh()->users()->sole();

        $this->assertSame('owner@example.com', $user->email);
        $this->assertTrue(Hash::check('Sift123a', $user->password));
        $this->assertSame($user->id, $membership->id);
        $this->assertSame(WorkspaceRole::Owner, $membership->pivot->role);
    }

    public function test_cancelling_the_command_leaves_the_workspace_untouched(): void
    {
        $workspace = $this->createWorkspace('cancelled');

        $this->artisan('sift:claim-workspace', ['workspace' => $workspace->id])
            ->expectsQuestion('Owner name', 'Legacy Owner')
            ->expectsQuestion('Owner email', 'owner@example.com')
            ->expectsQuestion('Owner password', 'Sift123a')
            ->expectsQuestion('Confirm owner password', 'Sift123a')
            ->expectsConfirmation("Create this owner for workspace {$workspace->id}?", 'no')
            ->expectsOutput('No changes were made.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('workspace_user', 0);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_the_command_refuses_to_change_a_workspace_that_already_has_a_membership(): void
    {
        $workspace = $this->createWorkspace('claimed');
        $existingUser = User::query()->create([
            'name' => 'Existing Owner',
            'email' => 'existing@example.com',
            'password' => 'StrongPassword123',
        ]);
        $workspace->users()->attach($existingUser->id, ['role' => WorkspaceRole::Owner->value]);

        $this->artisan('sift:claim-workspace', ['workspace' => $workspace->id])
            ->expectsQuestion('Owner name', 'Second Owner')
            ->expectsQuestion('Owner email', 'second@example.com')
            ->expectsQuestion('Owner password', 'Sift123a')
            ->expectsQuestion('Confirm owner password', 'Sift123a')
            ->expectsConfirmation("Create this owner for workspace {$workspace->id}?", 'yes')
            ->expectsOutput('The workspace already has at least one membership.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('workspace_user', 1);
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    private function createWorkspace(string $slug): Workspace
    {
        return Workspace::query()->create([
            'name' => str($slug)->headline()->toString(),
            'slug' => "claim-{$slug}",
        ]);
    }
}
