<?php

namespace statikbe\trinity\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\db\EntryQuery;
use craft\elements\ElementCollection;
use craft\elements\Entry;
use craft\fields\Matrix;
use craft\models\EntryType;
use statikbe\trinity\conditions\RowCellsConditionRule;
use statikbe\trinity\events\DefinePageWidthEvent;
use statikbe\trinity\events\RegisterPageWidthsEvent;
use statikbe\trinity\layout\Layout;
use statikbe\trinity\models\Settings;
use statikbe\trinity\Trinity;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Rows in a content builder: a row entry with a layout (1–3 columns) and one cell entry per column, from left to right.
 * The Craft side of the layout maths: reading rows and cells, page widths, validation and the control panel settings.
 */
class Rows extends Component
{
    /**
     * @event DefinePageWidthEvent The width of the content builder on a page, to override the configured one
     */
    public const EVENT_DEFINE_PAGE_WIDTH = 'definePageWidth';

    /**
     * @event RegisterPageWidthsEvent Every width the content builder can have, for the "Column width" condition's options
     */
    public const EVENT_REGISTER_PAGE_WIDTHS = 'registerPageWidths';

    /**
     * @var array<string, array{fraction: float, width: float}|null> column per "rowId-cellId" (a cell can be shared by a row and
     * its drafts and revisions), as every field of a cell is checked by the column condition rules
     */
    private array $columns = [];

    public function getLayouts(): Layout
    {
        return Trinity::getInstance()->getLayouts();
    }

    public function getSettings(): Settings
    {
        return Trinity::getInstance()->getSettings();
    }

    public function isRow(?ElementInterface $element): bool
    {
        return $element instanceof Entry && $element->getType()->handle === $this->getSettings()->rowEntryType;
    }

    /**
     * The row's layout value (Layout::LAYOUTS key); full when none is chosen.
     */
    public function layout(Entry $row): string
    {
        return (string)($row->getFieldValue($this->getSettings()->layoutField)?->value ?: 'full');
    }

    /**
     * A cell's type for templates and CSS: the entry type handle without the cell type prefix, e.g. cellText → text.
     */
    public function cellType(Entry|EntryType $cell): string
    {
        $handle = $cell instanceof Entry ? $cell->getType()->handle : $cell->handle;
        $prefix = $this->getSettings()->cellTypePrefix;
        return lcfirst($prefix !== '' && str_starts_with($handle, $prefix) ? substr($handle, strlen($prefix)) : $handle);
    }

    /**
     * Everything the templates need to render a row: its layout, the width of the content builder on the page, the breakpoint
     * from which its columns sit side by side (and from which a row with a 1/3 column shows two columns, see
     * Layout::twoColumnBreakpoint()), and per column the cell entry, its type, its share of the row (readable: "1/3",
     * "1/2", "2/3", "full"), its effective width (fraction of the page), its position while stacked (see Layout::mobileOrder())
     * and responsive image sizes for an image filling the column.
     *
     * @return array{layout: string, pageWidth: float, breakpoint: int, twoColumnBreakpoint: int|null, cellTypes: string[], columns: array<array{cell: Entry, type: string, fraction: string, width: float, mobileOrder: int|null, sizes: string, srcsetSizes: string[]}>}
     */
    public function row(Entry $row): array
    {
        $layouts = $this->getLayouts();
        $pageWidth = $this->rowPageWidth($row);
        $layout = $this->layout($row);
        $cells = $row->getFieldValue($this->getSettings()->cellsField)->eagerly()->all();
        $fractions = $layouts->fractions($layout);

        $mobileOrder = $layouts->mobileOrder(array_map(fn(Entry $cell) => $this->requestedMobileOrder($cell), $cells));
        $columns = [];
        foreach ($cells as $i => $cell) {
            // Extra cells (layout changed without removing them) render as full rows below; validation prevents saving that
            $fraction = $fractions[$i] ?? 1;
            $width = $fraction * $pageWidth;
            $this->setColumn($cell, $fraction, $width);
            $this->clearHiddenFields($cell);
            $columns[] = [
                'cell' => $cell,
                'type' => $this->cellType($cell),
                'fraction' => $layouts->formatWidth($fraction),
                'width' => $width,
                'mobileOrder' => $mobileOrder[$i] ?? null,
            ] + $layouts->imageSizes($layout, $fraction, $pageWidth);
        }

        return [
            'layout' => $layout,
            'pageWidth' => $pageWidth,
            'breakpoint' => $layouts->breakpoint($layout),
            'twoColumnBreakpoint' => $layouts->twoColumnBreakpoint($layout),
            'cellTypes' => array_column($columns, 'type'),
            'columns' => $columns,
        ];
    }

