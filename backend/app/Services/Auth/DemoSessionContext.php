<?php

namespace App\Services\Auth;

use App\Enums\WorkspaceAccessMode;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DemoSessionContext
{
    public const ACCESS_MODE_KEY = 'sift.access_mode';

    public const USER_ID_KEY = 'sift.demo_user_id';

    public const WORKSPACE_ID_KEY = 'sift.demo_workspace_id';

    public const RATE_LIMIT_ID_KEY = 'sift.demo_rate_limit_id';

    public function __construct(
        private readonly Request $request,
    ) {}

    public function isValidFor(User $user, Workspace $workspace): bool
    {
        if (! $this->request->hasSession()) {
            return false;
        }

        $session = $this->request->session();

        return $session->get(self::ACCESS_MODE_KEY) === WorkspaceAccessMode::Demo->value
            && $this->matchesIdentifier($session->get(self::USER_ID_KEY), $user->getKey())
            && $this->matchesIdentifier($session->get(self::WORKSPACE_ID_KEY), $workspace->getKey())
            && $this->hasRateLimitIdentifier($session->get(self::RATE_LIMIT_ID_KEY));
    }

    public function establish(User $user, Workspace $workspace): void
    {
        $this->request->session()->put([
            self::ACCESS_MODE_KEY => WorkspaceAccessMode::Demo->value,
            self::USER_ID_KEY => $user->getKey(),
            self::WORKSPACE_ID_KEY => $workspace->getKey(),
            self::RATE_LIMIT_ID_KEY => Str::random(40),
        ]);
    }

    public function rateLimitIdentifier(User $user, Workspace $workspace): ?string
    {
        if (! $this->isValidFor($user, $workspace)) {
            return null;
        }

        $identifier = $this->request->session()->get(self::RATE_LIMIT_ID_KEY);

        return is_string($identifier) && $identifier !== '' ? $identifier : null;
    }

    public function clear(): void
    {
        if (! $this->request->hasSession()) {
            return;
        }

        $this->request->session()->forget([
            self::ACCESS_MODE_KEY,
            self::USER_ID_KEY,
            self::WORKSPACE_ID_KEY,
            self::RATE_LIMIT_ID_KEY,
        ]);
    }

    private function matchesIdentifier(mixed $value, mixed $expected): bool
    {
        $sessionIdentifier = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $expectedIdentifier = filter_var($expected, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $sessionIdentifier !== false
            && $expectedIdentifier !== false
            && $sessionIdentifier === $expectedIdentifier;
    }

    private function hasRateLimitIdentifier(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
