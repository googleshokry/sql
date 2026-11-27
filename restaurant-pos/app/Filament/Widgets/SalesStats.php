<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = Order::whereDate('created_at', today())->where('status', '!=', OrderStatus::Cancelled);
        $paid = (clone $today)->where('is_paid', true);
        $cur = config('pos.currency');

        $sales = (float) (clone $paid)->sum('total');
        $yesterday = (float) Order::whereDate('created_at', today()->subDay())
            ->where('status', '!=', OrderStatus::Cancelled)->where('is_paid', true)->sum('total');
        $count = (clone $today)->count();

        return [
            Stat::make('مبيعات اليوم', number_format($sales, 2).' '.$cur)
                ->description('أمس: '.number_format($yesterday, 2))
                ->color($sales >= $yesterday ? 'success' : 'danger')
                ->descriptionIcon($sales >= $yesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'),
            Stat::make('طلبات اليوم', $count)
                ->description('متوسط الطلب: '.number_format($count ? $sales / max(1, (clone $paid)->count()) : 0, 2)),
            Stat::make('كاش في الدرج', number_format((float) (clone $paid)->where('payment_method', PaymentMethod::Cash)->sum('total'), 2))
                ->description('فيزا/محفظة: '.number_format((float) (clone $paid)->where('payment_method', '!=', PaymentMethod::Cash)->sum('total'), 2)),
            Stat::make('دليفري اليوم', (clone $today)->where('type', OrderType::Delivery)->count())
                ->description('صالة: '.(clone $today)->where('type', OrderType::DineIn)->count().' | تيك أواي: '.(clone $today)->where('type', OrderType::Takeaway)->count()),
            Stat::make('طلبات مفتوحة', Order::whereIn('status', OrderStatus::open())->count())
                ->color('warning'),
            Stat::make('غير محصل', number_format((float) Order::where('is_paid', false)->where('status', '!=', OrderStatus::Cancelled)->sum('total'), 2))
                ->color('danger'),
        ];
    }
}
