<?php

namespace App\Enums;

enum AssistantInteractionOrigin: string
{
    case Assistant = 'assistant';
    case Widget = 'widget';
}
