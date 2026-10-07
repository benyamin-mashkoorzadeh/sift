<?php

namespace App\Actions\Widget;

use App\Data\Widget\StartedWidgetConversation;
use App\Models\WorkspaceWidget;
use App\Services\Widget\WidgetConversationTokenGenerator;

class StartWidgetConversation
{
    public function __construct(
        private readonly WidgetConversationTokenGenerator $tokenGenerator,
    ) {}

    public function handle(WorkspaceWidget $widget): StartedWidgetConversation
    {
        $token = $this->tokenGenerator->generate();
        $now = now();
        $expiresAt = $now->copy()->addMinutes(
            max(1, (int) config('widget.conversation_lifetime_minutes')),
        );

        $widget->workspace->widgetConversations()->create([
            'session_token_hash' => $token->hash,
            'expires_at' => $expiresAt,
            'last_activity_at' => $now,
        ]);

        return new StartedWidgetConversation(
            rawToken: $token->rawToken,
            expiresAt: $expiresAt,
        );
    }
}
