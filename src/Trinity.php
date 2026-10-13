<?php

namespace statikbe\trinity;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use statikbe\trinity\layout\Layout;
use statikbe\trinity\layout\LayoutConfig;
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

    private ?Layout $_layouts = null;

    public function init(): void
    {
        parent::init();
    }

    /**
     * The layout maths with this project's settings. Invalid settings are left out and logged as a warning.
     */
    public function getLayouts(): Layout
    {
        if ($this->_layouts === null) {
            $config = LayoutConfig::fromSettings($this->getSettings());
            foreach ($config->warnings as $warning) {
                Craft::warning("config/trinity.php → {$warning}", __METHOD__);
            }
            $this->_layouts = new Layout($config);
        }
        return $this->_layouts;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
