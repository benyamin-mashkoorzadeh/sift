<?php

namespace App\Actions\Review;

use App\Enums\ReviewItemStatus;
use App\Exceptions\Review\ReviewItemAlreadyResolvedException;
use App\Models\ReviewItem;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class ResolveReviewItem
{
    public function handle(Workspace $workspace, ReviewItem $reviewItem, string $resolution): ReviewItem
    {
        return DB::transaction(function () use ($workspace, $reviewItem, $resolution): ReviewItem {
            $lockedItem = ReviewItem::query()
                ->whereBelongsTo($workspace)
                ->whereKey($reviewItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status === ReviewItemStatus::Resolved) {
                throw new ReviewItemAlreadyResolvedException;
            }

            $lockedItem->update([
                'status' => ReviewItemStatus::Resolved,
                'resolution' => trim($resolution),
                'deduplication_key' => null,
                'resolved_at' => now(),
            ]);

            return $lockedItem->refresh();
        });
    }
}
