<?php

namespace App\Actions\Documents;

use App\Contracts\Documents\PdfTextExtractor;
use App\Data\Documents\ExtractedDocumentText;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentExtractionException;
use App\Exceptions\Documents\InsufficientExtractableText;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractDocumentText
{
    public function __construct(private readonly PdfTextExtractor $extractor) {}

    public function handle(Document $document): ExtractedDocumentText
    {
        try {
            $contents = Storage::disk($document->storage_disk)->get($document->storage_path);

            if (! is_string($contents) || $contents === '') {
                throw new DocumentExtractionException(DocumentExtractionException::USER_MESSAGE);
            }

            $extractedText = $this->extractor->extract($contents);
            $minimumCharacters = max(1, (int) config('documents.minimum_extracted_characters'));

            if ($extractedText->extractedCharacterCount() < $minimumCharacters) {
                throw new InsufficientExtractableText(InsufficientExtractableText::USER_MESSAGE);
            }

            $document->forceFill([
                'status' => DocumentStatus::Processing,
                'page_count' => $extractedText->pageCount(),
                'processing_error' => null,
                'processed_at' => null,
            ])->save();

            return $extractedText;
        } catch (InsufficientExtractableText $exception) {
            $this->markFailed($document, InsufficientExtractableText::USER_MESSAGE);

            throw $exception;
        } catch (Throwable $exception) {
            $failure = $exception instanceof DocumentExtractionException
                ? $exception
                : new DocumentExtractionException(
                    DocumentExtractionException::USER_MESSAGE,
                    previous: $exception,
                );

            $this->markFailed($document, DocumentExtractionException::USER_MESSAGE);
            report($exception);

            throw $failure;
        }
    }

    private function markFailed(Document $document, string $safeError): void
    {
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'page_count' => null,
            'processing_error' => $safeError,
            'processed_at' => null,
        ])->save();
    }
}
