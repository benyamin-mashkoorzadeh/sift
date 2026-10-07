<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceWidgetRequest extends FormRequest
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
            'enabled' => ['bail', 'required', 'boolean'],
        ];

        foreach (array_diff(array_keys($this->all()), ['enabled']) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
