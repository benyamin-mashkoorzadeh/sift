<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceAccessMode;
use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\DemoConfiguration;
use App\Services\Auth\ResolveEffectiveWorkspaceAccess;
use Illuminate\Foundation\Http\FormRequest;

class AskWorkspaceQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('question'))) {
            $this->merge([
                'question' => trim($this->input('question')),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(
        ResolveEffectiveWorkspaceAccess $resolveEffectiveWorkspaceAccess,
        DemoConfiguration $demoConfiguration,
    ): array {
        $maxLength = 2000;
        $user = $this->user();
        $workspace = $this->route('workspace');

        if ($user instanceof User && $workspace instanceof Workspace) {
            $access = $resolveEffectiveWorkspaceAccess->forWorkspace($user, $workspace);

            if ($access?->accessMode === WorkspaceAccessMode::Demo
                && $access->allows(WorkspacePermission::UseAssistant)) {
                $maxLength = $demoConfiguration->assistantMaxQuestionLength();
            }
        }

        return [
            'question' => ['bail', 'required', 'string', 'max:'.$maxLength],
        ];
    }
}
