<?php

namespace App\Http\Requests;

use App\Actions\Team\ResolveActiveWorkspaceInvitation;
use App\Models\User;
use App\Support\Auth\PasswordRequirements;
use Illuminate\Foundation\Http\FormRequest;

class AcceptWorkspaceInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(ResolveActiveWorkspaceInvitation $resolveInvitation): array
    {
        $invitation = $resolveInvitation->handle((string) $this->route('token'));

        if ($this->user() !== null) {
            return $this->prohibitUnexpectedFields([], []);
        }

        $existingUser = User::query()->where('email', $invitation->email)->exists();

        if ($existingUser) {
            return $this->prohibitUnexpectedFields([
                'password' => ['bail', 'required', 'string'],
            ], ['password']);
        }

        return $this->prohibitUnexpectedFields([
            'name' => ['bail', 'required', 'string', 'max:255'],
            'password' => [
                'bail',
                'required',
                'confirmed',
                PasswordRequirements::rule(),
            ],
            'password_confirmation' => ['bail', 'required', 'string'],
        ], ['name', 'password', 'password_confirmation']);
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @param  list<string>  $allowed
     * @return array<string, list<mixed>>
     */
    private function prohibitUnexpectedFields(array $rules, array $allowed): array
    {
        foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
