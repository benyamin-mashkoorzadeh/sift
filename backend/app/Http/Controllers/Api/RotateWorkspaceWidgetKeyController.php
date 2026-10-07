<?php

namespace App\Http\Controllers\Api;

use App\Actions\Widget\RotateWorkspaceWidgetKey;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceWidgetSettingsResource;
use App\Models\Workspace;

class RotateWorkspaceWidgetKeyController extends Controller
{
    public function __invoke(
        Workspace $workspace,
        RotateWorkspaceWidgetKey $rotateWorkspaceWidgetKey,
    ): WorkspaceWidgetSettingsResource {
        return new WorkspaceWidgetSettingsResource(
            $rotateWorkspaceWidgetKey->handle($workspace),
        );
    }
}
