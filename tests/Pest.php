<?php

use statikbe\trinity\layout\Layout;
use statikbe\trinity\layout\LayoutConfig;

/**
 * Layout with the given settings (as in config/trinity.php), defaults for the rest.
 *
 * @param array<string, mixed> $config
 */
function layout(array $config = []): Layout
{
    return new Layout(LayoutConfig::fromArray($config));
}
