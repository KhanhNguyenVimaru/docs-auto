<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:25'],
            'files.*' => ['required', 'file', 'mimes:doc,docx,rtf,odt', 'max:51200'],
            'document_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'output_mode' => ['sometimes', 'string', 'in:copy,overwrite'],
            'generate_validation_report' => ['sometimes', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
