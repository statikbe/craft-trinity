<?php

namespace statikbe\trinity\conditions;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\ElementCollection;
use craft\elements\Entry;
use statikbe\trinity\Trinity;

/**
 * Entry condition rule "Row cells": whether a row has a cell of one of the chosen types in its columns
 * (the cells field). Use it as a field condition in the row entry type's field layout, e.g. to only show
 * "Background Color" when the row has no FAQ. Rows without columns, and other entries, count as having no cells.
 */
class RowCellsConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('trinity', 'Row cells');
    }

    public function getExclusiveQueryParams(): array
    {
        return [];
    }

    /**
     * The cell types of the cells field, by entry type UID.
     */
    protected function options(): array
    {
        $options = [];
        foreach (Trinity::getInstance()->getRows()->cellEntryTypes() as $entryType) {
            $options[$entryType->uid] = Craft::t('site', $entryType->name);
        }
        return $options;
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $field = Craft::$app->getFields()->getFieldByHandle(Trinity::getInstance()->getSettings()->cellsField);
        $entriesService = Craft::$app->getEntries();
        $typeIds = array_filter(array_map(fn(string $uid) => $entriesService->getEntryTypeByUid($uid)?->id, $this->getValues()));
        if (!$field || !$typeIds || !in_array($this->operator, [self::OPERATOR_IN, self::OPERATOR_NOT_IN], true)) {
            return;
        }

        // Owners of a cell entry of one of these types in the cells field
        $ownerIds = (new Query())
            ->select(['owners.ownerId'])
            ->from(['owners' => Table::ELEMENTS_OWNERS])
            ->innerJoin(['cells' => Table::ENTRIES], '[[cells.id]] = [[owners.elementId]]')
            ->where(['cells.fieldId' => $field->id, 'cells.typeId' => $typeIds]);
        $query->andWhere([$this->operator === self::OPERATOR_IN ? 'in' : 'not in', 'elements.id', $ownerIds]);
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($this->cellTypeUids($element));
    }

    /**
     * Entry type UIDs of the cells in the row's columns, disabled ones included (the editor sees them too).
     *
     * @return string[]
     */
    private function cellTypeUids(ElementInterface $element): array
    {
        $handle = Trinity::getInstance()->getSettings()->cellsField;
        if (!$element instanceof Entry || !$element->getFieldLayout()?->getFieldByHandle($handle)) {
            return [];
        }
        $value = $element->getFieldValue($handle);
        /** @var Entry[] $cells */
        $cells = $value instanceof EntryQuery ? (clone $value)->status(null)->all() : ($value instanceof ElementCollection ? $value->all() : []);
        return array_values(array_unique(array_map(fn(Entry $cell) => $cell->getType()->uid, $cells)));
    }
}
