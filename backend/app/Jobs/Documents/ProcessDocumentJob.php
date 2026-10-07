<?php

namespace App\Jobs\Documents;

use App\Actions\Documents\ProcessDocument;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentProcessingException;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $documentId)
    {
        $this->timeout = max(1, (int) config('documents.processing.timeout'));
        $this->onQueue((string) config('documents.processing.queue'));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("document-processing:{$this->documentId}"))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(ProcessDocument $processDocument): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $processDocument->handle($document);
    }

    public function failed(?Throwable $exception): void
    {
        Document::query()
            ->whereKey($this->documentId)
            ->where('status', DocumentStatus::Processing->value)
            ->update([
                'status' => DocumentStatus::Failed->value,
                'processing_error' => DocumentProcessingException::USER_MESSAGE,
                'processed_at' => null,
                'updated_at' => now(),
            ]);
    }
}
