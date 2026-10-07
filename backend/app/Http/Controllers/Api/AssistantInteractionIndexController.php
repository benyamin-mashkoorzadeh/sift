<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAssistantInteractionsRequest;
use App\Http\Resources\AssistantInteractionResource;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssistantInteractionIndexController extends Controller
{
    public function __invoke(
        IndexAssistantInteractionsRequest $request,
        Workspace $workspace,
    ): AnonymousResourceCollection {
        $interactions = $workspace->assistantInteractions()
            ->with(['citations', 'reviewItem'])
            ->when(
                $request->validated('status'),
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return AssistantInteractionResource::collection($interactions);
    }
}
