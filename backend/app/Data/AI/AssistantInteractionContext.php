<?php

namespace App\Data\AI;

use App\Enums\AssistantInteractionOrigin;
use App\Models\WidgetConversation;
use App\Models\Workspace;
use InvalidArgumentException;

final readonly class AssistantInteractionContext
{
    private function __construct(
        public AssistantInteractionOrigin $origin,
        public ?WidgetConversation $widgetConversation,
    ) {}

    public static function assistant(): self
    {
        return new self(AssistantInteractionOrigin::Assistant, null);
    }

    public static function widget(WidgetConversation $conversation): self
    {
        return new self(AssistantInteractionOrigin::Widget, $conversation);
    }

    public function assertValidFor(Workspace $workspace): void
    {
        if ($this->origin === AssistantInteractionOrigin::Assistant) {
            return;
        }

        if (! $this->widgetConversation?->exists
            || (int) $this->widgetConversation->workspace_id !== (int) $workspace->getKey()) {
            throw new InvalidArgumentException('The widget conversation does not belong to the workspace.');
        }
    }
}