    /**
     * Whether a field is shown for this element, following the field's condition in the field layout (e.g. a row's background
     * only for some cell types). A hidden field keeps its last value, so templates check this before using it.
     */
    public function showsField(ElementInterface $element, string $handle): bool
    {
        try {
            $layoutElement = $element->getFieldLayout()?->getField($handle);
        } catch (InvalidArgumentException) {
            return false;
        }
        $condition = $layoutElement?->getElementCondition();
        return !$condition || $condition->matchElement($element);
    }

    /**
     * Empties the fields of a cell that its field layout hides (field conditions, e.g. "Column width" or "Column span"), for
     * rendering only: a hidden field keeps its last value, which the templates shouldn't show. Never saved.
     */
    public function clearHiddenFields(Entry $cell): void
    {
        foreach ($cell->getFieldLayout()?->getCustomFieldElements() ?? [] as $layoutElement) {
            $condition = $layoutElement->getElementCondition();
            if ($condition && !$condition->matchElement($cell)) {
                $cell->setFieldValue($layoutElement->getField()->handle, null);
            }
        }
    }

    /**
     * Width of a content builder on this page (the element owning the rows), as a fraction of the page: 1, or e.g. 2/3 next
     * to a sidebar (config/trinity.php → pageWidths, or EVENT_DEFINE_PAGE_WIDTH). Pass the handle of the builder's Matrix field
     * (as in the page's field layout) for a page with more than one content builder.
     */
    public function pageWidth(?ElementInterface $page, ?string $field = null): float
    {
        if (!$page instanceof Entry) {
            return 1;
        }
        $event = new DefinePageWidthEvent([
            'page' => $page,
            'field' => $field,
            'width' => $this->getLayouts()->configuredPageWidth($page->getType()->handle, $page->getSection()?->handle, $field),
        ]);
        if ($this->hasEventHandlers(self::EVENT_DEFINE_PAGE_WIDTH)) {
            $this->trigger(self::EVENT_DEFINE_PAGE_WIDTH, $event);
        }
        return $event->width;
    }

    /**
     * Width of the content builder a row is in: the width of its page, for the Matrix field the row belongs to.
     */
    public function rowPageWidth(Entry $row): float
    {
        try {
            $field = $row->getField()?->handle;
        } catch (InvalidConfigException) {
            $field = null;
        }
        return $this->pageWidth($row->getOwner(), $field);
    }

    /**
     * Every width the content builder can have: full, the configured page widths and those from EVENT_REGISTER_PAGE_WIDTHS.
     *
     * @return float[] widest first
     */
    public function pageWidths(): array
    {
        $event = new RegisterPageWidthsEvent();
        if ($this->hasEventHandlers(self::EVENT_REGISTER_PAGE_WIDTHS)) {
            $this->trigger(self::EVENT_REGISTER_PAGE_WIDTHS, $event);
        }
        return $this->getLayouts()->pageWidths($event->widths);
    }

    /**
     * Width of a column on the page, for editors: "1/2 of the page", "full page width".
     */
    public function formatPageWidth(float $width): string
    {
        $fraction = $this->getLayouts()->formatWidth($width);
        return $fraction === 'full'
            ? Craft::t('trinity', 'full page width')
            : Craft::t('trinity', '{width} of the page', ['width' => $fraction]);
    }

    /**
     * Sets a cell's column when it is already known (row() while rendering), so the column condition rules don't look it up again.
     *
     * @param float $fraction share of the row
     * @param float $width width on the page
     */
    public function setColumn(Entry $cell, float $fraction, float $width): void
    {
        if ($cell->id) {
            $this->columns[$this->columnKey($cell)] = ['fraction' => $fraction, 'width' => $width];
        }
    }

