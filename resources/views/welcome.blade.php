<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocFormatter — DOCX Style Reformatter</title>
    <meta name="description" content="Upload a DOCX file, reformat its style, preview and export with ease.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Drop zone animation */
        .drop-active { border-color: oklch(var(--p)) !important; background: oklch(var(--p) / .06) !important; }

        /* Sidebar panel scroll */
        .sidebar-scroll { scrollbar-width: thin; scrollbar-color: oklch(var(--b3)) transparent; }

        /* Preview area */
        #preview-frame {
            font-family: var(--preview-font, 'Times New Roman', serif);
            font-size: var(--preview-size, 13pt);
            line-height: var(--preview-line, 1.5);
            color: oklch(var(--bc));
        }

        /* Skeleton pulse for loading state */
        @keyframes shimmer {
            0% { opacity: .4 } 50% { opacity: .8 } 100% { opacity: .4 }
        }
        .shimmer { animation: shimmer 1.6s ease-in-out infinite; }

        /* Sidebar slide transition */
        #sidebar {
            transition: width 0.25s cubic-bezier(.4,0,.2,1),
                        opacity 0.2s ease,
                        padding 0.25s ease;
            overflow: hidden;
        }
        #sidebar.collapsed {
            width: 0 !important;
            opacity: 0;
            padding: 0;
            border: none;
        }

        /* Sidebar toggle button icon animation */
        #sidebar-toggle svg {
            transition: transform 0.25s ease;
        }
        #sidebar-toggle.collapsed svg {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="font-sans antialiased min-h-screen bg-base-200 flex flex-col">

    <!-- ═══════════════════════ TOPBAR ═══════════════════════ -->
    <header class="h-14 flex items-center justify-between px-4 bg-base-100 border-b border-base-300 shrink-0 z-40">
        <!-- Left: sidebar toggle + logo -->
        <div class="flex items-center gap-2">
            <!-- Sidebar toggle button -->
            <button id="sidebar-toggle"
                    onclick="toggleSidebar()"
                    class="btn btn-ghost btn-sm btn-square"
                    title="Toggle sidebar">
                <!-- Panel-left icon -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12H12m-8.25 5.25h16.5"/>
                </svg>
            </button>

            <!-- Logo -->
            <a href="#" class="flex items-center gap-2.5 font-black text-lg tracking-tight">
                <span class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center text-primary-content shadow">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                        <path d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0016.5 9h-1.875a1.875 1.875 0 01-1.875-1.875V5.25A3.75 3.75 0 009 1.5H5.625z"/>
                        <path d="M12.971 1.816A5.23 5.23 0 0114.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 013.434 1.279 9.768 9.768 0 00-6.963-6.963z"/>
                    </svg>
                </span>
                <span>Doc<span class="text-primary">Formatter</span></span>
            </a>
        </div>

        <!-- Status pill -->
        <div id="status-pill" class="hidden items-center gap-2 px-3 py-1 rounded-full bg-success/10 text-success text-xs font-semibold border border-success/20">
            <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
            <span id="status-text">File loaded</span>
        </div>

        <!-- Right: theme toggle + step indicators -->
        <div class="flex items-center gap-4">
            <!-- Step breadcrumb -->
            <div class="hidden sm:flex items-center gap-1 text-xs font-medium text-base-content/50">
                <span id="step-1" class="flex items-center gap-1 transition-colors">
                    <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-[10px] font-bold">1</span>
                    Upload
                </span>
                <span class="mx-1">›</span>
                <span id="step-2" class="flex items-center gap-1 transition-colors opacity-40">
                    <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-[10px] font-bold">2</span>
                    Format
                </span>
                <span class="mx-1">›</span>
                <span id="step-3" class="flex items-center gap-1 transition-colors opacity-40">
                    <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-[10px] font-bold">3</span>
                    Export
                </span>
            </div>
        </div>
    </header>

    <!-- ═══════════════════════ MAIN LAYOUT ═══════════════════════ -->
    <div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 3.5rem)">

        <!-- ───────── LEFT SIDEBAR ───────── -->
        <aside id="sidebar" class="w-80 shrink-0 bg-base-100 border-r border-base-300 flex flex-col overflow-y-auto sidebar-scroll">

            <!-- ① Upload Section -->
            <section class="p-5 border-b border-base-300">
                <h2 class="text-xs font-bold uppercase tracking-widest text-base-content/40 mb-3">① Upload Document</h2>

                <!-- Drop Zone -->
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

                <!-- File Info (hidden until file loaded) -->
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
            </section>

        </aside>

        <!-- ───────── RIGHT: PREVIEW ───────── -->
        <main class="flex-1 flex flex-col overflow-hidden bg-base-200">

            <!-- Preview Topbar -->
            <div class="h-12 flex items-center justify-between px-5 bg-base-100 border-b border-base-300 shrink-0">
                <div class="flex items-center gap-2 text-sm font-semibold text-base-content/60">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Preview
                    <span id="preview-filename" class="text-xs font-normal text-base-content/30"></span>
                </div>

                <!-- Export Button -->
                <button id="btn-export" onclick="exportDocument()"
                        class="btn btn-success btn-sm gap-2 shadow-md disabled:opacity-40"
                        disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Export DOCX
                </button>
            </div>

            <!-- Page Canvas -->
            <div class="flex-1 overflow-y-auto p-6 lg:p-10 sidebar-scroll">

                <!-- Empty State -->
                <div id="empty-state" class="h-full flex flex-col items-center justify-center text-center">
                    <div class="w-24 h-24 rounded-2xl bg-base-100 border-2 border-dashed border-base-300 flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-10 h-10 text-base-content/20">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                    </div>
                    <p class="font-bold text-base-content/40 text-lg">No document loaded</p>
                    <p class="text-sm text-base-content/25 mt-1">Upload a DOCX file to see the preview here</p>
                </div>

                <!-- Loading Skeleton -->
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

                <!-- A4 Document Preview -->
                <div id="doc-preview" class="hidden max-w-[794px] mx-auto">
                    <div class="bg-white text-gray-900 shadow-2xl rounded-sm"
                         style="min-height: 1123px;"
                         id="preview-page">
                        <div id="preview-frame" class="p-10 leading-relaxed"
                             style="font-family: 'Times New Roman', serif; font-size: 13pt; line-height: 1.5; text-align: left;">
                            <!-- Content injected by JS -->
                        </div>
                    </div>
                    <p class="text-center text-xs text-base-content/30 mt-4">A4 Preview — 794 × 1123 px</p>
                </div>

            </div>
        </main>
    </div>

    <!-- ═══════════════════════ TOAST ═══════════════════════ -->
    <div id="toast" class="toast toast-top toast-end z-[999] hidden">
        <div id="toast-inner" class="alert alert-success shadow-lg text-sm py-2.5 px-4">
            <span id="toast-msg">Done!</span>
        </div>
    </div>

    <!-- ═══════════════════════ SCRIPTS ═══════════════════════ -->
    <script>
    // ─── Sidebar toggle ──────────────────────────────
    let sidebarOpen = true;

    function toggleSidebar() {
        sidebarOpen = !sidebarOpen;
        const sidebar = document.getElementById('sidebar');
        const btn     = document.getElementById('sidebar-toggle');
        sidebar.classList.toggle('collapsed', !sidebarOpen);
        btn.classList.toggle('collapsed', !sidebarOpen);
        btn.title = sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar';
    }

    // ─── State ───────────────────────────────────────
    let currentFile = null;
    let formattedContent = '';

    // ─── Drag & Drop ────────────────────────────────
    function handleDragOver(e) {
        e.preventDefault();
        document.getElementById('drop-zone').classList.add('drop-active');
    }
    function handleDragLeave(e) {
        document.getElementById('drop-zone').classList.remove('drop-active');
    }
    function handleDrop(e) {
        e.preventDefault();
        document.getElementById('drop-zone').classList.remove('drop-active');
        const file = e.dataTransfer.files[0];
        if (file) loadFile(file);
    }
    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (file) loadFile(file);
    }

    function loadFile(file) {
        if (!file.name.endsWith('.docx')) {
            showToast('Please upload a .docx file', 'error');
            return;
        }
        currentFile = file;

        // Show file info
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-size').textContent = (file.size / 1024).toFixed(1) + ' KB';
        document.getElementById('file-info').classList.remove('hidden');
        document.getElementById('drop-zone').classList.add('hidden');
        document.getElementById('preview-filename').textContent = '— ' + file.name;

        // Enable controls
        document.getElementById('btn-apply').removeAttribute('disabled');

        // Update steps
        setStep(2);
        showStatus('File loaded — configure style & apply');

        // Auto-apply with defaults to show preview
        showLoading();
        setTimeout(() => {
            generatePreview();
        }, 800);
    }

    function clearFile() {
        currentFile = null;
        document.getElementById('file-info').classList.add('hidden');
        document.getElementById('drop-zone').classList.remove('hidden');
        document.getElementById('file-input').value = '';
        document.getElementById('btn-export').setAttribute('disabled', '');
        document.getElementById('doc-preview').classList.add('hidden');
        document.getElementById('empty-state').classList.remove('hidden');
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('preview-filename').textContent = '';
        document.getElementById('status-pill').classList.add('hidden');
        setStep(1);
    }

    // ─── Apply & Preview ─────────────────────────────
    function applyFormat() {
        if (!currentFile) return;
        showLoading();
        setTimeout(() => {
            generatePreview();
            showToast('Formatting applied!', 'success');
            setStep(3);
            document.getElementById('btn-export').removeAttribute('disabled');
        }, 600);
    }

    function generatePreview() {
        // In a real implementation this would parse the DOCX via a backend API.
        // For UI demonstration we render a realistic placeholder document.
        const frame = document.getElementById('preview-frame');
        const fname = currentFile ? currentFile.name.replace('.docx', '') : 'Document';

        formattedContent = `
            <h1 style="font-size:1.6em; margin-bottom:0.4em;">${fname}</h1>
            <p style="color:#666; font-size:0.9em; margin-bottom:1.5em;">Last modified · ${new Date().toLocaleDateString('vi-VN')}</p>

            <h2 style="font-size:1.2em; margin-top:1.8em; margin-bottom:0.5em;">1. Introduction</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>

            <p style="margin-top:0.8em;">Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

            <h2 style="font-size:1.2em; margin-top:1.8em; margin-bottom:0.5em;">2. Methodology</h2>
            <p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.</p>

            <p style="margin-top:0.8em;">Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet.</p>

            <h2 style="font-size:1.2em; margin-top:1.8em; margin-bottom:0.5em;">3. Results</h2>
            <p>At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis praesentium voluptatum deleniti atque corrupti quos dolores et quas molestias excepturi sint occaecati cupiditate non provident.</p>

            <h2 style="font-size:1.2em; margin-top:1.8em; margin-bottom:0.5em;">4. Conclusion</h2>
            <p>Nam libero tempore, cum soluta nobis est eligendi optio cumque nihil impedit quo minus id quod maxime placeat facere possimus, omnis voluptas assumenda est, omnis dolor repellendus.</p>
        `;

        frame.innerHTML = formattedContent;

        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('empty-state').classList.add('hidden');
        document.getElementById('doc-preview').classList.remove('hidden');
    }

    // ─── Export ──────────────────────────────────────
    function exportDocument() {
        if (!currentFile) return;
        showToast('Preparing export... (connect backend to generate DOCX)', 'info');
        // Real implementation: POST formatted options to Laravel controller → return DOCX blob
    }

    // ─── UI Helpers ──────────────────────────────────
    function showLoading() {
        document.getElementById('empty-state').classList.add('hidden');
        document.getElementById('doc-preview').classList.add('hidden');
        document.getElementById('loading-state').classList.remove('hidden');
    }

    function setStep(n) {
        [1,2,3].forEach(i => {
            document.getElementById('step-' + i).classList.toggle('opacity-40', i > n);
            document.getElementById('step-' + i).classList.toggle('text-primary', i === n);
        });
    }

    function showStatus(msg) {
        const pill = document.getElementById('status-pill');
        document.getElementById('status-text').textContent = msg;
        pill.classList.remove('hidden');
        pill.classList.add('flex');
    }

    let toastTimer;
    function showToast(msg, type = 'success') {
        const toast = document.getElementById('toast');
        const inner = document.getElementById('toast-inner');
        document.getElementById('toast-msg').textContent = msg;
        inner.className = `alert alert-${type} shadow-lg text-sm py-2.5 px-4`;
        toast.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.add('hidden'), 3000);
    }
    </script>

</body>
</html>
