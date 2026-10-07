<?php

namespace App\Http\Controllers\Api;

use App\Actions\Team\GetWorkspaceInvitationPreview;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceInvitationPreviewResource;

class WorkspaceInvitationPreviewController extends Controller
{
    public function __invoke(
        string $token,
        GetWorkspaceInvitationPreview $getWorkspaceInvitationPreview,
    ): WorkspaceInvitationPreviewResource {
        return new WorkspaceInvitationPreviewResource(
            $getWorkspaceInvitationPreview->handle($token),
        );
    }
}
