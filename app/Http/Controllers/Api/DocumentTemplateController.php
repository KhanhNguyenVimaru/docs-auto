<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentTemplateRequest;
use App\Http\Requests\UpdateDocumentTemplateRequest;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use Illuminate\Http\JsonResponse;

class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateService $documentTemplateService
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Document templates retrieved successfully.',
            'data' => $this->documentTemplateService->paginate(),
        ]);
    }

    public function store(StoreDocumentTemplateRequest $request): JsonResponse
    {
        $template = $this->documentTemplateService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Document template created successfully.',
            'data' => $template,
        ], 201);
    }

    public function show(DocumentTemplate $document_template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Document template retrieved successfully.',
            'data' => $document_template,
        ]);
    }

    public function update(UpdateDocumentTemplateRequest $request, DocumentTemplate $document_template): JsonResponse
    {
        $template = $this->documentTemplateService->update($document_template, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Document template updated successfully.',
            'data' => $template,
        ]);
    }

    public function destroy(DocumentTemplate $document_template): JsonResponse
    {
        $this->documentTemplateService->delete($document_template);

        return response()->json([
            'success' => true,
            'message' => 'Document template deleted successfully.',
            'data' => null,
        ]);
    }
}
