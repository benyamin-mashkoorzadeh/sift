<?php

namespace App\Data\Auth;

use App\Models\User;
use App\Models\Workspace;

final readonly class ConfiguredDemoEnvironment
{
    public function __construct(
        public User $user,
        public Workspace $workspace,
    ) {}
}
