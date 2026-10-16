<?php

namespace statikbe\trinity\models;

use craft\base\Model;

/**
 * Trinity settings, set per project in config/trinity.php.
 *
 * The defaults for the handles match the Statik base install, so a project started from it only needs to set what differs.
 * Widths are fractions of the full page width, e.g. 2 / 3.
 */
class Settings extends Model
{
    /** Handle of the row entry type in the content builder Matrix */
    public string $rowEntryType = 'gridRow';

    /** Handle of the row's layout field (a Button Group whose option values are Trinity's layout keys) */
    public string $layoutField = 'gridLayout';

    /** Handle of the row's Matrix field with one cell entry per column, left to right */
    public string $cellsField = 'gridCells';

    /** Handle of the dropdown on every cell type: the column's position while the columns are stacked ('' = auto, '1'–'3') */
    public string $mobileOrderField = 'mobileOrder';

    /** Prefix of the cell entry type handles, stripped to get the cell's type (cellText → text) */
    public string $cellTypePrefix = 'cell';

    /** Narrowest width a column may have on the page; layouts with narrower columns are not allowed (1/3 columns on a 2/3 page) */
    public float $minColumnWidth = 1 / 4;

    /**
     * Pages where the content builder is narrower than the page (e.g. next to a sidebar): its width as a fraction of the page,
     * by entry type or section handle; an entry type wins over its section.
     *
     * @var array{entryTypes?: array<string, float>, sections?: array<string, float>}
     */
    public array $pageWidths = [
        'entryTypes' => [],
        'sections' => [],
    ];

    /** From which viewport width those narrower pages show the content builder at that width (full width below it) */
    public int $narrowFromViewport = 980;

    /**
     * Cell type handle => minimum width on the page; other types are allowed from the narrowest column
     *
     * @var array<string, float>
     */
    public array $minWidthPerType = [
        'cellCards' => 1 / 2,
        'cellTable' => 1 / 2,
        'cellFaq' => 1 / 2,
        'cellEmbed' => 1 / 2,
        'cellForm' => 1 / 2,
    ];

    /**
     * Cell type handle => maximum width on the page; other types are allowed up to full width
     *
     * @var array<string, float>
     */
    public array $maxWidthPerType = [];

    /**
     * Pairs of cell type handles that can't be in the same row, e.g. [['cellQuote', 'cellFaq']]; a single pair may be given as
     * ['cellQuote', 'cellFaq']. The same handle twice allows at most one of that type per row.
     *
     * @var array<array{string, string}>|array{string, string}
     */
    public array $blockedCombinations = [];

    /**
     * Min viewport width (px) => page container width (px), matching the project's container CSS; below the first: $mobileSize.
     * Used for responsive image sizes.
     *
     * @var array<int, int>
     */
    public array $containers = [480 => 448, 660 => 628, 820 => 788, 980 => 948, 1200 => 1168];

    /** Gap between columns in px (the project's column gap in CSS) */
    public int $columnGap = 48;

    /**
     * Layout value => gap between its columns in px, for layouts whose gap differs from $columnGap, e.g. ['thirds' => 24]
     *
     * @var array<string, int>
     */
    public array $columnGaps = [];

    /** Narrowest column (px) at which columns still sit side by side; below that a row stacks its columns */
    public int $columnMinWidth = 256;

    /** Image size below the first container breakpoint */
    public string $mobileSize = '95vw';

    protected function defineRules(): array
    {
        return [
            [['rowEntryType', 'layoutField', 'cellsField', 'mobileOrderField'], 'required'],
            [['minColumnWidth'], 'number', 'min' => 0, 'max' => 1],
            [['narrowFromViewport', 'columnGap', 'columnMinWidth'], 'integer', 'min' => 0],
        ];
    }
}
