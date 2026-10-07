<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexReviewItemsRequest;
use App\Http\Resources\ReviewItemResource;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewItemIndexController extends Controller
{
    public function __invoke(
        IndexReviewItemsRequest $request,
        Workspace $workspace,
    ): AnonymousResourceCollection {
        $status = ReviewItemStatus::from(
            $request->validated('status', ReviewItemStatus::Pending->value),
        );

        $reviewItems = $workspace->reviewItems()
            ->where('status', $status)
            ->when(
                $status === ReviewItemStatus::Pending,
                fn ($query) => $query->latest('last_asked_at'),
                fn ($query) => $query->latest('resolved_at'),
            )
            ->paginate(20)
            ->withQueryString();

        return ReviewItemResource::collection($reviewItems);
    }
}
