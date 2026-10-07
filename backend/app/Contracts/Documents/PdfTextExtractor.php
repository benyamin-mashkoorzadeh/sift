<?php

namespace App\Contracts\Documents;

use App\Data\Documents\ExtractedDocumentText;

interface PdfTextExtractor
{
    public function extract(string $pdfContents): ExtractedDocumentText;
}
