<?php

namespace App\Actions\Auth;

use App\Data\Auth\AuthenticatedWorkspaceContext;
use App\Enums\WorkspaceAccessMode;
use App\Exceptions\Auth\DemoSessionConflictException;
use App\Exceptions\Auth\DemoUnavailableException;
use App\Services\Auth\DemoConfiguration;
use App\Services\Auth\DemoSessionContext;
use App\Services\Auth\ResolveConfiguredDemoEnvironment;
use App\Services\Auth\ResolveEffectiveWorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class EnterDemoMode
{
    public function __construct(
        private readonly DemoConfiguration $configuration,
        private readonly DemoSessionContext $demoSessionContext,
        private readonly ResolveConfiguredDemoEnvironment $resolveConfiguredDemoEnvironment,
        private readonly ResolveEffectiveWorkspaceAccess $resolveEffectiveWorkspaceAccess,
    ) {}

    public function handle(Request $request): AuthenticatedWorkspaceContext
    {
        if ($request->user() !== null) {
            throw new DemoSessionConflictException(DemoSessionConflictException::USER_MESSAGE);
        }

        if (! $this->configuration->enabled()) {
            throw new DemoUnavailableException(DemoUnavailableException::USER_MESSAGE);
        }

        $environment = $this->resolveConfiguredDemoEnvironment->handle();
        $user = $environment->user;
        $workspace = $environment->workspace;

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->demoSessionContext->establish($user, $workspace);

        try {
            $context = $this->resolveEffectiveWorkspaceAccess->forUser($user);

            if ($context->accessMode !== WorkspaceAccessMode::Demo || $context->permissions === []) {
                throw new DemoUnavailableException(DemoUnavailableException::USER_MESSAGE);
            }

            return $context;
        } catch (Throwable $exception) {
            Auth::guard('web')->logout();
            $this->demoSessionContext->clear();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if (! $exception instanceof DemoUnavailableException) {
                report($exception);
            }

            throw new DemoUnavailableException(
                DemoUnavailableException::USER_MESSAGE,
                previous: $exception,
            );
        }
    }
}
