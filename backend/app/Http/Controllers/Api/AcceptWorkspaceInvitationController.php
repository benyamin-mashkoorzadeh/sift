<?php

namespace App\Http\Controllers\Api;

use App\Actions\Team\AcceptWorkspaceInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptWorkspaceInvitationRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\Auth\ResolveUserWorkspace;
use Illuminate\Support\Facades\Auth;

class AcceptWorkspaceInvitationController extends Controller
{
    public function __invoke(
        AcceptWorkspaceInvitationRequest $request,
        string $token,
        AcceptWorkspaceInvitation $acceptWorkspaceInvitation,
        ResolveUserWorkspace $resolveUserWorkspace,
    ): AuthenticatedUserResource {
        $user = $acceptWorkspaceInvitation->handle(
            $token,
            $request->user(),
            $request->validated(),
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new AuthenticatedUserResource($resolveUserWorkspace->handle($user));
    }
}
