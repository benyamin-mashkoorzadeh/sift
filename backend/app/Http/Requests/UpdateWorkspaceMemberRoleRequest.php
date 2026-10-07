<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'role' => ['bail', 'required', 'string', Rule::in([
                WorkspaceRole::Admin->value,
                WorkspaceRole::Member->value,
            ])],
        ];

        foreach (array_diff(array_keys($this->all()), ['role']) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
