<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->decimal('top_margin_cm', 4, 2)->default(2.00);
            $table->decimal('bottom_margin_cm', 4, 2)->default(2.00);
            $table->decimal('left_margin_cm', 4, 2)->default(3.00);
            $table->decimal('right_margin_cm', 4, 2)->default(2.00);
            $table->string('font_name')->default('Times New Roman');
            $table->unsignedSmallInteger('font_size')->default(13);
            $table->decimal('line_spacing', 4, 2)->default(1.10);
            $table->string('normal_alignment')->default('justify');
            $table->decimal('normal_first_line_indent_cm', 4, 2)->default(1.27);
            $table->string('heading_alignment')->default('left');
            $table->decimal('heading_first_line_indent_cm', 4, 2)->default(0);
            $table->boolean('apply_numbering')->default(true);
            $table->json('numbering_levels')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
