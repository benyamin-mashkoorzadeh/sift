<?php

namespace App\Services\Auth;

use App\Data\Auth\ConfiguredDemoEnvironment;
use App\Exceptions\Auth\DemoUnavailableException;
use App\Models\User;
use App\Models\Workspace;

class ResolveConfiguredDemoEnvironment
{
    public function __construct(
        private readonly DemoConfiguration $configuration,
    ) {}

    public function handle(): ConfiguredDemoEnvironment
    {
        $userId = $this->configuration->userId();
        $workspaceId = $this->configuration->workspaceId();

        if ($userId === null || $workspaceId === null) {
            throw new DemoUnavailableException(DemoUnavailableException::USER_MESSAGE);
        }

        $user = User::query()->find($userId);
        $workspace = Workspace::query()->find($workspaceId);

        if ($user === null || $workspace === null) {
            throw new DemoUnavailableException(DemoUnavailableException::USER_MESSAGE);
        }

        $workspaces = $user->workspaces()->limit(2)->get();

        if ($workspaces->count() !== 1 || ! $workspaces->firstOrFail()->is($workspace)) {
            throw new DemoUnavailableException(DemoUnavailableException::USER_MESSAGE);
        }

        return new ConfiguredDemoEnvironment($user, $workspace);
    }
}
