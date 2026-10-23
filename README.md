<img src="src/icon.svg" alt="Trinity" width="100" height="100">

# Trinity

Row and column layouts for Craft CMS 5 Matrix fields.

A **row** is a nested entry in your content builder with a layout of 1–3 columns and a Matrix holding one **cell** entry per
column, left to right. Everything is native Craft: drafts, revisions, propagation, search and eager loading keep working, and so
do the fields when Trinity's control panel layer is unavailable.

![Two rows in the control panel: a 1/3 + 2/3 and a 2/3 + 1/3 layout, each cell card showing its width on the page](docs/images/rows.png)

Your project owns the entry types and the templates. Trinity provides:

- **Layout maths**: column widths, the effective width on the page, mobile order, breakpoints and responsive image sizes
- **Validation**: the number of cells matches the layout, every cell type fits its column, no blocked combinations
- **Field conditions**: *Column width*, *Column span* and *Row cells*
- **A control panel layer**: cells shown as the row's columns, empty columns, adding and merging columns, moving a cell to another row
- **Twig helpers**: the data your templates need to render a row
- **`craft trinity/doctor`**: checks your setup

## Requirements

Craft CMS 5.10 or later, PHP 8.2 or later.

## Installation

```bash
composer require statikbe/craft-trinity
php craft plugin/install trinity
```

## Content model