    /**
     * The column a cell is in: its share of the row and its width on the page, or null when it isn't in a row.
     *
     * @return array{fraction: float, width: float}|null
     */
    public function column(ElementInterface $cell): ?array
    {
        if (!$cell instanceof Entry || !$cell->fieldId) {
            return null;
        }
        // Not cached while editing: the layout or the cell's column can change between two checks
        $cache = !Craft::$app->getRequest()->getIsCpRequest() && $cell->id;
        if ($cache && array_key_exists($this->columnKey($cell), $this->columns)) {
            return $this->columns[$this->columnKey($cell)];
        }

        $column = null;
        $row = $cell->getOwner();
        if ($row instanceof Entry && $this->isRow($row)) {
            $index = $this->columnIndex($row, $cell);
            if ($index !== null) {
                // Cells beyond the layout's columns render as full rows (see row())
                $fraction = $this->getLayouts()->fractions($this->layout($row))[$index] ?? 1;
                $column = ['fraction' => $fraction, 'width' => $fraction * $this->rowPageWidth($row)];
            }
        }

        if ($cache) {
            $this->columns[$this->columnKey($cell)] = $column;
        }
        return $column;
    }

    /**
     * Settings for the control panel layer on a row's cells field: which layouts can be used, how wide the content builder is on
     * this page, the minimum and maximum width per cell type and the cell types each type can't share a row with (by entry type id).
     */
    public function cpConfig(?ElementInterface $row): array
    {
        $layouts = $this->getLayouts();
        $pageWidth = $row instanceof Entry ? $this->rowPageWidth($row) : 1;
        $minWidthPerType = [];
        $maxWidthPerType = [];
        $blockedCombinations = [];
        $entryTypes = $this->cellEntryTypes();
        foreach ($entryTypes as $entryType) {
            $minWidthPerType[$entryType->id] = $layouts->minWidth($entryType->handle);
            $maxWidthPerType[$entryType->id] = $layouts->maxWidth($entryType->handle);
            foreach ($entryTypes as $other) {
                if (!$layouts->isCombinationAllowed($entryType->handle, $other->handle)) {
                    $blockedCombinations[$entryType->id][] = $other->id;
                }
            }
        }

        $settings = $this->getSettings();
        return [
            'handles' => [
                'layoutField' => $settings->layoutField,
                'cellsField' => $settings->cellsField,
                'rowSpacingField' => $settings->rowSpacingField,
                'rowBackgroundField' => $settings->rowBackgroundField,
            ],
            'rowBackgroundDefault' => $settings->rowBackgroundDefault,
            'layouts' => Layout::LAYOUTS,
            'allowedLayouts' => $layouts->allowedLayouts($pageWidth),
            'contextWidth' => $pageWidth,
            'minWidthPerType' => $minWidthPerType,
            'maxWidthPerType' => $maxWidthPerType,
            'blockedCombinations' => $blockedCombinations,
            'hasBlockConditions' => $this->hasBlockConditions(),
        ];
    }

    /**
     * The cell entry types: the entry types of the cells field.
     *
     * @return EntryType[]
     */
    public function cellEntryTypes(): array
    {
        $field = Craft::$app->getFields()->getFieldByHandle($this->getSettings()->cellsField);
        return $field instanceof Matrix ? $field->getEntryTypes() : [];
    }

