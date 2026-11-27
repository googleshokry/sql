<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filament\Pages\Pos;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ManageOrders extends ManageRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pos')->label(__('طلب جديد'))->icon(Heroicon::OutlinedPlus)->url(Pos::getUrl()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'open' => Tab::make(__('المفتوحة'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', OrderStatus::open())),
            'app' => Tab::make(__('طلبات التطبيق'))
                ->badge(fn () => \App\Models\Order::where('source', 'customer_app')->where('status', OrderStatus::Pending)->count() ?: null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('source', 'customer_app')->whereIn('status', OrderStatus::open())),
            'today' => Tab::make(__('اليوم'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today())),
            'dine_in' => Tab::make(__('صالة'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', OrderType::DineIn)),
            'takeaway' => Tab::make(__('تيك أواي'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', OrderType::Takeaway)),
            'delivery' => Tab::make(__('دليفري'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', OrderType::Delivery)),
            'unpaid' => Tab::make(__('غير مدفوع'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_paid', false)->where('status', '!=', OrderStatus::Cancelled)),
            'all' => Tab::make(__('الكل')),
        ];
    }
}
