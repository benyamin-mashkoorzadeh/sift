<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMembership
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace || ! $user?->workspaces()->whereKey($workspace->getKey())->exists()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
