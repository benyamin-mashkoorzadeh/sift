<?php

namespace App\Data\Auth;

use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

final readonly class AuthenticatedWorkspaceContext
{
    /**
     * @param  list<WorkspacePermission>  $permissions
     */
    public function __construct(
        public User $user,
        public Workspace $workspace,
        public WorkspaceRole $role,
        public WorkspaceAccessMode $accessMode,
        public array $permissions,
    ) {}

    public function allows(WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
