<?php

namespace App\Data\Team;

use App\Models\WorkspaceInvitation;

final readonly class WorkspaceInvitationLink
{
    public function __construct(
        public WorkspaceInvitation $invitation,
        public string $url,
    ) {}
}
