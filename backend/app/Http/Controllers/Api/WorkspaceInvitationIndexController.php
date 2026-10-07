<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceInvitationResource;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceInvitationIndexController extends Controller
{
    public function __invoke(Workspace $workspace): AnonymousResourceCollection
    {
        $invitations = $workspace->invitations()
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('active_key')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return WorkspaceInvitationResource::collection($invitations);
    }
}
