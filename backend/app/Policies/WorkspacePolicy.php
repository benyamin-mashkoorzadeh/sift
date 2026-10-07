<?php

namespace App\Policies;

use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\ResolveEffectiveWorkspaceAccess;

class WorkspacePolicy
{
    public function __construct(
        private readonly ResolveEffectiveWorkspaceAccess $resolveEffectiveWorkspaceAccess,
    ) {}

    public function viewOverview(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ViewOverview);
    }

    public function useAssistant(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::UseAssistant);
    }

    public function viewKnowledge(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ViewKnowledge);
    }

    public function manageKnowledge(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ManageKnowledge);
    }

    public function viewConversations(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ViewConversations);
    }

    public function viewReview(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ViewReview);
    }

    public function resolveReview(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ResolveReview);
    }

    public function viewSettings(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ViewSettings);
    }

    public function updateSettings(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::UpdateSettings);
    }

    public function viewTeam(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ViewTeam);
    }

    public function manageTeam(User $user, Workspace $workspace): bool
    {
        return $this->allows($user, $workspace, WorkspacePermission::ManageTeam);
    }

    private function allows(User $user, Workspace $workspace, WorkspacePermission $permission): bool
    {
        return $this->resolveEffectiveWorkspaceAccess
            ->forWorkspace($user, $workspace)
            ?->allows($permission) ?? false;
    }
}
