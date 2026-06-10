<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_template_id',
        'name',
        'status',
        'input_files',
        'output_files',
        'validation_report',
        'options',
        'processed_count',
        'failed_count',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'input_files' => 'array',
            'output_files' => 'array',
            'validation_report' => 'array',
            'options' => 'array',
            'processed_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }
}
