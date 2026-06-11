<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocFormatter - DOCX Style Reformatter</title>
    <meta name="description" content="Upload a DOCX file, reformat its style, preview and export with ease.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --preview-font: "Bahnschrift", sans-serif;
        }

        body,
        button,
        input,
        textarea,
        select,
        .btn,
        .card,
        .menu,
        .table {
            font-family: "Bahnschrift", sans-serif;
        }

        .drop-active {
            border-color: oklch(var(--p)) !important;
            background: oklch(var(--p) / 0.06) !important;
        }

        .sidebar-scroll {
            scrollbar-width: thin;
            scrollbar-color: oklch(var(--b3)) transparent;
        }

        #preview-frame {
            font-family: "Bahnschrift", sans-serif;
            font-size: var(--preview-size, 13pt);
            line-height: var(--preview-line, 1.5);
            color: oklch(var(--bc));
        }

        @keyframes shimmer {
            0% { opacity: 0.4; }
            50% { opacity: 0.8; }
            100% { opacity: 0.4; }
        }

        .shimmer {
            animation: shimmer 1.6s ease-in-out infinite;
        }

        #sidebar {
            transition: width 0.25s cubic-bezier(.4, 0, .2, 1), opacity 0.2s ease, padding 0.25s ease;
            overflow: hidden;
        }

        #sidebar.collapsed {
            width: 0 !important;
            opacity: 0;
            padding: 0;
            border: none;
        }

        #sidebar-toggle svg {
            transition: transform 0.25s ease;
        }

        #sidebar-toggle.collapsed svg {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="font-sans antialiased min-h-screen bg-base-200 flex flex-col">
    <header class="h-14 flex items-center justify-between px-4 bg-base-100 border-b border-base-300 shrink-0 z-40">
        <div class="flex items-center gap-2">
            <button id="sidebar-toggle"
                    onclick="toggleSidebar()"
                    class="btn btn-ghost btn-sm btn-square"
                    title="Toggle sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12H12m-8.25 5.25h16.5"/>
                </svg>
            </button>

            <a href="#" class="flex items-center gap-2.5 font-black text-lg tracking-tight">
                <span>Doc<span class="text-primary">Formatter</span></span>
            </a>
        </div>

        <div id="status-pill" class="hidden items-center gap-2 px-3 py-1 rounded-full bg-success/10 text-success text-xs font-semibold border border-success/20">
            <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
            <span id="status-text">File loaded</span>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden sm:flex items-center gap-1 text-xs font-medium text-base-content/50">
                <span id="step-1" class="flex items-center gap-1 transition-colors">
                    <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-[10px] font-bold">1</span>
                    Upload
                </span>
                <span class="mx-1">></span>
                <span id="step-2" class="flex items-center gap-1 transition-colors opacity-40">
                    <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-[10px] font-bold">2</span>
                    Format
                </span>
                <span class="mx-1">></span>
                <span id="step-3" class="flex items-center gap-1 transition-colors opacity-40">
                    <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-[10px] font-bold">3</span>
                    Export
                </span>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 3.5rem)">
        <aside id="sidebar" class="w-80 shrink-0 bg-base-100 border-r border-base-300 flex flex-col overflow-y-auto sidebar-scroll">
            <section class="p-5 border-b border-base-300">
                <h2 class="text-xs font-bold uppercase text-base-content/40 mb-3">1 Upload Document</h2>

                <div id="drop-zone"
                     class="border-2 border-dashed border-base-300 rounded-xl p-6 text-center cursor-pointer transition-all duration-200 hover:border-primary hover:bg-primary/5 group"
                     onclick="document.getElementById('file-input').click()"
                     ondragover="handleDragOver(event)"
                     ondragleave="handleDragLeave(event)"
                     ondrop="handleDrop(event)">
                    <div class="w-12 h-12 rounded-xl bg-base-200 group-hover:bg-primary/10 flex items-center justify-center mx-auto mb-3 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-base-content/40 group-hover:text-primary transition-colors">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-base-content/70 group-hover:text-primary transition-colors">Drop DOCX here</p>
                    <p class="text-xs text-base-content/40 mt-1">or click to browse</p>
                    <span class="badge badge-ghost badge-xs mt-3">.docx only</span>
                </div>
                <input type="file" id="file-input" accept=".docx" class="hidden" onchange="handleFileSelect(event)">

                <div id="file-info" class="hidden mt-3 p-3 bg-base-200 rounded-xl flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-primary/15 text-primary flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-5 h-5">
                            <path d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0016.5 9h-1.875a1.875 1.875 0 01-1.875-1.875V5.25A3.75 3.75 0 009 1.5H5.625z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p id="file-name" class="text-sm font-semibold truncate"></p>
                        <p id="file-size" class="text-xs text-base-content/40"></p>
                    </div>
                    <button onclick="clearFile()" class="btn btn-ghost btn-xs btn-circle text-base-content/30 hover:text-error">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div id="sidebar-tutorial" class="hidden mt-4 rounded-xl border border-base-300 bg-base-200/70 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-base-content/40">Workflow</p>
                    <ol class="mt-3 space-y-2 text-sm text-base-content/70">
                        <li class="flex gap-2">
                            <span class="font-semibold text-primary">1.</span>
                            <span>Load a DOCX file to enable formatting.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-primary">2.</span>
                            <span>Click <span class="font-semibold">Format DOCX</span> to run Word processing.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-primary">3.</span>
                            <span>Download the formatted file after the preview appears.</span>
                        </li>
                    </ol>
                    <p class="mt-3 text-xs leading-5 text-base-content/45">
                        The main preview stays empty until formatting completes.
                    </p>
                </div>
            </section>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden bg-base-200">
            <div class="h-12 flex items-center justify-between px-5 bg-base-100 border-b border-base-300 shrink-0">
                <div class="flex items-center gap-2 text-sm font-semibold text-base-content/60">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Preview
                    <span id="preview-filename" class="text-xs font-normal text-base-content/30"></span>
                </div>

                <div class="flex items-center gap-2">
                    <a href="/download-template"
                       class="btn btn-ghost btn-sm gap-2 border border-base-300 hover:bg-base-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Download Form
                    </a>
                    <button id="btn-export" onclick="exportDocument()"
                            class="btn btn-success btn-sm gap-2 shadow-md text-white hover:text-white disabled:opacity-40"
                            disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127c.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128c.332-.183.582-.495.645-.869l.214-1.28z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Format DOCX
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-6 lg:p-10 sidebar-scroll">
                <div id="empty-state" class="h-full flex flex-col items-center justify-center text-center">
                    <div class="w-24 h-24 rounded-2xl bg-base-100 border-2 border-dashed border-base-300 flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-10 h-10 text-base-content/20">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                    </div>
                    <p class="font-bold text-base-content/40 text-lg">No document loaded</p>
                    <p class="text-sm text-base-content/25 mt-1">Upload a DOCX file to see the preview here</p>
                </div>

                <div id="loading-state" class="hidden max-w-[794px] mx-auto">
                    <div class="bg-base-100 shadow-xl rounded-xl p-10 space-y-4">
                        <div class="h-6 w-1/2 bg-base-300 rounded-lg shimmer"></div>
                        <div class="h-3 w-full bg-base-300 rounded shimmer"></div>
                        <div class="h-3 w-5/6 bg-base-300 rounded shimmer"></div>
                        <div class="h-3 w-full bg-base-300 rounded shimmer"></div>
                        <div class="h-3 w-4/6 bg-base-300 rounded shimmer"></div>
                        <div class="h-3 w-full bg-base-300 rounded shimmer"></div>
                        <div class="h-5 w-1/3 bg-base-300 rounded-lg shimmer mt-6"></div>
                        <div class="h-3 w-full bg-base-300 rounded shimmer"></div>
                        <div class="h-3 w-3/4 bg-base-300 rounded shimmer"></div>
                    </div>
                </div>

                <div id="doc-preview" class="hidden max-w-[794px] mx-auto">
                    <div class="bg-white text-gray-900 shadow-2xl rounded-sm"
                         style="min-height: 1123px;"
                         id="preview-page">
                        <div id="preview-frame"
                             class="p-10 leading-relaxed"
                             style="font-family: 'Times New Roman', serif; font-size: 13pt; line-height: 1.5; text-align: left;"></div>
                    </div>
                    <p class="text-center text-xs text-base-content/30 mt-4">A4 Preview - 794 x 1123 px</p>
                </div>
            </div>
        </main>
    </div>

    <div id="toast" class="toast toast-top toast-end z-[999] hidden">
        <div id="toast-inner" class="alert alert-success shadow-lg text-sm py-2.5 px-4">
            <span id="toast-msg">Done!</span>
        </div>
    </div>

    <script>
        let sidebarOpen = true;
        let currentFile = null;
        let currentJobId = null;
        let downloadUrl = null;
        let toastTimer = null;
        let isProcessing = false;

        function toggleSidebar() {
            sidebarOpen = !sidebarOpen;
            const sidebar = document.getElementById('sidebar');
            const button = document.getElementById('sidebar-toggle');

            sidebar.classList.toggle('collapsed', !sidebarOpen);
            button.classList.toggle('collapsed', !sidebarOpen);
            button.title = sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar';
        }

        function handleDragOver(event) {
            event.preventDefault();
            document.getElementById('drop-zone').classList.add('drop-active');
        }

        function handleDragLeave(event) {
            event.preventDefault();
            document.getElementById('drop-zone').classList.remove('drop-active');
        }

        function handleDrop(event) {
            event.preventDefault();
            document.getElementById('drop-zone').classList.remove('drop-active');

            const file = event.dataTransfer.files[0];
            if (file) {
                loadFile(file);
            }
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                loadFile(file);
            }
        }

        function loadFile(file) {
            if (!file.name.toLowerCase().endsWith('.docx')) {
                showToast('Please upload a .docx file', 'error');
                return;
            }

            currentFile = file;
            currentJobId = null;
            downloadUrl = null;
            isProcessing = false;

            document.getElementById('file-name').textContent = file.name;
            document.getElementById('file-size').textContent = formatFileSize(file.size);
            document.getElementById('file-info').classList.remove('hidden');
            document.getElementById('sidebar-tutorial').classList.remove('hidden');
            document.getElementById('drop-zone').classList.add('hidden');
            document.getElementById('preview-filename').textContent = '- ' + file.name;

            setExportButton('Format DOCX', false);
            setStep(2);
            showStatus('File loaded');
            showBlankMain();
        }

        function clearFile() {
            currentFile = null;
            currentJobId = null;
            downloadUrl = null;
            isProcessing = false;

            document.getElementById('file-info').classList.add('hidden');
            document.getElementById('sidebar-tutorial').classList.add('hidden');
            document.getElementById('drop-zone').classList.remove('hidden');
            document.getElementById('file-input').value = '';
            document.getElementById('preview-filename').textContent = '';
            document.getElementById('preview-frame').innerHTML = '';
            document.getElementById('status-pill').classList.add('hidden');
            document.getElementById('status-pill').classList.remove('flex');

            setExportButton('Format DOCX', true);
            setStep(1);
            showEmptyState();
        }

        function showBlankMain() {
            document.getElementById('empty-state').classList.add('hidden');
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('doc-preview').classList.add('hidden');
            document.getElementById('preview-frame').innerHTML = '';
        }

        async function exportDocument() {
            if (!currentFile) {
                showToast('Please choose a document first', 'error');
                return;
            }

            if (downloadUrl) {
                downloadFormattedFile(downloadUrl);
                return;
            }

            if (isProcessing) {
                return;
            }

            isProcessing = true;
            setExportButton('Processing...', true);
            showLoading();
            showStatus('Uploading file');

            const formData = new FormData();
            formData.append('files[]', currentFile);
            formData.append('output_mode', 'copy');
            formData.append('generate_validation_report', '1');
            formData.append('name', currentFile.name.replace(/\.[^.]+$/, ''));

            try {
                showStatus('Formatting in backend');

                const response = await fetch('/api/documents/process', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                    },
                    body: formData,
                });

                const payload = await parseJsonResponse(response);
                if (!response.ok || payload.success !== true) {
                    throw new Error(payload.message || 'Document processing failed.');
                }

                const previewHtml = payload?.data?.preview_html ?? null;
                currentJobId = payload?.data?.id ?? null;
                const outputFile = payload?.data?.output_files?.[0] ?? null;

                if (typeof previewHtml === 'string' && previewHtml.trim() !== '') {
                    document.getElementById('preview-frame').innerHTML = previewHtml;
                }

                downloadUrl = resolveDownloadUrl(outputFile, currentJobId);

                document.getElementById('empty-state').classList.add('hidden');
                document.getElementById('loading-state').classList.add('hidden');
                document.getElementById('doc-preview').classList.remove('hidden');

                setStep(3);
                showStatus('Preview ready');
                showToast('Document processed successfully', 'success');
                setExportButton(downloadUrl ? 'Download formatted file' : 'Format DOCX', false);

                if (!downloadUrl) {
                    showToast('Preview is ready, but the download link is missing.', 'error');
                }
            } catch (error) {
                showBlankMain();
                currentJobId = null;
                showStatus('Processing failed');
                showToast(error.message || 'Document processing failed.', 'error');
                setExportButton('Format DOCX', false);
            } finally {
                isProcessing = false;
            }
        }

        async function parseJsonResponse(response) {
            const contentType = response.headers.get('content-type') || '';

            if (contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();

            try {
                return JSON.parse(text);
            } catch (error) {
                return {
                    success: false,
                    message: text || 'Unexpected server response.',
                };
            }
        }

        function downloadFormattedFile(url) {
            if (!url) {
                showToast('Formatted file is not available yet.', 'error');
                return;
            }

            window.location.href = url;
        }

        function resolveDownloadUrl(outputFile, jobId) {
            if (outputFile && typeof outputFile.download_url === 'string' && outputFile.download_url.trim() !== '') {
                return outputFile.download_url;
            }

            if (jobId !== null && jobId !== undefined) {
                return `/api/document-jobs/${encodeURIComponent(jobId)}/downloads/0`;
            }

            return null;
        }

        function showEmptyState() {
            document.getElementById('empty-state').classList.remove('hidden');
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('doc-preview').classList.add('hidden');
            document.getElementById('preview-frame').innerHTML = '';
        }

        function showLoading() {
            document.getElementById('empty-state').classList.add('hidden');
            document.getElementById('doc-preview').classList.add('hidden');
            document.getElementById('loading-state').classList.remove('hidden');
        }

        function setExportButton(label, disabled) {
            const button = document.getElementById('btn-export');
            button.disabled = disabled;
            let iconHtml = '';
            
            if (label.toLowerCase().includes('processing')) {
                iconHtml = `
                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                `;
            } else if (label.toLowerCase().includes('download')) {
                iconHtml = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                `;
            } else {
                iconHtml = `
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127c.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128c.332-.183.582-.495.645-.869l.214-1.28z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                `;
            }
            button.innerHTML = `
                ${iconHtml}
                <span>${escapeHtml(label)}</span>
            `;
        }

        function setStep(step) {
            [1, 2, 3].forEach((index) => {
                const element = document.getElementById('step-' + index);
                element.classList.toggle('opacity-40', index > step);
                element.classList.toggle('text-primary', index === step);
            });
        }

        function showStatus(message) {
            const pill = document.getElementById('status-pill');
            document.getElementById('status-text').textContent = message;
            pill.classList.remove('hidden');
            pill.classList.add('flex');
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const inner = document.getElementById('toast-inner');
            document.getElementById('toast-msg').textContent = message;
            
            let bgClass = '';
            if (type === 'success') {
                bgClass = 'bg-emerald-600 text-white';
            } else if (type === 'error' || type === 'danger') {
                bgClass = 'bg-red-600 text-white';
            } else if (type === 'warning') {
                bgClass = 'bg-amber-500 text-white';
            } else {
                bgClass = 'bg-slate-800 text-white';
            }
            
            inner.className = `alert shadow-lg text-sm py-2.5 px-4 font-semibold ${bgClass}`;
            toast.classList.remove('hidden');

            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => toast.classList.add('hidden'), 5000);
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatFileSize(bytes) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }

        setExportButton('Format DOCX', true);
        showEmptyState();
    </script>
</body>
</html>
