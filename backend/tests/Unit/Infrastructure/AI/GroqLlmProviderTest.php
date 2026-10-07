<?php

namespace Tests\Unit\Infrastructure\AI;

use App\Contracts\AI\LlmProvider;
use App\Data\AI\GenerationOptions;
use App\Data\AI\GenerationRequest;
use App\Enums\GenerationDecision;
use App\Exceptions\AI\LlmProviderException;
use App\Infrastructure\AI\GroqLlmProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqLlmProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.llm.provider', 'groq');
        config()->set('services.groq.api_key', 'test-groq-secret');
        config()->set('services.groq.base_url', 'https://api.groq.test/openai/v1');
        config()->set('services.groq.model', 'openai/gpt-oss-20b');
        config()->set('services.groq.connect_timeout', 1);
        config()->set('services.groq.timeout', 2);
        config()->set('services.groq.max_attempts', 3);
        config()->set('services.groq.retry_delay_ms', 0);
        config()->set('services.groq.reasoning_effort', 'low');

        Http::preventStrayRequests();
    }

    public function test_the_contract_resolves_to_the_groq_adapter(): void
    {
        $this->assertInstanceOf(GroqLlmProvider::class, $this->app->make(LlmProvider::class));
    }

    public function test_it_requests_a_strict_grounded_answer_and_maps_the_result(): void
    {
        Http::fake([
            'https://api.groq.test/openai/v1/chat/completions' => Http::response([
                'model' => 'openai/gpt-oss-20b',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'decision' => 'answered',
                            'answer' => 'Unused products may be returned within 30 days.',
                            'source_keys' => ['source_2', 'source_1'],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $result = $this->provider()->generate($this->request());

        $this->assertSame(GenerationDecision::Answered, $result->decision);
        $this->assertSame('Unused products may be returned within 30 days.', $result->answer);
        $this->assertSame(['source_2', 'source_1'], $result->sourceKeys);
        $this->assertSame('groq', $result->provider);
        $this->assertSame('openai/gpt-oss-20b', $result->model);

        Http::assertSent(function (Request $request): bool {
            $schema = $request['response_format']['json_schema'];

            return $request->url() === 'https://api.groq.test/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-groq-secret')
                && $request->hasHeader('X-Client-Name', 'Sift')
                && $request['model'] === 'openai/gpt-oss-20b'
                && $request['temperature'] === 0.0
                && $request['max_completion_tokens'] === 600
                && $request['reasoning_effort'] === 'low'
                && $request['citation_options'] === 'disabled'
                && $request['stream'] === false
                && $request['messages'][0]['role'] === 'system'
                && $request['messages'][1]['role'] === 'user'
                && $schema['strict'] === true
                && $schema['schema']['additionalProperties'] === false
                && $schema['schema']['properties']['source_keys']['items']['enum'] === ['source_1', 'source_2'];
        });
    }

    public function test_it_maps_a_structured_insufficient_result(): void
    {
        Http::fake(['*' => Http::response([
            'model' => 'openai/gpt-oss-20b',
            'choices' => [[
                'message' => ['content' => '{"decision":"insufficient","answer":null,"source_keys":[]}'],
            ]],
        ])]);

        $result = $this->provider()->generate($this->request());

        $this->assertSame(GenerationDecision::Insufficient, $result->decision);
        $this->assertNull($result->answer);
        $this->assertSame([], $result->sourceKeys);
    }

    public function test_it_rejects_unknown_sources_and_invalid_response_shapes(): void
    {
        Http::fakeSequence()
            ->push([
                'model' => 'openai/gpt-oss-20b',
                'choices' => [[
                    'message' => ['content' => '{"decision":"answered","answer":"Unsupported.","source_keys":["source_99"]}'],
                ]],
            ])
            ->push([
                'model' => 'openai/gpt-oss-20b',
                'choices' => [[
                    'message' => ['content' => 'not-json'],
                ]],
            ]);

        foreach (['unknown source', 'invalid JSON'] as $case) {
            try {
                $this->provider()->generate($this->request());
                $this->fail("The provider should reject {$case}.");
            } catch (LlmProviderException $exception) {
                $this->assertSame(LlmProviderException::USER_MESSAGE, $exception->getMessage());
            }
        }
    }

    public function test_it_retries_transient_failures(): void
    {
        Http::fakeSequence()
            ->pushStatus(500)
            ->pushStatus(429)
            ->push([
                'model' => 'openai/gpt-oss-20b',
                'choices' => [[
                    'message' => ['content' => '{"decision":"insufficient","answer":null,"source_keys":[]}'],
                ]],
            ]);

        $this->provider()->generate($this->request());

        Http::assertSentCount(3);
    }

    public function test_it_does_not_retry_or_expose_credentials_for_authentication_failures(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'invalid key']], 401)]);

        try {
            $this->provider()->generate($this->request());
            $this->fail('An authentication failure should fail safely.');
        } catch (LlmProviderException $exception) {
            $this->assertSame(LlmProviderException::USER_MESSAGE, $exception->getMessage());
            $this->assertStringNotContainsString('test-groq-secret', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    private function provider(): GroqLlmProvider
    {
        return $this->app->make(GroqLlmProvider::class);
    }

    private function request(): GenerationRequest
    {
        return new GenerationRequest(
            systemPrompt: 'Use only supplied evidence.',
            userPrompt: '{"question":"What is the return period?","untrusted_sources":[]}',
            allowedSourceKeys: ['source_1', 'source_2'],
            options: new GenerationOptions(600, 0.0),
        );
    }
}
