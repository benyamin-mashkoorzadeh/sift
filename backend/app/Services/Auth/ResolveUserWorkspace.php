<?php

namespace App\Services\Auth;

use App\Data\Auth\AuthenticatedWorkspaceContext;
use App\Models\User;

class ResolveUserWorkspace
{
    public function __construct(
        private readonly ResolveEffectiveWorkspaceAccess $resolveEffectiveWorkspaceAccess,
    ) {}

    public function handle(User $user): AuthenticatedWorkspaceContext
    {
        return $this->resolveEffectiveWorkspaceAccess->forUser($user);
    }
}
