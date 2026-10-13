<?php

namespace statikbe\trinity\variables;

use craft\base\ElementInterface;
use craft\elements\Entry;
use statikbe\trinity\models\Settings;
use statikbe\trinity\Trinity;

/**
 * craft.trinity in Twig: the data templates need to render rows. The markup stays in the project's templates.
 */
class TrinityVariable
{
    /**
     * Layout, breakpoint and columns (cell, type, share of the row, width on the page, mobile order, image sizes) of a row,
     * see Rows::row().
     */
    public function row(Entry $row): array
    {
        return Trinity::getInstance()->getRows()->row($row);
    }

    /**
     * Whether a field is shown for this element, following its field condition: a hidden field keeps its last value, so check
     * this before rendering a field that has a condition, e.g. a row's background colour.
     */
    public function showsField(ElementInterface $element, string $handle): bool
    {
        return Trinity::getInstance()->getRows()->showsField($element, $handle);
    }

    /**
     * Width of the content builder on this page as a fraction of the page: 1, or e.g. 2/3 next to a sidebar.
     */
    public function pageWidth(?ElementInterface $page): float
    {
        return Trinity::getInstance()->getRows()->pageWidth($page);
    }

    /**
     * `sizes` and the srcset widths for an image filling a column of the given share of the row.
     *
     * @return array{sizes: string, srcsetSizes: string[]}
     */
    public function imageSizes(string $layout, float $fraction, float $pageWidth = 1): array
    {
        return Trinity::getInstance()->getLayouts()->imageSizes($layout, $fraction, $pageWidth);
    }

    public function getSettings(): Settings
    {
        return Trinity::getInstance()->getSettings();
    }
}
