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
            Action::make('pos')->label('طلب جديد')->icon(Heroicon::OutlinedPlus)->url(Pos::getUrl()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('المفتوحة')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', OrderStatus::open())),
            'today' => Tab::make('اليوم')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today())),
            'dine_in' => Tab::make('صالة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', OrderType::DineIn)),
            'takeaway' => Tab::make('تيك أواي')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', OrderType::Takeaway)),
            'delivery' => Tab::make('دليفري')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', OrderType::Delivery)),
            'unpaid' => Tab::make('غير مدفوع')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_paid', false)->where('status', '!=', OrderStatus::Cancelled)),
            'all' => Tab::make('الكل'),
        ];
    }
}
