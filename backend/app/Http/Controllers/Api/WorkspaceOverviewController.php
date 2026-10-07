<?php

namespace App\Http\Controllers\Api;

use App\Actions\Overview\GetWorkspaceOverview;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceOverviewResource;
use App\Models\Workspace;

class WorkspaceOverviewController extends Controller
{
    public function __invoke(
        Workspace $workspace,
        GetWorkspaceOverview $getWorkspaceOverview,
    ): WorkspaceOverviewResource {
        return new WorkspaceOverviewResource($getWorkspaceOverview->handle($workspace));
    }
}
