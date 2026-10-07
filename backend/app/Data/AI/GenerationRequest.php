<?php

namespace App\Data\AI;

use InvalidArgumentException;

final readonly class GenerationRequest
{
    /**
     * @param  list<string>  $allowedSourceKeys
     */
    public function __construct(
        public string $systemPrompt,
        public string $userPrompt,
        public array $allowedSourceKeys,
        public GenerationOptions $options,
    ) {
        if (trim($systemPrompt) === '' || trim($userPrompt) === '') {
            throw new InvalidArgumentException('Generation prompts cannot be empty.');
        }

        if ($allowedSourceKeys === [] || count($allowedSourceKeys) !== count(array_unique($allowedSourceKeys))) {
            throw new InvalidArgumentException('Generation source keys must be non-empty and unique.');
        }

        foreach ($allowedSourceKeys as $key) {
            if (! is_string($key) || preg_match('/^source_[1-9][0-9]*$/', $key) !== 1) {
                throw new InvalidArgumentException('A generation source key is invalid.');
            }
        }
    }
}
