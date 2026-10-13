<?php

namespace statikbe\trinity;

use craft\base\Model;
use craft\base\Plugin;
use statikbe\trinity\models\Settings;

/**
 * Trinity: row and column layouts for Craft CMS Matrix fields.
 *
 * A row entry type holds a layout (1–3 columns) and a Matrix with one cell entry per column. The project owns the entry types
 * and templates; Trinity provides the layout maths, validation, field conditions and the control panel editing layer.
 *
 * @method static Trinity getInstance()
 * @method Settings getSettings()
 */
class Trinity extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
