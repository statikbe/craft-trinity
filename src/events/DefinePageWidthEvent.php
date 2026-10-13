<?php

namespace statikbe\trinity\events;

use craft\base\ElementInterface;
use yii\base\Event;

/**
 * Rows::EVENT_DEFINE_PAGE_WIDTH: the width of the content builder on a page (the owner of a row), as a fraction of the page.
 * $width holds the configured width (config/trinity.php → pageWidths, else 1); set it to override, e.g. for a page that renders
 * the builder next to a sidebar based on something other than its entry type or section.
 */
class DefinePageWidthEvent extends Event
{
    public ElementInterface $page;

    public float $width = 1;
}
