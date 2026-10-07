<?php

namespace App\Services\Widget;

use App\Data\Widget\GeneratedWidgetConversationToken;

class WidgetConversationTokenGenerator
{
    public function generate(): GeneratedWidgetConversationToken
    {
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new GeneratedWidgetConversationToken(
            rawToken: $rawToken,
            hash: $this->hash($rawToken),
        );
    }

    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
