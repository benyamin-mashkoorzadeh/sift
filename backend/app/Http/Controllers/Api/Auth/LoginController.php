<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\Auth\WorkspaceContextException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use App\Services\Auth\DemoConfiguration;
use App\Services\Auth\DemoSessionContext;
use App\Services\Auth\ResolveUserWorkspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request,
        DemoConfiguration $demoConfiguration,
        DemoSessionContext $demoSessionContext,
        ResolveUserWorkspace $resolveUserWorkspace,
    ): AuthenticatedUserResource {
        $requestedUser = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if ($requestedUser !== null && $demoConfiguration->identifies($requestedUser)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! Auth::guard('web')->attempt($request->safe()->only(['email', 'password']))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $demoSessionContext->clear();
        $request->session()->regenerate();
        $user = $request->user();

        try {
            $context = $resolveUserWorkspace->handle($user);
        } catch (WorkspaceContextException $exception) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw $exception;
        }

        return new AuthenticatedUserResource($context);
    }
}
