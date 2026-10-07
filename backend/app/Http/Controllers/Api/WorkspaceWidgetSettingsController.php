<?php

namespace App\Http\Controllers\Api;

use App\Actions\Widget\ProvisionWorkspaceWidget;
use App\Actions\Widget\SetWorkspaceWidgetEnabled;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkspaceWidgetRequest;
use App\Http\Resources\WorkspaceWidgetSettingsResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceWidgetSettingsController extends Controller
{
    public function store(
        Workspace $workspace,
        ProvisionWorkspaceWidget $provisionWorkspaceWidget,
    ): JsonResponse {
        $result = $provisionWorkspaceWidget->handle($workspace);

        return (new WorkspaceWidgetSettingsResource($result->widget))
            ->response()
            ->setStatusCode($result->created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function update(
        UpdateWorkspaceWidgetRequest $request,
        Workspace $workspace,
        SetWorkspaceWidgetEnabled $setWorkspaceWidgetEnabled,
    ): WorkspaceWidgetSettingsResource {
        return new WorkspaceWidgetSettingsResource(
            $setWorkspaceWidgetEnabled->handle(
                $workspace,
                $request->boolean('enabled'),
            ),
        );
    }
}
