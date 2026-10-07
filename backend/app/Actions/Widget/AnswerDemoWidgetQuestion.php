<?php

namespace App\Actions\Widget;

use App\Data\Widget\WidgetAnswerResult;
use App\Enums\RagAnswerStatus;
use App\Exceptions\Widget\DemoWidgetUnavailableException;
use App\Services\AI\RagService;
use App\Services\Auth\DemoConfiguration;
use App\Services\Auth\ResolveConfiguredDemoEnvironment;
use Throwable;

class AnswerDemoWidgetQuestion
{
    public const NEEDS_REVIEW_ANSWER = "I couldn't find a reliable answer in the available knowledge.";

    public function __construct(
        private readonly RagService $ragService,
        private readonly DemoConfiguration $demoConfiguration,
        private readonly ResolveConfiguredDemoEnvironment $resolveConfiguredDemoEnvironment,
    ) {}

    public function handle(string $question): WidgetAnswerResult
    {
        if (! $this->demoConfiguration->widgetEnabled()) {
            throw new DemoWidgetUnavailableException(DemoWidgetUnavailableException::USER_MESSAGE);
        }

        try {
            $environment = $this->resolveConfiguredDemoEnvironment->handle();
            $result = $this->ragService->answer($environment->workspace, $question);

            return new WidgetAnswerResult(
                status: $result->status,
                answer: $result->status === RagAnswerStatus::NeedsReview
                    ? self::NEEDS_REVIEW_ANSWER
                    : $result->answer,
            );
        } catch (DemoWidgetUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DemoWidgetUnavailableException(
                DemoWidgetUnavailableException::USER_MESSAGE,
                previous: $exception,
            );
        }
    }
}
