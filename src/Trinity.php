<?php

namespace statikbe\trinity;

use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Element;
use craft\base\Field;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\Entry;
use craft\events\DefineFieldHtmlEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\fields\Matrix;
use craft\helpers\Html;
use craft\web\twig\variables\CraftVariable;
use statikbe\trinity\conditions\ColumnSpanConditionRule;
use statikbe\trinity\conditions\ColumnWidthConditionRule;
use statikbe\trinity\conditions\RowCellsConditionRule;
use statikbe\trinity\layout\Layout;
use statikbe\trinity\layout\LayoutConfig;
use statikbe\trinity\models\Settings;
use statikbe\trinity\services\Rows;
use statikbe\trinity\variables\TrinityVariable;
use statikbe\trinity\web\assets\cp\TrinityCpAsset;
use yii\base\Event;

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

    public static function config(): array
    {
        return [
            'components' => [
                'rows' => Rows::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('trinity', TrinityVariable::class);
        });

        // "Row cells" condition rule, e.g. to show a row's field only for some cell types
        Event::on(EntryCondition::class, BaseCondition::EVENT_REGISTER_CONDITION_RULES, function(RegisterConditionRulesEvent $event) {
            $event->conditionRules[] = RowCellsConditionRule::class;
        });

        // "Column width" and "Column span" condition rules, to show a cell's field only at some widths. Only for field conditions in
        // the layout of a cell type (every cell type has the mobile order field), not for filters on entry indexes.
        Event::on(EntryCondition::class, BaseCondition::EVENT_REGISTER_CONDITION_RULES, function(RegisterConditionRulesEvent $event) {
            $fieldLayouts = $event->sender->getFieldLayouts();
            if (count($fieldLayouts) === 1 && $fieldLayouts[0]->getFieldByHandle($this->getSettings()->mobileOrderField)) {
                $event->conditionRules[] = ColumnWidthConditionRule::class;
                $event->conditionRules[] = ColumnSpanConditionRule::class;
            }
        });

        // Layout, number of columns and cell types per column width
        Event::on(Entry::class, Element::EVENT_AFTER_VALIDATE, function(Event $event) {
            $this->getRows()->validateRow($event->sender);
        });

        // The control panel layer comes with the row's cells field and reads its settings from it
        Event::on(Matrix::class, Field::EVENT_DEFINE_INPUT_HTML, function(DefineFieldHtmlEvent $event) {
            /** @var Matrix $field */
            $field = $event->sender;
            if ($field->handle === $this->getSettings()->cellsField) {
                Craft::$app->getView()->registerAssetBundle(TrinityCpAsset::class);
                $event->html = Html::tag('div', '', [
                    'hidden' => true,
                    'data' => ['trinity' => $this->getRows()->cpConfig($event->element)],
                ]) . $event->html;
            }
        });
    }

    public function getRows(): Rows
    {
        /** @var Rows */
        return $this->get('rows');
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
