<?php

namespace statikbe\trinity\events;

use craft\base\ElementInterface;
use yii\base\Event;

/**
 * Rows::EVENT_DEFINE_PAGE_WIDTH: the width of a content builder on a page (the owner of a row), as a fraction of the page.
 * $width holds the configured width (config/trinity.php → pageWidths, else 1); set it to override, e.g. for a page that renders
 * the builder next to a sidebar based on something other than its entry type, section or field.
 */
class DefinePageWidthEvent extends Event
{
    public ElementInterface $page;

    /** Handle of the builder's Matrix field (as in the page's field layout), when known */
    public ?string $field = null;

    public float $width = 1;
}
