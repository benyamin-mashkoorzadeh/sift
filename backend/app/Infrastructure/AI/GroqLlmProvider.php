<?php

namespace App\Infrastructure\AI;

use App\Contracts\AI\LlmProvider;
use App\Data\AI\GenerationRequest;
use App\Data\AI\GenerationResult;
use App\Enums\GenerationDecision;
use App\Exceptions\AI\LlmProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use JsonException;
use Throwable;

class GroqLlmProvider implements LlmProvider
{
    public function __construct(private readonly Factory $http) {}

    public function generate(GenerationRequest $request): GenerationResult
    {
        $apiKey = (string) config('services.groq.api_key');
        $model = (string) config('services.groq.model');

        if ($apiKey === '' || $model === '') {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $request->systemPrompt],
                ['role' => 'user', 'content' => $request->userPrompt],
            ],
            'temperature' => $request->options->temperature,
            'max_completion_tokens' => $request->options->maxOutputTokens,
            'reasoning_effort' => (string) config('services.groq.reasoning_effort'),
            'citation_options' => 'disabled',
            'stream' => false,
            'response_format' => $this->responseFormat($request->allowedSourceKeys),
        ];

        try {
            $response = $this->http
                ->baseUrl((string) config('services.groq.base_url'))
                ->withToken($apiKey)
                ->withHeaders(['X-Client-Name' => 'Sift'])
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) config('services.groq.connect_timeout'))
                ->timeout((int) config('services.groq.timeout'))
                ->retry(
                    times: max(1, (int) config('services.groq.max_attempts')),
                    sleepMilliseconds: fn (int $attempt): int => (int) config('services.groq.retry_delay_ms') * $attempt,
                    when: fn (Throwable $exception): bool => $this->isRetryable($exception),
                    throw: false,
                )
                ->post('/chat/completions', $payload);
        } catch (Throwable) {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        if (! $response->successful()) {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        $content = $response->json('choices.0.message.content');
        $resolvedModel = $response->json('model');

        if (! is_string($content) || trim($content) === '' || ! is_string($resolvedModel) || trim($resolvedModel) === '') {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        return $this->resultFromResponse($decoded, $request, $resolvedModel);
    }

    /**
     * @param  list<string>  $allowedSourceKeys
     * @return array<string, mixed>
     */
    private function responseFormat(array $allowedSourceKeys): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'grounded_answer',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'decision' => [
                            'type' => 'string',
                            'enum' => array_column(GenerationDecision::cases(), 'value'),
                        ],
                        'answer' => [
                            'anyOf' => [
                                ['type' => 'string'],
                                ['type' => 'null'],
                            ],
                        ],
                        'source_keys' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                                'enum' => $allowedSourceKeys,
                            ],
                        ],
                    ],
                    'required' => ['decision', 'answer', 'source_keys'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function resultFromResponse(mixed $decoded, GenerationRequest $request, string $model): GenerationResult
    {
        if (! is_array($decoded)
            || ! is_string($decoded['decision'] ?? null)
            || ! array_key_exists('answer', $decoded)
            || ! is_array($decoded['source_keys'] ?? null)) {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        $decision = GenerationDecision::tryFrom($decoded['decision']);
        $answer = $decoded['answer'];
        $sourceKeys = $decoded['source_keys'];

        if ($decision === null
            || ($answer !== null && ! is_string($answer))
            || array_filter($sourceKeys, static fn ($key): bool => ! is_string($key)) !== []) {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        if (count($sourceKeys) !== count(array_unique($sourceKeys))
            || array_diff($sourceKeys, $request->allowedSourceKeys) !== []) {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        if ($decision === GenerationDecision::Answered) {
            $answer = trim((string) $answer);

            if ($answer === '' || $sourceKeys === []) {
                throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
            }
        } elseif ($answer !== null || $sourceKeys !== []) {
            throw new LlmProviderException(LlmProviderException::USER_MESSAGE);
        }

        return new GenerationResult(
            decision: $decision,
            answer: $answer,
            sourceKeys: array_values($sourceKeys),
            provider: 'groq',
            model: $model,
        );
    }

    private function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        return $exception->response->status() === 429
            || $exception->response->serverError();
    }
}
