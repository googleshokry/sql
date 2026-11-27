<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Passes a resource's Arabic labels through the translator so the panel works in Arabic and English.
 */
trait TranslatesResourceLabels
{
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(parent::getPluralModelLabel());
    }

    public static function getTitleCaseModelLabel(): string
    {
        return ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return ucfirst(static::getPluralModelLabel());
    }

    public static function getNavigationLabel(): string|Htmlable
    {
        return __(static::$navigationLabel ?? static::getTitleCasePluralModelLabel());
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }
}
