<?php

namespace App\Actions\Team;

use App\Exceptions\Team\WorkspaceInvitationConflictException;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;

class CancelWorkspaceInvitation
{
    public function handle(Workspace $workspace, WorkspaceInvitation $invitation): void
    {
        DB::transaction(function () use ($workspace, $invitation): void {
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

            $lockedInvitation->update([
                'cancelled_at' => now(),
                'active_key' => null,
            ]);
        });
    }
}
