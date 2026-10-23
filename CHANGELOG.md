# Release Notes for Trinity

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
