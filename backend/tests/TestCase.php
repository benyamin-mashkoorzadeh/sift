<?php

namespace Tests;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsWorkspaceMember(
        Workspace $workspace,
        WorkspaceRole $role = WorkspaceRole::Owner,
    ): User {
        $user = User::query()->create([
            'name' => 'Workspace User',
            'email' => Str::uuid().'@example.com',
            'password' => 'StrongPassword123',
        ]);
        $workspace->users()->attach($user->id, ['role' => $role->value]);
        $this->actingAs($user);

        return $user;
    }
}
