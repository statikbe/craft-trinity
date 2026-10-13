<?php

namespace statikbe\trinity\events;

use yii\base\Event;

/**
 * Rows::EVENT_REGISTER_PAGE_WIDTHS: every width the content builder can have, as fractions of the page. Add the widths
 * you set through Rows::EVENT_DEFINE_PAGE_WIDTH, so the "Column width" condition offers the column widths on those pages.
 */
class RegisterPageWidthsEvent extends Event
{
    /** @var float[] */
    public array $widths = [];
}