    /**
     * Whether a field in the row's layout is shown or hidden by the "Row cells" condition rule: the control panel then
     * re-checks the field conditions when the cells in a row change.
     */
    public function hasBlockConditions(): bool
    {
        $rowType = Craft::$app->getEntries()->getEntryTypeByHandle($this->getSettings()->rowEntryType);
        foreach ($rowType?->getFieldLayout()->getCustomFieldElements() ?? [] as $layoutElement) {
            foreach ($layoutElement->getElementCondition()?->getConditionRules() ?? [] as $rule) {
                if ($rule instanceof RowCellsConditionRule) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Validates a row: allowed layout, number of cells matches the layout, every cell type fits its column and no blocked
     * combinations. Only for live saves, so drafts and autosaves can be incomplete.
     */
    public function validateRow(Entry $row): void
    {
        if (!$this->isRow($row) || $row->getScenario() !== Element::SCENARIO_LIVE) {
            return;
        }

        $settings = $this->getSettings();
        $layouts = $this->getLayouts();
        $pageWidth = $this->rowPageWidth($row);
        $layout = $this->layout($row);

        if (!in_array($layout, $layouts->allowedLayouts($pageWidth), true)) {
            $row->addError($settings->layoutField, Craft::t('trinity', 'This layout has columns that are too narrow for this page, choose a layout with wider columns.'));
            return;
        }

        // Disabled cells don't render, so they don't count as a column
        $cells = array_values(array_filter(
            $row->getFieldValue($settings->cellsField)->all(),
            fn(Entry $cell) => $cell->enabled && $cell->getEnabledForSite(),
        ));
        $widths = $layouts->columnWidths($layout, $pageWidth);

        if (count($cells) !== count($widths)) {
            $row->addError($settings->cellsField, Craft::t('trinity', 'This layout has {columns, plural, =1{1 column} other{# columns}}, but there {cells, plural, =1{is 1 block} other{are # blocks}}. Add or remove blocks, or choose another layout.', [
                'columns' => count($widths),
                'cells' => count($cells),
            ]));
            return;
        }

        foreach ($cells as $i => $cell) {
            $type = $cell->getType();
            if (!$layouts->isTypeAllowed($type->handle, $widths[$i])) {
                $params = [
                    'type' => Craft::t('site', $type->name),
                    'column' => $i + 1,
                    'width' => $this->formatPageWidth($widths[$i]),
                    'minWidth' => $layouts->formatWidth($layouts->minWidth($type->handle)),
                    'maxWidth' => $layouts->formatWidth($layouts->maxWidth($type->handle)),
                ];
                $row->addError($settings->cellsField, $widths[$i] < $layouts->minWidth($type->handle)
                    ? Craft::t('trinity', '“{type}” does not fit in column {column} ({width}), it needs at least {minWidth}. Choose a wider layout or another block.', $params)
                    : Craft::t('trinity', '“{type}” is too wide in column {column} ({width}), it can be at most {maxWidth}. Choose a layout with narrower columns or another block.', $params));
            }
        }

        foreach ($cells as $i => $cell) {
            foreach (array_slice($cells, $i + 1) as $other) {
                if (!$layouts->isCombinationAllowed($cell->getType()->handle, $other->getType()->handle)) {
                    $row->addError($settings->cellsField, Craft::t('trinity', '“{type}” and “{other}” can’t be in the same row. Choose another block.', [
                        'type' => Craft::t('site', $cell->getType()->name),
                        'other' => Craft::t('site', $other->getType()->name),
                    ]));
                }
            }
        }
    }

    /**
     * The position a cell asks for while the columns are stacked: its mobile order field, 0 for auto or when it has none.
     */
    private function requestedMobileOrder(Entry $cell): int
    {
        $handle = $this->getSettings()->mobileOrderField;
        return $cell->getFieldLayout()?->getFieldByHandle($handle)
            ? (int)$cell->getFieldValue($handle)?->value
            : 0;
    }

    /**
     * The cell's column in the row (0 = left), counting only the cells that render, like the templates and the validation.
     * The cell itself counts even when disabled, so its fields follow the column it is in for the editor.
     */
    private function columnIndex(Entry $row, Entry $cell): ?int
    {
        $value = $row->getFieldValue($this->getSettings()->cellsField);
        $cells = $value instanceof EntryQuery ? (clone $value)->status(null)->all() : ($value instanceof ElementCollection ? $value->all() : []);
        // A cell edited in a slideout can be a draft of the cell in the row
        $ids = array_filter([$cell->id, $cell->getCanonicalId()]);

        $index = 0;
        foreach ($cells as $other) {
            if (in_array($other->id, $ids, true) || in_array($other->getCanonicalId(), $ids, true)) {
                return $index;
            }
            if ($other->enabled && $other->getEnabledForSite()) {
                $index++;
            }
        }
        return null;
    }

    private function columnKey(Entry $cell): string
    {
        return $cell->ownerId . '-' . $cell->id;
    }
}
