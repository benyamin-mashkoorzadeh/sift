<?php

namespace App\Actions\Team;

use App\Data\Team\WorkspaceInvitationLink;
use App\Enums\WorkspaceRole;
use App\Exceptions\Team\WorkspaceInvitationConflictException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Team\WorkspaceInvitationTokenGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class CreateWorkspaceInvitation
{
    public function __construct(
        private readonly WorkspaceInvitationTokenGenerator $tokenGenerator,
    ) {}

    public function handle(
        Workspace $workspace,
        User $inviter,
        string $email,
        WorkspaceRole $role,
    ): WorkspaceInvitationLink {
        $email = Str::lower(trim($email));
        $activeKey = hash('sha256', $workspace->getKey().'|'.$email);

        try {
            return DB::transaction(function () use ($workspace, $inviter, $email, $role, $activeKey): WorkspaceInvitationLink {
                $existingUser = User::query()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                if ($existingUser !== null) {
                    $workspaceIds = $existingUser->workspaces()->pluck('workspaces.id');

                    if ($workspaceIds->contains($workspace->getKey())) {
                        throw WorkspaceInvitationConflictException::alreadyMember();
                    }

                    if ($workspaceIds->isNotEmpty()) {
                        throw WorkspaceInvitationConflictException::incompatibleAccount();
                    }
                }

                $existingInvitation = WorkspaceInvitation::query()
                    ->where('active_key', $activeKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingInvitation !== null) {
                    if ($existingInvitation->expires_at->isFuture()) {
                        throw WorkspaceInvitationConflictException::duplicate();
                    }

                    $existingInvitation->update(['active_key' => null]);
                }

                $ttlDays = (int) config('team.invitation_ttl_days');

                if ($ttlDays < 1) {
                    throw new LogicException('Team invitation lifetime must be at least one day.');
                }

                $generatedToken = $this->tokenGenerator->generate();
                $invitation = WorkspaceInvitation::query()->create([
                    'workspace_id' => $workspace->getKey(),
                    'invited_by_user_id' => $inviter->getKey(),
                    'email' => $email,
                    'role' => $role,
                    'token_hash' => $generatedToken->hash,
                    'active_key' => $activeKey,
                    'expires_at' => now()->addDays($ttlDays),
                ]);

                return new WorkspaceInvitationLink($invitation, $generatedToken->url);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw WorkspaceInvitationConflictException::duplicate();
            }

            throw $exception;
        }
    }
}
