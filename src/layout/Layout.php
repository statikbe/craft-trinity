<?php

namespace statikbe\trinity\layout;

/**
 * The layout maths of a row: column widths, which layouts and cell types fit, mobile order and responsive image sizes.
 *
 * Widths are fractions of the full page width. On pages where the content builder itself is narrower (e.g. 2/3 of the page
 * next to a sidebar), every column is that much narrower: layouts with columns under the minimum column width are not allowed
 * there, and cell types are checked against that effective width.
 *
 * Pure PHP without Craft; the Craft side (entries, fields, errors) lives in the Rows service.
 */
final class Layout
{
    /** Layout value => column widths as fractions of the row, left to right. Stored as the layout field's option values. */
    public const LAYOUTS = [
        'full' => [1.0],
        'halves' => [1 / 2, 1 / 2],
        'thirds' => [1 / 3, 1 / 3, 1 / 3],
        'thirdTwoThirds' => [1 / 3, 2 / 3],
        'twoThirdsThird' => [2 / 3, 1 / 3],
    ];

    /** Margin for comparing fractions (1/2 × 2/3 must count as 1/3) */
    public const EPSILON = 0.01;

    public function __construct(
        public readonly LayoutConfig $config = new LayoutConfig(),
    ) {
    }

    /**
     * Column widths of a layout as fractions of the row; an unknown layout counts as full.
     *
     * @return float[]
     */
    public function fractions(string $layout): array
    {
        return self::LAYOUTS[$layout] ?? self::LAYOUTS['full'];
    }

    /**
     * Effective column widths (fractions of the page) of a layout, on a page where the content builder has this width.
     *
     * @return float[]
     */
    public function columnWidths(string $layout, float $pageWidth = 1): array
    {
        return array_map(fn(float $fraction) => $fraction * $pageWidth, $this->fractions($layout));
    }

    /**
     * Layouts that can be used on a page where the content builder has this width.
     *
     * @return string[]
     */
    public function allowedLayouts(float $pageWidth = 1): array
    {
        return array_values(array_filter(
            array_keys(self::LAYOUTS),
            fn(string $layout) => min($this->columnWidths($layout, $pageWidth)) >= $this->config->minColumnWidth - self::EPSILON,
        ));
    }

    public function minWidth(string $cellType): float
    {
        return $this->config->minWidthPerType[$cellType] ?? 0.0;
    }

    public function maxWidth(string $cellType): float
    {
        return $this->config->maxWidthPerType[$cellType] ?? 1.0;
    }

    /**
     * Whether a cell type may be in a column with this effective width (fraction of the page).
     */
    public function isTypeAllowed(string $cellType, float $width): bool
    {
        return $width >= $this->minWidth($cellType) - self::EPSILON
            && $width <= $this->maxWidth($cellType) + self::EPSILON;
    }

