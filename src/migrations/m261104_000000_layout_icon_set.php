<?php

namespace statikbe\trinity\migrations;

use Craft;
use craft\db\Migration;
use craft\fields\BaseOptionsField;
use craft\fields\ButtonGroup;
use statikbe\trinity\layout\Layout;
use statikbe\trinity\Trinity;

/**
 * The layout icons come from Trinity's icon set (`trinity:<layout>`) instead of the SVG files
 * (`@statikbe/trinity/icons/layouts/<layout>.svg`):
 *  - every option field with such a file as an icon gets the icon from the set;
 *  - the layout field (layoutField) gets the icon of its layout on options without an icon, and shows icons only.
 */
class m261104_000000_layout_icon_set extends Migration
{
    private const OLD_ICON_PATH = '@statikbe/trinity/icons/layouts/';

    public function safeUp(): bool
    {
        // The fields came with the project config: another environment already made the change
        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.trinity.schemaVersion', true);
        if ($schemaVersion && version_compare($schemaVersion, '1.1.0', '>=')) {
            return true;
        }

        $fieldsService = Craft::$app->getFields();
        $layoutField = Trinity::getInstance()->getSettings()->layoutField;

        foreach ($fieldsService->getAllFields() as $field) {
            if (!$field instanceof BaseOptionsField) {
                continue;
            }
            $isLayoutField = $field->handle === $layoutField && $field instanceof ButtonGroup;
            $changed = false;
            $options = $field->options;
            foreach ($options as &$option) {
                if (isset($option['optgroup'])) {
                    continue;
                }
                $icon = $option['icon'] ?? null;
                $newIcon = $this->iconFor($icon, (string)($option['value'] ?? ''), $isLayoutField);
                if ($newIcon !== null && $newIcon !== $icon) {
                    $option['icon'] = $newIcon;
                    $changed = true;
                }
            }
            unset($option);

            if ($isLayoutField && !$field->iconsOnly) {
                $field->iconsOnly = true;
                $changed = true;
            }
            if (!$changed) {
                continue;
            }

            $field->options = $options;
            if (!$fieldsService->saveField($field)) {
                echo "    > Couldn't update the icons of the \"{$field->handle}\" field: " . implode(', ', $field->getFirstErrors()) . "\n";
                return false;
            }
            echo "    > Layout icons of the \"{$field->handle}\" field now come from the Trinity icon set\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261104_000000_layout_icon_set cannot be reverted.\n";
        return false;
    }

    /**
     * The icon from the set for an option: for an icon that is one of the layout SVG files, or for a layout option of the layout
     * field without an icon. Null to leave the option as it is.
     */
    private function iconFor(?string $icon, string $value, bool $isLayoutField): ?string
    {
        if ($icon && str_starts_with($icon, self::OLD_ICON_PATH)) {
            $name = substr($icon, strlen(self::OLD_ICON_PATH), -strlen('.svg'));
            return array_key_exists($name, Layout::LAYOUTS) ? Trinity::ICON_SET . ':' . $name : null;
        }
        if ($isLayoutField && !$icon && array_key_exists($value, Layout::LAYOUTS)) {
            return Trinity::ICON_SET . ':' . $value;
        }
        return null;
    }
}
