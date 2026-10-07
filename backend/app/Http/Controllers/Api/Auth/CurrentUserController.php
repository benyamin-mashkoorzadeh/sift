<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\Auth\ResolveUserWorkspace;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(
        Request $request,
        ResolveUserWorkspace $resolveUserWorkspace,
    ): AuthenticatedUserResource {
        return new AuthenticatedUserResource($resolveUserWorkspace->handle($request->user()));
    }
}
