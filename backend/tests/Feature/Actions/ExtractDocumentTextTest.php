<?php

namespace Tests\Feature\Actions;

use App\Actions\Documents\ExtractDocumentText;
use App\Contracts\Documents\PdfTextExtractor;
use App\Data\Documents\ExtractedDocumentText;
use App\Data\Documents\ExtractedPageText;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentExtractionException;
use App\Exceptions\Documents\InsufficientExtractableText;
use App\Infrastructure\Documents\SmalotPdfTextExtractor;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ExtractDocumentTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_extractor_contract_resolves_to_the_smalot_adapter(): void
    {
        $this->assertInstanceOf(
            SmalotPdfTextExtractor::class,
            $this->app->make(PdfTextExtractor::class),
        );
    }

    public function test_successful_extraction_updates_page_count_and_keeps_processing_status(): void
    {
        [$document, $action, $result] = $this->prepareExtraction([
            new ExtractedPageText(1, 'This is the first page with useful policy information.'),
            new ExtractedPageText(2, ''),
            new ExtractedPageText(3, 'This is the third physical page of the source document.'),
        ]);

        $extractedText = $action->handle($document);
        $document->refresh();

        $this->assertSame($result, $extractedText);
        $this->assertSame([1, 2, 3], array_column($extractedText->pages, 'pageNumber'));
        $this->assertSame('', $extractedText->pages[1]->text);
        $this->assertSame(3, $document->page_count);
        $this->assertSame(DocumentStatus::Processing, $document->status);
        $this->assertNull($document->processing_error);
        $this->assertNull($document->processed_at);
    }

    public function test_little_or_no_text_marks_the_document_failed_with_a_safe_error(): void
    {
        config()->set('documents.minimum_extracted_characters', 20);

        [$document, $action] = $this->prepareExtraction([
            new ExtractedPageText(1, 'tiny'),
            new ExtractedPageText(2, ''),
        ]);

        try {
            $action->handle($document);
            $this->fail('Insufficient text should stop document processing.');
        } catch (InsufficientExtractableText $exception) {
            $this->assertSame(InsufficientExtractableText::USER_MESSAGE, $exception->getMessage());
        }

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(InsufficientExtractableText::USER_MESSAGE, $document->processing_error);
        $this->assertNull($document->page_count);
        $this->assertNull($document->processed_at);
    }

    public function test_extractor_failures_mark_the_document_failed_without_persisting_vendor_details(): void
    {
        Storage::fake('documents');
        $document = $this->createStoredDocument();

        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldReceive('extract')
            ->once()
            ->andThrow(new DocumentExtractionException(
                DocumentExtractionException::USER_MESSAGE,
                previous: new \RuntimeException('Sensitive parser detail.'),
            ));
        $this->app->instance(PdfTextExtractor::class, $extractor);

        try {
            $this->app->make(ExtractDocumentText::class)->handle($document);
            $this->fail('An extraction error should stop document processing.');
        } catch (DocumentExtractionException $exception) {
            $this->assertSame(DocumentExtractionException::USER_MESSAGE, $exception->getMessage());
        }

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(DocumentExtractionException::USER_MESSAGE, $document->processing_error);
        $this->assertStringNotContainsString('Sensitive parser detail', $document->processing_error);
    }

    public function test_a_missing_stored_pdf_marks_the_document_failed(): void
    {
        Storage::fake('documents');
        $document = $this->createDocumentRecord();

        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldNotReceive('extract');
        $this->app->instance(PdfTextExtractor::class, $extractor);

        try {
            $this->app->make(ExtractDocumentText::class)->handle($document);
            $this->fail('A missing PDF should stop document processing.');
        } catch (DocumentExtractionException $exception) {
            $this->assertSame(DocumentExtractionException::USER_MESSAGE, $exception->getMessage());
        }

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(DocumentExtractionException::USER_MESSAGE, $document->processing_error);
        $this->assertNull($document->page_count);
    }

    /**
     * @param  list<ExtractedPageText>  $pages
     * @return array{Document, ExtractDocumentText, ExtractedDocumentText}
     */
    private function prepareExtraction(array $pages): array
    {
        Storage::fake('documents');
        config()->set('documents.minimum_extracted_characters', 10);

        $document = $this->createStoredDocument();
        $result = new ExtractedDocumentText($pages);
        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldReceive('extract')->once()->with('%PDF test contents')->andReturn($result);
        $this->app->instance(PdfTextExtractor::class, $extractor);

        return [$document, $this->app->make(ExtractDocumentText::class), $result];
    }

    private function createStoredDocument(): Document
    {
        $document = $this->createDocumentRecord();
        Storage::disk('documents')->put($document->storage_path, '%PDF test contents');

        return $document;
    }

    private function createDocumentRecord(): Document
    {
        $workspace = Workspace::query()->create([
            'name' => 'Extraction Test',
            'slug' => 'extraction-test-'.str()->random(8),
        ]);

        return $workspace->documents()->create([
            'original_filename' => 'policy.pdf',
            'storage_disk' => 'documents',
            'storage_path' => "workspaces/{$workspace->id}/documents/policy.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Processing,
        ]);
    }
}
