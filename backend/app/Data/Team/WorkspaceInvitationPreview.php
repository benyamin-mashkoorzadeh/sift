<?php

namespace App\Data\Team;

use App\Enums\InvitationAccountState;
use App\Models\WorkspaceInvitation;

final readonly class WorkspaceInvitationPreview
{
    public function __construct(
        public WorkspaceInvitation $invitation,
        public InvitationAccountState $accountState,
    ) {}
}
