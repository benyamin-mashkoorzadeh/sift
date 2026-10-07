<?php

namespace App\Actions\Review;

use App\Enums\ReviewItemStatus;
use App\Exceptions\Review\ReviewPersistenceException;
use App\Models\ReviewItem;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RecordNeedsReviewQuestion
{
    public function handle(Workspace $workspace, string $question): ReviewItem
    {
        $question = Str::squish($question);
        $deduplicationKey = hash('sha256', Str::lower($question));
        $now = now();

        try {
            return DB::transaction(function () use ($workspace, $question, $deduplicationKey, $now): ReviewItem {
                ReviewItem::query()->upsert([[
                    'workspace_id' => $workspace->getKey(),
                    'question' => $question,
                    'deduplication_key' => $deduplicationKey,
                    'status' => ReviewItemStatus::Pending->value,
                    'resolution' => null,
                    'last_asked_at' => $now,
                    'resolved_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['workspace_id', 'deduplication_key'], [
                    'last_asked_at',
                    'updated_at',
                ]);

                return ReviewItem::query()
                    ->whereBelongsTo($workspace)
                    ->where('deduplication_key', $deduplicationKey)
                    ->firstOrFail();
            });
        } catch (Throwable $exception) {
            report($exception);

            throw new ReviewPersistenceException(
                ReviewPersistenceException::USER_MESSAGE,
                previous: $exception,
            );
        }
    }
}
