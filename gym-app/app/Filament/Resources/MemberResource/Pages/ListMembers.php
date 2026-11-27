<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('تصدير Excel (CSV)')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(route('export.members'), true),
            CreateAction::make()->label('إضافة مشترك'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),
            'active' => Tab::make('ساري')->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->where('status', 'active')))
                ->badge(fn () => Member::whereHas('latestSubscription', fn ($s) => $s->where('status', 'active'))->count())->badgeColor('success'),
            'expiring' => Tab::make('ينتهي قريباً')->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->expiringWithin(7)))
                ->badge(fn () => Member::whereHas('latestSubscription', fn ($s) => $s->expiringWithin(7))->count())->badgeColor('warning'),
            'expired' => Tab::make('منتهي')->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->whereIn('status', ['expired', 'cancelled'])))
                ->badgeColor('danger'),
            'frozen' => Tab::make('مجمد')->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->where('status', 'frozen'))),
            'none' => Tab::make('بدون اشتراك')->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('subscriptions')),
        ];
    }
}
