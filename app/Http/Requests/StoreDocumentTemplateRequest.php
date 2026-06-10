<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:document_templates,name'],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'top_margin_cm' => ['sometimes', 'numeric', 'min:0'],
            'bottom_margin_cm' => ['sometimes', 'numeric', 'min:0'],
            'left_margin_cm' => ['sometimes', 'numeric', 'min:0'],
            'right_margin_cm' => ['sometimes', 'numeric', 'min:0'],
            'font_name' => ['sometimes', 'string', 'max:255'],
            'font_size' => ['sometimes', 'integer', 'min:1', 'max:128'],
            'line_spacing' => ['sometimes', 'numeric', 'min:0.5'],
            'normal_alignment' => ['sometimes', 'string', 'max:32'],
            'normal_first_line_indent_cm' => ['sometimes', 'numeric', 'min:0'],
            'heading_alignment' => ['sometimes', 'string', 'max:32'],
            'heading_first_line_indent_cm' => ['sometimes', 'numeric', 'min:0'],
            'apply_numbering' => ['sometimes', 'boolean'],
            'numbering_levels' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
