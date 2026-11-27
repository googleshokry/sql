<?php

namespace App\Filament\Actions;

use App\Services\Posting;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** ترحيل / إلغاء ترحيل للمستندات غير البيعية (تحويلات، جرد، رواتب) */
class SimplePostActions
{
    public static function make(): array
    {
        return [
            Action::make('post')->label(__('ترحيل'))->icon(Heroicon::OutlinedCheckBadge)->color('success')
                ->requiresConfirmation()
                ->visible(fn (Model $record) => ! $record->isPosted())
                ->action(fn (Model $record) => DocumentActions::run(fn () => Posting::post($record), __('تم الترحيل بنجاح'))),
            Action::make('unpost')->label(__('إلغاء الترحيل'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Model $record) => $record->isPosted() && auth()->user()?->hasRole('manager', 'accountant'))
                ->action(fn (Model $record) => DocumentActions::run(fn () => Posting::unpost($record), __('تم إلغاء الترحيل'))),
        ];
    }
}
