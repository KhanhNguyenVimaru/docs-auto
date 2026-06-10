<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessDocumentsRequest;
use App\Http\Requests\ValidateDocumentRequest;
use App\Models\DocumentJob;
use App\Models\DocumentTemplate;
use App\Services\DocumentProcessingService;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentProcessingController extends Controller
{
    public function __construct(
        private readonly DocumentProcessingService $documentProcessingService
    ) {
    }

    public function process(ProcessDocumentsRequest $request): JsonResponse
    {
        $template = $this->resolveTemplate($request->validated('document_template_id'));
        $options = Arr::except($request->validated(), ['files', 'document_template_id']);
        $job = $this->documentProcessingService->process(
            $request->file('files', []),
            $template,
            $options
        );

        if ($job->processed_count === 0 || empty($job->output_files)) {
            return response()->json([
                'success' => false,
                'message' => $job->error_message ?: 'Document processing failed.',
                'data' => $this->buildJobResponseData($job, includePreviewHtml: true),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Documents processed successfully.',
            'data' => $this->buildJobResponseData($job, includePreviewHtml: true),
        ]);
    }

    public function validateDocument(ValidateDocumentRequest $request): JsonResponse
    {
        $template = $this->resolveTemplate($request->validated('document_template_id'));
        $job = $this->documentProcessingService->validateFile(
            $request->file('file'),
            $template
        );

        return response()->json([
            'success' => true,
            'message' => 'Document validated successfully.',
            'data' => $job,
        ]);
    }

    public function show(DocumentJob $document_job): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Document job retrieved successfully.',
            'data' => $this->buildJobResponseData($document_job->load('template')),
        ]);
    }

    public function download(DocumentJob $document_job, int $index): Response|StreamedResponse
    {
        $outputFiles = $document_job->output_files ?? [];
        $outputFile = $outputFiles[$index] ?? null;

        if (! is_array($outputFile) || empty($outputFile['path'])) {
            throw new NotFoundHttpException('Output file not found.');
        }

        $disk = Storage::disk('public');
        $path = $outputFile['path'];

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException('Output file not found.');
        }

        return $disk->download($path, $outputFile['file_name'] ?? basename($path));
    }

    private function resolveTemplate(mixed $templateId): ?DocumentTemplate
    {
        if (! $templateId) {
            return null;
        }

        return DocumentTemplate::query()->find($templateId);
    }

    private function buildJobResponseData(DocumentJob $job, bool $includePreviewHtml = false): array
    {
        $data = $job->toArray();
        $outputFiles = $job->output_files ?? [];

        $data['preview_html'] = $includePreviewHtml ? $job->getAttribute('preview_html') : null;
        $data['preview_available'] = filled($data['preview_html']);
        $data['output_files'] = array_map(
            fn (mixed $file, int $index): mixed => is_array($file)
                ? [
                    ...$file,
                    'download_url' => route('document-jobs.download', [
                        'document_job' => $job,
                        'index' => $index,
                    ], false),
                    'download_available' => ! empty($file['path']),
                ]
                : $file,
            $outputFiles,
            array_keys($outputFiles)
        );

        return $data;
    }
}
