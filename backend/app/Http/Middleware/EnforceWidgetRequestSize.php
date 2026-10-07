<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceWidgetRequestSize
{
    public function handle(
        Request $request,
        Closure $next,
        string $configurationKey = 'widget.limits.maximum_request_bytes',
    ): Response {
        $maximumBytes = max(1, (int) config($configurationKey));
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > $maximumBytes || strlen($request->getContent()) > $maximumBytes) {
            return response()->json([
                'message' => 'Widget request is too large.',
                'code' => 'widget_request_too_large',
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }
}
