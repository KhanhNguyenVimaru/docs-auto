<?php

namespace Tests\Feature;

use App\Models\DocumentJob;
use App\Models\DocumentTemplate;
use App\Services\DocumentProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_template_can_be_created(): void
    {
        $response = $this->postJson('/api/document-templates', [
            'name' => 'Academic Standard',
            'is_default' => true,
            'top_margin_cm' => 2,
            'bottom_margin_cm' => 2,
            'left_margin_cm' => 3,
            'right_margin_cm' => 2,
            'font_name' => 'Times New Roman',
            'font_size' => 13,
            'line_spacing' => 1.1,
            'normal_alignment' => 'justify',
            'normal_first_line_indent_cm' => 1.27,
            'heading_alignment' => 'left',
            'heading_first_line_indent_cm' => 0,
            'apply_numbering' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Academic Standard');

        $this->assertDatabaseHas('document_templates', [
            'name' => 'Academic Standard',
            'is_default' => true,
        ]);
    }

    public function test_document_can_be_validated_and_processed(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $template = DocumentTemplate::create([
            'name' => 'Academic Standard',
            'is_default' => true,
            'top_margin_cm' => 2,
            'bottom_margin_cm' => 2,
            'left_margin_cm' => 3,
            'right_margin_cm' => 2,
            'font_name' => 'Times New Roman',
            'font_size' => 13,
            'line_spacing' => 1.1,
            'normal_alignment' => 'justify',
            'normal_first_line_indent_cm' => 1.27,
            'heading_alignment' => 'left',
            'heading_first_line_indent_cm' => 0,
            'apply_numbering' => true,
        ]);

        $documentPath = $this->createSampleDocument();
        $uploadedFile = new UploadedFile(
            $documentPath,
            'sample.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $validationResponse = $this->post('/api/documents/validate', [
            'file' => $uploadedFile,
            'document_template_id' => $template->id,
        ]);

        $validationResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'validated');

        $processResponse = $this->post('/api/documents/process', [
            'files' => [$uploadedFile],
            'document_template_id' => $template->id,
            'output_mode' => 'copy',
            'generate_validation_report' => true,
            'name' => 'Sample Batch',
        ]);

        $processResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $previewHtml = data_get($processResponse->json(), 'data.preview_html');
        $downloadUrl = data_get($processResponse->json(), 'data.output_files.0.download_url');

        $outputPath = data_get($processResponse->json(), 'data.output_files.0.path');
        $jobId = data_get($processResponse->json(), 'data.id');

        $this->assertNotEmpty($previewHtml);
        $this->assertStringContainsString('Introduction', $previewHtml);
        $this->assertNotEmpty($downloadUrl);
        $this->assertNotEmpty($outputPath);
        Storage::disk('public')->assertExists($outputPath);
        $this->assertFormattedDocumentStructure(Storage::disk('public')->path($outputPath));

        $this->get($downloadUrl)
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->getJson("/api/document-jobs/{$jobId}/downloads/99")
            ->assertNotFound();

        @unlink($documentPath);
    }

    public function test_download_endpoint_returns_not_found_when_file_is_missing(): void
    {
        Storage::fake('public');

        $job = DocumentJob::create([
            'status' => 'completed',
            'input_files' => [],
            'output_files' => [
                [
                    'file_name' => 'missing.docx',
                    'path' => 'document-exports/missing.docx',
                ],
            ],
            'validation_report' => [],
            'options' => [],
            'processed_count' => 1,
            'failed_count' => 0,
        ]);

        $this->getJson("/api/document-jobs/{$job->id}/downloads/0")
            ->assertNotFound();
    }

    public function test_process_endpoint_returns_error_when_no_output_file_is_generated(): void
    {
        $failedJob = DocumentJob::create([
            'status' => 'failed',
            'input_files' => [
                [
                    'original_name' => 'broken.docx',
                    'extension' => 'docx',
                    'size' => 1024,
                ],
            ],
            'output_files' => [],
            'validation_report' => [
                [
                    'source_name' => 'broken.docx',
                    'error' => 'Formatting engine failed.',
                ],
            ],
            'options' => [],
            'processed_count' => 0,
            'failed_count' => 1,
            'error_message' => 'Formatting engine failed.',
        ]);

        $this->mock(DocumentProcessingService::class, function (MockInterface $mock) use ($failedJob): void {
            $mock->shouldReceive('process')
                ->once()
                ->andReturn($failedJob);
        });

        $uploadedFile = UploadedFile::fake()->create(
            'broken.docx',
            10,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $this->post('/api/documents/process', [
            'files' => [$uploadedFile],
            'output_mode' => 'copy',
            'generate_validation_report' => true,
            'name' => 'Broken Batch',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Formatting engine failed.')
            ->assertJsonPath('data.status', 'failed');
    }

    private function createSampleDocument(): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addTitle('Introduction', 1);
        $section->addText('This is a normal paragraph.');

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('phpword_', true) . '.docx';
        $phpWord->save($path, 'Word2007');

        return $path;
    }

    private function assertFormattedDocumentStructure(string $documentPath): void
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($documentPath) === true);

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($documentXml);
        $this->assertStringContainsString('w:left="1701"', $documentXml);
        $this->assertStringContainsString('w:top="1134"', $documentXml);
        $this->assertStringContainsString('w:firstLine="720"', $documentXml);
        $this->assertStringContainsString('w:line="264"', $documentXml);
    }
}
