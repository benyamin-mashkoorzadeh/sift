<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * @return list<WorkspacePermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => WorkspacePermission::cases(),
            self::Admin => [
                WorkspacePermission::ViewOverview,
                WorkspacePermission::UseAssistant,
                WorkspacePermission::ViewKnowledge,
                WorkspacePermission::ManageKnowledge,
                WorkspacePermission::ViewConversations,
                WorkspacePermission::ViewReview,
                WorkspacePermission::ResolveReview,
                WorkspacePermission::ViewSettings,
            ],
            self::Member => [
                WorkspacePermission::ViewOverview,
                WorkspacePermission::UseAssistant,
                WorkspacePermission::ViewReview,
                WorkspacePermission::ResolveReview,
            ],
        };
    }

    public function allows(WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
