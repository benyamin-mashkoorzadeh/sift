<?php

namespace App\Services\Team;

use App\Data\Team\GeneratedInvitationToken;
use LogicException;

class WorkspaceInvitationTokenGenerator
{
    public function generate(): GeneratedInvitationToken
    {
        $frontendUrl = rtrim((string) config('team.frontend_url'), '/');
        $scheme = parse_url($frontendUrl, PHP_URL_SCHEME);

        if (
            $frontendUrl === ''
            || filter_var($frontendUrl, FILTER_VALIDATE_URL) === false
            || ! in_array($scheme, ['http', 'https'], true)
        ) {
            throw new LogicException('A valid Sift frontend URL must be configured.');
        }

        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new GeneratedInvitationToken(
            hash: $this->hash($rawToken),
            url: "{$frontendUrl}/invite/{$rawToken}",
        );
    }

    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
