<?php

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreWorkspaceInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge([
                'email' => Str::lower(trim($this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'role' => ['bail', 'required', 'string', Rule::in([
                WorkspaceRole::Admin->value,
                WorkspaceRole::Member->value,
            ])],
        ];

        foreach (array_diff(array_keys($this->all()), ['email', 'role']) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
