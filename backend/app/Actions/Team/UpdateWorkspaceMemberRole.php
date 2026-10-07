<?php

namespace App\Actions\Team;

use App\Enums\WorkspaceRole;
use App\Exceptions\Team\WorkspaceInvitationConflictException;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class UpdateWorkspaceMemberRole
{
    public function handle(Workspace $workspace, User $user, WorkspaceRole $role): User
    {
        DB::transaction(function () use ($workspace, $user, $role): void {
            $membership = DB::table('workspace_user')
                ->where('workspace_id', $workspace->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->role === WorkspaceRole::Owner->value) {
                throw WorkspaceInvitationConflictException::ownerProtected();
            }

            DB::table('workspace_user')
                ->where('workspace_id', $workspace->getKey())
                ->where('user_id', $user->getKey())
                ->update([
                    'role' => $role->value,
                    'updated_at' => now(),
                ]);
        });

        return $workspace->users()->whereKey($user->getKey())->firstOrFail();
    }
}
