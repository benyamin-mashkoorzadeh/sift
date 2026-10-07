<?php

namespace App\Http\Resources\Widget;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetConversationResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'conversation_token' => $this->rawToken,
            'expires_at' => $this->expiresAt->toISOString(),
        ];
    }
}
