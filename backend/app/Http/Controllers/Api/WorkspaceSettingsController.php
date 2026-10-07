<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkspaceSettingsRequest;
use App\Http\Resources\WorkspaceSettingsResource;
use App\Models\Workspace;

class WorkspaceSettingsController extends Controller
{
    public function show(Workspace $workspace): WorkspaceSettingsResource
    {
        return new WorkspaceSettingsResource($workspace->load('widget'));
    }

    public function update(
        UpdateWorkspaceSettingsRequest $request,
        Workspace $workspace,
    ): WorkspaceSettingsResource {
        $workspace->name = $request->validated('name');
        $workspace->save();

        return new WorkspaceSettingsResource($workspace->refresh()->load('widget'));
    }
}
