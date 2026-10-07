<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\RegisterCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\Auth\DemoSessionContext;
use App\Services\Auth\ResolveUserWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        RegisterCompany $registerCompany,
        DemoSessionContext $demoSessionContext,
        ResolveUserWorkspace $resolveUserWorkspace,
    ): JsonResponse {
        $user = $registerCompany->handle($request->validated());

        Auth::guard('web')->login($user);
        $demoSessionContext->clear();
        $request->session()->regenerate();

        return (new AuthenticatedUserResource($resolveUserWorkspace->handle($user)))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
