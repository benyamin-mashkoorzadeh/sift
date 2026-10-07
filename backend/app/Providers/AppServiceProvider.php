<?php

namespace App\Providers;

use App\Contracts\AI\EmbeddingProvider;
use App\Contracts\AI\LlmProvider;
use App\Contracts\AI\Retriever;
use App\Contracts\Documents\PdfTextExtractor;
use App\Contracts\Documents\TextChunker;
use App\Data\Auth\AuthenticatedWorkspaceContext;
use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspacePermission;
use App\Infrastructure\AI\CohereEmbeddingProvider;
use App\Infrastructure\AI\GroqLlmProvider;
use App\Infrastructure\AI\PgVectorRetriever;
use App\Infrastructure\Documents\SmalotPdfTextExtractor;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;
use App\Services\Auth\DemoConfiguration;
use App\Services\Auth\DemoSessionContext;
use App\Services\Auth\ResolveEffectiveWorkspaceAccess;
use App\Services\Documents\PageAwareTextChunker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmbeddingProvider::class, function ($app): EmbeddingProvider {
            return match (config('ai.embeddings.provider')) {
                'cohere' => $app->make(CohereEmbeddingProvider::class),
                default => throw new \LogicException('Unsupported embedding provider.'),
            };
        });
        $this->app->bind(Retriever::class, function ($app): Retriever {
            return match (config('ai.retrieval.driver')) {
                'pgvector' => $app->make(PgVectorRetriever::class),
                default => throw new \LogicException('Unsupported retrieval driver.'),
            };
        });
        $this->app->bind(LlmProvider::class, function ($app): LlmProvider {
            return match (config('ai.llm.provider')) {
                'groq' => $app->make(GroqLlmProvider::class),
                default => throw new \LogicException('Unsupported language model provider.'),
            };
        });
        $this->app->bind(PdfTextExtractor::class, SmalotPdfTextExtractor::class);
        $this->app->bind(TextChunker::class, PageAwareTextChunker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);

        RateLimiter::for('auth-registration', static fn (Request $request): Limit => Limit::perMinute(3)
            ->by($request->ip()));

        RateLimiter::for('auth-login', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('demo-entry', function (Request $request): Limit {
            $configuration = $this->app->make(DemoConfiguration::class);

            return $this->withSafeDemoRateLimitResponse(
                Limit::perHour($configuration->entryRequestsPerHourPerIp())
                    ->by('demo-entry:'.hash('sha256', (string) $request->ip())),
            );
        });

        RateLimiter::for('demo-widget', function (Request $request): array {
            $configuration = $this->app->make(DemoConfiguration::class);
            $ipIdentity = hash('sha256', (string) $request->ip());
            $workspaceIdentity = hash('sha256', (string) ($configuration->workspaceId() ?? 'unconfigured'));

            return [
                $this->withSafeDemoWidgetRateLimitResponse(
                    Limit::perMinute($configuration->widgetRequestsPerMinutePerIp())
                        ->by('demo-widget-minute:'.$ipIdentity),
                ),
                $this->withSafeDemoWidgetRateLimitResponse(
                    Limit::perHour($configuration->widgetRequestsPerHourPerIp())
                        ->by('demo-widget-hour:'.$ipIdentity),
                ),
                $this->withSafeDemoWidgetRateLimitResponse(
                    Limit::perDay($configuration->widgetRequestsPerDay())
                        ->by('demo-widget-daily:'.$workspaceIdentity),
                ),
            ];
        });

        RateLimiter::for('workspace-assistant', function (Request $request) {
            $configuration = $this->app->make(DemoConfiguration::class);
            $demoAccess = $this->resolveDemoAssistantAccess($request, $configuration);

            if ($demoAccess !== null) {
                if (! $configuration->assistantEnabled()) {
                    return Limit::none();
                }

                $workspaceId = (string) $demoAccess->workspace->getKey();
                $sessionIdentity = $this->app->make(DemoSessionContext::class)
                    ->rateLimitIdentifier($demoAccess->user, $demoAccess->workspace);

                if ($sessionIdentity === null) {
                    return Limit::none();
                }

                return [
                    $this->withSafeDemoRateLimitResponse(
                        Limit::perMinute($configuration->assistantRequestsPerMinute())
                            ->by('demo-assistant-session:'.hash('sha256', $sessionIdentity)),
                    ),
                    $this->withSafeDemoRateLimitResponse(
                        Limit::perHour($configuration->assistantRequestsPerHourPerIp())
                            ->by('demo-assistant-ip:'.hash('sha256', $workspaceId.'|'.(string) $request->ip())),
                    ),
                    $this->withSafeDemoRateLimitResponse(
                        Limit::perDay($configuration->assistantRequestsPerDay())
                            ->by('demo-assistant-daily:'.hash('sha256', $workspaceId)),
                    ),
                ];
            }

            if ($request->user() instanceof User && $configuration->identifies($request->user())) {
                return Limit::none();
            }

            return Limit::perMinute(10)->by((string) $request->user()?->getAuthIdentifier());
        });

        RateLimiter::for('widget-bootstrap', static fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('widget.limits.bootstrap_requests_per_minute')),
        )->by('widget-bootstrap:'.hash('sha256', (string) $request->ip())));

        RateLimiter::for('widget-conversations', static fn (Request $request): Limit => Limit::perHour(
            max(1, (int) config('widget.limits.session_creations_per_hour')),
        )->by('widget-conversations:'.hash('sha256', implode('|', [
            (string) $request->route('widgetKey'),
            (string) $request->ip(),
        ]))));

        RateLimiter::for('widget-messages', static function (Request $request): array {
            $widgetKey = (string) $request->route('widgetKey');
            $conversationToken = (string) $request->header('X-Sift-Conversation-Token');

            return [
                Limit::perMinute(max(1, (int) config('widget.limits.messages_per_minute_per_conversation')))
                    ->by('widget-conversation:'.hash('sha256', $conversationToken)),
                Limit::perHour(max(1, (int) config('widget.limits.messages_per_hour_per_ip_widget')))
                    ->by('widget-ip:'.hash('sha256', $widgetKey.'|'.(string) $request->ip())),
                Limit::perDay(max(1, (int) config('widget.limits.messages_per_day_per_widget')))
                    ->by('widget-daily:'.hash('sha256', $widgetKey)),
            ];
        });
    }

    private function resolveDemoAssistantAccess(
        Request $request,
        DemoConfiguration $configuration,
    ): ?AuthenticatedWorkspaceContext {
        $user = $request->user();
        $configuredWorkspaceId = $configuration->workspaceId();

        if (! $user instanceof User
            || ! $configuration->enabled()
            || ! $configuration->identifies($user)
            || $configuredWorkspaceId === null
            || ! $request->hasSession()
            || $this->routeWorkspaceId($request) !== $configuredWorkspaceId) {
            return null;
        }

        $workspace = Workspace::query()->find($configuredWorkspaceId);

        if ($workspace === null) {
            return null;
        }

        $access = $this->app->make(ResolveEffectiveWorkspaceAccess::class)
            ->forWorkspace($user, $workspace);

        return $access?->accessMode === WorkspaceAccessMode::Demo
            && $access->allows(WorkspacePermission::UseAssistant)
                ? $access
                : null;
    }

    private function routeWorkspaceId(Request $request): ?int
    {
        $routeWorkspace = $request->route('workspace');
        $value = $routeWorkspace instanceof Workspace
            ? $routeWorkspace->getKey()
            : $routeWorkspace;
        $identifier = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $identifier === false ? null : $identifier;
    }

    private function withSafeDemoRateLimitResponse(Limit $limit): Limit
    {
        return $limit->response(static fn (Request $request, array $headers): JsonResponse => response()->json([
            'message' => 'Too many Demo requests. Please try again later.',
            'code' => 'demo_rate_limited',
        ], 429, $headers));
    }

    private function withSafeDemoWidgetRateLimitResponse(Limit $limit): Limit
    {
        return $limit->response(static fn (Request $request, array $headers): JsonResponse => response()->json([
            'message' => 'The Demo assistant is busy. Please try again later.',
            'code' => 'demo_widget_rate_limited',
        ], 429, $headers));
    }
}
