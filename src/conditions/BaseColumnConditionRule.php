<?php

namespace statikbe\trinity\conditions;

use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use statikbe\trinity\Trinity;

/**
 * Base for the entry condition rules on the column a cell is in: "Column width" (its width on the page) and "Column span"
 * (its share of the row). Use them as field conditions in a cell type's field layout; they're only offered there.
 *
 * Cells that are not in a row have no column, so "is one of" never matches them and "is not one of" always does.
 */
abstract class BaseColumnConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * The value this rule compares, from the cell's column.
     *
     * @param array{fraction: float, width: float} $column
     */
    abstract protected function columnValue(array $column): float;

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        // The column depends on the row and the page, not on anything in the cell's own row in the database
    }

    public function matchElement(ElementInterface $element): bool
    {
        $column = Trinity::getInstance()->getRows()->column($element);
        return $this->matchValue($column === null ? null : (string)self::key($this->columnValue($column)));
    }

    /**
     * A fraction as an option key: hundredths, e.g. 33 for 1/3 (stored in project config).
     */
    protected static function key(float $fraction): int
    {
        return (int)round($fraction * 100);
    }
}
