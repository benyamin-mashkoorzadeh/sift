<?php

namespace App\Actions\Auth;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use LogicException;

class ClaimExistingWorkspace
{
    public function handle(
        int $workspaceId,
        string $name,
        string $email,
        string $password,
    ): User {
        return DB::transaction(function () use ($workspaceId, $name, $email, $password): User {
            $workspace = Workspace::query()->lockForUpdate()->find($workspaceId);

            if ($workspace === null) {
                throw new LogicException('The workspace does not exist.');
            }

            if ($workspace->users()->exists()) {
                throw new LogicException('The workspace already has at least one membership.');
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw new LogicException('The email address is already in use.');
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $workspace->users()->attach($user->id, [
                'role' => WorkspaceRole::Owner->value,
            ]);

            return $user;
        });
    }
}
