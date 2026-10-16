<?php

namespace statikbe\trinity\layout;

use statikbe\trinity\models\Settings;

/**
 * The settings the layout maths needs, normalised: invalid page widths and blocked combinations are left out and reported in
 * $warnings, for the caller to log. Plain PHP, so Layout can be used and tested without Craft.
 */
final class LayoutConfig
{
    /**
     * @param array{entryTypes: array<string, float>, sections: array<string, float>} $pageWidths
     * @param array<string, float> $minWidthPerType
     * @param array<string, float> $maxWidthPerType
     * @param array<array{string, string}> $blockedCombinations
     * @param array<int, int> $containers
     * @param array<string, int> $columnGaps
     * @param string[] $warnings
     */
    public function __construct(
        public readonly float $minColumnWidth = 1 / 4,
        public readonly array $pageWidths = ['entryTypes' => [], 'sections' => []],
        public readonly int $narrowFromViewport = 980,
        public readonly array $minWidthPerType = [],
        public readonly array $maxWidthPerType = [],
        public readonly array $blockedCombinations = [],
        public readonly array $containers = [480 => 448, 660 => 628, 820 => 788, 980 => 948, 1200 => 1168],
        public readonly int $columnGap = 48,
        public readonly array $columnGaps = [],
        public readonly int $columnMinWidth = 256,
        public readonly string $mobileSize = '95vw',
        public readonly array $warnings = [],
    ) {
    }

    public static function fromSettings(Settings $settings): self
    {
        return self::fromArray([
            'minColumnWidth' => $settings->minColumnWidth,
            'pageWidths' => $settings->pageWidths,
            'narrowFromViewport' => $settings->narrowFromViewport,
            'minWidthPerType' => $settings->minWidthPerType,
            'maxWidthPerType' => $settings->maxWidthPerType,
            'blockedCombinations' => $settings->blockedCombinations,
            'containers' => $settings->containers,
            'columnGap' => $settings->columnGap,
            'columnGaps' => $settings->columnGaps,
            'columnMinWidth' => $settings->columnMinWidth,
            'mobileSize' => $settings->mobileSize,
        ]);
    }

    /**
     * @param array<string, mixed> $config setting name => value, as in config/trinity.php; missing ones get their default
     */
    public static function fromArray(array $config): self
    {
        $defaults = new self();
        $minColumnWidth = (float)($config['minColumnWidth'] ?? $defaults->minColumnWidth);
        $warnings = [];

        $pageWidths = ['entryTypes' => [], 'sections' => []];
        foreach (array_keys($pageWidths) as $key) {
            foreach ((array)($config['pageWidths'][$key] ?? []) as $handle => $width) {
                if (is_numeric($width) && $width >= $minColumnWidth && $width <= 1) {
                    $pageWidths[$key][$handle] = (float)$width;
                } else {
                    $warnings[] = "pageWidths.{$key}.{$handle}: the width must be a fraction of the page between {$minColumnWidth} and 1, e.g. 2 / 3.";
                }
            }
        }

        // A single pair may be given without the outer brackets: ['cellQuote', 'cellFaq']
        $setting = array_values((array)($config['blockedCombinations'] ?? []));
        if (count($setting) === 2 && is_string($setting[0]) && is_string($setting[1])) {
            $setting = [$setting];
        }
        $blockedCombinations = [];
        foreach ($setting as $pair) {
            $pair = is_array($pair) ? array_values($pair) : [];
            if (count($pair) === 2 && is_string($pair[0]) && is_string($pair[1])) {
                $blockedCombinations[] = $pair;
            } else {
                $warnings[] = "blockedCombinations: every entry must be a pair of cell type handles, e.g. ['cellQuote', 'cellFaq'].";
            }
        }

        $columnGaps = [];
        foreach ((array)($config['columnGaps'] ?? []) as $layout => $gap) {
            if (isset(Layout::LAYOUTS[$layout]) && is_numeric($gap) && $gap >= 0) {
                $columnGaps[$layout] = (int)$gap;
            } else {
                $warnings[] = "columnGaps.{$layout}: the key must be a layout (" . implode(', ', array_keys(Layout::LAYOUTS)) . ') and the gap a number of px, e.g. 24.';
            }
        }

        $containers = (array)($config['containers'] ?? $defaults->containers);
        ksort($containers);

        return new self(
            minColumnWidth: $minColumnWidth,
            pageWidths: $pageWidths,
            narrowFromViewport: (int)($config['narrowFromViewport'] ?? $defaults->narrowFromViewport),
            minWidthPerType: array_map('floatval', (array)($config['minWidthPerType'] ?? [])),
            maxWidthPerType: array_map('floatval', (array)($config['maxWidthPerType'] ?? [])),
            blockedCombinations: $blockedCombinations,
            containers: array_map('intval', $containers),
            columnGap: (int)($config['columnGap'] ?? $defaults->columnGap),
            columnGaps: $columnGaps,
            columnMinWidth: (int)($config['columnMinWidth'] ?? $defaults->columnMinWidth),
            mobileSize: (string)($config['mobileSize'] ?? $defaults->mobileSize),
            warnings: $warnings,
        );
    }
}
