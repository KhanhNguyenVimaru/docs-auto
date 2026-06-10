<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DocumentTemplateService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return DocumentTemplate::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = DocumentTemplate::create($data);

            if ($template->is_default) {
                $this->clearOtherDefaults($template->id);
            }

            return $template->refresh();
        });
    }

    public function update(DocumentTemplate $template, array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $template->update($data);

            if ($template->is_default) {
                $this->clearOtherDefaults($template->id);
            }

            return $template->refresh();
        });
    }

    public function delete(DocumentTemplate $template): void
    {
        $template->delete();
    }

    public function defaultTemplate(): DocumentTemplate
    {
        return DocumentTemplate::query()->default()->first() ?? DocumentTemplate::make([
            'name' => 'Default 3-2-2-2',
            'is_default' => true,
            'top_margin_cm' => 2.00,
            'bottom_margin_cm' => 2.00,
            'left_margin_cm' => 3.00,
            'right_margin_cm' => 2.00,
            'font_name' => 'Times New Roman',
            'font_size' => 13,
            'line_spacing' => 1.10,
            'normal_alignment' => 'justify',
            'normal_first_line_indent_cm' => 1.27,
            'heading_alignment' => 'left',
            'heading_first_line_indent_cm' => 0,
            'apply_numbering' => true,
            'numbering_levels' => [
                1 => ['format' => 'decimal'],
                2 => ['format' => 'decimal'],
                3 => ['format' => 'decimal'],
                4 => ['format' => 'decimal'],
            ],
        ]);
    }

    private function clearOtherDefaults(int $templateId): void
    {
        DocumentTemplate::query()
            ->where('id', '!=', $templateId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
