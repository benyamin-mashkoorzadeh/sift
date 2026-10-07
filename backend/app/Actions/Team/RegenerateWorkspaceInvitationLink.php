<?php

namespace App\Actions\Team;

use App\Data\Team\WorkspaceInvitationLink;
use App\Exceptions\Team\WorkspaceInvitationConflictException;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Team\WorkspaceInvitationTokenGenerator;
use Illuminate\Support\Facades\DB;
use LogicException;

class RegenerateWorkspaceInvitationLink
{
    public function __construct(
        private readonly WorkspaceInvitationTokenGenerator $tokenGenerator,
    ) {}

    public function handle(Workspace $workspace, WorkspaceInvitation $invitation): WorkspaceInvitationLink
    {
        return DB::transaction(function () use ($workspace, $invitation): WorkspaceInvitationLink {
            $lockedInvitation = WorkspaceInvitation::query()
                ->whereBelongsTo($workspace)
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->expires_at->isPast()) {
                throw WorkspaceInvitationConflictException::expired();
            }

            if (
                $lockedInvitation->accepted_at !== null
                || $lockedInvitation->cancelled_at !== null
                || $lockedInvitation->active_key === null
            ) {
                throw WorkspaceInvitationConflictException::inactive();
            }

            $ttlDays = (int) config('team.invitation_ttl_days');

            if ($ttlDays < 1) {
                throw new LogicException('Team invitation lifetime must be at least one day.');
            }

            $generatedToken = $this->tokenGenerator->generate();
            $lockedInvitation->update([
                'token_hash' => $generatedToken->hash,
                'expires_at' => now()->addDays($ttlDays),
            ]);

            return new WorkspaceInvitationLink($lockedInvitation->refresh(), $generatedToken->url);
        });
    }
}
