<?php

namespace App\Enums;

enum WorkspacePermission: string
{
    case ViewOverview = 'overview.view';
    case UseAssistant = 'assistant.use';
    case ViewKnowledge = 'knowledge.view';
    case ManageKnowledge = 'knowledge.manage';
    case ViewConversations = 'conversations.view';
    case ViewReview = 'review.view';
    case ResolveReview = 'review.resolve';
    case ViewSettings = 'settings.view';
    case UpdateSettings = 'settings.update';
    case ViewTeam = 'team.view';
    case ManageTeam = 'team.manage';
}
