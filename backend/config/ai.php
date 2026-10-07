<?php

$minimumSimilarity = env('RAG_RETRIEVAL_MIN_SIMILARITY');

return [
    'embeddings' => [
        'provider' => env('EMBEDDING_PROVIDER', 'cohere'),
        'dimensions' => (int) env('EMBEDDING_DIMENSION', 1024),
        'batch_size' => (int) env('EMBEDDING_BATCH_SIZE', 64),
    ],
    'retrieval' => [
        'driver' => env('RETRIEVER', 'pgvector'),
        'top_k' => (int) env('RAG_RETRIEVAL_TOP_K', 5),
        'minimum_similarity' => $minimumSimilarity === null || $minimumSimilarity === ''
            ? null
            : (float) $minimumSimilarity,
    ],
    'llm' => [
        'provider' => env('LLM_PROVIDER', 'groq'),
        'max_output_tokens' => (int) env('RAG_MAX_OUTPUT_TOKENS', 600),
        'temperature' => (float) env('RAG_TEMPERATURE', 0),
    ],
    'context' => [
        'max_chunks' => (int) env('RAG_CONTEXT_MAX_CHUNKS', 5),
        'max_characters' => (int) env('RAG_CONTEXT_MAX_CHARACTERS', 12000),
    ],
];
