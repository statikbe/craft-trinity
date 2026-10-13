# Release Notes for Trinity

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
