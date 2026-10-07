<?php

namespace App\Actions\Overview;

use App\Enums\DocumentStatus;
use App\Enums\RagAnswerStatus;
use App\Enums\ReviewItemStatus;
use App\Models\Workspace;

class GetWorkspaceOverview
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Workspace $workspace): array
    {
        $documentCounts = $workspace->documents()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS ready', [DocumentStatus::Ready->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS processing', [DocumentStatus::Processing->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS failed', [DocumentStatus::Failed->value])
            ->toBase()
            ->first();

        $interactionCounts = $workspace->assistantInteractions()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS answered', [RagAnswerStatus::Answered->value])
            ->selectRaw('COUNT(*) FILTER (WHERE status = ?) AS needs_review', [RagAnswerStatus::NeedsReview->value])
            ->toBase()
            ->first();

        return [
            'workspace' => $workspace,
            'documents' => [
                'total' => (int) $documentCounts->total,
                'ready' => (int) $documentCounts->ready,
                'processing' => (int) $documentCounts->processing,
                'failed' => (int) $documentCounts->failed,
            ],
            'interactions' => [
                'total' => (int) $interactionCounts->total,
                'answered' => (int) $interactionCounts->answered,
                'needs_review' => (int) $interactionCounts->needs_review,
            ],
            'reviews' => [
                'pending' => $workspace->reviewItems()
                    ->where('status', ReviewItemStatus::Pending)
                    ->count(),
            ],
            'recent_documents' => $workspace->documents()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get([
                    'id',
                    'workspace_id',
                    'original_filename',
                    'status',
                    'page_count',
                    'size_bytes',
                    'created_at',
                ]),
            'recent_interactions' => $workspace->assistantInteractions()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get([
                    'id',
                    'workspace_id',
                    'question',
                    'status',
                    'created_at',
                ]),
        ];
    }
}
