<?php

use App\Exceptions\AI\AssistantInteractionPersistenceException;
use App\Exceptions\AI\DemoAssistantUnavailableException;
use App\Exceptions\AI\RagAnswerException;
use App\Exceptions\Auth\DemoSessionConflictException;
use App\Exceptions\Auth\DemoUnavailableException;
use App\Exceptions\Auth\WorkspaceContextException;
use App\Exceptions\Review\ReviewItemAlreadyResolvedException;
use App\Exceptions\Review\ReviewPersistenceException;
use App\Exceptions\Team\WorkspaceInvitationConflictException;
use App\Exceptions\Team\WorkspaceInvitationUnavailableException;
use App\Exceptions\Team\WorkspaceInvitationWrongAccountException;
use App\Exceptions\Widget\DemoWidgetUnavailableException;
use App\Exceptions\Widget\WidgetAnswerUnavailableException;
use App\Exceptions\Widget\WidgetApiUnavailableException;
use App\Exceptions\Widget\WidgetConversationUnavailableException;
use App\Exceptions\Widget\WidgetUnavailableException;
use App\Http\Middleware\EnsureWorkspaceMembership;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(null);
        $middleware->statefulApi();
        $middleware->alias([
            'workspace.member' => EnsureWorkspaceMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (RagAnswerException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => RagAnswerException::USER_MESSAGE,
                'code' => 'answer_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (DemoAssistantUnavailableException $exception, Request $request) {
            if (! $request->routeIs('workspaces.answers.store')) {
                return null;
            }

            return response()->json([
                'message' => DemoAssistantUnavailableException::USER_MESSAGE,
                'code' => 'demo_assistant_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (AssistantInteractionPersistenceException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => AssistantInteractionPersistenceException::USER_MESSAGE,
                'code' => 'history_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (ReviewPersistenceException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => ReviewPersistenceException::USER_MESSAGE,
                'code' => 'review_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (ReviewItemAlreadyResolvedException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => ReviewItemAlreadyResolvedException::USER_MESSAGE,
                'code' => 'review_item_already_resolved',
            ], Response::HTTP_CONFLICT);
        });
        $exceptions->render(function (WorkspaceContextException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => WorkspaceContextException::USER_MESSAGE,
                'code' => 'workspace_context_unavailable',
            ], Response::HTTP_CONFLICT);
        });
        $exceptions->render(function (DemoSessionConflictException $exception, Request $request) {
            if (! $request->is('api/auth/demo')) {
                return null;
            }

            return response()->json([
                'message' => DemoSessionConflictException::USER_MESSAGE,
                'code' => 'demo_session_conflict',
            ], Response::HTTP_CONFLICT);
        });
        $exceptions->render(function (DemoUnavailableException $exception, Request $request) {
            if (! $request->is('api/auth/demo')) {
                return null;
            }

            return response()->json([
                'message' => DemoUnavailableException::USER_MESSAGE,
                'code' => 'demo_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (WorkspaceInvitationConflictException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $response = response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], Response::HTTP_CONFLICT);

            if ($request->routeIs('invitations.*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }

            return $response;
        });
        $exceptions->render(function (WorkspaceInvitationUnavailableException $exception, Request $request) {
            if (! $request->is('api/invitations/*')) {
                return null;
            }

            return response()->json([
                'message' => WorkspaceInvitationUnavailableException::USER_MESSAGE,
                'code' => 'invitation_unavailable',
            ], Response::HTTP_NOT_FOUND)->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Referrer-Policy' => 'no-referrer',
            ]);
        });
        $exceptions->render(function (WorkspaceInvitationWrongAccountException $exception, Request $request) {
            if (! $request->is('api/invitations/*')) {
                return null;
            }

            return response()->json([
                'message' => WorkspaceInvitationWrongAccountException::USER_MESSAGE,
                'code' => 'invitation_wrong_account',
            ], Response::HTTP_FORBIDDEN)->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Referrer-Policy' => 'no-referrer',
            ]);
        });
        $exceptions->render(function (WidgetUnavailableException $exception, Request $request) {
            if (! $request->is('api/widget/v1/*')) {
                return null;
            }

            return response()->json([
                'message' => WidgetUnavailableException::USER_MESSAGE,
                'code' => 'widget_unavailable',
            ], Response::HTTP_NOT_FOUND);
        });
        $exceptions->render(function (DemoWidgetUnavailableException $exception, Request $request) {
            if (! $request->is('api/demo/widget/*')) {
                return null;
            }

            return response()->json([
                'message' => DemoWidgetUnavailableException::USER_MESSAGE,
                'code' => 'demo_widget_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (WidgetConversationUnavailableException $exception, Request $request) {
            if (! $request->is('api/widget/v1/*')) {
                return null;
            }

            return response()->json([
                'message' => WidgetConversationUnavailableException::USER_MESSAGE,
                'code' => 'conversation_unavailable',
            ], Response::HTTP_NOT_FOUND);
        });
        $exceptions->render(function (WidgetAnswerUnavailableException $exception, Request $request) {
            if (! $request->is('api/widget/v1/*')) {
                return null;
            }

            return response()->json([
                'message' => WidgetAnswerUnavailableException::USER_MESSAGE,
                'code' => 'widget_answer_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (WidgetApiUnavailableException $exception, Request $request) {
            if (! $request->is('api/widget/v1/*')) {
                return null;
            }

            return response()->json([
                'message' => WidgetApiUnavailableException::USER_MESSAGE,
                'code' => 'widget_temporarily_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/widget/v1/*')
                || $exception instanceof ValidationException
                || $exception instanceof HttpExceptionInterface) {
                return null;
            }

            report($exception);

            return response()->json([
                'message' => WidgetApiUnavailableException::USER_MESSAGE,
                'code' => 'widget_temporarily_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/demo/widget/*')
                || $exception instanceof ValidationException
                || $exception instanceof HttpResponseException
                || $exception instanceof HttpExceptionInterface) {
                return null;
            }

            report($exception);

            return response()->json([
                'message' => DemoWidgetUnavailableException::USER_MESSAGE,
                'code' => 'demo_widget_unavailable',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->is('api/widget/v1/*') || $request->is('api/demo/widget/*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('Referrer-Policy', 'no-referrer');
                $response->headers->set('X-Content-Type-Options', 'nosniff');
            }

            return $response;
        });
    })->create();
