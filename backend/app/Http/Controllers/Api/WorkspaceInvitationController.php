<?php

namespace App\Http\Controllers\Api;

use App\Actions\Team\CancelWorkspaceInvitation;
use App\Actions\Team\CreateWorkspaceInvitation;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkspaceInvitationRequest;
use App\Http\Resources\WorkspaceInvitationLinkResource;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceInvitationController extends Controller
{
    public function store(
        StoreWorkspaceInvitationRequest $request,
        Workspace $workspace,
        CreateWorkspaceInvitation $createWorkspaceInvitation,
    ): JsonResponse {
        $link = $createWorkspaceInvitation->handle(
            $workspace,
            $request->user(),
            $request->validated('email'),
            WorkspaceRole::from($request->validated('role')),
        );

        return (new WorkspaceInvitationLinkResource($link))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(
        Workspace $workspace,
        WorkspaceInvitation $invitation,
        CancelWorkspaceInvitation $cancelWorkspaceInvitation,
    ): JsonResponse {
        $cancelWorkspaceInvitation->handle($workspace, $invitation);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
