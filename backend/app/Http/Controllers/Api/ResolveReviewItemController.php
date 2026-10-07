<?php

namespace App\Http\Controllers\Api;

use App\Actions\Review\ResolveReviewItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveReviewItemRequest;
use App\Http\Resources\ReviewItemResource;
use App\Models\ReviewItem;
use App\Models\Workspace;

class ResolveReviewItemController extends Controller
{
    public function __invoke(
        ResolveReviewItemRequest $request,
        Workspace $workspace,
        ReviewItem $reviewItem,
        ResolveReviewItem $resolveReviewItem,
    ): ReviewItemResource {
        return new ReviewItemResource($resolveReviewItem->handle(
            $workspace,
            $reviewItem,
            $request->validated('resolution'),
        ));
    }
}
