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
            Action::make('export')->label(__('تصدير Excel (CSV)'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->url(fn () => route('export.members', ['gym' => \Filament\Facades\Filament::getTenant()]), true),
            CreateAction::make()->label(__('إضافة مشترك')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('الكل')),
            'active' => Tab::make(__('ساري'))->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->where('status', 'active')))
                ->badge(fn () => Member::whereHas('latestSubscription', fn ($s) => $s->where('status', 'active'))->count())->badgeColor('success'),
            'expiring' => Tab::make(__('ينتهي قريباً'))->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->expiringWithin(7)))
                ->badge(fn () => Member::whereHas('latestSubscription', fn ($s) => $s->expiringWithin(7))->count())->badgeColor('warning'),
            'expired' => Tab::make(__('منتهي'))->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->whereIn('status', ['expired', 'cancelled'])))
                ->badgeColor('danger'),
            'frozen' => Tab::make(__('مجمد'))->modifyQueryUsing(fn (Builder $query) => $query->whereHas('latestSubscription', fn ($s) => $s->where('status', 'frozen'))),
            'none' => Tab::make(__('بدون اشتراك'))->modifyQueryUsing(fn (Builder $query) => $query->doesntHave('subscriptions')),
        ];
    }
}
