<?php

namespace App\Http\Requests\Widget;

use Illuminate\Foundation\Http\FormRequest;

class StoreWidgetMessageRequest extends FormRequest
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
    public function rules(): array
    {
        $rules = [
            'question' => [
                'bail',
                'required',
                'string',
                'max:'.max(1, (int) config('widget.limits.maximum_question_length')),
            ],
        ];

        foreach (array_diff(array_keys($this->all()), ['question']) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
