<?php

namespace App\Actions\Team;

use App\Data\Team\WorkspaceInvitationPreview;
use App\Enums\InvitationAccountState;
use App\Models\User;

class GetWorkspaceInvitationPreview
{
    public function __construct(
        private readonly ResolveActiveWorkspaceInvitation $resolveActiveWorkspaceInvitation,
    ) {}

    public function handle(string $rawToken): WorkspaceInvitationPreview
    {
        $invitation = $this->resolveActiveWorkspaceInvitation->handle($rawToken);
        $accountExists = User::query()->where('email', $invitation->email)->exists();

        return new WorkspaceInvitationPreview(
            invitation: $invitation,
            accountState: $accountExists
                ? InvitationAccountState::ExistingAccount
                : InvitationAccountState::NewAccount,
        );
    }
}
