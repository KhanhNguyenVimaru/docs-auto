<?php

namespace App\Services;

use App\Helpers\DocumentFormatHelper;
use App\Models\DocumentJob;
use App\Models\DocumentTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\Style\Section as SectionStyle;
use PhpOffice\PhpWord\Writer\HTML as HtmlWriter;

class DocumentProcessingService
{
    public function __construct(
        private readonly DocumentTemplateService $templateService
    ) {
    }

    public function process(array $files, ?DocumentTemplate $template = null, array $options = []): DocumentJob
    {
        $effectiveTemplate = $this->resolveTemplate($template);
        $job = $this->createJob($effectiveTemplate, $files, $options, 'processing');

        $outputs = [];
        $reports = [];
        $processed = 0;
        $failed = 0;
        $previewHtml = null;

        foreach ($files as $index => $file) {
            try {
                $stored = $this->storeUploadedFile($file, 'document-inputs');
                $sourcePath = Storage::disk('local')->path($stored['path']);
                $styleMetadata = $this->extractParagraphStyleMetadata($sourcePath);
                $phpWord = IOFactory::load($sourcePath);
                $formatResult = $this->formatDocument($phpWord, $effectiveTemplate, $styleMetadata);
                $output = $this->storeFormattedDocument($phpWord, $file, $options, $index);
                $previewHtml ??= $this->renderPreviewHtml($phpWord);
                $validation = $this->validatePhpWordDocument($phpWord, $effectiveTemplate, $styleMetadata);

                $outputs[] = $output;
                $reports[] = [
                    'source' => $stored,
                    'output' => $output,
                    'formatting' => $formatResult,
                    'validation' => $validation,
                ];

                ++$processed;
            } catch (\Throwable $throwable) {
                ++$failed;
                Log::error('Document processing failed.', [
                    'source_name' => $file instanceof UploadedFile ? $file->getClientOriginalName() : null,
                    'exception' => $throwable,
                ]);

                $errorMessage = trim($throwable->getMessage()) !== ''
                    ? $throwable->getMessage()
                    : sprintf(
                        'Processing failed with %s.',
                        class_basename($throwable)
                    );

                $reports[] = [
                    'source_name' => $file instanceof UploadedFile ? $file->getClientOriginalName() : null,
                    'error' => $errorMessage,
                ];
            }
        }

        $status = $failed > 0 && $processed === 0
            ? 'failed'
            : ($failed > 0 ? 'completed_with_warnings' : 'completed');

        $jobErrorMessage = $status === 'failed'
            ? collect($reports)
                ->pluck('error')
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->first()
            : null;

        $job->update([
            'status' => $status,
            'output_files' => $outputs,
            'validation_report' => $reports,
            'processed_count' => $processed,
            'failed_count' => $failed,
            'error_message' => $jobErrorMessage,
        ]);
        $job = $job->refresh();
        $job->setAttribute('preview_html', $previewHtml);

        return $job;
    }

    public function validateFile(UploadedFile $file, ?DocumentTemplate $template = null): DocumentJob
    {
        $effectiveTemplate = $this->resolveTemplate($template);
        $job = $this->createJob($effectiveTemplate, [$file], [], 'validating');

        $stored = $this->storeUploadedFile($file, 'document-validation-inputs');
        $sourcePath = Storage::disk('local')->path($stored['path']);
        $styleMetadata = $this->extractParagraphStyleMetadata($sourcePath);
        $phpWord = IOFactory::load($sourcePath);
        $report = $this->validatePhpWordDocument($phpWord, $effectiveTemplate, $styleMetadata);

        $job->update([
            'status' => 'validated',
            'input_files' => [$stored],
            'validation_report' => [$report],
            'processed_count' => 1,
            'failed_count' => 0,
        ]);

        return $job->refresh();
    }

    private function resolveTemplate(?DocumentTemplate $template): DocumentTemplate
    {
        return $template ?? $this->templateService->defaultTemplate();
    }

