<?php

use App\Http\Controllers\Api\DocumentProcessingController;
use App\Http\Controllers\Api\DocumentTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('document-templates')->group(function () {
    Route::get('/', [DocumentTemplateController::class, 'index']);
    Route::post('/', [DocumentTemplateController::class, 'store']);
    Route::get('{document_template}', [DocumentTemplateController::class, 'show']);
    Route::put('{document_template}', [DocumentTemplateController::class, 'update']);
    Route::patch('{document_template}', [DocumentTemplateController::class, 'update']);
    Route::delete('{document_template}', [DocumentTemplateController::class, 'destroy']);
});

Route::prefix('documents')->group(function () {
    Route::post('process', [DocumentProcessingController::class, 'process']);
    Route::post('validate', [DocumentProcessingController::class, 'validateDocument']);
});

Route::prefix('document-jobs')->group(function () {
    Route::get('{document_job}', [DocumentProcessingController::class, 'show']);
    Route::get('{document_job}/downloads/{index}', [DocumentProcessingController::class, 'download'])
        ->whereNumber('index')
        ->name('document-jobs.download');
});
