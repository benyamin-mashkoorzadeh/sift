<?php

namespace App\Http\Requests;

use App\Enums\RagAnswerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAssistantInteractionsRequest extends FormRequest
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
        return [
            'status' => ['sometimes', 'string', Rule::enum(RagAnswerStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