    private function createJob(DocumentTemplate $template, array $files, array $options, string $status): DocumentJob
    {
        $inputFiles = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $inputFiles[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'extension' => $file->getClientOriginalExtension(),
                    'size' => $file->getSize(),
                ];
            }
        }

        return DocumentJob::create([
            'document_template_id' => $template->exists ? $template->id : null,
            'name' => $options['name'] ?? null,
            'status' => $status,
            'input_files' => $inputFiles,
            'options' => $options,
            'processed_count' => 0,
            'failed_count' => 0,
        ]);
    }

    private function storeUploadedFile(UploadedFile $file, string $directory): array
    {
        $storedName = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = Storage::disk('local')->putFileAs($directory, $file, $storedName);

        return [
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'path' => $path,
            'absolute_path' => Storage::disk('local')->path($path),
        ];
    }

    private function storeFormattedDocument(PhpWord $phpWord, UploadedFile $file, array $options, int $index): array
    {
        $overwrite = ($options['output_mode'] ?? 'copy') === 'overwrite';
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName) ?: 'document';
        $fileName = $overwrite ? $safeBaseName . '.docx' : $safeBaseName . '-formatted-' . ($index + 1) . '.docx';
        $directory = 'document-exports';
        $relativePath = $directory . '/' . $fileName;
        $absolutePath = Storage::disk('public')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $phpWord->save($absolutePath, 'Word2007');

        return [
            'file_name' => $fileName,
            'path' => $relativePath,
        ];
    }

    private function renderPreviewHtml(PhpWord $phpWord): string
    {
        $writer = new HtmlWriter($phpWord);
        $writer->setDefaultGenericFont('Times New Roman');
        $writer->setDefaultWhiteSpace('normal');

        $html = $writer->getContent();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return $html;
        }

        $fragment = '';
        foreach ($body->childNodes as $child) {
            $fragment .= $dom->saveHTML($child);
        }

        return trim($fragment) !== '' ? $fragment : $html;
    }

    private function formatDocument(PhpWord $phpWord, DocumentTemplate $template, array $styleMetadata): array
    {
        $this->applyGlobalStyles($phpWord, $template);

        $state = [
            'heading_counters' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
        ];

        $report = [
            'sections' => [],
            'numbered_headings' => 0,
        ];

        foreach ($phpWord->getSections() as $sectionIndex => $section) {
            $sectionPath = 'Section ' . ($sectionIndex + 1);
            $this->applySectionStyle($section->getStyle(), $template);
            $this->traverseContainer($section, $template, $styleMetadata, $state, $report, $sectionPath, true, null, true);

            foreach ($section->getHeaders() as $headerIndex => $header) {
                $this->traverseContainer($header, $template, $styleMetadata, $state, $report, $sectionPath . ' > Header ' . ($headerIndex + 1), true, null, true);
            }

            foreach ($section->getFooters() as $footerIndex => $footer) {
                $this->traverseContainer($footer, $template, $styleMetadata, $state, $report, $sectionPath . ' > Footer ' . ($footerIndex + 1), true, null, true);
            }
        }

        return $report;
    }

    private function applyGlobalStyles(PhpWord $phpWord, DocumentTemplate $template): void
    {
        $phpWord->setDefaultFontName($template->font_name);
        $phpWord->setDefaultFontSize((int) $template->font_size);
        $phpWord->setDefaultParagraphStyle($this->buildParagraphStyleForLevel($template));

        $headingFont = [
            'name' => $template->font_name,
            'size' => (int) $template->font_size,
            'bold' => true,
        ];

        foreach ([1, 2, 3, 4] as $depth) {
            $phpWord->addTitleStyle($depth, $headingFont, $this->buildParagraphStyleForLevel($template, $depth));
        }
    }

    private function applySectionStyle(?SectionStyle $style, DocumentTemplate $template): void
    {
        if ($style === null) {
            return;
        }

        $style->setMarginTop(DocumentFormatHelper::cmToTwips((float) $template->top_margin_cm));
        $style->setMarginBottom(DocumentFormatHelper::cmToTwips((float) $template->bottom_margin_cm));
        $style->setMarginLeft(DocumentFormatHelper::cmToTwips((float) $template->left_margin_cm));
        $style->setMarginRight(DocumentFormatHelper::cmToTwips((float) $template->right_margin_cm));
    }

    private function traverseContainer(object $container, DocumentTemplate $template, array $styleMetadata, array &$state, array &$report, string $path, bool $applyFormatting, ?int $headingLevelContext = null, bool $isTopLevel = false): void
    {
        if ($container instanceof Table) {
            foreach ($container->getRows() as $rowIndex => $row) {
                $this->traverseRow($row, $template, $styleMetadata, $state, $report, $path . ' > TableRow ' . ($rowIndex + 1), $applyFormatting, $headingLevelContext);
            }

            return;
        }

        if (! method_exists($container, 'getElements')) {
            return;
        }

        foreach ($container->getElements() as $elementIndex => $element) {
            $elementPath = $path . ' > ' . class_basename($element) . ' ' . ($elementIndex + 1);
            $this->traverseElement($element, $template, $styleMetadata, $state, $report, $elementPath, $applyFormatting, $headingLevelContext, $isTopLevel);
        }
    }

    private function traverseRow(Row $row, DocumentTemplate $template, array $styleMetadata, array &$state, array &$report, string $path, bool $applyFormatting, ?int $headingLevelContext = null): void
    {
        foreach ($row->getCells() as $cellIndex => $cell) {
            $this->traverseCell($cell, $template, $styleMetadata, $state, $report, $path . ' > Cell ' . ($cellIndex + 1), $applyFormatting, $headingLevelContext);
        }
    }

    private function traverseCell(Cell $cell, DocumentTemplate $template, array $styleMetadata, array &$state, array &$report, string $path, bool $applyFormatting, ?int $headingLevelContext = null): void
    {
        if (! method_exists($cell, 'getElements')) {
            return;
        }

        foreach ($cell->getElements() as $elementIndex => $element) {
            $elementPath = $path . ' > ' . class_basename($element) . ' ' . ($elementIndex + 1);
            $this->traverseElement($element, $template, $styleMetadata, $state, $report, $elementPath, $applyFormatting, $headingLevelContext, true);
        }
    }

    private function traverseElement(object $element, DocumentTemplate $template, array $styleMetadata, array &$state, array &$report, string $path, bool $applyFormatting, ?int $headingLevelContext = null, bool $isTopLevel = false): void
    {
        if ($element instanceof Title) {
            $this->handleTitle($element, $template, $state, $report, $path, $applyFormatting);

            return;
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            if ($applyFormatting) {
                $this->processImageElement($element, $template);
            }
            return;
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
            if ($applyFormatting) {
                $textObj = $element->getTextObject();
                if ($textObj !== null) {
                    $textObj->setParagraphStyle([
                        'spaceAfter' => 0,
                        'spaceBefore' => 0,
                        'lineHeight' => 1.0,
                    ]);
                }
            }
            $this->traverseContainer($element, $template, $styleMetadata, $state, $report, $path, $applyFormatting, $headingLevelContext, false);
            return;
        }

        $headingLevel = $this->resolveHeadingLevel($element, $styleMetadata) ?? $headingLevelContext;

        if ($element instanceof TextRun) {
            $hasImage = $this->hasImage($element);

            if ($applyFormatting) {
                if ($hasImage) {
                    $leftMargin = (float) $template->left_margin_cm;
                    $rightMargin = (float) $template->right_margin_cm;
                    $printableWidthPt = (21.0 - $leftMargin - $rightMargin) * 28.35;

                    $hasNarrow = false;
                    foreach ($element->getElements() as $child) {
                        if ($child instanceof \PhpOffice\PhpWord\Element\Image) {
                            $this->processImageElement($child, $template);
                            $style = $child->getStyle();
                            if ($style !== null && (float) $style->getWidth() < $printableWidthPt - 0.5) {
                                $hasNarrow = true;
                            }
                        }
                    }

                    $element->setParagraphStyle([
                        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                        'spaceAfter' => (int) round($template->line_spacing * $template->font_size * 20),
                    ]);
                } else {
                    if ($element instanceof \PhpOffice\PhpWord\Element\ListItemRun) {
                        $element->setParagraphStyle([
                            'spaceAfter' => 0,
                            'spaceBefore' => 0,
                            'lineHeight' => 1.0,
                        ]);
                    } else {
                        $element->setParagraphStyle($this->buildParagraphStyleForLevel($template, $headingLevel));

                        if ($isTopLevel && $headingLevel === null) {
                            $elements = $element->getElements();
                            if (count($elements) > 0 && $elements[0] instanceof Text) {
                                $firstText = $elements[0];
                                $val = $firstText->getText();
                                if (!str_starts_with($val, "\t")) {
                                    $firstText->setText("\t" . $val);
                                }
                            } else {
                                $this->prependTabToTextRun($element, $template);
                            }
                        }
                    }
                }
            }

            $this->traverseContainer($element, $template, $styleMetadata, $state, $report, $path, $applyFormatting, $headingLevel, false);

            return;
        }

        if ($element instanceof Table) {
            $this->traverseContainer($element, $template, $styleMetadata, $state, $report, $path, $applyFormatting, $headingLevel, false);

            return;
        }

        if ($element instanceof Text) {
            if ($applyFormatting && $isTopLevel && $headingLevel === null) {
                $val = $element->getText();
                if (!str_starts_with($val, "\t")) {
                    $element->setText("\t" . $val);
                }
            }
            $this->handleTextElement($element, $template, $headingLevel, $report, $path, $applyFormatting);

            return;
        }

        if ($element instanceof Header || $element instanceof Footer) {
            $this->traverseContainer($element, $template, $styleMetadata, $state, $report, $path, $applyFormatting, $headingLevel, true);

            return;
        }

        if (method_exists($element, 'setFontStyle') && method_exists($element, 'setParagraphStyle')) {
            $hasImage = $this->hasImage($element);
            if ($applyFormatting && $isTopLevel && $headingLevel === null && !$hasImage) {
                if (method_exists($element, 'getText') && method_exists($element, 'setText')) {
                    $val = $element->getText();
                    if (!str_starts_with($val, "\t")) {
                        $element->setText("\t" . $val);
                    }
                }
            }
            $this->handleTextLikeElement($element, $template, $headingLevel, $report, $path, $applyFormatting);

            return;
        }

        if (method_exists($element, 'getElements')) {
            $this->traverseContainer($element, $template, $styleMetadata, $state, $report, $path, $applyFormatting, $headingLevel, false);
        }
    }

    private function handleTitle(Title $title, DocumentTemplate $template, array &$state, array &$report, string $path, bool $applyFormatting): void
    {
        $depth = max(1, min(4, $title->getDepth()));
        $this->incrementHeadingCounters($state['heading_counters'], $depth);

        $titleText = $this->readTitleText($title);
        if (is_string($titleText) && $template->apply_numbering) {
            $prefixed = $this->prefixHeadingText($titleText, $state['heading_counters'], $depth);
            if ($prefixed !== $titleText) {
                $this->writeTitleText($title, $prefixed);
                ++$report['numbered_headings'];
            }
        } elseif ($titleText instanceof TextRun) {
            if ($template->apply_numbering) {
                $prefix = $this->buildHeadingPrefix($state['heading_counters'], $depth);
                $this->prependPrefixToTextRun($titleText, $prefix, $template);
                ++$report['numbered_headings'];
            }

            $this->traverseContainer($titleText, $template, [], $state, $report, $path . ' > TitleText', $applyFormatting, $depth, false);
        }
    }

    private function handleTextElement(Text $element, DocumentTemplate $template, ?int $headingLevel, array &$report, string $path, bool $applyFormatting): void
    {
        $isHeading = $headingLevel !== null;

        if ($applyFormatting) {
            $existing = $element->getFontStyle();
            $sourceStyle = $existing instanceof Font ? [
                'bold' => $existing->isBold(),
                'italic' => $existing->isItalic(),
                'underline' => $existing->getUnderline(),
                'color' => $existing->getColor(),
            ] : null;

            $fontStyle = DocumentFormatHelper::buildFontStyle(
                $template->font_name,
                (int) $template->font_size,
                $sourceStyle,
                $isHeading ? true : null,
            );

            $paragraphStyle = $this->buildParagraphStyleForLevel($template, $headingLevel);

            $element->setFontStyle($fontStyle, $paragraphStyle);
            $element->setParagraphStyle($paragraphStyle);
        }

        $this->validateTextElement($element, $template, $isHeading, $report, $path);
    }

    private function handleTextLikeElement(object $element, DocumentTemplate $template, ?int $headingLevel, array &$report, string $path, bool $applyFormatting): void
    {
        $isHeading = $headingLevel !== null;

        if ($applyFormatting) {
            $existing = method_exists($element, 'getFontStyle') ? $element->getFontStyle() : null;
            $sourceStyle = $existing instanceof Font ? [
                'bold' => $existing->isBold(),
                'italic' => $existing->isItalic(),
                'underline' => $existing->getUnderline(),
                'color' => $existing->getColor(),
            ] : null;

            $fontStyle = DocumentFormatHelper::buildFontStyle(
                $template->font_name,
                (int) $template->font_size,
                $sourceStyle,
                $isHeading ? true : null,
            );

            $paragraphStyle = $this->buildParagraphStyleForLevel($template, $headingLevel);

            $element->setFontStyle($fontStyle, $paragraphStyle);
            $element->setParagraphStyle($paragraphStyle);
        }

        if (method_exists($element, 'getFontStyle')) {
            $this->validateTextLikeElement($element, $template, $isHeading, $report, $path);
        }
    }

    private function validatePhpWordDocument(PhpWord $phpWord, DocumentTemplate $template, array $styleMetadata): array
    {
        $issues = [];
        $state = ['heading_counters' => [1 => 0, 2 => 0, 3 => 0, 4 => 0]];

        foreach ($phpWord->getSections() as $sectionIndex => $section) {
            $sectionPath = 'Section ' . ($sectionIndex + 1);
            $this->validateSectionStyle($section->getStyle(), $template, $sectionPath, $issues);
            $this->walkValidationContainer($section, $template, $styleMetadata, $state, $issues, $sectionPath, null, true);

            foreach ($section->getHeaders() as $headerIndex => $header) {
                $this->walkValidationContainer($header, $template, $styleMetadata, $state, $issues, $sectionPath . ' > Header ' . ($headerIndex + 1), null, true);
            }

            foreach ($section->getFooters() as $footerIndex => $footer) {
                $this->walkValidationContainer($footer, $template, $styleMetadata, $state, $issues, $sectionPath . ' > Footer ' . ($footerIndex + 1), null, true);
            }
        }

        return [
            'template' => $template->name,
            'compliant' => count($issues) === 0,
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }

    private function validateSectionStyle(?SectionStyle $style, DocumentTemplate $template, string $path, array &$issues): void
    {
        if ($style === null) {
            return;
        }

        $this->compareNumeric(
            $issues,
            $path,
            'top_margin_cm',
            (float) $template->top_margin_cm,
            DocumentFormatHelper::twipsToCm((int) $style->getMarginTop())
        );
        $this->compareNumeric(
            $issues,
            $path,
            'bottom_margin_cm',
            (float) $template->bottom_margin_cm,
            DocumentFormatHelper::twipsToCm((int) $style->getMarginBottom())
        );
        $this->compareNumeric(
            $issues,
            $path,
            'left_margin_cm',
            (float) $template->left_margin_cm,
            DocumentFormatHelper::twipsToCm((int) $style->getMarginLeft())
        );
        $this->compareNumeric(
            $issues,
            $path,
            'right_margin_cm',
            (float) $template->right_margin_cm,
            DocumentFormatHelper::twipsToCm((int) $style->getMarginRight())
        );
    }

    private function walkValidationContainer(object $container, DocumentTemplate $template, array $styleMetadata, array &$state, array &$issues, string $path, ?int $headingLevelContext = null, bool $isTopLevel = false): void
    {
        if ($container instanceof Table) {
            foreach ($container->getRows() as $rowIndex => $row) {
                $this->walkValidationRow($row, $template, $styleMetadata, $state, $issues, $path . ' > TableRow ' . ($rowIndex + 1), $headingLevelContext);
            }

            return;
        }

        if (! method_exists($container, 'getElements')) {
            return;
        }

        foreach ($container->getElements() as $elementIndex => $element) {
            $elementPath = $path . ' > ' . class_basename($element) . ' ' . ($elementIndex + 1);
            $this->walkValidationElement($element, $template, $styleMetadata, $state, $issues, $elementPath, $headingLevelContext, $isTopLevel);
        }
    }

    private function walkValidationRow(Row $row, DocumentTemplate $template, array $styleMetadata, array &$state, array &$issues, string $path, ?int $headingLevelContext = null): void
    {
        foreach ($row->getCells() as $cellIndex => $cell) {
            $this->walkValidationCell($cell, $template, $styleMetadata, $state, $issues, $path . ' > Cell ' . ($cellIndex + 1), $headingLevelContext);
        }
    }

    private function walkValidationCell(Cell $cell, DocumentTemplate $template, array $styleMetadata, array &$state, array &$issues, string $path, ?int $headingLevelContext = null): void
    {
        if (! method_exists($cell, 'getElements')) {
            return;
        }

        foreach ($cell->getElements() as $elementIndex => $element) {
            $elementPath = $path . ' > ' . class_basename($element) . ' ' . ($elementIndex + 1);
            $this->walkValidationElement($element, $template, $styleMetadata, $state, $issues, $elementPath, $headingLevelContext, true);
        }
    }

    private function walkValidationElement(object $element, DocumentTemplate $template, array $styleMetadata, array &$state, array &$issues, string $path, ?int $headingLevelContext = null, bool $isTopLevel = false): void
    {
        if ($element instanceof Title) {
            $this->validateTitleElement($element, $template, $state, $issues, $path);

            return;
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            $style = $element->getStyle();
            if ($style !== null) {
                $leftMargin = (float) $template->left_margin_cm;
                $rightMargin = (float) $template->right_margin_cm;
                $printableWidthPt = (21.0 - $leftMargin - $rightMargin) * 28.35;
                $width = (float) $style->getWidth();

                if ($width > $printableWidthPt + 1.0) {
                    $issues[] = [
                        'path' => $path,
                        'field' => 'image_width',
                        'expected' => $printableWidthPt,
                        'actual' => $width,
                        'message' => 'Direct image width is wider than the printable area and was not scaled down.',
                    ];
                }

                if ($width < $printableWidthPt - 1.0) {
                    $actualAlignment = $style->getAlignment();
                    $expectedAlignment = \PhpOffice\PhpWord\SimpleType\Jc::CENTER;
                    if ($actualAlignment !== $expectedAlignment) {
                        $issues[] = [
                            'path' => $path,
                            'field' => 'alignment',
                            'expected' => $expectedAlignment,
                            'actual' => $actualAlignment,
                            'message' => 'Direct image style must be center aligned when narrower than printable area.',
                        ];
                    }
                }
            }
            return;
        }

        if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
            $paragraphStyle = null;
            $textObj = $element->getTextObject();
            if ($textObj !== null) {
                $paragraphStyle = $textObj->getParagraphStyle();
            }
            if ($paragraphStyle instanceof Paragraph) {
                $actualSpaceAfter = (int) ($paragraphStyle->getSpaceAfter() ?? 0);
                if ($actualSpaceAfter !== 0) {
                    $issues[] = [
                        'path' => $path,
                        'field' => 'space_after',
                        'expected' => 0,
                        'actual' => $actualSpaceAfter,
                        'message' => 'Bullet list item spacing after must be 0.',
                    ];
                }
            }
            $this->walkValidationContainer($element, $template, $styleMetadata, $state, $issues, $path, $headingLevelContext, false);
            return;
        }

        $headingLevel = $this->resolveHeadingLevel($element, $styleMetadata) ?? $headingLevelContext;

        if ($element instanceof TextRun) {
            if ($element instanceof \PhpOffice\PhpWord\Element\ListItemRun) {
                $paragraphStyle = $element->getParagraphStyle();
                if ($paragraphStyle instanceof Paragraph) {
                    $actualSpaceAfter = (int) ($paragraphStyle->getSpaceAfter() ?? 0);
                    if ($actualSpaceAfter !== 0) {
                        $issues[] = [
                            'path' => $path,
                            'field' => 'space_after',
                            'expected' => 0,
                            'actual' => $actualSpaceAfter,
                            'message' => 'Bullet list item spacing after must be 0.',
                        ];
                    }
                }
                $this->walkValidationContainer($element, $template, $styleMetadata, $state, $issues, $path, $headingLevel, false);
                return;
            }

            $hasImage = $this->hasImage($element);

            if ($hasImage) {
                $leftMargin = (float) $template->left_margin_cm;
                $rightMargin = (float) $template->right_margin_cm;
                $printableWidthPt = (21.0 - $leftMargin - $rightMargin) * 28.35;

                $hasNarrowImage = false;
                foreach ($element->getElements() as $child) {
                    if ($child instanceof \PhpOffice\PhpWord\Element\Image) {
                        $style = $child->getStyle();
                        if ($style !== null) {
                            $width = (float) $style->getWidth();
                            if ($width > $printableWidthPt + 1.0) {
                                $issues[] = [
                                    'path' => $path,
                                    'field' => 'image_width',
                                    'expected' => $printableWidthPt,
                                    'actual' => $width,
                                    'message' => 'Image width is wider than the printable area and was not scaled down.',
                                ];
                            }
                            if ($width < $printableWidthPt - 1.0) {
                                $hasNarrowImage = true;
                            }
                        }
                    }
                }

                $paragraphStyle = $element->getParagraphStyle();
                if ($paragraphStyle instanceof Paragraph) {
                    $expectedSpaceAfter = (int) round($template->line_spacing * $template->font_size * 20);
                    $actualSpaceAfter = (int) ($paragraphStyle->getSpaceAfter() ?? 0);
                    if (abs($actualSpaceAfter - $expectedSpaceAfter) > 20) {
                        $issues[] = [
                            'path' => $path,
                            'field' => 'space_after',
                            'expected' => $expectedSpaceAfter,
                            'actual' => $actualSpaceAfter,
                            'message' => 'Incorrect paragraph spacing after for image paragraph.',
                        ];
                    }

                    if ($hasNarrowImage) {
                        $actualAlignment = $paragraphStyle->getAlignment();
                        $expectedAlignment = \PhpOffice\PhpWord\SimpleType\Jc::CENTER;
                        if ($actualAlignment !== $expectedAlignment) {
                            $issues[] = [
                                'path' => $path,
                                'field' => 'alignment',
                                'expected' => $expectedAlignment,
                                'actual' => $actualAlignment,
                                'message' => 'Paragraph containing a narrow image must be center aligned.',
                            ];
                        }
                    }
                }

                if ($isTopLevel) {
                    $elements = $element->getElements();
                    $firstText = '';
                    if (count($elements) > 0 && $elements[0] instanceof Text) {
                        $firstText = $elements[0]->getText();
                    }
                    if (str_starts_with($firstText, "\t")) {
                        $issues[] = [
                            'path' => $path,
                            'field' => 'first_line_indent_tab',
                            'expected' => '',
                            'actual' => "\t",
                            'message' => 'Paragraph containing an image must not start with a tab character.',
                        ];
                    }
                }
            } else {
                $this->validateParagraphStyle($element->getParagraphStyle(), $template, $headingLevel, $issues, $path);

                if ($isTopLevel && $headingLevel === null) {
                    $elements = $element->getElements();
                    $firstText = '';
                    if (count($elements) > 0 && $elements[0] instanceof Text) {
                        $firstText = $elements[0]->getText();
                    }
                    if (!str_starts_with($firstText, "\t")) {
                        $issues[] = [
                            'path' => $path,
                            'field' => 'first_line_indent_tab',
                            'expected' => "\t",
                            'actual' => substr($firstText, 0, 1),
                            'message' => 'Paragraph must start with a tab character for indentation.',
                        ];
                    }
                }
            }

            $this->walkValidationContainer($element, $template, $styleMetadata, $state, $issues, $path, $headingLevel, false);

            return;
        }

        if ($element instanceof Text) {
            if ($isTopLevel && $headingLevel === null) {
                $text = $element->getText();
                if (!str_starts_with($text, "\t")) {
                    $issues[] = [
                        'path' => $path,
                        'field' => 'first_line_indent_tab',
                        'expected' => "\t",
                        'actual' => substr($text, 0, 1),
                        'message' => 'Paragraph must start with a tab character for indentation.',
                    ];
                }
            }
            $this->validateTextElement($element, $template, $headingLevel !== null, $issues, $path);

            return;
        }

        if (method_exists($element, 'getFontStyle') && method_exists($element, 'getParagraphStyle')) {
            $hasImage = $this->hasImage($element);
            if ($hasImage) {
                $leftMargin = (float) $template->left_margin_cm;
                $rightMargin = (float) $template->right_margin_cm;
                $printableWidthPt = (21.0 - $leftMargin - $rightMargin) * 28.35;

                $hasNarrowImage = false;
                if (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $child) {
                        if ($child instanceof \PhpOffice\PhpWord\Element\Image) {
                            $style = $child->getStyle();
                            if ($style !== null) {
                                $width = (float) $style->getWidth();
                                if ($width > $printableWidthPt + 1.0) {
                                    $issues[] = [
                                        'path' => $path,
                                        'field' => 'image_width',
                                        'expected' => $printableWidthPt,
                                        'actual' => $width,
                                        'message' => 'Image width is wider than the printable area and was not scaled down.',
                                    ];
                                }
                                if ($width < $printableWidthPt - 1.0) {
                                    $hasNarrowImage = true;
                                }
                            }
                        }
                    }
                }

                $paragraphStyle = $element->getParagraphStyle();
                if ($paragraphStyle instanceof Paragraph) {
                    $expectedSpaceAfter = (int) round($template->line_spacing * $template->font_size * 20);
                    $actualSpaceAfter = (int) ($paragraphStyle->getSpaceAfter() ?? 0);
                    if (abs($actualSpaceAfter - $expectedSpaceAfter) > 20) {
                        $issues[] = [
                            'path' => $path,
                            'field' => 'space_after',
                            'expected' => $expectedSpaceAfter,
                            'actual' => $actualSpaceAfter,
                            'message' => 'Incorrect paragraph spacing after for image paragraph.',
                        ];
                    }

                    if ($hasNarrowImage) {
                        $actualAlignment = $paragraphStyle->getAlignment();
                        $expectedAlignment = \PhpOffice\PhpWord\SimpleType\Jc::CENTER;
                        if ($actualAlignment !== $expectedAlignment) {
                            $issues[] = [
                                'path' => $path,
                                'field' => 'alignment',
                                'expected' => $expectedAlignment,
                                'actual' => $actualAlignment,
                                'message' => 'Paragraph containing a narrow image must be center aligned.',
                            ];
                        }
                    }
                }

                if ($isTopLevel && method_exists($element, 'getText')) {
                    $text = $element->getText();
                    if (str_starts_with($text, "\t")) {
                        $issues[] = [
                            'path' => $path,
                            'field' => 'first_line_indent_tab',
                            'expected' => '',
                            'actual' => "\t",
                            'message' => 'Paragraph containing an image must not start with a tab character.',
                        ];
                    }
                }
            } else {
                if ($isTopLevel && $headingLevel === null) {
                    if (method_exists($element, 'getText')) {
                        $text = $element->getText();
                        if (!str_starts_with($text, "\t")) {
                            $issues[] = [
                                'path' => $path,
                                'field' => 'first_line_indent_tab',
                                'expected' => "\t",
                                'actual' => substr($text, 0, 1),
                                'message' => 'Paragraph must start with a tab character for indentation.',
                            ];
                        }
                    }
                }
                $this->validateTextLikeElement($element, $template, $headingLevel !== null, $issues, $path);
            }

            return;
        }

        if (method_exists($element, 'getElements')) {
            $this->walkValidationContainer($element, $template, $styleMetadata, $state, $issues, $path, $headingLevel, false);
        }
    }

    private function validateTitleElement(Title $title, DocumentTemplate $template, array &$state, array &$issues, string $path): void
    {
        $depth = max(1, min(4, $title->getDepth()));
        $this->incrementHeadingCounters($state['heading_counters'], $depth);

        $text = $this->readTitleText($title);
        if (is_string($text) && $template->apply_numbering) {
            $expectedPrefix = $this->buildHeadingPrefix($state['heading_counters'], $depth);
            if (! $this->textHasHeadingPrefix($text, $expectedPrefix)) {
                $issues[] = [
                    'path' => $path,
                    'field' => 'heading_numbering',
                    'expected' => $expectedPrefix,
                    'actual' => $text,
                    'message' => 'Heading number is missing or incorrect.',
                ];
            }

            return;
        }

        if ($text instanceof TextRun) {
            $this->validateParagraphStyle($text->getParagraphStyle(), $template, $depth, $issues, $path);
            $this->walkValidationContainer($text, $template, [], $state, $issues, $path . ' > TitleText', $depth, false);
        }
    }

    private function validateTextElement(Text $element, DocumentTemplate $template, bool $isHeading, array &$issues, string $path): void
    {
        $this->validateTextLikeElement($element, $template, $isHeading, $issues, $path);
    }

    private function validateTextLikeElement(object $element, DocumentTemplate $template, bool $isHeading, array &$issues, string $path): void
    {
        $fontStyle = method_exists($element, 'getFontStyle') ? $element->getFontStyle() : null;
        $paragraphStyle = method_exists($element, 'getParagraphStyle') ? $element->getParagraphStyle() : null;

        if ($fontStyle instanceof Font) {
            if ($fontStyle->getName() !== $template->font_name) {
                $issues[] = [
                    'path' => $path,
                    'field' => 'font_name',
                    'expected' => $template->font_name,
                    'actual' => $fontStyle->getName(),
                    'message' => 'Incorrect font family.',
                ];
            }

            if ((int) $fontStyle->getSize() !== (int) $template->font_size) {
                $issues[] = [
                    'path' => $path,
                    'field' => 'font_size',
                    'expected' => (int) $template->font_size,
                    'actual' => $fontStyle->getSize(),
                    'message' => 'Incorrect font size.',
                ];
            }

            if ($isHeading && ! $fontStyle->isBold()) {
                $issues[] = [
                    'path' => $path,
                    'field' => 'font_bold',
                    'expected' => true,
                    'actual' => $fontStyle->isBold(),
                    'message' => 'Heading text must be bold.',
                ];
            }
        }

        $this->validateParagraphStyle($paragraphStyle, $template, $isHeading, $issues, $path);
    }

    private function validateParagraphStyle(Paragraph|string|null $paragraphStyle, DocumentTemplate $template, int|bool|null $headingLevel, array &$issues, string $path): void
    {
        if (! $paragraphStyle instanceof Paragraph) {
            return;
        }

        $isHeading = is_int($headingLevel) || $headingLevel === true;
        $resolvedHeadingLevel = is_int($headingLevel) ? $headingLevel : ($headingLevel ? 1 : null);

        $expectedAlignment = DocumentFormatHelper::normalizeAlignment(
            $isHeading ? $this->headingAlignmentForLevel($resolvedHeadingLevel) : $template->normal_alignment,
            $isHeading
        );

        if ($paragraphStyle->getAlignment() !== $expectedAlignment) {
            $issues[] = [
                'path' => $path,
                'field' => 'alignment',
                'expected' => $expectedAlignment,
                'actual' => $paragraphStyle->getAlignment(),
                'message' => 'Incorrect paragraph alignment.',
            ];
        }

        $expectedLineHeight = (float) $template->line_spacing;
        $actualLineHeight = (float) $paragraphStyle->getLineHeight();
        if ($actualLineHeight > 10.0) {
            $actualLineHeight = round($actualLineHeight / 240.0, 2);
        }
        if (abs($actualLineHeight - $expectedLineHeight) > 0.05) {
            $issues[] = [
                'path' => $path,
                'field' => 'line_height',
                'expected' => $expectedLineHeight,
                'actual' => $actualLineHeight,
                'message' => 'Incorrect line spacing.',
            ];
        }

        $expectedSpaceAfter = (int) round($template->line_spacing * $template->font_size * 20);
        $actualSpaceAfter = (int) ($paragraphStyle->getSpaceAfter() ?? 0);

        if (abs($actualSpaceAfter - $expectedSpaceAfter) > 20) {
            $issues[] = [
                'path' => $path,
                'field' => 'space_after',
                'expected' => $expectedSpaceAfter,
                'actual' => $actualSpaceAfter,
                'message' => 'Incorrect paragraph spacing after.',
            ];
        }

        $expectedIndent = $isHeading
            ? (float) $template->heading_first_line_indent_cm
            : 0.0;
        $actualIndent = (float) DocumentFormatHelper::twipsToCm((int) ($paragraphStyle->getIndentFirstLine() ?? 0));

        if (abs($actualIndent - $expectedIndent) > 0.05) {
            $issues[] = [
                'path' => $path,
                'field' => 'first_line_indent_cm',
                'expected' => $expectedIndent,
                'actual' => $actualIndent,
                'message' => 'Incorrect first-line indentation.',
            ];
        }
    }

    private function compareNumeric(array &$issues, string $path, string $field, float $expected, float $actual): void
    {
        if (abs($expected - $actual) > 0.05) {
            $issues[] = [
                'path' => $path,
                'field' => $field,
                'expected' => $expected,
                'actual' => $actual,
                'message' => 'Incorrect formatting value.',
            ];
        }
    }

    private function readTitleText(Title $title): string|TextRun
    {
        $reflection = new \ReflectionClass($title);
        $property = $reflection->getProperty('text');
        $property->setAccessible(true);

        return $property->getValue($title);
    }

    private function writeTitleText(Title $title, string $text): void
    {
        $reflection = new \ReflectionClass($title);
        $property = $reflection->getProperty('text');
        $property->setAccessible(true);
        $property->setValue($title, $text);
    }

    private function prefixHeadingText(string $text, array $counters, int $depth): string
    {
        $prefix = $this->buildHeadingPrefix($counters, $depth);

        if ($this->textHasHeadingPrefix($text, $prefix)) {
            return $text;
        }

        return $prefix . ' ' . ltrim($text);
    }

    private function textHasHeadingPrefix(string $text, string $prefix): bool
    {
        return (bool) preg_match('/^' . preg_quote($prefix, '/') . '(?:\s|\.|$)/u', ltrim($text));
    }

    private function buildHeadingPrefix(array $counters, int $depth): string
    {
        $segments = [];

        for ($i = 1; $i <= $depth; ++$i) {
            $segments[] = (string) max(1, (int) ($counters[$i] ?? 0));
        }

        return implode('.', $segments);
    }

    private function incrementHeadingCounters(array &$counters, int $depth): void
    {
        if (! isset($counters[$depth])) {
            $counters[$depth] = 0;
        }

        ++$counters[$depth];

        for ($i = $depth + 1; $i <= 4; ++$i) {
            $counters[$i] = 0;
        }
    }

    private function prependPrefixToTextRun(TextRun $textRun, string $prefix, DocumentTemplate $template): void
    {
        $text = new Text(
            $prefix,
            DocumentFormatHelper::buildFontStyle($template->font_name, (int) $template->font_size, null, true),
            $this->buildParagraphStyleForLevel($template, 1)
        );

        if (method_exists($text, 'setParentContainer')) {
            $text->setParentContainer($textRun);
        }

        if (method_exists($text, 'setElementIndex')) {
            $text->setElementIndex(1);
        }

        if (method_exists($text, 'setElementId')) {
            $text->setElementId();
        }

        $reflection = new \ReflectionClass($textRun);
        $property = $reflection->getParentClass()?->getProperty('elements');
        if ($property !== null) {
            $property->setAccessible(true);
            $elements = $property->getValue($textRun);
            array_unshift($elements, $text);
            $property->setValue($textRun, array_values($elements));
        }
    }

    private function prependTabToTextRun(TextRun $textRun, DocumentTemplate $template): void
    {
        $text = new Text(
            "\t",
            DocumentFormatHelper::buildFontStyle($template->font_name, (int) $template->font_size, null, false),
            $this->buildParagraphStyleForLevel($template)
        );

        if (method_exists($text, 'setParentContainer')) {
            $text->setParentContainer($textRun);
        }

        if (method_exists($text, 'setElementIndex')) {
            $text->setElementIndex(1);
        }

        if (method_exists($text, 'setElementId')) {
            $text->setElementId();
        }

        $reflection = new \ReflectionClass($textRun);
        $property = $reflection->getParentClass()?->getProperty('elements');
        if ($property !== null) {
            $property->setAccessible(true);
            $elements = $property->getValue($textRun);
            array_unshift($elements, $text);
            $property->setValue($textRun, array_values($elements));
        }
    }

    private function hasImage(object $element): bool
    {
        if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
            return true;
        }
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                if ($child instanceof \PhpOffice\PhpWord\Element\Image) {
                    return true;
                }
            }
        }
        return false;
    }

    private function processImageElement(\PhpOffice\PhpWord\Element\Image $image, DocumentTemplate $template): void
    {
        $style = $image->getStyle();
        if ($style === null) {
            return;
        }

        $width = (float) $style->getWidth();
        $height = (float) $style->getHeight();

        $leftMargin = (float) $template->left_margin_cm;
        $rightMargin = (float) $template->right_margin_cm;
        $printableWidthPt = (21.0 - $leftMargin - $rightMargin) * 28.35;

        if ($width > $printableWidthPt) {
            $aspectRatio = $width / $height;
            $newWidth = $printableWidthPt;
            $newHeight = $printableWidthPt / $aspectRatio;

            $style->setWidth($newWidth);
            $style->setHeight($newHeight);
        } else {
            $style->setAlignment(\PhpOffice\PhpWord\SimpleType\Jc::CENTER);
        }
    }

    private function buildParagraphStyleForLevel(DocumentTemplate $template, ?int $headingLevel = null): array
    {
        $isHeading = $headingLevel !== null;

        return DocumentFormatHelper::buildParagraphStyle(
            (float) $template->line_spacing,
            $isHeading ? $this->headingAlignmentForLevel($headingLevel) : $template->normal_alignment,
            $isHeading,
            $isHeading
                ? (float) $template->heading_first_line_indent_cm
                : null,
            0,
            (int) round($template->line_spacing * $template->font_size * 20)
        );
    }

    private function headingAlignmentForLevel(?int $headingLevel): string
    {
        return $headingLevel === 1 ? 'center' : 'left';
    }

    private function resolveHeadingLevel(object $element, array $styleMetadata): ?int
    {
        $paragraphStyle = method_exists($element, 'getParagraphStyle') ? $element->getParagraphStyle() : null;

        if ($paragraphStyle instanceof Paragraph) {
            foreach ([$paragraphStyle->getStyleName(), $paragraphStyle->getBasedOn()] as $styleName) {
                $headingLevel = $this->resolveHeadingLevelFromStyleName($styleName, $styleMetadata);
                if ($headingLevel !== null) {
                    return $headingLevel;
                }
            }
        }

        if (is_string($paragraphStyle)) {
            return $this->resolveHeadingLevelFromStyleName($paragraphStyle, $styleMetadata);
        }

        return null;
    }

    private function resolveHeadingLevelFromStyleName(?string $styleName, array $styleMetadata): ?int
    {
        if (! filled($styleName)) {
            return null;
        }

        $normalizedStyleName = $this->normalizeStyleName($styleName);
        if (preg_match('/heading([1-9])/', $normalizedStyleName, $matches) === 1) {
            return (int) $matches[1];
        }

        $styleDefinition = $styleMetadata[$styleName] ?? $styleMetadata[$normalizedStyleName] ?? null;
        if (is_array($styleDefinition) && isset($styleDefinition['heading_level'])) {
            return (int) $styleDefinition['heading_level'];
        }

        return null;
    }

    private function extractParagraphStyleMetadata(string $documentPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($documentPath) !== true) {
            return [];
        }

        $stylesXml = $zip->getFromName('word/styles.xml');
        $zip->close();

        if (! is_string($stylesXml) || trim($stylesXml) === '') {
            return [];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($stylesXml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $metadata = [];
        foreach ($xpath->query('//w:style[@w:type="paragraph"]') ?: [] as $styleNode) {
            if (! $styleNode instanceof \DOMElement) {
                continue;
            }

            $styleId = $styleNode->getAttribute('w:styleId');
            $displayName = $xpath->evaluate('string(w:name/@w:val)', $styleNode);
            $normalizedDisplayName = $this->normalizeStyleName($displayName);
            $headingLevel = null;

            if (preg_match('/heading([1-9])/', $normalizedDisplayName, $matches) === 1) {
                $headingLevel = (int) $matches[1];
            }

            $definition = [
                'style_id' => $styleId,
                'name' => $displayName,
                'heading_level' => $headingLevel,
            ];

            if ($styleId !== '') {
                $metadata[$styleId] = $definition;
            }

            if ($normalizedDisplayName !== '') {
                $metadata[$normalizedDisplayName] = $definition;
            }
        }

        return $metadata;
    }

    private function normalizeStyleName(?string $styleName): string
    {
        return strtolower(str_replace([' ', '_', '-'], '', trim((string) $styleName)));
    }
}
