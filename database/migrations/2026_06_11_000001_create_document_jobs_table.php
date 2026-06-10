<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('status')->default('pending');
            $table->json('input_files')->nullable();
            $table->json('output_files')->nullable();
            $table->json('validation_report')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_jobs');
    }
};
