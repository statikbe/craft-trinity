<?php

namespace statikbe\trinity\console\controllers;

use Craft;
use craft\base\conditions\ConditionRuleInterface;
use craft\console\Controller;
use craft\fields\BaseOptionsField;
use craft\fields\Matrix;
use craft\helpers\Console;
use statikbe\trinity\layout\Layout;
use statikbe\trinity\layout\LayoutConfig;
use statikbe\trinity\Trinity;
use yii\console\ExitCode;

/**
 * Checks that the project's rows match Trinity's settings: the handles in config/trinity.php, the layout options, the mobile
 * order field on every cell type, the cell types named in the settings and the condition rules stored in project config.
 */
class DoctorController extends Controller
{
    public $defaultAction = 'index';

    /** @var string[] */
    private array $errors = [];

    /** @var string[] */
    private array $warnings = [];

    /**
     * Checks the project's rows against Trinity's settings.
     */
    public function actionIndex(): int
    {
        $settings = Trinity::getInstance()->getSettings();
        $fields = Craft::$app->getFields();

        // Row entry type and its fields
        $rowType = Craft::$app->getEntries()->getEntryTypeByHandle($settings->rowEntryType);
        if (!$rowType) {
            $this->addError("Row entry type \"{$settings->rowEntryType}\" (rowEntryType) doesn't exist.");
        } else {
            foreach (['layoutField', 'cellsField'] as $setting) {
                if (!$rowType->getFieldLayout()->getFieldByHandle($settings->$setting)) {
                    $this->addError("The row entry type \"{$rowType->handle}\" has no \"{$settings->$setting}\" field ({$setting}).");
                }
            }
        }

        // Layout field: options must be layouts, and their icons must exist
        $layoutField = $fields->getFieldByHandle($settings->layoutField);
        if (!$layoutField instanceof BaseOptionsField) {
            $this->addError("Layout field \"{$settings->layoutField}\" (layoutField) doesn't exist or has no options (use a Button Group).");
        } else {
            foreach ($layoutField->options as $option) {
                if (isset($option['optgroup'])) {
                    continue;
                }
                if (!array_key_exists($option['value'], Layout::LAYOUTS)) {
                    $this->addError("Layout field option \"{$option['value']}\" is not a layout; use one of: " . implode(', ', array_keys(Layout::LAYOUTS)) . '.');
                }
                $icon = $option['icon'] ?? null;
                if ($icon && str_starts_with($icon, '@')) {
                    $path = Craft::getAlias($icon, false);
                    if (!$path || !is_file($path)) {
                        $this->addError("Layout field option \"{$option['value']}\": icon \"{$icon}\" doesn't exist.");
                    }
                }
            }
        }

        // Cells field and the cell types
        $cellsField = $fields->getFieldByHandle($settings->cellsField);
        $cellTypes = [];
        if (!$cellsField instanceof Matrix) {
            $this->addError("Cells field \"{$settings->cellsField}\" (cellsField) doesn't exist or isn't a Matrix field.");
        } else {
            foreach ($cellsField->getEntryTypes() as $entryType) {
                $cellTypes[] = $entryType->handle;
                if (!$entryType->getFieldLayout()->getFieldByHandle($settings->mobileOrderField)) {
                    $this->addError("Cell type \"{$entryType->handle}\" has no \"{$settings->mobileOrderField}\" field (mobileOrderField): it can't be ordered on mobile and the column conditions aren't offered in its field layout.");
                }
                if ($settings->cellTypePrefix !== '' && !str_starts_with($entryType->handle, $settings->cellTypePrefix)) {
                    $this->addWarning("Cell type \"{$entryType->handle}\" doesn't start with \"{$settings->cellTypePrefix}\" (cellTypePrefix): its type in templates is the full handle.");
                }
            }
        }

        // Cell types named in the settings
        $config = LayoutConfig::fromSettings($settings);
        foreach ($config->warnings as $warning) {
            $this->addError("config/trinity.php → {$warning}");
        }
        if ($cellTypes) {
            $named = [
                'minWidthPerType' => array_keys($config->minWidthPerType),
                'maxWidthPerType' => array_keys($config->maxWidthPerType),
                'blockedCombinations' => array_merge(...array_map(fn(array $pair) => $pair, $config->blockedCombinations ?: [[]])),
            ];
            foreach ($named as $setting => $handles) {
                foreach (array_diff(array_unique($handles), $cellTypes) as $handle) {
                    $this->addWarning("{$setting}: \"{$handle}\" is not a cell type of \"{$settings->cellsField}\".");
                }
            }
        }

        // Pages with a narrower content builder
        foreach ($config->pageWidths['entryTypes'] as $handle => $width) {
            if (!Craft::$app->getEntries()->getEntryTypeByHandle($handle)) {
                $this->addWarning("pageWidths.entryTypes: entry type \"{$handle}\" doesn't exist.");
            }
        }
        foreach ($config->pageWidths['sections'] as $handle => $width) {
            if (!Craft::$app->getEntries()->getSectionByHandle($handle)) {
                $this->addWarning("pageWidths.sections: section \"{$handle}\" doesn't exist.");
            }
        }

        // Condition rules stored in project config whose class doesn't exist (e.g. after moving to Trinity)
        $this->checkConditionRules(Craft::$app->getProjectConfig()->get() ?? []);

        foreach ($this->errors as $message) {
            $this->stdout('✗ ' . $message . PHP_EOL, Console::FG_RED);
        }
        foreach ($this->warnings as $message) {
            $this->stdout('! ' . $message . PHP_EOL, Console::FG_YELLOW);
        }
        if (!$this->errors && !$this->warnings) {
            $this->stdout('✓ Trinity is set up correctly.' . PHP_EOL, Console::FG_GREEN);
        }
        return $this->errors ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    private function checkConditionRules(array $config, string $path = ''): void
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $this->checkConditionRules($value, $path === '' ? (string)$key : "{$path}.{$key}");
            } elseif ($key === 'class' && is_string($value) && str_ends_with($value, 'ConditionRule')) {
                if (!class_exists($value) || !is_subclass_of($value, ConditionRuleInterface::class)) {
                    $this->addError("Condition rule class \"{$value}\" doesn't exist (project config: {$path}).");
                }
            }
        }
    }

    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }
}
