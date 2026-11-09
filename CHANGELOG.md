# Release Notes for Trinity

## 1.5.0 - 2026-11-09

### Changed
- A row's title bar shows its columns from left to right, e.g. "Text + Image" (an empty column is "Empty"), followed by the row's UI label, both when the row is collapsed and expanded. A collapsed row shows that instead of the values of its fields. Give the row entry type a UI label format that names the row, e.g. the title of its first column: `{{ object.gridCells.all()|map(cell => cell.blockTitle ?? '')|filter|first|default('') }}`
- The gap between rows in the control panel no longer follows their "Space above" choice (`close`, `extra`): the rows stay in place when the spacing changes
- The "Space above" control is hidden while a row is collapsed. Before, it took the collapsed row's place, so the row's title bar wasn't visible

## 1.4.0 - 2026-11-04

### Added
- The layout icons are a Craft icon set (Craft 5.12): `trinity:full`, `trinity:halves`, `trinity:thirds`, `trinity:thirdTwoThirds` and `trinity:twoThirdsThird`. The layout field's options use them like any other icon, and they are in the icon picker of a Button Group's options (set "Trinity")
- `craft trinity/doctor` checks icons from an icon set, and warns about layout icons that are still set as a file

### Changed
- Requires Craft CMS 5.12 or later
- Updating runs a migration: option fields whose icons are Trinity's layout files (`@statikbe/trinity/icons/layouts/<layout>.svg`) get the icon from the set instead (`trinity:<layout>`). The layout field (`layoutField`) also gets the icon of its layout on options without an icon, and shows icons only. On other environments the change comes with the project config. The labels of the options stay, as the buttons' tooltip and accessible name

## 1.3.3 - 2026-10-31

### Changed
- The "Row cells" condition (e.g. a row's background colour shown only without an FAQ) and the column conditions load the cells of all rows of a page in one query, instead of one query per row: on the front end, in the control panel and for other plugins that check whether a field is shown (`Rows::showsField()`). On a page with 19 rows, the row conditions took 4 queries instead of 21. New: `Rows::allCells()`, the cells of a row with the disabled ones
## 1.3.2 - 2026-10-30

### Fixed
- Errors on a row's layout or columns field from other checks than Trinity's (e.g. a project's own validation of the columns' content) stayed visible only in the error summary: the control panel removed them from the row as soon as the row's layout was valid. They now stay on the row until the next save; Trinity's own errors still disappear once the editor fixes the layout or the columns
## 1.3.1 - 2026-10-23

### Changed
- Labels that stand on their own start with a capital, following the Statik code guidelines (sentence case): the "Column span" option "Full row", and "Full page width" in the "Column width" options and on a cell card's width label. Inside a sentence, such as a validation message, the width stays lowercase. Saved conditions are unaffected: they store the widths, not the labels

## 1.3.0 - 2026-10-23

### Added
- `twoColumnStep` (default `true`): rows with a 1/3 column (thirds, 1/3 + 2/3, 2/3 + 1/3) show two equal columns while they are too narrow for their layout but wide enough for halves; the third column of thirds wraps below, as a half on the left. `Layout::twoColumnBreakpoint()`, `twoColumnBreakpoint` in `craft.trinity.row()`

### Changed
- Image sizes follow the two-column step: every column a half

## 1.2.0 - 2026-10-17

### Added
- `autoRowSpacing` (default `true`): whether templates decide "auto" spacing from the content or treat it as normal spacing, available as `craft.trinity.settings.autoRowSpacing`. With `rowSpacingField` set to `null` as well, a project switches row spacing off altogether

## 1.1.1 - 2026-10-17

### Changed
- On a row whose background differs from the row above, the "Space above" control shows why it isn't available ("Not available: the row above has another background") instead of dimmed, disabled options

### Fixed
- The "Space above" label and the options or reason next to it are aligned on their text baseline

## 1.1.0 - 2026-10-17

### Added
- `pageWidths.fields`: the width of a content builder by the handle of its Matrix field (as in the field layout), for entries with more than one content builder (Craft allows a Matrix field only once per field layout, so a second builder is a second Matrix field with the same row entry type). A field wins over an entry type and section
- `Rows::rowPageWidth()`; `craft.trinity.pageWidth()` and `Rows::pageWidth()` take the builder's field handle as an optional second argument; `DefinePageWidthEvent::$field`
- `craft trinity/doctor` warns about a `pageWidths.fields` handle that isn't a Matrix field with rows in any field layout

- `rowSpacingField`: a Button Group on the row entry type for the space above a row. The control panel hides it and shows its options as a compact "Space above" control at the top of every row but the first; the gap between the blocks follows the choice (`close`, `extra`). What the values mean is up to the templates. `craft trinity/doctor` checks the field
- `rowBackgroundField` and `rowBackgroundDefault`: with these, the spacing control is disabled on a row whose background differs from the row above (live, also when a background changes or rows move)

### Changed
- The "Add content" menu of an empty column shows the entry type groups of the cells field, with a heading per group, like Craft's own "New entry" menu (only when there's more than one group)

## 1.0.1 - 2026-10-16

### Fixed
- `craft trinity/doctor` no longer asks for the mobile order field on cell types that can only be full width (`minWidthPerType` 1): they are alone in their row

## 1.0.0 - 2026-10-16

The first stable release.

### Added
- `columnGaps` setting: a gap per layout (px) for layouts whose columns have another gap than `columnGap`, e.g. `['thirds' => 24]`. Breakpoints and image sizes of that layout use it

### Changed
- `fraction` in the columns of `craft.trinity.row()` is the share of the row as text (`1/3`, `1/2`, `2/3`, `full`) instead of a number; the number was the same as `width` on a full-width page. Use `width` for calculations.

## 1.0.0-beta.3 - 2026-10-13

### Changed
- The "Content row blocks" condition rule is now "Row cells" (`RowCellsConditionRule`, was `ContentRowBlocksConditionRule`)

> [!WARNING]
> Field conditions are stored with their class name. Replace `statikbe\trinity\conditions\ContentRowBlocksConditionRule` with `statikbe\trinity\conditions\RowCellsConditionRule` in your project config before updating, or Craft drops the condition.

## 1.0.0-beta.2 - 2026-10-13

### Added
- Plugin icon

### Changed
- The Composer package no longer includes tests, CI and code style files

## 1.0.0-beta.1 - 2026-10-13

First beta, extracted from the Statik base install's "Content row" block.

### Added
- Row and column layouts for Matrix fields: `full`, `halves`, `thirds`, `thirdTwoThirds`, `twoThirdsThird`
- Settings in `config/trinity.php`: field handles, page widths (by entry type or section), minimum and maximum width per cell type, blocked combinations, container widths for image sizes
- Validation of rows on live saves: allowed layout, one cell per column, cell types within their width, no blocked combinations
- Field conditions: "Column width", "Column span" and "Content row blocks"
- Control panel layer on the cells field: cells as columns, empty columns with an "Add content" menu, adding and merging columns, moving a cell to another row, errors put back after a failed save
- `craft.trinity` in Twig: `row()`, `showsField()`, `pageWidth()`, `imageSizes()`, `settings`
- `Rows::EVENT_DEFINE_PAGE_WIDTH` and `Rows::EVENT_REGISTER_PAGE_WIDTHS`
- `craft trinity/doctor` to check a project's setup
- Dutch and French translations
