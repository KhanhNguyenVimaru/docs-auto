<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get('/download-template', function () {
    $path = storage_path('base-docs.docx');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->download($path, 'base-docs.docx');
});