Trinity finds your content model by handle (see [Configuration](#configuration); the defaults are in brackets). Create it in the
control panel:

| Part | What it is |
|---|---|
| Row entry type (`gridRow`) | An entry type in your content builder Matrix, with the two fields below. Add anything else a row needs (alignment, background, …). |
| Layout field (`gridLayout`) | A **Button Group** with these option values: `full`, `halves`, `thirds`, `thirdTwoThirds`, `twoThirdsThird`. Leave out the ones you don't want. Icons: `@statikbe/trinity/icons/layouts/<value>.svg`. |
| Cells field (`gridCells`) | A **Matrix** field in **cards** view, max 3 entries: one cell per column, left to right. |
| Cell entry types (`cell…`) | One entry type per kind of column content, e.g. `cellText`, `cellImage`. Their handle without the prefix is the cell's type in templates and CSS (`cellText` → `text`). |
| Mobile order field (`mobileOrder`) | A **Dropdown** with the options *Auto* (empty value), `1`, `2`, `3`, on **every** cell type, except types that can only be full width (`minWidthPerType` 1): they are alone in their row. |

The layouts are fixed: the validation, the conditions and the control panel layer are built around them.

| Layout | Columns |
|---|---|
| `full` | 1 |
| `halves` | 1/2 + 1/2 |
| `thirds` | 1/3 + 1/3 + 1/3 |
| `thirdTwoThirds` | 1/3 + 2/3 |
| `twoThirdsThird` | 2/3 + 1/3 |

Run `php craft trinity/doctor` to check the setup.

## Configuration

Create `config/trinity.php`. Every setting is optional. Widths are fractions of the full page width, e.g. `2 / 3`.

```php
<?php

return [
    '*' => [
        // Content builders that are narrower than the page (e.g. next to a sidebar), by entry type, section or builder field
        'pageWidths' => [
            'entryTypes' => ['pageWithSidebar' => 2 / 3],
            'sections' => [],
            'fields' => ['sidebarBuilder' => 1 / 3],
        ],
        // Cell types that need at least / can be at most this width of the page
        'minWidthPerType' => ['cellTable' => 1 / 2],
        'maxWidthPerType' => ['cellQuote' => 1 / 2],
        // Pairs of cell types that can't share a row
        'blockedCombinations' => [['cellQuote', 'cellFaq']],
    ],
];
```

| Setting | Default | What it does |
|---|---|---|
| `rowEntryType` | `'gridRow'` | Handle of the row entry type |
| `layoutField` | `'gridLayout'` | Handle of the layout Button Group |
| `cellsField` | `'gridCells'` | Handle of the cells Matrix |
| `mobileOrderField` | `'mobileOrder'` | Handle of the mobile order Dropdown on every cell type |
| `rowSpacingField` | `null` | Handle of a Button Group on the row entry type for the space above a row; shown as a compact control between rows (see [Space above a row](#space-above-a-row)) |
| `autoRowSpacing` | `true` | For your templates: whether *auto* spacing is decided from the content (`true`) or is normal spacing (`false`); also without `rowSpacingField` (see [Space above a row](#space-above-a-row)) |
| `rowBackgroundField` | `null` | Handle of the row's background field; on a row whose background differs from the row above, the spacing control shows why it isn't available instead of its options |
| `rowBackgroundDefault` | `''` | The background of a row whose background field is empty or hidden by its condition |
| `cellTypePrefix` | `'cell'` | Prefix stripped from cell type handles to get their type (`cellText` → `text`) |
| `minColumnWidth` | `1 / 4` | Narrowest a column may be on the page; layouts with narrower columns aren't allowed (1/3 columns on a 2/3 page) |
| `pageWidths` | none | `entryTypes` / `sections` / `fields` => `[handle => width]`: content builders that are narrower than the page. `fields` is the handle of a builder's Matrix field (as in the field layout), for pages with more than one content builder. A field wins over an entry type, an entry type over its section. |
| `narrowFromViewport` | `980` | Viewport width (px) from which those pages show the builder at that width; full width below it (for image sizes) |
| `minWidthPerType` | cards, table, FAQ, embed, form: `1 / 2` | Cell type handle => minimum width on the page. Your list replaces the default one. |
| `maxWidthPerType` | none | Cell type handle => maximum width on the page. A type with a maximum below full can't fill a row on its own. |
| `blockedCombinations` | none | Pairs of cell type handles that can't be in the same row. The same handle twice allows at most one of that type per row. |
| `containers` | `[480 => 448, 660 => 628, 820 => 788, 980 => 948, 1200 => 1168]` | Viewport width => page container width (px), matching your CSS; for image sizes |
| `columnGap` | `48` | Gap between columns (px), matching your CSS; also the gap between the content builder and a sidebar |
| `columnGaps` | none | Layout value => gap (px) for layouts with another gap between their columns, e.g. `['thirds' => 24]` |
| `columnMinWidth` | `256` | Narrowest a column gets (px) before the row stacks its columns |
| `twoColumnStep` | `true` | Rows with a 1/3 column show two equal columns between the halves breakpoint and their own (the third column of thirds wraps below, as a half on the left); `false`: they stack until their own breakpoint |
| `mobileSize` | `'95vw'` | Image size below the first container breakpoint |

### Widths on narrower pages

Every column on a page with a narrower content builder is that much narrower, and cell types are checked against that
**effective width**: 1/2 + 1/2 on a 2/3 page is 1/3 of the page each. With the default `minColumnWidth` of 1/4:

| Page width | Layouts | Column widths on the page |
|---|---|---|
| full | all | 1, 1/2, 1/3, 2/3 |
| 3/4 | all | 3/4, 3/8, 1/4, 1/2 |
| 2/3 | `full`, `halves` | 2/3, 1/3 |

A row must sit directly in the page's content builder: its owner is the page.

### More than one content builder on a page

Craft allows a Matrix field only once per field layout. For a second content builder on the same entry (e.g. a sidebar), create
another Matrix field that offers the same row entry type. Trinity recognises rows by their entry type, so the second builder
gets the same validation, conditions and control panel layer without further setup.

Give it its own width with `pageWidths.fields`, keyed by the field's handle in the field layout:

```php
'pageWidths' => [
    'fields' => ['sidebarBuilder' => 1 / 3],
],
```

Its rows then get the layouts, width rules and image sizes of a 1/3 builder, while the main builder keeps its own width. In
templates, `craft.trinity.row(row)` picks this up from the row; for the page width itself pass the field:
`craft.trinity.pageWidth(entry, 'sidebarBuilder')`. `craft trinity/doctor` warns about a handle that isn't a Matrix field with
rows.

## Templates

Trinity renders no markup. In your row template, get the row's data with `craft.trinity.row()` and include a template per cell
type:

```twig
{% set grid = craft.trinity.row(block) %}

<div class="grid grid-cols-1 gap-x-12 @container" data-row-layout="{{ grid.layout }}" data-row-cells="{{ grid.cellTypes|join(' ') }}">
    {% for column in grid.columns %}
        <div class="@container min-w-0" data-cell="{{ column.type }}">
            {% include '_grid/_' ~ column.type with { cell: column.cell, column: column } %}
        </div>
    {% endfor %}
</div>
```

`craft.trinity.row(block)` returns:

| Key | What it is |
|---|---|
| `layout` | The layout value, e.g. `thirdTwoThirds` (`full` when none is chosen) |
| `pageWidth` | Width of the content builder on this page, e.g. `0.6667` |
| `breakpoint` | Row width (px) from which the columns sit side by side: their narrowest column is then `columnMinWidth` wide (560 for halves, 864 for layouts with a 1/3 column) |
| `twoColumnBreakpoint` | Row width (px) from which a row with a 1/3 column shows two equal columns until `breakpoint` (560), or `null` (full, halves, `twoColumnStep` off) |
| `cellTypes` | The cells' types, left to right, e.g. `['text', 'image']` |
| `columns` | Per cell, left to right: `cell` (the entry), `type`, `fraction` (share of the row as text: `1/3`, `1/2`, `2/3` or `full`), `width` (share of the page, a number, e.g. `0.3333`), `mobileOrder` (position while stacked, or `null` when every column is on *Auto*), `sizes` and `srcsetSizes` (for an image that fills the column, also in the two-column step, where every column is a half) |

Cells beyond the layout's columns (the layout was changed without removing them) come last with a `fraction` of `full`; validation
prevents saving that.

Fields of a cell that its field layout hides (any field condition, e.g. *Column width*) are emptied before you get the cell, so
their last value never shows. For other elements, check `craft.trinity.showsField(element, 'handle')` before using a field with a
condition: a hidden field keeps its last value.

| Twig | Returns |
|---|---|
| `craft.trinity.row(row)` | The row data above |
| `craft.trinity.showsField(element, handle)` | Whether a field is shown for this element, following its field condition |
| `craft.trinity.pageWidth(page, field)` | Width of a content builder on this page; `field` (optional) is the builder's Matrix field handle |
| `craft.trinity.imageSizes(layout, fraction, pageWidth)` | `sizes` and `srcsetSizes` for an image filling such a column |
| `craft.trinity.settings` | The settings |

### CSS

Use container queries, not viewport breakpoints: the same row can be on a full-width page or next to a sidebar.

- **Columns side by side**: from `breakpoint` (the row's width), e.g. container sizes `--container-cols-2: 560px` and
  `--container-cols-3: 864px` with `@cols-2:grid-cols-2` / `@cols-3:grid-cols-3`. Change `columnMinWidth` / `columnGap`
  together with them. A layout with its own gap in `columnGaps` has its own breakpoint (thirds with a 24px gap: 816px).
- **Two-column step**: from `twoColumnBreakpoint`, a row with a 1/3 column shows two equal columns, e.g. `@cols-2:grid-cols-2
  @cols-3:grid-cols-3`; the third column of thirds wraps below, as a half on the left. A 2/3 column only spans 2 from
  `@cols-3`. Mobile order then applies below `@cols-2`.
- **Mobile order**: below the breakpoint, give each column `order-{{ column.mobileOrder }}` when it's set. Only the visual
  order changes; the source order (screen readers, tabbing) stays left to right.
- **Image sizes** follow `containers`, `columnGap`, `columnGaps` and `narrowFromViewport`: keep them in sync with your CSS. A mismatch only
  gives slightly wrong `sizes`, never a broken layout.

## Field conditions

In a field layout, open a field's settings → *Visibility Conditions* → *Entry Condition*:

- **Row cells** (in the row entry type's layout): whether the row has a cell of one of the chosen types, e.g. a
  background colour only when the row has no FAQ. The control panel re-checks it when cells are added or removed.
- **Column span** (in a cell type's layout): the cell's share of the row: full row, 2/3, 1/2 or 1/3 of the row, the same on
  every page. For conditions about the layout, e.g. a field only when the cell has the row to itself.
- **Column width** (in a cell type's layout): the cell's effective width on the page, e.g. 1/3 of the page for a 1/2 column on
  a 2/3 page. For conditions about room, e.g. *is not one of 1/4, 1/3 of the page* for a field that needs some width.

*Column span* and *Column width* are only offered in the layouts of cell types (layouts with the mobile order field). A cell
outside a row has no column: *is not one of* matches it. Their values are stored as hundredths (`100`, `67`, `50`, `33`, …).

![The Row cells condition: Row cells is not one of FAQ](docs/images/condition-row-cells.png)

![The Column width condition: Column width is one of full page width](docs/images/condition-column-width.png)

## Control panel

On the cells field, Trinity:

- lays out the cell cards like the row's columns, each with its width on the page;
- shows empty columns, with an *Add content* menu that lists the types that fit (others disabled, with the reason),
  grouped under headings like the entry type groups of the cells field (when it has more than one group), and buttons to merge
  an empty column with the one next to it;
- adds a column on the left or right (up to 3, as far as the page allows);
- hides the layouts that can't be used on the page;
- moves a cell dragged onto an empty column of another row (it's duplicated there and deleted here, in the page's draft, so
  discarding the draft undoes it);
- puts the row's errors back on its fields after a failed save;
- with `rowSpacingField`, shows a compact *Space above* control at the top of every row but the first (see below).

Empty columns only exist in the control panel: saving a row with fewer cells than columns fails validation. The layer is made
for a content builder Matrix in **blocks** view (inline-editable blocks).

![An empty column with the Add content menu, listing the cell types](docs/images/add-content.png)

![A row with three columns: two empty ones with Add content and Merge with left, and types that need a wider column disabled with their minimum width](docs/images/empty-columns.png)

### Space above a row

Rows that follow each other often belong together (a title row and its follow-up) or start a new part of the page. To let
editors set that, add a **Button Group** to the row entry type, e.g. `rowSpacing` with the options `auto`, `close`, `normal`
and `extra` (icons help), and name it in the settings:

```php
'rowSpacingField' => 'rowSpacing',
```

The control panel then hides the field and shows its options as a small *Space above* control at the top of every row except
the first one in the content builder, and the gap between the blocks follows the choice (`close` and `extra`) as a hint.
Clicking an option sets the field, so drafts, autosave and revisions work as for any field.

Spacing usually only applies between rows with the same background. Name the background field too, and on a row whose
background differs from the row above the control shows *Space above: Not available: the row above has another background*
instead of its options. It follows background changes and moved rows live:

```php
'rowBackgroundField' => 'backgroundColor',
'rowBackgroundDefault' => 'section--default', // the background when the field is empty or hidden
```
 What the values do on the front
end is up to your templates and CSS: e.g. a `data-row-spacing` attribute on the row's section when it has the same background as
the row above, with `auto` decided from the content (a row without a title follows the one above). `craft trinity/doctor`
checks that the field is on the row entry type.

`autoRowSpacing` (default `true`) lets a project switch that content rule off without touching templates: read it as
`craft.trinity.settings.autoRowSpacing` and treat `auto` as normal spacing when it's `false`. To switch spacing off altogether,
set both:

```php
'rowSpacingField' => null,   // no Space above control (remove the field from the row entry type too)
'autoRowSpacing' => false,   // auto = normal spacing
```

## Validation

On live saves (drafts and autosaves can be incomplete), a row must have:

- a layout that's allowed on its page;
- one enabled cell per column;
- every cell type within its minimum and maximum width;
- no blocked combinations.

## Events

```php
use statikbe\trinity\events\DefinePageWidthEvent;
use statikbe\trinity\events\RegisterPageWidthsEvent;
use statikbe\trinity\services\Rows;
use yii\base\Event;

// The width of a content builder on a page, for pages pageWidths can't describe ($event->field: the builder's field handle)
Event::on(Rows::class, Rows::EVENT_DEFINE_PAGE_WIDTH, function(DefinePageWidthEvent $event) {
    if ($event->page->slug === 'narrow-page' && $event->field !== 'sidebarBuilder') {
        $event->width = 3 / 4;
    }
});

// Widths set through the event above, so "Column width" offers the column widths on those pages
Event::on(Rows::class, Rows::EVENT_REGISTER_PAGE_WIDTHS, function(RegisterPageWidthsEvent $event) {
    $event->widths[] = 3 / 4;
});
```

## Adding a cell type

1. Create the entry type with the prefix (`cellMap`), add the mobile order field, and add it to the cells field.
2. Add its template (`_grid/_map.twig`, or wherever your row template includes it).
3. Optional: a minimum or maximum width (`minWidthPerType` / `maxWidthPerType`) or blocked combinations.

Validation, the control panel and the conditions pick it up automatically. Run `php craft trinity/doctor`.

## Translations

The control panel texts are in English, Dutch and French (`src/translations`). To change one, add
`translations/<language>/trinity.php` to your project.

## License

MIT, see [LICENSE.md](LICENSE.md).
