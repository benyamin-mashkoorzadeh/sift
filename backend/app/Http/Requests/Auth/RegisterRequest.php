<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\PasswordRequirements;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'company_name'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($this->input('email')));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'company_name' => ['bail', 'required', 'string', 'max:255'],
            'password' => [
                'bail',
                'required',
                'confirmed',
                PasswordRequirements::rule(),
            ],
        ];
    }
}
