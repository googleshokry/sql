<?php

namespace App\Filament\Concerns;

use App\Tenancy\PlanLimits;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;

/** زر "إضافة" بيحترم حدود الباقة */
class LimitedCreateAction
{
    public static function make(string $limit): CreateAction
    {
        return CreateAction::make()
            ->before(function (CreateAction $action) use ($limit) {
                if (PlanLimits::reached($limit)) {
                    Notification::make()->danger()->title(PlanLimits::message($limit))->persistent()->send();
                    $action->halt();
                }
            });
    }
}
