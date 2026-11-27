<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Passes a custom page's Arabic title / navigation labels through the translator.
 */
trait TranslatesPageLabels
{
    public function getTitle(): string|Htmlable
    {
        $title = parent::getTitle();

        return is_string($title) ? __($title) : $title;
    }

    public static function getNavigationLabel(): string|Htmlable
    {
        return __(parent::getNavigationLabel());
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }
}
