<?php

namespace App\Http\Middleware;

use App\Exceptions\Widget\WidgetConversationUnavailableException;
use App\Models\WidgetConversation;
use App\Models\WorkspaceWidget;
use App\Services\Widget\WidgetConversationTokenGenerator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWidgetConversation
{
    public const ATTRIBUTE = 'sift.widget_conversation';

    public function __construct(
        private readonly WidgetConversationTokenGenerator $tokenGenerator,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var WorkspaceWidget|null $widget */
        $widget = $request->attributes->get(ResolvePublicWidget::ATTRIBUTE);
        $rawToken = $request->header('X-Sift-Conversation-Token');

        if ($widget === null || ! is_string($rawToken) || $rawToken === '') {
            throw new WidgetConversationUnavailableException(
                WidgetConversationUnavailableException::USER_MESSAGE,
            );
        }

        $conversation = WidgetConversation::query()
            ->whereBelongsTo($widget->workspace)
            ->where('session_token_hash', $this->tokenGenerator->hash($rawToken))
            ->where('expires_at', '>', now())
            ->when(
                $widget->key_rotated_at !== null,
                fn ($query) => $query->where('created_at', '>=', $widget->key_rotated_at),
            )
            ->first();

        if ($conversation === null) {
            throw new WidgetConversationUnavailableException(
                WidgetConversationUnavailableException::USER_MESSAGE,
            );
        }

        $request->attributes->set(self::ATTRIBUTE, $conversation);

        return $next($request);
    }
}
