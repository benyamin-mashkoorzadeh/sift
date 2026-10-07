<?php

namespace Tests\Unit\Infrastructure\AI;

use App\Contracts\AI\EmbeddingProvider;
use App\Data\AI\EmbeddingInput;
use App\Data\AI\EmbeddingRequest;
use App\Enums\EmbeddingInputType;
use App\Exceptions\AI\EmbeddingProviderException;
use App\Infrastructure\AI\CohereEmbeddingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CohereEmbeddingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cohere.api_key', 'test-cohere-secret');
        config()->set('services.cohere.base_url', 'https://api.cohere.test');
        config()->set('services.cohere.embedding_model', 'embed-v4.0');
        config()->set('services.cohere.connect_timeout', 1);
        config()->set('services.cohere.timeout', 2);
        config()->set('services.cohere.max_attempts', 3);
        config()->set('services.cohere.retry_delay_ms', 0);
        config()->set('ai.embeddings.dimensions', 1024);

        Http::preventStrayRequests();
    }

    public function test_the_contract_resolves_to_the_cohere_adapter(): void
    {
        $this->assertInstanceOf(
            CohereEmbeddingProvider::class,
            $this->app->make(EmbeddingProvider::class),
        );
    }

    public function test_it_sends_document_inputs_and_preserves_their_keys(): void
    {
        Http::fake([
            'https://api.cohere.test/v2/embed' => Http::response([
                'embeddings' => [
                    'float' => [
                        array_fill(0, 1024, 0.125),
                        array_fill(0, 1024, -0.25),
                    ],
                ],
            ]),
        ]);

        $result = $this->provider()->embed($this->request([
            new EmbeddingInput('chunk-17', 'Returns are accepted within thirty days.'),
            new EmbeddingInput('chunk-29', 'Warranty coverage lasts for one year.'),
        ]));

        $this->assertSame('cohere', $result->provider);
        $this->assertSame('embed-v4.0', $result->model);
        $this->assertSame(1024, $result->dimensions);
        $this->assertSame(['chunk-17', 'chunk-29'], array_column($result->outputs, 'key'));
        $this->assertSame(0.125, $result->outputs[0]->vector[0]);
        $this->assertSame(-0.25, $result->outputs[1]->vector[1023]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.cohere.test/v2/embed'
                && $request->hasHeader('Authorization', 'Bearer test-cohere-secret')
                && $request->hasHeader('X-Client-Name', 'Sift')
                && $request['model'] === 'embed-v4.0'
                && $request['input_type'] === 'search_document'
                && $request['embedding_types'] === ['float']
                && $request['output_dimension'] === 1024
                && $request['truncate'] === 'NONE'
                && $request['texts'] === [
                    'Returns are accepted within thirty days.',
                    'Warranty coverage lasts for one year.',
                ];
        });
    }

    public function test_it_translates_the_query_purpose_to_search_query(): void
    {
        Http::fake([
            '*' => Http::response([
                'embeddings' => ['float' => [array_fill(0, 1024, 0.2)]],
            ]),
        ]);

        $result = $this->provider()->embed(new EmbeddingRequest(
            inputs: [new EmbeddingInput('query', 'What is the return policy?')],
            inputType: EmbeddingInputType::Query,
        ));

        $this->assertCount(1, $result->outputs);
        $this->assertCount(1024, $result->outputs[0]->vector);

        Http::assertSent(fn (Request $request): bool => $request['input_type'] === 'search_query'
            && $request['model'] === 'embed-v4.0'
            && $request['output_dimension'] === 1024);
    }

    public function test_it_rejects_an_output_count_mismatch(): void
    {
        Http::fake([
            '*' => Http::response([
                'embeddings' => ['float' => [array_fill(0, 1024, 0.1)]],
            ]),
        ]);

        $this->expectException(EmbeddingProviderException::class);

        $this->provider()->embed($this->request([
            new EmbeddingInput('one', 'First input.'),
            new EmbeddingInput('two', 'Second input.'),
        ]));
    }

    public function test_it_rejects_wrong_dimensions_and_non_numeric_values(): void
    {
        Http::fakeSequence()
            ->push(['embeddings' => ['float' => [array_fill(0, 1023, 0.1)]]])
            ->push(['embeddings' => ['float' => [array_merge(['not-a-number'], array_fill(0, 1023, 0.1))]]]);

        foreach (['wrong dimensions', 'non-numeric component'] as $case) {
            try {
                $this->provider()->embed($this->request([
                    new EmbeddingInput($case, 'Content to embed.'),
                ]));
                $this->fail("The provider should reject {$case}.");
            } catch (EmbeddingProviderException $exception) {
                $this->assertSame(EmbeddingProviderException::USER_MESSAGE, $exception->getMessage());
            }
        }
    }

    public function test_it_retries_transient_responses(): void
    {
        Http::fakeSequence()
            ->pushStatus(500)
            ->pushStatus(429)
            ->push([
                'embeddings' => ['float' => [array_fill(0, 1024, 0.5)]],
            ]);

        $result = $this->provider()->embed($this->request([
            new EmbeddingInput('retry', 'Retryable content.'),
        ]));

        $this->assertSame(0.5, $result->outputs[0]->vector[0]);
        Http::assertSentCount(3);
    }

    public function test_it_does_not_retry_or_expose_credentials_for_authentication_failures(): void
    {
        Http::fake(['*' => Http::response(['message' => 'invalid token'], 401)]);

        try {
            $this->provider()->embed($this->request([
                new EmbeddingInput('unauthorized', 'Unauthorized content.'),
            ]));
            $this->fail('An authentication failure should fail safely.');
        } catch (EmbeddingProviderException $exception) {
            $this->assertSame(EmbeddingProviderException::USER_MESSAGE, $exception->getMessage());
            $this->assertStringNotContainsString('test-cohere-secret', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    private function provider(): CohereEmbeddingProvider
    {
        return $this->app->make(CohereEmbeddingProvider::class);
    }

    /**
     * @param  list<EmbeddingInput>  $inputs
     */
    private function request(array $inputs): EmbeddingRequest
    {
        return new EmbeddingRequest($inputs, EmbeddingInputType::Document);
    }
}
