<?php

namespace statikbe\trinity\conditions;

use Craft;
use statikbe\trinity\layout\Layout;
use statikbe\trinity\Trinity;

/**
 * Entry condition rule "Column span": the share of its row a cell takes, from the row's layout and the cell's column, the same
 * on every page (a cell that fills its row is "Full row" on a 2/3 page too). E.g. "is one of: Full row" for a field only when the
 * cell has the row to itself, "is not one of: Full row" for the reverse. For conditions about the room a field gets on the page,
 * use "Column width".
 */
class ColumnSpanConditionRule extends BaseColumnConditionRule
{
    public function getLabel(): string
    {
        return Craft::t('trinity', 'Column span');
    }

    /**
     * Every share of the row a column can have in the layouts: full row, 2/3, 1/2, 1/3 of the row.
     */
    protected function options(): array
    {
        $fractions = [];
        foreach (Layout::LAYOUTS as $layoutFractions) {
            foreach ($layoutFractions as $fraction) {
                $fractions[self::key($fraction)] = $fraction;
            }
        }
        arsort($fractions);
        $layouts = Trinity::getInstance()->getLayouts();
        return array_map(fn(float $fraction) => $fraction >= 1
            ? Craft::t('trinity', 'Full row')
            : Craft::t('trinity', '{width} of the row', ['width' => $layouts->formatWidth($fraction)]), $fractions);
    }

    protected function columnValue(array $column): float
    {
        return $column['fraction'];
    }
}
