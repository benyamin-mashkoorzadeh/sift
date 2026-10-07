<?php

return [
    'disk' => env('DOCUMENTS_DISK', 'local'),
    'max_upload_kb' => (int) env('DOCUMENT_UPLOAD_MAX_KB', 20 * 1024),
    'minimum_extracted_characters' => (int) env('DOCUMENT_MIN_EXTRACTED_CHARACTERS', 50),
    'chunking' => [
        'size' => (int) env('RAG_CHUNK_SIZE', 1200),
        'overlap' => (int) env('RAG_CHUNK_OVERLAP', 200),
    ],
    'processing' => [
        'queue' => env('DOCUMENT_PROCESSING_QUEUE', 'documents'),
        'timeout' => (int) env('DOCUMENT_PROCESSING_TIMEOUT', 300),
    ],
];
