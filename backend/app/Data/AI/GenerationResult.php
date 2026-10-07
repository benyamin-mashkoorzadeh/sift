<?php

namespace App\Data\AI;

use App\Enums\GenerationDecision;
use InvalidArgumentException;

final readonly class GenerationResult
{
    /**
     * @param  list<string>  $sourceKeys
     */
    public function __construct(
        public GenerationDecision $decision,
        public ?string $answer,
        public array $sourceKeys,
        public string $provider,
        public string $model,
    ) {
        if (trim($provider) === '' || trim($model) === '') {
            throw new InvalidArgumentException('Generation provider metadata cannot be empty.');
        }

        if (count($sourceKeys) !== count(array_unique($sourceKeys))) {
            throw new InvalidArgumentException('Generated source keys must be unique.');
        }

        foreach ($sourceKeys as $key) {
            if (! is_string($key) || preg_match('/^source_[1-9][0-9]*$/', $key) !== 1) {
                throw new InvalidArgumentException('A generated source key is invalid.');
            }
        }

        if ($decision === GenerationDecision::Answered
            && (trim((string) $answer) === '' || $sourceKeys === [])) {
            throw new InvalidArgumentException('A grounded answer requires answer text and sources.');
        }

        if ($decision === GenerationDecision::Insufficient
            && ($answer !== null || $sourceKeys !== [])) {
            throw new InvalidArgumentException('An insufficient result cannot contain an answer or sources.');
        }
    }
}
