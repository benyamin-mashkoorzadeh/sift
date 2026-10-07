<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceMemberIndexController extends Controller
{
    public function __invoke(Workspace $workspace): AnonymousResourceCollection
    {
        $members = $workspace->users()
            ->orderByPivot('created_at')
            ->orderBy('users.id')
            ->get();

        return WorkspaceMemberResource::collection($members);
    }
}