    /**
     * Whether two cell types can be in the same row (blockedCombinations, in either order).
     */
    public function isCombinationAllowed(string $a, string $b): bool
    {
        foreach ($this->config->blockedCombinations as [$x, $y]) {
            if (($x === $a && $y === $b) || ($x === $b && $y === $a)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether all these cell types (the cells of one row) can be in the same row.
     *
     * @param string[] $cellTypes
     */
    public function areCombinationsAllowed(array $cellTypes): bool
    {
        $cellTypes = array_values($cellTypes);
        foreach ($cellTypes as $i => $a) {
            foreach (array_slice($cellTypes, $i + 1) as $b) {
                if (!$this->isCombinationAllowed($a, $b)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Position of every column while the columns are stacked (1 = top), from the mobile order each cell asks for:
     * a column with a number gets that position (higher than the number of columns = last; when two columns ask for the
     * same one, the left one gets it and the other the nearest free position after it, else before it), and the
     * "Auto" columns (0) fill the remaining positions from left to right.
     *
     * @param int[] $requested per cell, left to right: the requested position, or 0 for auto
     * @return int[]|null cell index => position, or null when every column is on auto (the stacked order is the source order)
     */
    public function mobileOrder(array $requested): ?array
    {
        $requested = array_values($requested);
        $count = count($requested);
        $wanted = [];
        foreach ($requested as $i => $value) {
            if ($value > 0) {
                $wanted[$i] = min($value, $count);
            }
        }
        if (!$wanted) {
            return null;
        }

        // By requested position; on a tie, left to right (asort() keeps the order of equal values)
        asort($wanted);
        $positions = [];
        foreach ($wanted as $i => $position) {
            $free = array_diff(range(1, $count), $positions);
            $after = array_filter($free, fn(int $p) => $p >= $position);
            $positions[$i] = $after ? min($after) : max($free);
        }
        $free = array_values(array_diff(range(1, $count), $positions));
        foreach (array_keys($requested) as $i) {
            $positions[$i] ??= array_shift($free);
        }
        ksort($positions);
        return $positions;
    }

    /**
     * Gap (px) between the columns of a layout: its columnGaps value, else columnGap.
     */
    public function columnGap(string $layout): int
    {
        return $this->config->columnGaps[$layout] ?? $this->config->columnGap;
    }

    /**
     * Row width (px) from which a row shows its columns side by side: its narrowest column is then at least columnMinWidth wide,
     * e.g. 560px for halves and 864px for layouts with a 1/3 column (with the defaults). Match the project's container queries.
     */
    public function breakpoint(string $layout): int
    {
        $gap = $this->columnGap($layout);
        return (int)round(($this->config->columnMinWidth + $gap) / min($this->fractions($layout)) - $gap);
    }

    /**
     * `sizes` and the srcset widths for an image that fills a column of the given fraction of the row, on a page where the
     * content builder has this width.
     *
     * @return array{sizes: string, srcsetSizes: string[]}
     */
    public function imageSizes(string $layout, float $fraction, float $pageWidth = 1): array
    {
        $containers = $this->config->containers;
        $gap = $this->config->columnGap;
        $layoutGap = $this->columnGap($layout);
        $viewports = array_keys($containers);
        $sizes = ['(max-width: ' . ($viewports[0] - 1) . 'px) ' . $this->config->mobileSize];
        $srcset = [];
        foreach ($viewports as $i => $viewport) {
            $builder = $containers[$viewport];
            if ($viewport >= $this->config->narrowFromViewport) {
                // The content builder and the sidebar are columns of a grid with the column gap between them
                $builder = (int)round($pageWidth * ($builder + $gap) - $gap);
            }
            $width = $builder >= $this->breakpoint($layout)
                ? (int)round($fraction * ($builder + $layoutGap) - $layoutGap)
                : $builder;

            $next = $viewports[$i + 1] ?? null;
            $sizes[] = "(min-width: {$viewport}px)" . ($next ? ' and (max-width: ' . ($next - 1) . 'px)' : '') . " {$width}px";
            $srcset[] = "{$width}w";
        }

        return [
            'sizes' => implode(', ', $sizes),
            'srcsetSizes' => array_values(array_unique($srcset)),
        ];
    }

    /**
     * Width of a content builder on a page with this entry type and section, in the Matrix field with this handle, as a
     * fraction of the page: the field's configured width, else the entry type's, else the section's, else full.
     */
    public function configuredPageWidth(string $entryTypeHandle, ?string $sectionHandle = null, ?string $fieldHandle = null): float
    {
        return $this->config->pageWidths['fields'][$fieldHandle ?? '']
            ?? $this->config->pageWidths['entryTypes'][$entryTypeHandle]
            ?? $this->config->pageWidths['sections'][$sectionHandle ?? '']
            ?? 1.0;
    }

    /**
     * Every width the content builder can have: full, the configured page widths and any extra ones.
     *
     * @param float[] $extra
     * @return float[] widest first, without duplicates
     */
    public function pageWidths(array $extra = []): array
    {
        $widths = [1.0, ...array_values($this->config->pageWidths['entryTypes']), ...array_values($this->config->pageWidths['sections']), ...array_values($this->config->pageWidths['fields']), ...$extra];
        $unique = [];
        foreach ($widths as $width) {
            $unique[$this->formatWidth($width)] = (float)$width;
        }
        rsort($unique);
        return $unique;
    }

    /**
     * Width as a readable fraction: full, 1/3, 1/2, 2/3, 4/9 …
     */
    public function formatWidth(float $width): string
    {
        foreach ([1, 2, 3, 4, 6, 8, 9] as $denominator) {
            $numerator = $width * $denominator;
            if (abs($numerator - round($numerator)) < self::EPSILON) {
                return round($numerator) == $denominator ? 'full' : round($numerator) . '/' . $denominator;
            }
        }
        return (string)round($width, 2);
    }
}
