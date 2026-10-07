<?php

namespace App\Http\Controllers\Api;

use App\Actions\Team\RegenerateWorkspaceInvitationLink;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceInvitationLinkResource;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;

class RegenerateWorkspaceInvitationLinkController extends Controller
{
    public function __invoke(
        Workspace $workspace,
        WorkspaceInvitation $invitation,
        RegenerateWorkspaceInvitationLink $regenerateWorkspaceInvitationLink,
    ): WorkspaceInvitationLinkResource {
        return new WorkspaceInvitationLinkResource(
            $regenerateWorkspaceInvitationLink->handle($workspace, $invitation),
        );
    }
}
