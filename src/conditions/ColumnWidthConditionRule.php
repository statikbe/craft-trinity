<?php

namespace statikbe\trinity\conditions;

use Craft;
use statikbe\trinity\Trinity;

/**
 * Entry condition rule "Column width": the width a cell gets on the page, from the row's layout, the cell's column and the page
 * (on a 2/3 page every column is 2/3 as wide, see Rows::pageWidth()). For conditions about the room a field gets, e.g.
 * "is not one of: 1/4, 1/3 of the page" for a field that needs some width. For conditions about the row layout, whatever the
 * page, use "Column span".
 */
class ColumnWidthConditionRule extends BaseColumnConditionRule
{
    public function getLabel(): string
    {
        return Craft::t('trinity', 'Column width');
    }

    /**
     * Every width a column can have on a page: the column widths of all layouts allowed on every page width (Rows::pageWidths()).
     * Labels as in the control panel ("1/2 of the page").
     */
    protected function options(): array
    {
        $rows = Trinity::getInstance()->getRows();
        $layouts = $rows->getLayouts();
        $widths = [];
        foreach ($rows->pageWidths() as $pageWidth) {
            foreach ($layouts->allowedLayouts($pageWidth) as $layout) {
                foreach ($layouts->columnWidths($layout, $pageWidth) as $width) {
                    $widths[self::key($width)] = $width;
                }
            }
        }
        arsort($widths);
        return array_map(fn(float $width) => $rows->formatPageWidth($width), $widths);
    }

    protected function columnValue(array $column): float
    {
        return $column['width'];
    }
}
