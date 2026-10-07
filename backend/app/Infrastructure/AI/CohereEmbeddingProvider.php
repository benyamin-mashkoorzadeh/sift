<?php

namespace App\Infrastructure\AI;

use App\Contracts\AI\EmbeddingProvider;
use App\Data\AI\EmbeddingOutput;
use App\Data\AI\EmbeddingRequest;
use App\Data\AI\EmbeddingResult;
use App\Enums\EmbeddingInputType;
use App\Exceptions\AI\EmbeddingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Throwable;

class CohereEmbeddingProvider implements EmbeddingProvider
{
    private const MAX_TEXTS_PER_REQUEST = 96;

    public function __construct(private readonly Factory $http) {}

    public function embed(EmbeddingRequest $request): EmbeddingResult
    {
        $apiKey = (string) config('services.cohere.api_key');
        $model = (string) config('services.cohere.embedding_model');
        $dimensions = (int) config('ai.embeddings.dimensions');

        if ($apiKey === '' || $model === '' || $dimensions < 1) {
            throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
        }

        if (count($request->inputs) > self::MAX_TEXTS_PER_REQUEST) {
            throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
        }

        try {
            $response = $this->http
                ->baseUrl((string) config('services.cohere.base_url'))
                ->withToken($apiKey)
                ->withHeaders(['X-Client-Name' => 'Sift'])
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) config('services.cohere.connect_timeout'))
                ->timeout((int) config('services.cohere.timeout'))
                ->retry(
                    times: max(1, (int) config('services.cohere.max_attempts')),
                    sleepMilliseconds: fn (int $attempt): int => (int) config('services.cohere.retry_delay_ms') * $attempt,
                    when: fn (Throwable $exception): bool => $this->isRetryable($exception),
                    throw: false,
                )
                ->post('/v2/embed', [
                    'model' => $model,
                    'texts' => array_map(
                        static fn ($input): string => $input->text,
                        $request->inputs,
                    ),
                    'input_type' => $this->cohereInputType($request->inputType),
                    'embedding_types' => ['float'],
                    'output_dimension' => $dimensions,
                    'truncate' => 'NONE',
                ]);
        } catch (Throwable) {
            throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
        }

        if (! $response->successful()) {
            throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
        }

        $vectors = $response->json('embeddings.float');

        if (! is_array($vectors) || count($vectors) !== count($request->inputs)) {
            throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
        }

        $outputs = [];

        foreach ($request->inputs as $index => $input) {
            $vector = $vectors[$index] ?? null;

            if (! is_array($vector) || count($vector) !== $dimensions) {
                throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
            }

            $normalizedVector = [];

            foreach ($vector as $component) {
                if (! is_int($component) && ! is_float($component)) {
                    throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
                }

                $float = (float) $component;

                if (! is_finite($float)) {
                    throw new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE);
                }

                $normalizedVector[] = $float;
            }

            $outputs[] = new EmbeddingOutput($input->key, $normalizedVector);
        }

        return new EmbeddingResult(
            provider: 'cohere',
            model: $model,
            dimensions: $dimensions,
            outputs: $outputs,
        );
    }

    private function cohereInputType(EmbeddingInputType $inputType): string
    {
        return match ($inputType) {
            EmbeddingInputType::Document => 'search_document',
            EmbeddingInputType::Query => 'search_query',
        };
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
