<?php

namespace App\Data\AI;

use App\Enums\RagAnswerStatus;
use InvalidArgumentException;

final readonly class RagAnswerResult
{
    /**
     * @param  list<RagCitation>  $citations
     */
    public function __construct(
        public RagAnswerStatus $status,
        public string $answer,
        public array $citations,
        public ?string $provider,
        public ?string $model,
        public ?string $reason = null,
    ) {
        if (trim($answer) === '') {
            throw new InvalidArgumentException('A RAG result must contain response text.');
        }

        if ($status === RagAnswerStatus::Answered
            && ($citations === [] || trim((string) $provider) === '' || trim((string) $model) === '')) {
            throw new InvalidArgumentException('A grounded answer requires citations and provider metadata.');
        }

        if ($status === RagAnswerStatus::NeedsReview && $citations !== []) {
            throw new InvalidArgumentException('A Needs Review result cannot contain citations.');
        }
    }
}
