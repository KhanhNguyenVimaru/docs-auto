<?php

namespace App\Helpers;

use PhpOffice\PhpWord\SimpleType\Jc;

class DocumentFormatHelper
{
    public const STANDARD_TAB_STOP_TWIPS = 720;
    public const STANDARD_TAB_STOP_CM = 1.27;

    public static function cmToTwips(float $value): int
    {
        return (int) round($value * 567);
    }

    public static function twipsToCm(int $value): float
    {
        return round($value / 567, 2);
    }

    public static function standardTabStopTwips(): int
    {
        return self::STANDARD_TAB_STOP_TWIPS;
    }

    public static function standardTabStopCm(): float
    {
        return self::STANDARD_TAB_STOP_CM;
    }

    public static function normalizeAlignment(?string $alignment, bool $isHeading = false): string
    {
        if ($alignment === null || $alignment === '') {
            return $isHeading ? Jc::START : Jc::BOTH;
        }

        $alignment = strtolower($alignment);

        return match ($alignment) {
            'left', 'start' => Jc::START,
            'center' => Jc::CENTER,
            'right', 'end' => Jc::END,
            'justify', 'both' => Jc::BOTH,
            default => $isHeading ? Jc::START : Jc::BOTH,
        };
    }

    public static function buildParagraphStyle(
        float $lineSpacing,
        ?string $alignment = null,
        bool $isHeading = false,
        ?float $firstLineIndentCm = null,
        int $spaceBefore = 0,
        int $spaceAfter = 0
    ): array {
        $style = [
            'alignment' => self::normalizeAlignment($alignment, $isHeading),
            'lineHeight' => $lineSpacing,
            'spaceBefore' => $spaceBefore,
            'spaceAfter' => $spaceAfter,
        ];

        if ($firstLineIndentCm !== null) {
            $style['indentation'] = [
                'firstLine' => self::cmToTwips($firstLineIndentCm),
            ];
        }

        return $style;
    }

    public static function buildFontStyle(
        string $fontName,
        int $fontSize,
        ?array $sourceStyle = null,
        ?bool $forceBold = null
    ): array
    {
        return array_filter([
            'name' => $fontName,
            'size' => $fontSize,
            'bold' => $forceBold ?? ($sourceStyle['bold'] ?? null),
            'italic' => $sourceStyle['italic'] ?? null,
            'underline' => $sourceStyle['underline'] ?? null,
            'color' => $sourceStyle['color'] ?? null,
        ], static fn ($value) => $value !== null);
    }
}
