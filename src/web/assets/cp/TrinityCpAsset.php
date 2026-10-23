<?php

namespace statikbe\trinity\web\assets\cp;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

/**
 * Control panel layer for rows: shows the cells as the columns of the row's layout, with empty columns, adding columns on the
 * left/right, merging columns and moving cells to another row (see dist/trinity.js). Registered with the cells field.
 */
class TrinityCpAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $js = [
        'trinity.js',
    ];

    public $css = [
        'trinity.css',
    ];

    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('trinity', [
            'Add a column on the left',
            'Add a column on the right',
            'Add content',
            'Does not fit the layout',
            'Max. width {width}',
            'Merge with left',
            'Merge with right',
            'Min. width {width}',
            'Not with {type}',
            'Not available: the row above has another background',
            'Only between rows with the same background',
            'Space above',
            'This block can’t be in the same row as {type}: choose another block or move it.',
            'This block needs a narrower column: choose another layout or move it.',
            'This block needs a wider column: choose another layout or move it.',
            'This layout has columns that are too narrow for this page.',
            'Full page width',
            'not with {type}',
            'too narrow',
            'too wide',
            '{width} of the page',
            ]);
        }
    }
}
