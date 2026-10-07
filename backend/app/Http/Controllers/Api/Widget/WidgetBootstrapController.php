<?php

namespace App\Http\Controllers\Api\Widget;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicWidget;
use App\Http\Resources\Widget\WidgetBootstrapResource;
use App\Models\WorkspaceWidget;
use Illuminate\Http\Request;

class WidgetBootstrapController extends Controller
{
    public function __invoke(Request $request): WidgetBootstrapResource
    {
        /** @var WorkspaceWidget $widget */
        $widget = $request->attributes->get(ResolvePublicWidget::ATTRIBUTE);

        return new WidgetBootstrapResource($widget);
    }
}
