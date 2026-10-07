<?php

namespace App\Actions\Team;

use App\Exceptions\Team\WorkspaceInvitationUnavailableException;
use App\Models\WorkspaceInvitation;
use App\Services\Team\WorkspaceInvitationTokenGenerator;

class ResolveActiveWorkspaceInvitation
{
    public function __construct(
        private readonly WorkspaceInvitationTokenGenerator $tokenGenerator,
    ) {}

    public function handle(string $rawToken): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token_hash', $this->tokenGenerator->hash($rawToken))
            ->first();

        if ($invitation === null || ! $this->isActive($invitation)) {
            throw new WorkspaceInvitationUnavailableException;
        }

        return $invitation;
    }

    private function isActive(WorkspaceInvitation $invitation): bool
    {
        return $invitation->accepted_at === null
            && $invitation->cancelled_at === null
            && $invitation->active_key !== null
            && $invitation->expires_at->isFuture();
    }
}
