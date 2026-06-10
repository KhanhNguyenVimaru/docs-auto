<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:doc,docx,rtf,odt', 'max:51200'],
            'document_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
        ];
    }
}
