<?php

namespace App\Http\Controllers\Api;

use App\Actions\Team\RemoveWorkspaceMember;
use App\Actions\Team\UpdateWorkspaceMemberRole;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkspaceMemberRoleRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceMemberController extends Controller
{
    public function update(
        UpdateWorkspaceMemberRoleRequest $request,
        Workspace $workspace,
        User $user,
        UpdateWorkspaceMemberRole $updateWorkspaceMemberRole,
    ): WorkspaceMemberResource {
        return new WorkspaceMemberResource($updateWorkspaceMemberRole->handle(
            $workspace,
            $user,
            WorkspaceRole::from($request->validated('role')),
        ));
    }

    public function destroy(
        Workspace $workspace,
        User $user,
        RemoveWorkspaceMember $removeWorkspaceMember,
    ): JsonResponse {
        $removeWorkspaceMember->handle($workspace, $user);

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
