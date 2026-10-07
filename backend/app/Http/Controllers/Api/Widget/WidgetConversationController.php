<?php

namespace App\Http\Controllers\Api\Widget;

use App\Actions\Widget\StartWidgetConversation;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicWidget;
use App\Http\Resources\Widget\WidgetConversationResource;
use App\Models\WorkspaceWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WidgetConversationController extends Controller
{
    public function __invoke(
        Request $request,
        StartWidgetConversation $startWidgetConversation,
    ): JsonResponse {
        /** @var WorkspaceWidget $widget */
        $widget = $request->attributes->get(ResolvePublicWidget::ATTRIBUTE);

        return (new WidgetConversationResource(
            $startWidgetConversation->handle($widget),
        ))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
