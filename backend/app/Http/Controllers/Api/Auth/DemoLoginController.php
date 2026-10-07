<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\EnterDemoMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\Request;

class DemoLoginController extends Controller
{
    public function __invoke(
        Request $request,
        EnterDemoMode $enterDemoMode,
    ): AuthenticatedUserResource {
        return new AuthenticatedUserResource($enterDemoMode->handle($request));
    }
}
