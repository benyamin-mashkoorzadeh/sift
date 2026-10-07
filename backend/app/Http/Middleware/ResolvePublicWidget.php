<?php

namespace App\Http\Middleware;

use App\Exceptions\Widget\WidgetUnavailableException;
use App\Models\WorkspaceWidget;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicWidget
{
    public const ATTRIBUTE = 'sift.workspace_widget';

    public function handle(Request $request, Closure $next): Response
    {
        $widget = WorkspaceWidget::query()
            ->with('workspace')
            ->where('public_key', (string) $request->route('widgetKey'))
            ->where('enabled', true)
            ->first();

        if ($widget === null) {
            throw new WidgetUnavailableException(WidgetUnavailableException::USER_MESSAGE);
        }

        $request->attributes->set(self::ATTRIBUTE, $widget);

        return $next($request);
    }
}
