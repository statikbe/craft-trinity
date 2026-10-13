# Trinity

Row and column layouts for Craft CMS 5 Matrix fields.

A **row** is a nested entry with a layout of 1–3 columns and a Matrix holding one **cell** entry per column. Your project owns
the entry types and the templates; Trinity provides:

- the layout maths: column widths, effective width on the page, mobile order, responsive image sizes
- validation: cell types allowed per effective width, blocked combinations, one cell per column
- field conditions: *Column width*, *Column span* and *Content row blocks*
- a control panel layer to add, merge and move columns
- Twig helpers that give your templates the row data

> Work in progress: being extracted from the Statik base install.

## Requirements

Craft CMS 5.10 or later, PHP 8.2 or later.

## Installation

```bash
composer require statikbe/craft-trinity
php craft plugin/install trinity
```

## Configuration

Create `config/trinity.php`. Every setting is optional; the defaults match the Statik base install.

```php
<?php

return [
    // Pages where the content builder is narrower than the page, by entry type or section handle
    'pageWidths' => [
        'entryTypes' => ['pageWithSidebar' => 2 / 3],
        'sections' => [],
    ],
    // Cell types that need at least this width of the page
    'minWidthPerType' => [
        'cellCards' => 1 / 2,
    ],
    // Pairs of cell types that can't share a row
    'blockedCombinations' => [['cellQuote', 'cellFaq']],
];
```

See [`src/models/Settings.php`](src/models/Settings.php) for every setting.

## License

MIT, see [LICENSE.md](LICENSE.md).
