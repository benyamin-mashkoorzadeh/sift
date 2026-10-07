<?php

namespace App\Services\Auth;

use App\Models\User;

class DemoConfiguration
{
    public function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    public function userId(): ?int
    {
        return $this->identifier('demo.user_id');
    }

    public function workspaceId(): ?int
    {
        return $this->identifier('demo.workspace_id');
    }

    public function identifies(User $user): bool
    {
        $configuredUserId = $this->userId();

        return $configuredUserId !== null && $user->getKey() === $configuredUserId;
    }

    public function assistantEnabled(): bool
    {
        return (bool) config('demo.assistant.enabled');
    }

    public function assistantRequestsPerMinute(): int
    {
        return $this->positiveInteger('demo.assistant.requests_per_minute');
    }

    public function assistantRequestsPerHourPerIp(): int
    {
        return $this->positiveInteger('demo.assistant.requests_per_hour_per_ip');
    }

    public function assistantRequestsPerDay(): int
    {
        return $this->positiveInteger('demo.assistant.requests_per_day');
    }

    public function assistantMaxQuestionLength(): int
    {
        return $this->positiveInteger('demo.assistant.max_question_length');
    }

    public function entryRequestsPerHourPerIp(): int
    {
        return $this->positiveInteger('demo.entry.requests_per_hour_per_ip');
    }

    public function widgetEnabled(): bool
    {
        return (bool) config('demo.widget.enabled');
    }

    public function widgetRequestsPerMinutePerIp(): int
    {
        return $this->positiveInteger('demo.widget.requests_per_minute_per_ip');
    }

    public function widgetRequestsPerHourPerIp(): int
    {
        return $this->positiveInteger('demo.widget.requests_per_hour_per_ip');
    }

    public function widgetRequestsPerDay(): int
    {
        return $this->positiveInteger('demo.widget.requests_per_day');
    }

    public function widgetMaxQuestionLength(): int
    {
        return $this->positiveInteger('demo.widget.max_question_length');
    }

    public function widgetMaximumRequestBytes(): int
    {
        return $this->positiveInteger('demo.widget.maximum_request_bytes');
    }

    private function identifier(string $key): ?int
    {
        $value = filter_var(config($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $value === false ? null : $value;
    }

    private function positiveInteger(string $key): int
    {
        return max(1, (int) config($key));
    }
}
