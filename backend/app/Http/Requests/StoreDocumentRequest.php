<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
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
            'file' => [
                'required',
                File::types(['pdf'])
                    ->extensions(['pdf'])
                    ->max(max(1, (int) config('documents.max_upload_kb'))),
            ],
        ];
    }
}
