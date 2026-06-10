<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_default',
        'top_margin_cm',
        'bottom_margin_cm',
        'left_margin_cm',
        'right_margin_cm',
        'font_name',
        'font_size',
        'line_spacing',
        'normal_alignment',
        'normal_first_line_indent_cm',
        'heading_alignment',
        'heading_first_line_indent_cm',
        'apply_numbering',
        'numbering_levels',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'apply_numbering' => 'boolean',
            'top_margin_cm' => 'decimal:2',
            'bottom_margin_cm' => 'decimal:2',
            'left_margin_cm' => 'decimal:2',
            'right_margin_cm' => 'decimal:2',
            'font_size' => 'integer',
            'line_spacing' => 'decimal:2',
            'normal_first_line_indent_cm' => 'decimal:2',
            'heading_first_line_indent_cm' => 'decimal:2',
            'numbering_levels' => 'array',
            'settings' => 'array',
        ];
    }

    public function documentJobs()
    {
        return $this->hasMany(DocumentJob::class);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
