<?php

namespace App\Actions\Team;

use App\Enums\WorkspaceRole;
use App\Exceptions\Team\WorkspaceInvitationConflictException;
use App\Exceptions\Team\WorkspaceInvitationUnavailableException;
use App\Exceptions\Team\WorkspaceInvitationWrongAccountException;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\Team\WorkspaceInvitationTokenGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptWorkspaceInvitation
{
    public function __construct(
        private readonly WorkspaceInvitationTokenGenerator $tokenGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $rawToken, ?User $authenticatedUser, array $attributes): User
    {
        $tokenHash = $this->tokenGenerator->hash($rawToken);

        try {
            $result = DB::transaction(function () use ($tokenHash, $authenticatedUser, $attributes): User|WorkspaceInvitationConflictException {
                $invitation = WorkspaceInvitation::query()
                    ->where('token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                if (
                    $invitation === null
                    || ! hash_equals($invitation->token_hash, $tokenHash)
                    || ! $this->isActive($invitation)
                    || ! in_array($invitation->role, [WorkspaceRole::Admin, WorkspaceRole::Member], true)
                ) {
                    throw new WorkspaceInvitationUnavailableException;
                }

                $user = $authenticatedUser === null
                    ? $this->resolveUnauthenticatedUser($invitation, $attributes)
                    : $this->resolveAuthenticatedUser($invitation, $authenticatedUser);

                $memberships = DB::table('workspace_user')
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->pluck('workspace_id');

                if ($memberships->isNotEmpty()) {
                    $invitation->update([
                        'cancelled_at' => now(),
                        'active_key' => null,
                    ]);

                    return $memberships->contains($invitation->workspace_id)
                        ? WorkspaceInvitationConflictException::alreadyMember()
                        : WorkspaceInvitationConflictException::incompatibleAccount();
                }

                $now = now();
                DB::table('workspace_user')->insert([
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->getKey(),
                    'role' => $invitation->role->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $invitation->update([
                    'accepted_by_user_id' => $user->getKey(),
                    'accepted_at' => $now,
                    'active_key' => null,
                ]);

                return $user;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw WorkspaceInvitationConflictException::concurrency();
            }

            throw $exception;
        }

        if ($result instanceof WorkspaceInvitationConflictException) {
            throw $result;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveUnauthenticatedUser(
        WorkspaceInvitation $invitation,
        array $attributes,
    ): User {
        $user = User::query()
            ->where('email', $invitation->email)
            ->lockForUpdate()
            ->first();

        if ($user !== null) {
            if (! Hash::check((string) ($attributes['password'] ?? ''), $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['The provided credentials are incorrect.'],
                ]);
            }

            return $user;
        }

        if (! isset($attributes['name'], $attributes['password'])) {
            throw WorkspaceInvitationConflictException::concurrency();
        }

        return User::query()->create([
            'name' => trim((string) $attributes['name']),
            'email' => Str::lower($invitation->email),
            'password' => $attributes['password'],
        ]);
    }

    private function resolveAuthenticatedUser(
        WorkspaceInvitation $invitation,
        User $authenticatedUser,
    ): User {
        $user = User::query()->lockForUpdate()->findOrFail($authenticatedUser->getKey());

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw new WorkspaceInvitationWrongAccountException;
        }

        return $user;
    }

    private function isActive(WorkspaceInvitation $invitation): bool
    {
        return $invitation->accepted_at === null
            && $invitation->cancelled_at === null
            && $invitation->active_key !== null
            && $invitation->expires_at->isFuture();
    }
}
