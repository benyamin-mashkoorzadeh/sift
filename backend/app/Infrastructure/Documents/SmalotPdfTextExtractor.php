<?php

namespace App\Infrastructure\Documents;

use App\Contracts\Documents\PdfTextExtractor;
use App\Data\Documents\ExtractedDocumentText;
use App\Data\Documents\ExtractedPageText;
use App\Exceptions\Documents\DocumentExtractionException;
use Smalot\PdfParser\Parser;
use Throwable;

class SmalotPdfTextExtractor implements PdfTextExtractor
{
    public function __construct(private readonly Parser $parser) {}

    public function extract(string $pdfContents): ExtractedDocumentText
    {
        try {
            $pdf = $this->parser->parseContent($pdfContents);
            $pdfPages = $pdf->getPages();

            if ($pdfPages === []) {
                throw new DocumentExtractionException(DocumentExtractionException::USER_MESSAGE);
            }

            $pages = [];

            foreach (array_values($pdfPages) as $index => $page) {
                $pages[] = new ExtractedPageText(
                    pageNumber: $index + 1,
                    text: $this->normalizeText($page->getText()),
                );
            }

            return new ExtractedDocumentText($pages);
        } catch (DocumentExtractionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DocumentExtractionException(
                DocumentExtractionException::USER_MESSAGE,
                previous: $exception,
            );
        }
    }

    private function normalizeText(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace('/[ \t]+\n/u', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/\n{3,}/u', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }
}
