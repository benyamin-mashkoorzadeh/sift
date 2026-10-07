<?php

namespace App\Services\Auth;

use App\Data\Auth\AuthenticatedWorkspaceContext;
use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Exceptions\Auth\WorkspaceContextException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;

class ResolveEffectiveWorkspaceAccess
{
    /**
     * @var list<WorkspacePermission>
     */
    private const DEMO_PERMISSIONS = [
        WorkspacePermission::ViewOverview,
        WorkspacePermission::ViewKnowledge,
        WorkspacePermission::UseAssistant,
        WorkspacePermission::ViewConversations,
        WorkspacePermission::ViewReview,
    ];

    public function __construct(
        private readonly DemoConfiguration $demoConfiguration,
        private readonly DemoSessionContext $demoSessionContext,
    ) {}

    public function forUser(User $user): AuthenticatedWorkspaceContext
    {
        $workspaces = $user->workspaces()->limit(2)->get();

        if ($workspaces->count() !== 1) {
            throw new WorkspaceContextException(WorkspaceContextException::USER_MESSAGE);
        }

        return $this->build($user, $workspaces->firstOrFail());
    }

    public function forWorkspace(User $user, Workspace $workspace): ?AuthenticatedWorkspaceContext
    {
        $membership = $user->workspaces()
            ->whereKey($workspace->getKey())
            ->first();

        return $membership === null ? null : $this->build($user, $membership);
    }

    private function build(User $user, Workspace $workspace): AuthenticatedWorkspaceContext
    {
        /** @var WorkspaceUser $membership */
        $membership = $workspace->pivot;
        $role = $membership->role;

        if (! $role instanceof WorkspaceRole) {
            throw new WorkspaceContextException(WorkspaceContextException::USER_MESSAGE);
        }

        [$accessMode, $permissions] = $this->resolvePermissions($user, $workspace, $role);

        return new AuthenticatedWorkspaceContext(
            user: $user,
            workspace: $workspace,
            role: $role,
            accessMode: $accessMode,
            permissions: $permissions,
        );
    }

    /**
     * @return array{WorkspaceAccessMode, list<WorkspacePermission>}
     */
    private function resolvePermissions(
        User $user,
        Workspace $workspace,
        WorkspaceRole $role,
    ): array {
        if (! $this->demoConfiguration->identifies($user)) {
            return [WorkspaceAccessMode::Normal, $role->permissions()];
        }

        $configuredWorkspaceId = $this->demoConfiguration->workspaceId();
        $validDemoContext = $this->demoConfiguration->enabled()
            && $configuredWorkspaceId !== null
            && $workspace->getKey() === $configuredWorkspaceId
            && $this->demoSessionContext->isValidFor($user, $workspace);

        return [
            WorkspaceAccessMode::Demo,
            $validDemoContext ? self::DEMO_PERMISSIONS : [],
        ];
    }
}
